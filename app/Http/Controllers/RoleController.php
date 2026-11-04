<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\AccessMap;
use App\Support\Fields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    /** One role at a time: its areas down the page, the actions across. `copy` shows another role's grants to start from. */
    public function index(Request $request): View
    {
        $roles = Role::orderBy('name')->get();
        $role = $roles->firstWhere('id', (int) $request->query('role')) ?? $roles->first(fn (Role $r) => $r->code !== 'ADMIN') ?? $roles->first();
        $from = $roles->firstWhere('id', (int) $request->query('copy')) ?? $role;
        $held = $from ? $from->permissions() : [];
        $tree = AccessMap::tree();
        // A whole permission is held when the role has it, or every action of every area under it.
        $has = fn (string $permission, string $area, string $action): bool => in_array($permission, $held, true) || in_array(AccessMap::key($area, $action), $held, true);
        $plain = collect(config('permissions.catalog'))->reject(fn ($label, $key) => isset($tree[$key]));

        // One line per group for "what can this role do": every cell, some of them, or none.
        $summary = collect($tree)->map(function ($group, $permission) use ($has) {
            $cells = collect($group['areas'])->flatMap(fn ($area, $name) => collect($area['actions'])->map(fn ($a) => $has($permission, $name, $a)));

            return ['label' => $group['label'], 'done' => $cells->filter()->count(), 'of' => $cells->count()];
        })->filter(fn ($g) => $g['done'] > 0)->values();

        return view('roles.index', ['summary' => $summary, 'fields' => collect(Fields::KINDS)->map(fn ($p) => in_array($p, $held, true)),'roles' => $roles, 'role' => $role, 'copying' => $from && $from->id !== $role?->id ? $from : null, 'tree' => $tree,
            'plain' => $plain, 'limited' => AccessMap::viewLimited(), 'held' => $held, 'has' => $has, 'actions' => AccessMap::ACTIONS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'copy' => ['nullable', 'integer', 'exists:roles,id']]);
        $code = Str::upper(Str::slug($data['name'], '_'));
        $code = Role::where('code', $code)->exists() ? substr($code, 0, 30).'_'.Str::upper(Str::random(4)) : $code;
        $role = Role::create(['code' => substr($code, 0, 40), 'name' => $data['name'], 'is_system' => false]);
        if (! empty($data['copy'])) {
            $copied = Role::find($data['copy'])->permissions();
            DB::table('role_permissions')->insert(array_map(fn ($p) => ['role_id' => $role->id, 'permission' => $p], $copied));
        }
        app(\App\Services\Audit\Audit::class)->record('CREATED', 'Role', (string) $role->id, $role->name, []);

        return redirect()->route('roles.index', ['role' => $role->id])->with('success', 'Role added. Tick what it may do.');
    }

    public function update(Request $request): RedirectResponse
    {
        $input = $request->input('perm', []);
        if ($request->boolean('limits_shown')) {
            AccessMap::limitViewing(array_keys((array) $request->input('limit', [])));
        }
        $ids = array_unique([...array_keys($input), ...array_map('intval', (array) $request->input('roles', []))]);
        $audit = app(\App\Services\Audit\Audit::class);

        DB::transaction(function () use ($input, $ids, $audit) {
            foreach (Role::where('code', '!=', 'ADMIN')->whereIn('id', $ids)->get() as $role) {
                $new = $this->tidy(array_keys($input[$role->id] ?? []));
                $old = DB::table('role_permissions')->where('role_id', $role->id)->pluck('permission')->all();
                sort($old);
                if ($old === $new) {
                    continue;
                }
                DB::table('role_permissions')->where('role_id', $role->id)->delete();
                DB::table('role_permissions')->insert(array_map(fn ($p) => ['role_id' => $role->id, 'permission' => $p], $new));
                $audit->record('UPDATED', 'Role', (string) $role->id, $role->name, [
                    'permissions' => ['from' => implode(', ', $old), 'to' => implode(', ', $new)],
                ]);
            }
        });

        return back()->with('success', 'Permissions saved.');
    }

    /**
     * Valid keys only, sorted. A permission whose every action of every area is ticked is stored whole, so the role keeps
     * following it when an area is added; anything less is stored as single grants.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function tidy(array $keys): array
    {
        $tree = AccessMap::tree();
        $catalog = array_keys(config('permissions.catalog'));
        $keys = array_values(array_unique($keys));
        $out = array_values(array_intersect($catalog, $keys));
        foreach ($tree as $permission => $group) {
            $cells = collect($group['areas'])->flatMap(fn ($area, $name) => array_map(fn ($a) => AccessMap::key($name, $a), $area['actions']))->all();
            $ticked = array_values(array_intersect($cells, $keys));
            if (in_array($permission, $out, true) || ($cells && count($ticked) === count($cells))) {
                $out[] = $permission;
            } else {
                array_push($out, ...$ticked);
            }
        }
        // "Own records only" limits an area on top of whatever else the role holds.
        foreach (array_keys(config('permissions.own', [])) as $area) {
            if (in_array(AccessMap::key($area, 'own'), $keys, true)) {
                $out[] = AccessMap::key($area, 'own');
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }
}
