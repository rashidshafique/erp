<?php

namespace App\Http\Controllers;

use App\Models\ChargeBatch;
use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChargeBatchController extends Controller
{
    public function index(): View
    {
        $chargeBatches = ChargeBatch::query()
            ->with('items')
            ->withCount('items')
            ->orderBy('process_type')
            ->orderBy('code')
            ->paginate(25);

        return view('charge-batches.index', compact('chargeBatches'));
    }

    public function create(): View
    {
        $materials = Material::query()
            ->where('status', 'ACTIVE')
            ->orderBy('code')
            ->get();

        return view('charge-batches.create', [
            'chargeBatch' => new ChargeBatch,
            'materials' => $materials,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRecipe($request);

        $items = $validated['items'];
        unset($validated['items']);

        $recipeTotals = $this->validateRecipeItems(
            $items,
            isset($validated['target_total_charge_weight_kg'])
                ? (float) $validated['target_total_charge_weight_kg']
                : null,
        );
        // For fixed-kg recipes the components are the source of truth. For percentage
        // recipes the user-entered total is the basis for calculating each material.
        $validated['target_total_charge_weight_kg'] = $recipeTotals['total_kg'];

        $chargeBatch = DB::transaction(function () use ($validated, $items) {
            $batch = ChargeBatch::create($validated);

            foreach (array_values($items) as $index => $item) {
                $batch->items()->create([
                    'material_id' => $item['material_id'],
                    'sequence' => $index + 1,
                    'component_role' => $item['component_role'],
                    'target_qty_kg' => $item['target_qty_kg'] ?? null,
                    'target_percent' => $item['target_percent'] ?? null,
                    'minimum_qty_kg' => $item['minimum_qty_kg'] ?? null,
                    'maximum_qty_kg' => $item['maximum_qty_kg'] ?? null,
                    'is_required' => (bool) ($item['is_required'] ?? false),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $batch;
        });

        return redirect()
            ->route('charge-batches.show', $chargeBatch)
            ->with('success', "Charge recipe {$chargeBatch->code} created.");
    }

    public function show(ChargeBatch $chargeBatch): View
    {
        $chargeBatch->load(['items.material']);

        return view('charge-batches.show', compact('chargeBatch'));
    }

    private function validateRecipe(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('charge_batches', 'code')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'process_type' => ['required', Rule::in(['CUPOLA', 'INDUCTION', 'OTHER'])],
            'status' => ['required', Rule::in(['DRAFT', 'ACTIVE', 'INACTIVE'])],
            'target_metal_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'target_total_charge_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'revision' => ['required', 'string', 'max:20'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.material_id' => [
                'required', 'uuid',
                Rule::exists('materials', 'id')
                    ->where('status', 'ACTIVE')
                    ->whereNull('deleted_at'),
            ],
            'items.*.component_role' => ['required', 'string', 'max:40'],
            'items.*.target_qty_kg' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.target_percent' => ['nullable', 'numeric', 'gt:0', 'lte:100'],
            'items.*.minimum_qty_kg' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.maximum_qty_kg' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.is_required' => ['nullable', 'boolean'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function validateRecipeItems(array $items, ?float $targetTotalChargeWeightKg): array
    {
        $materialIds = array_column($items, 'material_id');

        if (count($materialIds) !== count(array_unique($materialIds))) {
            throw ValidationException::withMessages([
                'items' => 'A material may appear only once in a recipe. Combine its quantity into one component.',
            ]);
        }

        $hasFixed = false;
        $hasPercent = false;
        $percentTotal = 0.0;

        foreach ($items as $index => $item) {
            $qty = $item['target_qty_kg'] ?? null;
            $percent = $item['target_percent'] ?? null;

            if (($qty === null) === ($percent === null)) {
                throw ValidationException::withMessages([
                    "items.$index.target_qty_kg" => 'Enter either a fixed quantity or a percentage for each component, but not both.',
                ]);
            }

            $hasFixed = $hasFixed || $qty !== null;
            $hasPercent = $hasPercent || $percent !== null;

            if ($percent !== null) {
                $percentTotal += (float) $percent;
            }

            $minimum = isset($item['minimum_qty_kg']) ? (float) $item['minimum_qty_kg'] : null;
            $maximum = isset($item['maximum_qty_kg']) ? (float) $item['maximum_qty_kg'] : null;

            if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
                throw ValidationException::withMessages([
                    "items.$index.maximum_qty_kg" => 'Maximum quantity cannot be less than minimum quantity.',
                ]);
            }
        }

        if ($hasFixed && $hasPercent) {
            throw ValidationException::withMessages([
                'items' => 'Use either all fixed-quantity components or all percentage-based components in a recipe.',
            ]);
        }

        if ($hasPercent && abs($percentTotal - 100.0) > 0.01) {
            throw ValidationException::withMessages([
                'items' => 'Percentage-based recipe components must total 100%. Current total: '
                    .\App\Support\Formats::number($percentTotal, 4).'%.',
            ]);
        }

        if ($hasPercent) {
            if ($targetTotalChargeWeightKg === null || $targetTotalChargeWeightKg <= 0) {
                throw ValidationException::withMessages([
                    'target_total_charge_weight_kg' => 'Enter the target total charge weight in kg for a percentage-based recipe so each material quantity can be calculated.',
                ]);
            }

            return ['mode' => 'percentage', 'total_kg' => round($targetTotalChargeWeightKg, 3)];
        }

        $totalKg = array_sum(array_map(
            static fn (array $item): float => (float) ($item['target_qty_kg'] ?? 0),
            $items,
        ));

        if ($totalKg <= 0) {
            throw ValidationException::withMessages([
                'items' => 'The total fixed quantity must be greater than zero.',
            ]);
        }

        return ['mode' => 'fixed', 'total_kg' => round($totalKg, 3)];
    }
}
