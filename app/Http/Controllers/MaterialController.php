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

        $material = Material::create($validated);

        return redirect()
            ->route('materials.show', $material)
            ->with('success', "Material {$material->code} created.");
    }

    public function show(Material $material): View
    {
        $material->load([
            'lots' => fn ($query) => $query->orderByDesc('created_at'),
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
        return view('materials.edit', compact('material'));
    }

    public function update(Request $request, Material $material): RedirectResponse
    {
        $validated = $this->validateMaterial($request, $material);

        $material->update($validated);
        app(\App\Services\Stock\ReorderWatcher::class)->check($material);

        return redirect()
            ->route('materials.show', $material)
            ->with('success', "Material {$material->code} updated.");
    }

    private function validateMaterial(
        Request $request,
        ?Material $material = null
    ): array {
        return $request->validate(Material::rules($material));
    }
}

