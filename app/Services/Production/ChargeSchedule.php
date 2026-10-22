<?php

namespace App\Services\Production;

use App\Models\Heat;
use Carbon\CarbonInterface;
use App\Models\ChargeBatch;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * When each charge of a heat's recipe is due. The furnace melts a round's metal at its melting rate (HeatEta::rate: measured on
 * the heat, else the furnace's recorded average, else its rated speed), so a new round is due one "metal per round ÷ rate" after
 * the last: the bed at the start of the heat, charge 1 at blast on (or once the bed is in), then each charge that long after the
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
     * } state is "done", "due" (its time has come), "upcoming" (the next one, not yet due), "projected" (a later one, timed from the next) or "waiting" (the heat has not started, so no time can be given).
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
        $blast = $heat->isCupola() ? ($heat->blast_on_at ?? $start) : $start;

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
                $due === null => 'waiting',
                // Only the first charge not yet in can be due; the ones after it are projections that move as charges go in.
                $rounds->contains(fn ($r) => $r['state'] !== 'done') => 'projected',
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
            'next' => in_array($heat->status, ['CLOSED', 'CANCELLED'], true) ? null : $rounds->firstWhere('state', '!=', 'done'),
        ];
    }

    /**
     * Adds the charges missing from a heat that is on, from the total metal charged so far. The total is worth so many charges of
     * the recipe (total ÷ metal per charge, to the nearest whole charge); every charge up to that number that is not recorded is
     * added at its scheduled time (never in the future), together with the start-up charge if it is missing. Their recipe
     * quantities are scaled so the metal recorded across all charges comes to the total. The entries are marked in their notes
     * as filled in from the total, because nobody observed them.
     *
     * @return array{added: int, charges: int, factor: float}
     *
     * @throws ValidationException when the heat is not on, has no usable recipe, or the total leaves nothing to add
     */
    public function fillToTotal(Heat $heat, float $totalMetalKg, string $by, ChargeRecorder $recorder): array
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['total_charged_kg' => $message]);
        $heat->loadMissing('chargeBatch.items', 'materialConsumptions');
        $batch = $heat->chargeBatch;
        if (in_array($heat->status, ['CLOSED', 'CANCELLED'], true) || ! $heat->actual_start_at) {
            $fail('The heat must be on (started and not closed) to fill in charges from a total.');
        }
        if (! $batch) {
            $fail('No charge recipe is selected on this heat.');
        }
        $perRound = $batch->metalPerChargeKg();
        if ($perRound <= 0) {
            $fail('The recipe sets no metal weight and no metal materials per charge, so a total cannot be turned into charges.');
        }
        $charges = (int) round($totalMetalKg / $perRound);
        if ($charges < 1) {
            $fail('That is less than one charge ('.number_format($perRound).' kg of metal).');
        }

        $metalIds = $batch->items->where('charge_stage', 'ROUND')->reject(fn ($i) => in_array(strtoupper((string) $i->component_role), ChargeBatch::NON_METAL_ROLES, true))->pluck('material_id');
        $recordedRounds = $heat->materialConsumptions->where('charge_round', '>', 0)->pluck('charge_round')->unique();
        $recordedMetal = (float) $heat->materialConsumptions->where('charge_round', '>', 0)->whereIn('material_id', $metalIds)->sum('actual_qty_kg');
        $missing = collect(range(1, $charges))->reject(fn ($r) => $recordedRounds->contains($r))->values();
        $needBed = $batch->items->where('charge_stage', 'BED')->isNotEmpty() && $heat->materialConsumptions->where('charge_round', 0)->isEmpty();
        if ($missing->isEmpty() && ! $needBed) {
            $fail("Charges for that total are already recorded ({$charges} charges).");
        }

        $factor = 1.0;
        if ($missing->isNotEmpty()) {
            $remaining = $totalMetalKg - $recordedMetal;
            if ($remaining <= 0) {
                $fail('The charges already recorded come to '.number_format($recordedMetal).' kg of metal, which is not less than that total.');
            }
            $factor = $remaining / ($missing->count() * $perRound);
            if ($factor < 0.25 || $factor > 2) {
                $fail('That total does not fit the recorded charges: the missing charges would be '.round($factor * 100).' % of the recipe weight.');
            }
        }

        // The schedule is read after the planned charges cover the total, so every added charge has a time.
        if ($charges > $heat->chargeRounds()) {
            $heat->update(['charge_rounds' => $charges]);
        }
        $due = $this->for($heat->fresh())['rounds']->keyBy('round');
        $added = 0;
        foreach (array_merge($needBed ? [0] : [], $missing->all()) as $round) {
            $lines = $this->lines($heat, $round)->map(fn ($l) => $round === 0 ? $l : ['actual_qty_kg' => round($l['actual_qty_kg'] * $factor, 3)] + $l);
            $at = $due->get($round)['due_at'] ?? now();
            $recorder->recordRound($heat, $round, $lines, $by, $at->gt(now()) ? now() : $at, 'Filled in from the total metal charged ('.number_format($totalMetalKg).' kg), not observed.');
            $added++;
        }

        return ['added' => $added, 'charges' => $charges, 'factor' => $factor];
    }

    /**
     * The charges that should already have gone in: every one not yet recorded whose time has come, in order.
     *
     * @param  array<string, mixed>  $plan  from for()
     * @return Collection<int, array{round: int, due_at: CarbonInterface, recorded_at: null, state: string, minutes: ?int}>
     */
    public function past(array $plan): Collection
    {
        return $plan['rounds']->filter(fn ($r) => $r['state'] !== 'done' && $r['due_at'] !== null && $r['due_at']->lte(now()))->values();
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
