<?php

namespace App\Services\Production;

use App\Models\Heat;
use App\Support\PouringRules;
use Carbon\CarbonInterface;

/**
 * Judges a cupola's bottom drop against the time the campaign should have taken (target metal over the planned melting rate). A run
 * that reaches the set share of it (90% by default) counts as finished without asking; a shorter one has to be confirmed as
 * finished or dropped early, and an early drop needs its reason.
 */
class BottomDrop
{
    public function __construct(private HeatEta $eta) {}

    /**
     * @return array{expected: int, actual: int, percent: int, threshold: int, within: bool}|null null when there is no target or rate to judge by
     */
    public function assess(Heat $heat, ?CarbonInterface $start, CarbonInterface $droppedAt, ?float $target = null): ?array
    {
        $target ??= (float) $heat->target_metal_weight_kg;
        $rate = $this->eta->plannedRate($heat);
        if ($start === null || $target <= 0 || ! $rate) {
            return null;
        }
        $expected = (int) round($target / $rate * 60);
        $actual = max(0, (int) $start->diffInMinutes($droppedAt, false));
        $threshold = PouringRules::minutes('drop_finished_percent');

        return ['expected' => $expected, 'actual' => $actual, 'percent' => $expected > 0 ? (int) round($actual / $expected * 100) : 100, 'threshold' => $threshold,
            'within' => $expected <= 0 || $actual >= $expected * $threshold / 100];
    }

    /** Stores how the campaign ended; a null outcome clears it (the drop was taken back). */
    public function record(Heat $heat, ?array $assessment, ?string $outcome, ?string $reason): void
    {
        $heat->forceFill([
            'drop_outcome' => $outcome,
            'drop_reason' => $outcome === 'PREMATURE' ? trim((string) $reason) : null,
            'drop_expected_minutes' => $outcome !== null ? ($assessment['expected'] ?? null) : null,
            'drop_actual_minutes' => $outcome !== null ? ($assessment['actual'] ?? null) : null,
        ])->save();
    }
}
