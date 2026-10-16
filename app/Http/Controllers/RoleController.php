<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::orderBy('name')->get();
        $granted = DB::table('role_permissions')->get()->groupBy('role_id')->map(fn ($g) => $g->pluck('permission')->all());
        return view('roles.index', ['roles' => $roles, 'granted' => $granted, 'catalog' => config('permissions.catalog')]);
    }

    public function update(Request $request)
    {
        $catalog = array_keys(config('permissions.catalog'));
        $input = $request->input('perm', []);
        $audit = app(\App\Services\Audit\Audit::class);

        DB::transaction(function () use ($input, $catalog, $audit) {
            foreach (Role::where('code', '!=', 'ADMIN')->get() as $role) {
                $new = array_values(array_intersect($catalog, array_keys($input[$role->id] ?? [])));
                $old = DB::table('role_permissions')->where('role_id', $role->id)->pluck('permission')->all();
                sort($old);
                $sorted = $new;
                sort($sorted);
                if ($old === $sorted) {
                    continue;
                }
                DB::table('role_permissions')->where('role_id', $role->id)->delete();
                DB::table('role_permissions')->insert(array_map(fn ($p) => ['role_id' => $role->id, 'permission' => $p], $new));
                $audit->record('UPDATED', 'Role', (string) $role->id, $role->name, [
                    'permissions' => ['from' => implode(', ', $old), 'to' => implode(', ', $sorted)],
                ]);
            }
        });

        return back()->with('success', 'Permissions saved.');
    }
}
