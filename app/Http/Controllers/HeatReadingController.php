<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\ChargeBatch;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HeatChargePosting;
use App\Models\HeatMaterialConsumption;
use App\Models\HeatPowerReading;
use App\Models\HeatProcessReading;
use App\Models\IronGrade;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockMovement;
use App\Services\FurnaceEligibility;
use App\Services\Production\ChargeSchedule;
use App\Services\Production\HeatEta;
use App\Services\Stock\InsufficientStockException;
use App\Services\Stock\StockLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    /**
     * Changes the target metal weight of a heat that is not closed. A cupola's planned charges follow the new target, unless
     * someone had set them by hand or more charges are already in than the new target needs.
     */
    public function updateTarget(Request $request, Heat $heat): RedirectResponse
    {
        if (in_array($heat->status, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['target_metal_weight_kg' => 'The target of a closed or cancelled heat cannot be changed.']);
        }
        $data = $request->validate(['target_metal_weight_kg' => ['required', 'numeric', 'gt:0', 'max:999999999']]);
        $heat->loadMissing('furnace', 'chargeBatch.items', 'materialConsumptions');

        $followsTarget = $heat->isCupola() && (int) $heat->charge_rounds === $heat->defaultChargeRounds();
        $before = $heat->chargeRounds();
        $heat->update($data);

        $note = '';
        if ($followsTarget) {
            $charged = (int) $heat->materialConsumptions->max('charge_round');
            $heat->update(['charge_rounds' => max($heat->defaultChargeRounds(), $charged, 1)]);
            if ($heat->charge_rounds !== $before) {
                $note = " Planned charges changed from {$before} to {$heat->charge_rounds}.";
            }
        }

        return back()->with('success', 'Target metal set to '.\App\Support\Formats::number((float) $data['target_metal_weight_kg'], 1).' kg.'.$note);
    }

    /** How many charges would already be due for a heat started at a past time: the question the create form asks. */
    public function chargePreview(Request $request, ChargeSchedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'furnace_id' => ['required', 'uuid', 'exists:furnaces,id'], 'charge_batch_id' => ['required', 'uuid', 'exists:charge_batches,id'],
            'started_at' => ['required', 'date', 'before_or_equal:now'], 'target_metal_weight_kg' => ['nullable', 'numeric', 'gt:0'],
        ]);
        $furnace = Furnace::findOrFail($data['furnace_id']);
        $heat = (new Heat(['process_type' => $furnace->furnace_type, 'actual_start_at' => $data['started_at'], 'target_metal_weight_kg' => $data['target_metal_weight_kg'] ?? null]))
            ->setRelation('furnace', $furnace)->setRelation('chargeBatch', ChargeBatch::with('items')->findOrFail($data['charge_batch_id']))->setRelation('materialConsumptions', collect());
        $plan = $schedule->for($heat);

        return response()->json([
            'count' => $plan['reason'] ? 0 : $schedule->past($plan)->count(), 'reason' => $plan['reason'],
            'interval' => $plan['interval_minutes'] ? \App\Support\Formats::duration($plan['interval_minutes']) : null, 'rate' => $plan['rate']['label'] ?? null,
        ]);
    }

    /** The finishing estimate of a running heat, polled by the heat page every minute. */
    public function eta(Heat $heat, HeatEta $eta): JsonResponse
    {
        $heat->loadMissing('furnace');
        $run = $heat->furnaceRun();

        return response()->json([
            'running' => $run['running'],
            'since' => $run['start']?->toIso8601String(),
            'eta' => ($e = $eta->for($heat)) ? $this->etaPayload($e) : null,
            'charge_due_at' => (app(ChargeSchedule::class)->for($heat)['next']['due_at'] ?? null)?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $e */
    private function etaPayload(array $e): array
    {
        return [
            'finish_at' => $e['finish_at']->toIso8601String(), 'time' => $e['finish_at']->format('H:i'), 'in' => \App\Support\Formats::duration((int) now()->diffInMinutes($e['finish_at'], false)),
            'overdue' => $e['finish_at']->isPast(), 'remaining_kg' => $e['remaining_kg'], 'basis' => $e['label'],
        ];
    }

    public function show(Heat $heat, HeatEta $eta, ChargeSchedule $chargeSchedule): View
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

        $chargeRounds = $heat->chargeRounds();
        $plannedChargeItems->each(function ($item) use ($heat, $chargeRounds) {
            $perRound = $item->perChargeKg();
            $item->per_charge_qty_kg = $perRound;
            $item->planned_calculated_qty_kg = $perRound !== null && $item->charge_stage !== 'BED' ? $perRound * $chargeRounds : $perRound;
        });

        $roundsDone = $heat->materialConsumptions->whereNotNull('charge_round')->where('charge_round', '>', 0)->pluck('charge_round')->unique()->count();
        $bedItems = $plannedChargeItems->where('charge_stage', 'BED');
        $bedDone = $heat->materialConsumptions->where('charge_round', 0)->isNotEmpty();
        $nextRound = ($bedItems->isNotEmpty() && ! $bedDone) ? 0 : ((int) $heat->materialConsumptions->max('charge_round') + 1);
        $nextRoundItems = $plannedChargeItems->where('charge_stage', $nextRound === 0 ? 'BED' : 'ROUND')->values();

        $activeMaterials = Material::query()
            ->where('status', 'ACTIVE')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $acceptedLots = MaterialLot::query()
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
        $finish = $eta->for($heat);
        $schedule = $chargeSchedule->for($heat);
        $pastCount = $chargeSchedule->past($schedule)->count();
        $dueLines = $schedule['next'] ? $chargeSchedule->lines($heat, $schedule['next']['round'])->map(fn ($l) => ($activeMaterials->firstWhere('id', $l['material_id'])?->name ?? 'Material').' '.\App\Support\Formats::number($l['actual_qty_kg'], 1).' kg')->implode(', ') : '';
        $finish = $finish ? $this->etaPayload($finish) : null;

        return view('heats.show', compact(
            'heat',
            'finish',
            'schedule',
            'pastCount',
            'dueLines',
            'plannedChargeItems',
            'activeMaterials',
            'acceptedLots',
            'actualChargeQty',
            'actualChargeCost',
            'actualChargeCostPerKg',
            'chargeIsPosted',
            'chargeRounds',
            'roundsDone',
            'nextRound',
            'nextRoundItems',
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

    public function create(): View
    {
        $furnaces = Furnace::query()
            ->where('status', 'ACTIVE')
            ->orderBy('code')
            ->get();

        $grades = IronGrade::where('status', 'ACTIVE')->orderBy('code')->get();

        $chargeBatches = ChargeBatch::query()
            ->where('status', 'ACTIVE')
            ->with('items.material')
            ->orderBy('code')
            ->get();

        $recipeData = $chargeBatches->map(fn ($batch) => [
            'id' => $batch->id,
            'process' => $batch->process_type,
            'code' => $batch->code,
            'name' => $batch->name,
            'weight' => $batch->items->contains(fn ($item) => $item->target_percent !== null)
                ? $batch->target_total_charge_weight_kg
                : ($batch->items->isNotEmpty()
                    ? $batch->items->sum(fn ($item) => (float) ($item->target_qty_kg ?? 0))
                    : $batch->target_total_charge_weight_kg),
            'items' => $batch->items->map(fn ($item) => [
                'material' => $item->material?->code.' — '.$item->material?->name,
                'role' => $item->component_role,
                'qty' => $item->target_qty_kg,
                'percent' => $item->target_percent,
            ])->values(),
        ])->values();

        $gradeTemps = $grades->mapWithKeys(fn (IronGrade $grade) => [$grade->id => $grade->suggestedTapTemperature()])->filter();

        return view('heats.create', compact('furnaces', 'grades', 'chargeBatches', 'recipeData', 'gradeTemps'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'furnace_id' => [
                'required',
                'uuid',
                Rule::exists('furnaces', 'id')
                    ->where('status', 'ACTIVE')
                    ->whereNull('deleted_at'),
            ],
            'iron_grade_id' => ['nullable', 'uuid', 'exists:iron_grades,id'],
            'charge_batch_id' => [
                'nullable',
                'uuid',
                Rule::exists('charge_batches', 'id')->where('status', 'ACTIVE'),
            ],
            // A heat that has already started needs the time it started; that cannot be in the future.
            'planned_start_at' => ['nullable', 'date', 'required_if:already_started,1', 'before_or_equal:now'],
            'already_started' => ['nullable', 'boolean'],
            'fill_charges' => ['nullable', 'boolean'],
            'target_metal_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'operating_power_limit_kw' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'target_temperature_c' => ['nullable', 'numeric', 'gt:0', 'max:9999'],
            'operator_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ], ['planned_start_at.before_or_equal' => 'The start cannot be in the future. Leave "already started" off for a planned start.', 'planned_start_at.required_if' => 'Enter the time the heat started.']);

        $furnace = Furnace::query()
            ->whereKey($validated['furnace_id'])
            ->firstOrFail();

        if (! empty($validated['charge_batch_id'])) {
            $recipe = ChargeBatch::findOrFail($validated['charge_batch_id']);
            if ($recipe->process_type !== $furnace->furnace_type) {
                throw ValidationException::withMessages([
                    'charge_batch_id' => 'This charge recipe is for '.$recipe->process_type.' melting, but the furnace is '.$furnace->furnace_type.'.',
                ]);
            }
        }

        if (! empty($validated['iron_grade_id'])) {
            $grade = IronGrade::findOrFail($validated['iron_grade_id']);
            $elig = app(FurnaceEligibility::class);
            if (! $elig->canMelt($furnace, $grade)) {
                throw ValidationException::withMessages(['iron_grade_id' => $elig->reason($furnace, $grade)]);
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
                throw ValidationException::withMessages([
                    'operating_power_limit_kw' => 'The heat operating limit cannot exceed the furnace maximum of '
                        .\App\Support\Formats::number((float) $maximum, 3).' kW.',
                ]);
            }
        } else {
            // Induction power limits do not apply to cupola or other furnace types.
            $validated['operating_power_limit_kw'] = null;
        }

        $filled = 0;
        $heat = DB::transaction(function () use ($validated, $furnace, $request, &$filled) {
            $date = now()->format('Ymd');

            // Serialize heat-number generation for the same date in PostgreSQL.
            DB::select(
                'SELECT pg_advisory_xact_lock(hashtext(?))',
                ['heat-number-'.$date]
            );

            $lastNumber = (int) Heat::query()
                ->where('heat_number', 'like', 'H-'.$date.'-%')
                ->selectRaw("COALESCE(MAX(CAST(SPLIT_PART(heat_number, '-', 3) AS INTEGER)), 0) AS max_number")
                ->value('max_number');

            $heat = Heat::create([
                'heat_number' => 'H-'.$date.'-'.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT),
                'furnace_id' => $furnace->id,
                'process_type' => $furnace->furnace_type,
                'iron_grade_id' => $validated['iron_grade_id'] ?? null,
                'charge_batch_id' => $validated['charge_batch_id'] ?? null,
                'status' => 'PLANNED',
                'planned_start_at' => $validated['planned_start_at'] ?? null,
                'target_metal_weight_kg' => $validated['target_metal_weight_kg'] ?? null,
                'operating_power_limit_kw' => $validated['operating_power_limit_kw'] ?? null,
                'target_temperature_c' => $validated['target_temperature_c'] ?? null,
                'operator_name' => $validated['operator_name'],
                'notes' => $validated['notes'] ?? null,
            ]);
            $heat->load('furnace', 'chargeBatch');
            if ($heat->isCupola()) {
                $heat->update(['charge_rounds' => $heat->defaultChargeRounds()]);
            }

            // A heat entered after it started begins at its start time, and its charges since then can be filled in from the recipe.
            if ($request->boolean('already_started')) {
                $heat->update(['actual_start_at' => $heat->planned_start_at, 'status' => $heat->isCupola() ? 'MELTING' : 'IN_PROGRESS']);
                if ($request->boolean('fill_charges') && $heat->chargeBatch) {
                    $filled = app(ChargeSchedule::class)->backfill($heat->fresh(), $validated['operator_name'], app(\App\Services\Production\ChargeRecorder::class));
                }
            }

            return $heat;
        });

        return redirect()
            ->route('heats.show', $heat)
            ->with('success', "Heat {$heat->heat_number} created.".($request->boolean('already_started') ? ' It is recorded as started at '.$heat->planned_start_at->displayDateTime().'.' : '').($filled ? " {$filled} ".($filled === 1 ? 'charge' : 'charges').' filled in from the recipe at their scheduled times.' : ''));
    }

    public function hold(Request $request, Heat $heat): RedirectResponse
    {
        $data = $request->validate(['hold_reason' => ['required', 'in:SCRAP,EQUIPMENT'], 'hold_note' => ['required', 'string', 'max:500']]);
        $heat->update($data + ['held_at' => now()]);

        return back()->with('success', 'Heat put on hold.');
    }

    public function resume(Heat $heat): RedirectResponse
    {
        $heat->update(['hold_reason' => null, 'hold_note' => null, 'held_at' => null]);

        return back()->with('success', 'Heat taken off hold.');
    }

    public function postCharge(Request $request, Heat $heat): RedirectResponse
    {
        $validated = $request->validate([
            'posted_by' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $withoutLot = 0;
        DB::transaction(function () use ($heat, $validated, &$withoutLot) {
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

                    $returnMaterial = Material::query()
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

                        app(StockLedger::class)->receive($returnLot, $quantity, $unitCost, 'kg', [
                            'movement_number' => 'RET-'.strtoupper(str_replace('-', '', $entry->id)),
                            'source_type' => 'HEAT_RETURN',
                            'source_id' => $entry->id,
                            'occurred_at' => $now,
                            'recorded_by' => $validated['posted_by'],
                            'reason' => "Internal return received from heat {$lockedHeat->heat_number}, entry {$entry->sequence}.",
                        ]);
                    }

                    $entry->actual_total_cost = $returnCost;
                    $entry->save();

                    $totalQuantity += $quantity;
                    $totalCost += $returnCost;

                    continue;
                }

                if ($entry->consumption_type === 'RAW_MATERIAL' && $entry->material_id && ! $entry->material_lot_id
                    && ! Material::whereKey($entry->material_id)->value('requires_lot_tracking')) {
                    // Charged at standard cost without a lot: its cost is kept as recorded and no stock layer is issued.
                    $totalQuantity += $quantity;
                    $totalCost += (float) $entry->actual_total_cost;
                    $withoutLot++;

                    continue;
                }

                if ($entry->consumption_type !== 'RAW_MATERIAL'
                    || ! $entry->material_id
                    || ! $entry->material_lot_id) {
                    throw ValidationException::withMessages([
                        'posted_by' => "Charge entry {$entry->sequence} has invalid material or lot details.",
                    ]);
                }

                $lot = MaterialLot::query()
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

                try {
                    $issue = app(StockLedger::class)->issue($lot, $quantity, 'kg', [
                        'movement_number' => 'ISS-'.substr(str_replace('-', '', $lockedHeat->id), 0, 20).'-'.$entry->sequence,
                        'source_type' => 'HEAT_CHARGE',
                        'source_id' => $lockedHeat->id,
                        'occurred_at' => $now,
                        'recorded_by' => $validated['posted_by'],
                        'reason' => 'Charge issued to heat '.$lockedHeat->heat_number,
                    ]);
                } catch (InsufficientStockException $e) {
                    throw ValidationException::withMessages(['posted_by' => $e->getMessage()]);
                }
                $issueCost = (float) $issue->total_cost;

                $entry->actual_total_cost = $issueCost;
                $entry->save();

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
            ->with('success', 'Heat charge posted. Stock issues and charge costs have been recorded.'
                .($withoutLot ? " {$withoutLot} ".($withoutLot === 1 ? 'entry was' : 'entries were').' charged at standard cost without a lot, so no stock was issued for '.($withoutLot === 1 ? 'it' : 'them').'.' : ''));
    }
}
