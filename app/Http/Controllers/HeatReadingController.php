<?php

namespace App\Http\Controllers;
use App\Models\HeatChargePosting;
use App\Models\AppSetting;
use App\Models\MaterialLot;
use App\Models\HeatMaterialConsumption;
use App\Models\StockLotCostLayer;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Heat;
use App\Models\HeatPowerReading;
use App\Models\HeatProcessReading;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HeatReadingController extends Controller
{
    public function index(): View
    {
        $heats = Heat::query()
            ->with('furnace')
            ->withCount(['powerReadings', 'processReadings'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('heats.index', compact('heats'));
    }

    public function show(Heat $heat): View
    {
        $heat->load([
            'furnace',
            'chargeBatch.items.material',
            'materialConsumptions.material',
            'materialConsumptions.materialLot',
            'powerReadings',
            'processReadings',
        ]);

        $heat->load('chargePosting');

        $plannedChargeItems = $heat->chargeBatch?->items ?? collect();

        $plannedChargeItems->each(function ($item) use ($heat) {
            $basis = $heat->chargeBatch?->target_total_charge_weight_kg;
            $item->planned_calculated_qty_kg = $item->target_qty_kg !== null
                ? (float) $item->target_qty_kg
                : (
                    $item->target_percent !== null && $basis !== null
                        ? (float) $basis * (float) $item->target_percent / 100
                        : null
                );
        });

        $activeMaterials = \App\Models\Material::query()
            ->where('status', 'ACTIVE')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $acceptedLots = \App\Models\MaterialLot::query()
            ->with('material')
            ->where('status', 'ACCEPTED')
            ->whereNotNull('unit_cost')
            ->orderBy('lot_number')
            ->get();

        $heat->load('chargePosting');
        $actualChargeQty = (float) $heat->materialConsumptions->sum('actual_qty_kg');
        $actualChargeCost = $heat->chargePosting
            ? (float) $heat->chargePosting->total_cost_pkr
            : (float) $heat->materialConsumptions->sum('actual_total_cost');

        $actualChargeCostPerKg = $actualChargeQty > 0
            ? $actualChargeCost / $actualChargeQty
            : null;

        $chargeIsPosted = $heat->chargePosting !== null;

        return view('heats.show', compact(
            'heat',
            'plannedChargeItems',
            'activeMaterials',
            'acceptedLots',
            'actualChargeQty',
            'actualChargeCost',
            'actualChargeCostPerKg',
            'chargeIsPosted',
        ));
    }

    public function storePowerReading(
        Request $request,
        Heat $heat
    ): RedirectResponse {
        if ($heat->furnace?->furnace_type !== 'INDUCTION') {
            abort(422, 'Power meter readings are only enabled for induction heats.');
        }

        $validated = $request->validate([
            'recorded_at' => ['required', 'date'],
            'actual_power_kw' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'cumulative_energy_kwh' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'recorded_by' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (
            ($validated['actual_power_kw'] ?? null) === null
            && ($validated['cumulative_energy_kwh'] ?? null) === null
        ) {
            throw ValidationException::withMessages([
                'actual_power_kw' => 'Enter actual power, cumulative energy, or both.',
            ]);
        }

        DB::transaction(function () use ($heat, $validated) {
            // Serialize reading-number allocation for this heat.
            Heat::query()->whereKey($heat->getKey())->lockForUpdate()->firstOrFail();

            $number = ((int) HeatPowerReading::query()
                ->where('heat_id', $heat->id)
                ->max('reading_number')) + 1;

            HeatPowerReading::create([
                'heat_id' => $heat->id,
                'reading_number' => $number,
                'recorded_at' => $validated['recorded_at'],
                'actual_power_kw' => $validated['actual_power_kw'] ?? null,
                'cumulative_energy_kwh' => $validated['cumulative_energy_kwh'] ?? null,
                'source' => 'MANUAL',
                'recorded_by' => $validated['recorded_by'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('heats.show', $heat)
            ->with('success', 'Power reading saved.');
    }

    public function storeProcessReading(
        Request $request,
        Heat $heat
    ): RedirectResponse {
        if ($heat->furnace?->furnace_type !== 'CUPOLA') {
            abort(422, 'Process readings in this form are enabled for cupola heats.');
        }

        $validated = $request->validate([
            'recorded_at' => ['required', 'date'],
            'parameter_code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9_.-]+$/',
            ],
            'numeric_value' => ['required', 'numeric', 'min:-999999999', 'max:999999999'],
            'unit' => ['required', 'string', 'max:30'],
            'recorded_by' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($heat, $validated) {
            Heat::query()->whereKey($heat->getKey())->lockForUpdate()->firstOrFail();

            $number = ((int) HeatProcessReading::query()
                ->where('heat_id', $heat->id)
                ->max('reading_number')) + 1;

            HeatProcessReading::create([
                'heat_id' => $heat->id,
                'reading_number' => $number,
                'parameter_code' => strtoupper($validated['parameter_code']),
                'numeric_value' => $validated['numeric_value'],
                'unit' => $validated['unit'],
                'recorded_at' => $validated['recorded_at'],
                'source' => 'MANUAL',
                'recorded_by' => $validated['recorded_by'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('heats.show', $heat)
            ->with('success', 'Process reading saved.');
    }

    public function create(): \Illuminate\View\View
    {
        $furnaces = \App\Models\Furnace::query()
            ->where('status', 'ACTIVE')
            ->orderBy('code')
            ->get();

        $grades = \App\Models\IronGrade::where('status', 'ACTIVE')->orderBy('code')->get();

        $chargeBatches = \App\Models\ChargeBatch::query()
            ->where('status', 'ACTIVE')
            ->with('items.material')
            ->orderBy('code')
            ->get();

        $recipeData = $chargeBatches->map(fn ($batch) => [
            'id' => $batch->id,
            'process' => $batch->process_type,
            'code' => $batch->code,
            'name' => $batch->name,
            'weight' => $batch->target_total_charge_weight_kg,
            'items' => $batch->items->map(fn ($item) => [
                'material' => $item->material?->code.' — '.$item->material?->name,
                'role' => $item->component_role,
                'qty' => $item->target_qty_kg,
                'percent' => $item->target_percent,
            ])->values(),
        ])->values();

        return view('heats.create', compact('furnaces', 'grades', 'chargeBatches', 'recipeData'));
    }

    public function store(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'furnace_id' => [
                'required',
                'uuid',
                \Illuminate\Validation\Rule::exists('furnaces', 'id')
                    ->where('status', 'ACTIVE')
                    ->whereNull('deleted_at'),
            ],
            'iron_grade_id' => ['nullable', 'uuid', 'exists:iron_grades,id'],
            'charge_batch_id' => [
                'nullable',
                'uuid',
                \Illuminate\Validation\Rule::exists('charge_batches', 'id')->where('status', 'ACTIVE'),
            ],
            'planned_start_at' => ['nullable', 'date'],
            'target_metal_weight_kg' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'operating_power_limit_kw' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'target_temperature_c' => ['nullable', 'numeric', 'gt:0', 'max:9999'],
            'operator_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $furnace = \App\Models\Furnace::query()
            ->whereKey($validated['furnace_id'])
            ->firstOrFail();

        if (! empty($validated['charge_batch_id'])) {
            $recipe = \App\Models\ChargeBatch::findOrFail($validated['charge_batch_id']);
            if ($recipe->process_type !== $furnace->furnace_type) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'charge_batch_id' => 'This charge recipe is for '.$recipe->process_type.' melting, but the furnace is '.$furnace->furnace_type.'.',
                ]);
            }
        }

        if (! empty($validated['iron_grade_id'])) {
            $grade = \App\Models\IronGrade::findOrFail($validated['iron_grade_id']);
            $elig = app(\App\Services\FurnaceEligibility::class);
            if (! $elig->canMelt($furnace, $grade)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['iron_grade_id' => $elig->reason($furnace, $grade)]);
            }
        }

        if ($furnace->furnace_type === 'INDUCTION') {
            $limit = $validated['operating_power_limit_kw'] ?? null;
            $maximum = $furnace->max_operating_power_kw;

            if (
                $limit !== null
                && $maximum !== null
                && (float) $limit > (float) $maximum
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'operating_power_limit_kw' =>
                        'The heat operating limit cannot exceed the furnace maximum of '
                        .number_format((float) $maximum, 3).' kW.',
                ]);
            }
        } else {
            // Induction power limits do not apply to cupola or other furnace types.
            $validated['operating_power_limit_kw'] = null;
        }

        $heat = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $furnace) {
            $date = now()->format('Ymd');

            // Serialize heat-number generation for the same date in PostgreSQL.
            \Illuminate\Support\Facades\DB::select(
                'SELECT pg_advisory_xact_lock(hashtext(?))',
                ['heat-number-'.$date]
            );

            $lastNumber = (int) \App\Models\Heat::query()
                ->where('heat_number', 'like', 'H-'.$date.'-%')
                ->selectRaw("COALESCE(MAX(CAST(SPLIT_PART(heat_number, '-', 3) AS INTEGER)), 0) AS max_number")
                ->value('max_number');

            return \App\Models\Heat::create([
                'heat_number' => 'H-'.$date.'-'.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT),
                'furnace_id' => $furnace->id,
                'process_type' => $furnace->furnace_type,
                'iron_grade_id' => $validated['iron_grade_id'] ?? null,
                'charge_batch_id' => $validated['charge_batch_id'] ?? null,
                'status' => 'PLANNED',
                'planned_start_at' => $validated['planned_start_at'] ?? null,
                'target_metal_weight_kg' => $validated['target_metal_weight_kg'],
                'operating_power_limit_kw' => $validated['operating_power_limit_kw'] ?? null,
                'target_temperature_c' => $validated['target_temperature_c'] ?? null,
                'operator_name' => $validated['operator_name'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('heats.show', $heat)
            ->with('success', "Heat {$heat->heat_number} created.");
    }

    public function postCharge(Request $request, Heat $heat): RedirectResponse
    {
        $validated = $request->validate([
            'posted_by' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($heat, $validated) {
            $lockedHeat = Heat::query()
                ->whereKey($heat->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedHeat->status, ['CLOSED', 'CANCELLED'], true)) {
                throw ValidationException::withMessages([
                    'posted_by' => 'A closed or cancelled heat cannot be posted.',
                ]);
            }

            if (HeatChargePosting::query()->where('heat_id', $lockedHeat->id)->exists()) {
                throw ValidationException::withMessages([
                    'posted_by' => 'This heat has already been posted.',
                ]);
            }

            $entries = HeatMaterialConsumption::query()
                ->where('heat_id', $lockedHeat->id)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->get();

            if ($entries->isEmpty()) {
                throw ValidationException::withMessages([
                    'posted_by' => 'Record at least one charge entry before posting.',
                ]);
            }

            $totalQuantity = 0.0;
            $totalCost = 0.0;
            $now = now();

            foreach ($entries as $entry) {
                $quantity = (float) $entry->actual_qty_kg;

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'posted_by' => "Charge entry {$entry->sequence} has an invalid quantity.",
                    ]);
                }

                if ($entry->consumption_type === 'INTERNAL_RETURN') {
                    $unitCost = (float) $entry->transfer_cost_per_kg;

                    if ($unitCost <= 0) {
                        throw ValidationException::withMessages([
                            'posted_by' => "Internal return entry {$entry->sequence} needs a positive transfer cost.",
                        ]);
                    }

                    if (! $entry->material_id) {
                        throw ValidationException::withMessages([
                            'posted_by' => "Internal return entry {$entry->sequence} has no material selected.",
                        ]);
                    }

                    $returnMaterial = \App\Models\Material::query()
                        ->whereKey($entry->material_id)
                        ->where('status', 'ACTIVE')
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->first();

                    if (! $returnMaterial) {
                        throw ValidationException::withMessages([
                            'posted_by' => "The material for internal return entry {$entry->sequence} is inactive or unavailable.",
                        ]);
                    }

                    $returnCost = round($quantity * $unitCost, 4);

                    $autoPostReturns = filter_var(
                        AppSetting::getValue('auto_post_internal_returns_to_stock', 'false'),
                        FILTER_VALIDATE_BOOLEAN
                    );

                    if ($autoPostReturns) {
                        if (! $returnMaterial->is_stock_tracked) {
                            throw ValidationException::withMessages([
                                'posted_by' => "Material {$returnMaterial->name} is not configured for stock tracking. Enable stock tracking or turn off automatic internal-return posting.",
                            ]);
                        }

                        $existingReturnMovement = StockMovement::query()
                            ->where('source_type', 'HEAT_RETURN')
                            ->where('source_id', $entry->id)
                            ->exists();

                        if ($existingReturnMovement) {
                            throw ValidationException::withMessages([
                                'posted_by' => "Internal return entry {$entry->sequence} already has a stock receipt. Investigate before reposting.",
                            ]);
                        }

                        $returnLotNumber = 'RET-'
                            .strtoupper(str_replace('-', '', $entry->id));

                        $returnLot = MaterialLot::create([
                            'lot_number' => $returnLotNumber,
                            'material_id' => $returnMaterial->id,
                            'supplier_lot_number' => null,
                            'supplier_name_snapshot' => null,
                            'received_date' => $now->toDateString(),
                            'status' => 'ACCEPTED',
                            'received_qty' => $quantity,
                            'accepted_qty' => $quantity,
                            'unit_cost' => $unitCost,
                            'currency_code' => 'PKR',
                            'heat_or_batch_reference' => $lockedHeat->heat_number,
                            'notes' => "Automatic internal-return receipt from heat {$lockedHeat->heat_number}, charge entry {$entry->sequence}.",
                        ]);

                        $returnMovement = StockMovement::create([
                            'movement_number' => 'RET-'
                                .strtoupper(str_replace('-', '', $entry->id)),
                            'movement_type' => 'RECEIPT',
                            'material_id' => $returnMaterial->id,
                            'material_lot_id' => $returnLot->id,
                            'quantity' => $quantity,
                            'uom' => 'kg',
                            'unit_cost' => $unitCost,
                            'total_cost' => $returnCost,
                            'source_type' => 'HEAT_RETURN',
                            'source_id' => $entry->id,
                            'occurred_at' => $now,
                            'recorded_by' => $validated['posted_by'],
                            'reason' => "Internal return received from heat {$lockedHeat->heat_number}, entry {$entry->sequence}.",
                        ]);

                        StockLotCostLayer::create([
                            'material_lot_id' => $returnLot->id,
                            'stock_movement_id' => $returnMovement->id,
                            'original_qty' => $quantity,
                            'remaining_qty' => $quantity,
                            'unit_cost' => $unitCost,
                            'currency_code' => 'PKR',
                            'received_at' => $now,
                        ]);
                    }

                    $entry->actual_total_cost = $returnCost;
                    $entry->save();

                    $totalQuantity += $quantity;
                    $totalCost += $returnCost;

                    continue;
                }

                if ($entry->consumption_type !== 'RAW_MATERIAL'
                    || ! $entry->material_id
                    || ! $entry->material_lot_id) {
                    throw ValidationException::withMessages([
                        'posted_by' => "Charge entry {$entry->sequence} has invalid material or lot details.",
                    ]);
                }

                $lot = \App\Models\MaterialLot::query()
                    ->whereKey($entry->material_lot_id)
                    ->where('material_id', $entry->material_id)
                    ->where('status', 'ACCEPTED')
                    ->lockForUpdate()
                    ->first();

                if (! $lot) {
                    throw ValidationException::withMessages([
                        'posted_by' => "The selected lot for charge entry {$entry->sequence} is not accepted or does not match its material.",
                    ]);
                }

                if (strtoupper((string) $lot->currency_code) !== 'PKR') {
                    throw ValidationException::withMessages([
                        'posted_by' => "Lot {$lot->lot_number} is not costed in PKR.",
                    ]);
                }

                $layers = StockLotCostLayer::query()
                    ->where('material_lot_id', $lot->id)
                    ->where('currency_code', 'PKR')
                    ->where('remaining_qty', '>', 0)
                    ->orderBy('received_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $available = $layers->sum(
                    fn ($layer) => (float) $layer->remaining_qty
                );

                if ($available + 0.000001 < $quantity) {
                    throw ValidationException::withMessages([
                        'posted_by' => "Insufficient posted stock in lot {$lot->lot_number}. Available: "
                            .number_format($available, 3)
                            ." kg; required: ".number_format($quantity, 3)." kg.",
                    ]);
                }

                $remainingToIssue = $quantity;
                $issueCost = 0.0;

                foreach ($layers as $layer) {
                    if ($remainingToIssue <= 0.000001) {
                        break;
                    }

                    $layerRemaining = (float) $layer->remaining_qty;
                    $taken = min($layerRemaining, $remainingToIssue);
                    $layerCost = round($taken * (float) $layer->unit_cost, 4);

                    $layer->remaining_qty = round($layerRemaining - $taken, 3);
                    $layer->save();

                    $remainingToIssue = round($remainingToIssue - $taken, 3);
                    $issueCost += $layerCost;
                }
                if ($remainingToIssue > 0.000001) {
                    throw ValidationException::withMessages([
                        'posted_by' => "Could not fully allocate charge entry {$entry->sequence} to available cost layers.",
                    ]);
                }
                $issueCost = round($issueCost, 4);
                $averageCost = round($issueCost / $quantity, 4);

                $entry->actual_total_cost = $issueCost;
                $entry->save();

                StockMovement::create([
                    'movement_number' => 'ISS-'
                        .substr(str_replace('-', '', $lockedHeat->id), 0, 20)
                        .'-'.$entry->sequence,
                    'movement_type' => 'ISSUE',
                    'material_id' => $lot->material_id,
                    'material_lot_id' => $lot->id,
                    'quantity' => $quantity,
                    'uom' => 'kg',
                    'unit_cost' => $averageCost,
                    'total_cost' => $issueCost,
                    'source_type' => 'HEAT_CHARGE',
                    'source_id' => $lockedHeat->id,
                    'occurred_at' => $now,
                    'recorded_by' => $validated['posted_by'],
                    'reason' => 'Charge issued to heat '.$lockedHeat->heat_number,
                ]);

                $totalQuantity += $quantity;
                $totalCost += $issueCost;
            }

            HeatChargePosting::create([
                'heat_id' => $lockedHeat->id,
                'status' => 'POSTED',
                'total_quantity_kg' => round($totalQuantity, 3),
                'total_cost_pkr' => round($totalCost, 4),
                'posted_by' => $validated['posted_by'],
                'posted_at' => $now,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('heats.show', $heat)
            ->with('success', 'Heat charge posted. Stock issues and charge costs have been recorded.');
    }
}
