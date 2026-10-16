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
        $materials = Material::query()
            ->withCount(['lots', 'chargeBatchItems'])
            ->orderBy('code')
            ->paginate(25);

        return view('materials.index', compact('materials'));
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

        return redirect()
            ->route('materials.show', $material)
            ->with('success', "Material {$material->code} updated.");
    }

    private function validateMaterial(
        Request $request,
        ?Material $material = null
    ): array {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('materials', 'code')
                    ->ignore($material?->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'material_type' => [
                'required', 'string', 'max:40',
                Rule::in([
                    'METAL',
                    'RETURN',
                    'ALLOY',
                    'FLUX',
                    'FUEL',
                    'SAND',
                    'BINDER',
                    'ADDITIVE',
                    'OTHER',
                ]),
            ],
            'base_uom' => ['required', 'string', 'max:20'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
            'is_stock_tracked' => ['required', 'boolean'],
            'requires_lot_tracking' => ['required', 'boolean'],
            'minimum_stock_qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'reorder_qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'standard_cost_per_uom' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'description' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }
}

