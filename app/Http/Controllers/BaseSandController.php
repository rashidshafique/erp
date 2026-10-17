<?php

namespace App\Http\Controllers;

use App\Models\BaseSand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The base sands (silica, chromite, zircon…) sand systems are made of, each optionally linked to a raw material. */
class BaseSandController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        BaseSand::create($data + [
            'code' => BaseSand::freeCode($data['name']),
            'status' => 'ACTIVE',
            'sort_order' => $data['sort_order'] ?? BaseSand::nextSortOrder(),
        ]);

        return back()->with('success', "Sand {$data['name']} added.");
    }

    public function update(Request $request, BaseSand $baseSand): RedirectResponse
    {
        $baseSand->update($this->validated($request, $baseSand));

        return back()->with('success', "Sand {$baseSand->name} updated.");
    }

    public function destroy(BaseSand $baseSand): RedirectResponse
    {
        $count = $baseSand->sandSystems()->count();
        if ($count) {
            return back()->withErrors(['base_sand' => "{$baseSand->name} is used by $count sand system(s), so it cannot be deleted. Set it to inactive to stop offering it."]);
        }
        // Free the raw material so another sand can be linked to it.
        $baseSand->update(['material_id' => null]);
        $baseSand->delete();

        return back()->with('success', "Sand {$baseSand->name} deleted.");
    }

    /** @return array{name: string, status?: string, sort_order?: ?int, description: ?string, material_id: ?string} */
    private function validated(Request $request, ?BaseSand $baseSand = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'status' => [$baseSand ? 'required' : 'nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'material_id' => [
                'nullable', 'uuid',
                Rule::exists('materials', 'id')->where('material_type', 'SAND')->whereNull('deleted_at'),
                Rule::unique('base_sands', 'material_id')->ignore($baseSand?->id)->whereNull('deleted_at'),
            ],
        ], [
            'material_id.exists' => 'Choose a raw material whose type is Sand.',
            'material_id.unique' => 'That raw material is already linked to another sand.',
        ]);
        $data['name'] = BaseSand::tidyName($data['name']);
        if (BaseSand::nameTaken($data['name'], $baseSand?->id)) {
            throw ValidationException::withMessages(['name' => "There is already a sand called {$data['name']}."]);
        }
        $data['description'] ??= null;
        $data['material_id'] ??= null;

        return array_filter($data, fn ($v, $k) => $v !== null || in_array($k, ['description', 'material_id'], true), ARRAY_FILTER_USE_BOTH);
    }
}
