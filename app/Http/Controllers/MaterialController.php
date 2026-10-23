<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function index(): View
    {
        return view('materials.index');
    }

    public function create(): View
    {
        return view('materials.create', [
            'material' => new Material(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateMaterial($request);
        $chemistry = $this->validatedChemistry($request);

        $material = Material::create($validated);
        if ($chemistry !== null) {
            $material->saveChemistry($chemistry);
        }

        return redirect()
            ->route('materials.show', $material)
            ->with('success', "Material {$material->code} created.");
    }

    public function show(Material $material): View
    {
        $material->load([
            'lots' => fn ($query) => $query->orderByDesc('created_at'),
            'elements',
        ]);

        $material->loadCount('chargeBatchItems');

        $lotIds = $material->lots->pluck('id');

        $receiptMovements = \App\Models\StockMovement::query()
            ->whereIn('receipt_lot_id', $lotIds)
            ->get()
            ->keyBy('receipt_lot_id');

        return view('materials.show', [
            'material' => $material,
            'receiptMovements' => $receiptMovements,
            'onHand' => app(\App\Services\Stock\StockLevels::class)->onHand([$material->id])[$material->id] ?? 0.0,
            'stockLocations' => app(\App\Domains\Core\ModuleRegistry::class)->isEnabled('inventory')
                ? \App\Models\StockLocation::where('is_active', true)->orderBy('code')->get()
                : collect(),
        ]);
    }

    public function edit(Material $material): View
    {
        $material->load('elements');

        return view('materials.edit', compact('material'));
    }

    public function update(Request $request, Material $material): RedirectResponse
    {
        $validated = $this->validateMaterial($request, $material);
        $chemistry = $this->validatedChemistry($request);

        $material->update($validated);
        if ($chemistry !== null) {
            $material->saveChemistry($chemistry);
        }
        app(\App\Services\Stock\ReorderWatcher::class)->check($material);

        return redirect()
            ->route('materials.show', $material)
            ->with('success', "Material {$material->code} updated.");
    }

    /** The chemistry panel's figures, or null when the request carries none, which leaves the stored chemistry alone. @return array<string, array<string, mixed>>|null */
    private function validatedChemistry(Request $request): ?array
    {
        if (! $request->has('chemistry')) {
            return null;
        }
        $chemistry = $request->validate(Material::chemistryRules())['chemistry'] ?? [];
        if ($problems = Material::chemistryProblems($chemistry)) {
            throw \Illuminate\Validation\ValidationException::withMessages($problems);
        }

        return $chemistry;
    }

    private function validateMaterial(
        Request $request,
        ?Material $material = null
    ): array {
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);   // "fesi-75" and "FESI-75" are the same code
        }
        $data = $request->validate(Material::rules($material));
        if (blank($data['code'] ?? null)) {
            unset($data['code']);
        }

        return $data;
    }
}

