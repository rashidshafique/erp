<?php

namespace App\Services\Production;

use App\Models\Heat;
use App\Support\PouringRules;
use Carbon\CarbonInterface;
use App\Models\ChargeBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * When each charge of a heat's recipe is due. The furnace melts a round's metal at its melting rate (HeatEta::rate: measured on
 * the heat, else the furnace's recorded average, else its rated speed), so a new round is due one "metal per round ÷ rate" after
 * the last: the bed at the start of the heat, charge 1 at blast on, or an hour after light-up when blast on is not recorded (the lightup_to_first_charge_minutes rule),, then each charge that long after the
 * one before it was actually put in. Anchoring on what was really charged keeps a late charge from making the next one look
 * early. The schedule only says what is due; recording a charge is done by a person, so the consumption stays measured.
 */
class ChargeSchedule
{
    public function __construct(private HeatEta $eta) {}

    /**
     * @return array{
     *     rate: ?array{rate: float, source: string, label: string}, interval_minutes: ?int, metal_per_round_kg: ?float,
     *     rounds: Collection<int, array{round: int, due_at: ?CarbonInterface, recorded_at: ?CarbonInterface, state: string, minutes: ?int}>,
     *     next: ?array{round: int, due_at: ?CarbonInterface, recorded_at: ?CarbonInterface, state: string, minutes: ?int},
     *     reason: ?string
     * } state is "done", "skipped" (removed on purpose, so not added back), "due" (its time has come), "upcoming" (the next one, not yet due), "projected" (a later one, timed from the next) or "waiting" (the heat has not started, so no time can be given).
     */
    public function for(Heat $heat): array
    {
        $heat->loadMissing('chargeBatch.items', 'materialConsumptions', 'furnace');
        $batch = $heat->chargeBatch;
        $none = ['rate' => null, 'interval_minutes' => null, 'metal_per_round_kg' => null, 'rounds' => collect(), 'next' => null, 'reason' => null];
        if (! $batch || $batch->items->isEmpty()) {
            return ['reason' => 'No charge recipe is selected on this heat.'] + $none;
        }

        $perRound = $batch->metalPerChargeKg();
        $measured = $this->eta->rate($heat);
        $hasBed = $batch->items->where('charge_stage', 'BED')->isNotEmpty();
        $total = $heat->chargeRounds();
        $skipped = $heat->skippedChargeRounds();
        $charged = $heat->materialConsumptions->whereNotNull('charge_round')->groupBy('charge_round')
            ->map(fn (Collection $entries) => $entries->max('consumed_at'));

        $reason = null;
        if ($perRound <= 0) {
            $reason = 'The recipe sets no metal weight and no metal materials per charge, so the timing cannot be worked out.';
        } elseif (! $measured) {
            $reason = 'The furnace has no melting rate on record. Set its nominal melting rate to get a schedule.';
        }
        $interval = $reason === null ? (int) max(1, round($perRound / $measured['rate'] * 60)) : null;

        $start = $heat->actual_start_at;
        // A cupola's bed burns after light-up, so its first metal charge is due that long after the start unless blast on was recorded.
        $blast = $heat->isCupola() ? ($heat->blast_on_at ?? $start?->copy()->addMinutes(PouringRules::minutes('lightup_to_first_charge_minutes'))) : $start;

        $rounds = collect();
        $previousDue = null;
        foreach (array_merge($hasBed ? [0] : [], $total > 0 ? range(1, $total) : []) as $round) {
            $recordedAt = $charged->get($round);
            $due = null;
            if ($interval !== null) {
                if ($round === 0) {
                    $due = $start;
                } elseif ($round === 1) {
                    $due = $blast ?? $charged->get(0);
                } else {
                    $prior = $charged->get($round - 1) ?? $previousDue;
                    $due = $prior?->copy()->addMinutes($interval);
                }
            }
            $previousDue = $recordedAt ?? $due;
            $state = match (true) {
                $recordedAt !== null => 'done',
                in_array($round, $skipped, true) => 'skipped',
                $due === null => 'waiting',
                // Only the first charge not yet in can be due; the ones after it are projections that move as charges go in.
                $rounds->contains(fn ($r) => ! in_array($r['state'], ['done', 'skipped'], true)) => 'projected',
                $due->lte(now()) => 'due',
                default => 'upcoming',
            };
            $rounds->push(['round' => $round, 'due_at' => $due, 'recorded_at' => $recordedAt, 'state' => $state,
                'minutes' => $due && $recordedAt === null && $state !== 'projected' ? (int) round(now()->diffInMinutes($due, false)) : null]);
        }

        return [
            'rate' => $measured ? $measured + ['label' => ['live' => 'live rate', 'recorded' => 'recorded average', 'rated' => 'rated speed'][$measured['source']].' '.number_format($measured['rate']).' kg/h'] : null,
            'interval_minutes' => $interval, 'metal_per_round_kg' => $perRound > 0 ? $perRound : null,
            'rounds' => $rounds, 'reason' => $reason,
            'next' => in_array($heat->status, ['CLOSED', 'CANCELLED'], true) ? null : $rounds->first(fn ($r) => ! in_array($r['state'], ['done', 'skipped'], true)),
        ];
    }

    /**
     * The charges that should already have gone in: every one not yet recorded whose time has come, in order.
     *
     * @param  array<string, mixed>  $plan  from for()
     * @return Collection<int, array{round: int, due_at: CarbonInterface, recorded_at: null, state: string, minutes: ?int}>
     */
    public function past(array $plan): Collection
    {
        return $plan['rounds']->filter(fn ($r) => ! in_array($r['state'], ['done', 'skipped'], true) && $r['due_at'] !== null && $r['due_at']->lte(now()))->values();
    }

    /**
     * Records the past charges from the recipe, each at its scheduled time, as if the furnace had been charged on time. They are
     * marked in their notes as filled in from the schedule, because nobody observed them. Returns how many were recorded.
     */
    public function backfill(Heat $heat, string $by, ChargeRecorder $recorder): int
    {
        $count = 0;
        foreach ($this->past($this->for($heat)) as $round) {
            $lines = $this->lines($heat, $round['round']);
            if ($lines->isEmpty()) {
                continue;
            }
            $recorder->recordRound($heat, $round['round'], $lines, $by, $round['due_at'], 'Filled in from the charge schedule, not observed.');
            $count++;
        }

        return $count;
    }

    /**
     * Under the automatic charge rule, records the charges that have come due on a running heat with a recipe, each at its time.
     * Called every minute by the scheduler and whenever the heat's board refreshes, so charges keep going in at the furnace's
     * speed even where the scheduler is not running. The heat row is locked, so the two never record the same charge. Returns
     * how many were recorded; a heat on hold, dropped, not started or without a recipe gets none.
     */
    public function addDue(Heat $heat, ChargeRecorder $recorder): int
    {
        if (! PouringRules::autoCharges() || ! $heat->actual_start_at || ! $heat->charge_batch_id || $heat->dropped_at || $heat->hold_reason
            || in_array($heat->status, ['CLOSED', 'CANCELLED'], true)) {
            return 0;
        }

        return DB::transaction(function () use ($heat, $recorder) {
            $heat = Heat::whereKey($heat->id)->lockForUpdate()->firstOrFail()->load(['furnace', 'chargeBatch.items', 'materialConsumptions']);

            return $this->backfill($heat, 'Auto (furnace speed)', $recorder);
        });
    }

    /**
     * The lines of a round as the recipe sets them: one per material, with the weight one charge puts in.
     *
     * @return Collection<int, array{material_id: string, actual_qty_kg: float}>
     */
    public function lines(Heat $heat, int $round): Collection
    {
        $heat->loadMissing('chargeBatch.items.chargeBatch');

        return $heat->chargeBatch?->items->where('charge_stage', $round === 0 ? 'BED' : 'ROUND')->map(fn ($item) => ['material_id' => $item->material_id, 'actual_qty_kg' => (float) $item->perChargeKg()])
            ->filter(fn ($l) => $l['actual_qty_kg'] > 0)->values() ?? collect();
    }
}
