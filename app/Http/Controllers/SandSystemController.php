<?php

namespace App\Http\Controllers;

use App\Models\BaseSand;
use App\Models\Material;
use App\Models\SandSystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The foundry-wide, editable list of sand systems, and the base sands they are made of. */
class SandSystemController extends Controller
{
    public function index(): View
    {
        $sandSystems = SandSystem::ordered()->with('sands')->get();

        return view('sand-systems.index', [
            'sandSystems' => $sandSystems,
            'sandTypes' => SandSystem::TYPES,
            'sandUsage' => $sandSystems->mapWithKeys(fn (SandSystem $s) => [$s->id => $s->usage()])->all(),
            'baseSands' => BaseSand::ordered()->with('material')->withCount('sandSystems')->get(),
            'sandMaterials' => Material::where('material_type', 'SAND')->orderBy('name')->get(['id', 'code', 'name', 'status']),
        ]);
    }

    /**
     * Adds a sand system with a code made from its name. A quick add from a dropdown asks for JSON and gets back the option
     * to add to it; records hold the sand by name, so those dropdowns use the label as the value.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $sandSystem = SandSystem::create(collect($data)->except(['main_sand_id', 'sand_ids'])->all() + [
            'code' => SandSystem::freeCode($data['name']),
            'status' => 'ACTIVE',
            'sort_order' => $data['sort_order'] ?? SandSystem::nextSortOrder(),
        ]);
        if ($request->has('main_sand_id')) {
            $sandSystem->syncSands($data['main_sand_id'] ?? null, $data['sand_ids'] ?? []);
        }
        if ($request->wantsJson()) {
            return response()->json(['id' => $sandSystem->id, 'label' => $sandSystem->name], 201);
        }

        return back()->with('success', "Sand system {$data['name']} added.");
    }

    public function update(Request $request, SandSystem $sandSystem): RedirectResponse
    {
        $data = $this->validated($request, $sandSystem);
        $renamed = $sandSystem->name !== $data['name'];
        $oldName = $sandSystem->name;
        $sandSystem->rename($data['name']);
        $sandSystem->update(collect($data)->except(['name', 'main_sand_id', 'sand_ids'])->all());
        if ($request->has('main_sand_id')) {
            $sandSystem->syncSands($data['main_sand_id'] ?? null, $data['sand_ids'] ?? []);
        }

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

    /** @return array{name: string, system_type: string, status?: string, sort_order?: ?int, description?: ?string, main_sand_id?: ?string, sand_ids?: array<int, string>} */
    private function validated(Request $request, ?SandSystem $sandSystem = null): array
    {
        $baseSand = Rule::exists('base_sands', 'id')->whereNull('deleted_at');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'system_type' => ['required', Rule::in(array_keys(SandSystem::TYPES))],
            'status' => [$sandSystem ? 'required' : 'nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'main_sand_id' => ['nullable', 'uuid', $baseSand],
            'sand_ids' => ['nullable', 'array'],
            'sand_ids.*' => ['uuid', $baseSand],
        ], ['main_sand_id.exists' => 'Choose a sand from the list of sands.', 'sand_ids.*.exists' => 'Choose sands from the list of sands.']);
        $data['name'] = SandSystem::tidyName($data['name']);
        if (SandSystem::nameTaken($data['name'], $sandSystem?->id)) {
            throw ValidationException::withMessages(['name' => "There is already a sand system called {$data['name']}."]);
        }

        return array_filter($data, fn ($v, $k) => $v !== null || $k === 'description', ARRAY_FILTER_USE_BOTH);
    }
}
