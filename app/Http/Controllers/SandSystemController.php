<?php

namespace App\Http\Controllers;

use App\Models\SandSystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The editable list of sand systems, kept on the moulding teams and stations page. */
class SandSystemController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        SandSystem::create($data + [
            'code' => $this->freeCode($data['name']),
            'status' => 'ACTIVE',
            'sort_order' => $data['sort_order'] ?? ((int) SandSystem::withTrashed()->max('sort_order') + 10),
        ]);

        return back()->with('success', "Sand system {$data['name']} added.");
    }

    public function update(Request $request, SandSystem $sandSystem): RedirectResponse
    {
        $data = $this->validated($request, $sandSystem);
        $renamed = $sandSystem->name !== $data['name'];
        $oldName = $sandSystem->name;
        $sandSystem->rename($data['name']);
        $sandSystem->update(collect($data)->except('name')->all());

        return back()->with('success', $renamed
            ? "Sand system renamed from {$oldName} to {$data['name']}. Patterns, stations, work lists and moulding runs now show the new name."
            : "Sand system {$data['name']} updated.");
    }

    public function destroy(SandSystem $sandSystem): RedirectResponse
    {
        $usage = $sandSystem->usage();
        if ($usage) {
            $used = collect($usage)->map(fn ($count, $what) => "$count $what")->implode(', ');

            return back()->withErrors(['sand_system' => "{$sandSystem->name} is used by $used, so it cannot be deleted. Set it to inactive to stop offering it."]);
        }
        $sandSystem->delete();

        return back()->with('success', "Sand system {$sandSystem->name} deleted.");
    }

    /** @return array{name: string, system_type: string, status?: string, sort_order?: ?int, description?: ?string} */
    private function validated(Request $request, ?SandSystem $sandSystem = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'system_type' => ['required', Rule::in(array_keys(SandSystem::TYPES))],
            'status' => [$sandSystem ? 'required' : 'nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['name'] = trim(preg_replace('/\s+/', ' ', $data['name']));
        $taken = SandSystem::whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])
            ->when($sandSystem, fn ($q) => $q->whereKeyNot($sandSystem->id))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => "There is already a sand system called {$data['name']}."]);
        }

        return array_filter($data, fn ($v, $k) => $v !== null || $k === 'description', ARRAY_FILTER_USE_BOTH);
    }

    /** A unique code made from the name; codes of deleted sands stay taken. */
    private function freeCode(string $name): string
    {
        $base = Str::upper(Str::limit(Str::slug($name, '_'), 30, '')) ?: 'SAND';
        $code = $base;
        for ($i = 2; SandSystem::withTrashed()->where('code', $code)->exists(); $i++) {
            $code = $base.'_'.$i;
        }

        return $code;
    }
}
