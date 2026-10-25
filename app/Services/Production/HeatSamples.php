<?php

namespace App\Services\Production;

use App\Models\Heat;
use App\Models\Pour;
use App\Support\PouringRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The samples taken from a heat (thermal analysis and chemistry), on one timeline. A cupola is sampled while it melts, so a
 * pour is judged against the sample before it, or the first one after it when none came earlier.
 */
class HeatSamples
{
    /**
     * Each sample carries the carbon, carbon equivalent and silicon it gave (percent, null when not recorded): a thermal test's carbon
     * as reported by the analyser or else worked out from TL, and its silicon as used; a chemistry reading's own figures.
     *
     * @return Collection<int, array{kind: string, at: Carbon, reference: ?string, result: string, detail: ?string, id: string, c: ?float, ce: ?float, si: ?float}> oldest first
     */
    public function timeline(Heat $heat): Collection
    {
        $thermal = $heat->thermalTests()->get()->map(fn ($t) => [
            'kind' => 'THERMAL', 'at' => $t->taken_at, 'reference' => $t->sample_reference,
            'result' => match ($t->result) {
                'PASS' => 'PASS', 'FAIL' => 'FAIL', default => 'UNJUDGED'
            },
            'detail' => $t->result === 'FAIL' ? implode(' ', $t->findings ?? []) : null, 'id' => (string) $t->id,
            'c' => $this->number($t->carbon_reported_pct ?? $t->carbon_from_tl), 'ce' => $this->number($t->ce_from_tl), 'si' => $this->number($t->silicon_used),
        ]);
        $chemistry = $heat->chemistryReadings()->get()->map(fn ($r) => [
            'kind' => 'CHEMISTRY', 'at' => $r->tested_at ?? $r->created_at, 'reference' => $r->sample_reference ?: ('Reading '.$r->reading_number),
            'result' => $r->is_accepted === null ? 'UNJUDGED' : ($r->is_accepted ? 'PASS' : 'FAIL'),
            'detail' => null, 'id' => (string) $r->id,
            'c' => $this->number($r->carbon_percent), 'ce' => $this->number($r->carbon_equivalent), 'si' => $this->number($r->silicon_percent),
        ]);

        return $thermal->concat($chemistry)->filter(fn ($s) => $s['at'] !== null)->sortBy(fn ($s) => $s['at']->getTimestamp())->values();
    }

    private function number(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * Whether the next sample is due on a running cupola.
     *
     * @return ?array{last: ?Carbon, since_at: Carbon, minutes_since: int, interval: int, overdue: bool, minutes_left: int}
     */
    public function due(Heat $heat): ?array
    {
        if (! $heat->isCupola() || $heat->dropped_at) {
            return null;
        }
        $interval = PouringRules::minutes('test_interval_minutes');
        $started = $heat->first_tap_at ?? $heat->blast_on_at ?? $heat->actual_start_at;
        if ($interval < 1 || ! $started) {
            return null;
        }
        $last = $this->timeline($heat)->last()['at'] ?? null;
        $since = (int) ($last ?? $started)->diffInMinutes(now());

        return ['last' => $last, 'since_at' => $last ?? $started, 'minutes_since' => $since, 'interval' => $interval, 'overdue' => $since > $interval, 'minutes_left' => $interval - $since];
    }

    /**
     * The sample each kind of test gives a pour: the latest one before it started, else the first one after it.
     *
     * @return array<string, array{sample: array, timing: string, minutes: int}>
     */
    public function governing(Pour $pour): array
    {
        if (! $pour->started_at || ! $pour->heat) {
            return [];
        }
        $samples = $this->timeline($pour->heat);
        $found = [];
        foreach (['THERMAL', 'CHEMISTRY'] as $kind) {
            $ofKind = $samples->where('kind', $kind);
            $before = $ofKind->filter(fn ($s) => $s['at']->lte($pour->started_at))->last();
            $sample = $before ?? $ofKind->first(fn ($s) => $s['at']->gt($pour->started_at));
            if ($sample) {
                $found[$kind] = ['sample' => $sample, 'timing' => $before ? 'BEFORE' : 'AFTER', 'minutes' => (int) abs($sample['at']->diffInMinutes($pour->started_at))];
            }
        }

        return $found;
    }
}
