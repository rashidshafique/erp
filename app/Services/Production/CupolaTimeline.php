<?php

namespace App\Services\Production;

use App\Models\Heat;
use App\Models\Pour;
use App\Support\PouringRules;
use Illuminate\Support\Carbon;

/**
 * A cupola heat runs from light-up through blast on and the first tap to the bottom drop. The status follows the timeline, and
 * a pour started within the first-iron minutes of the first tap is marked as first iron.
 */
class CupolaTimeline
{
    /** @param array{actual_tapped_weight_kg?: ?string, actual_start_at?: ?Carbon, blast_on_at?: ?Carbon, first_tap_at?: ?Carbon, dropped_at?: ?Carbon} $times */
    public function save(Heat $heat, array $times): void
    {
        $heat->fill($times);
        if ($heat->isCupola()) {
            $heat->status = match (true) {
                $heat->dropped_at !== null => 'CLOSED',
                $heat->first_tap_at !== null => 'TAPPING',
                $heat->blast_on_at !== null || $heat->actual_start_at !== null => 'MELTING',
                default => 'PLANNED',
            };
            $heat->closed_at = $heat->dropped_at;
        }
        $heat->save();
    }

    /** Called as a pour starts: the first pour of a cupola heat is its first tap, and early pours are first iron. */
    public function onPourStart(Pour $pour): bool
    {
        $heat = $pour->heat;
        if (! $heat?->isCupola()) {
            return false;
        }
        if ($heat->first_tap_at === null) {
            $this->save($heat, ['first_tap_at' => $pour->started_at ?? now()]);
        }
        $window = PouringRules::minutes('first_iron_minutes');

        return $window > 0 && ($pour->started_at ?? now())->lte($heat->first_tap_at->copy()->addMinutes($window));
    }

    /** Minutes from blast on to the first tap, when both are known. */
    public function burnInMinutes(Heat $heat): ?int
    {
        return $heat->blast_on_at && $heat->first_tap_at ? (int) $heat->blast_on_at->diffInMinutes($heat->first_tap_at) : null;
    }
}
