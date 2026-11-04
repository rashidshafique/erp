<?php

namespace App\Services\Quality;

use App\Models\QualityInspection;
use App\Models\ThermalAnalysisTest;
use App\Models\User;
use App\Notifications\AppNotice;
use App\Services\Notifications\Notify;

/**
 * Tells the right people how a test came out. A fail goes to the people who must act (quality managers, plus the melt shop for a
 * thermal test or planners for a lot); a pass goes to the people waiting on it. The person who recorded it is not told.
 */
class TestAlerts
{
    public function __construct(private Notify $notify) {}

    /** A thermal sample, one or both cups. */
    public function thermal(ThermalAnalysisTest $test, ?User $by): int
    {
        $cups = $test->sample_group_id ? $test->siblings()->get()->push($test) : collect([$test]);
        $results = $cups->pluck('result');
        $failed = $results->contains('FAIL');
        if (! $failed && ! $results->contains('PASS')) {
            return 0;
        }
        $heat = $test->heat?->heat_number ?? 'heat';
        $notice = new AppNotice(
            'test-result', "Thermal test {$heat}: ".($failed ? 'FAIL' : 'PASS'),
            $failed ? 'Held for review. '.collect($test->findings ?? [])->flatten()->take(2)->implode(' ') : 'Within the window for the grade.',
            $failed ? 'danger' : 'success', route('thermal.show', $test),
        );

        return $this->send($failed ? ['quality.manage', 'melting.operate'] : ['melting.operate'], $notice, $by);
    }

    /** A chemistry analysis of a heat; nothing is sent when it could not be judged. @param  array{ok: ?bool, findings: array<int, string>}  $verdict */
    public function chemistry(\App\Models\Heat $heat, array $verdict, ?User $by): int
    {
        if ($verdict['ok'] === null) {
            return 0;
        }
        $bad = ! $verdict['ok'];
        $notice = new AppNotice('test-result', "Chemistry {$heat->heat_number}: ".($bad ? 'OUT OF LIMITS' : 'PASS'),
            $bad ? implode(' ', array_slice($verdict['findings'], 0, 2)) : 'Within the grade limits.', $bad ? 'danger' : 'success', route('heats.show', $heat));

        return $this->send($bad ? ['quality.manage', 'melting.operate'] : ['melting.operate'], $notice, $by);
    }

    /** A completed inspection of a lot. */
    public function inspection(QualityInspection $inspection, ?User $by): int
    {
        $inspection->loadMissing('lot');
        $bad = $inspection->quantity_rejected + $inspection->quantity_rework > 0 || $inspection->results()->where('passed', false)->exists();
        $lot = $inspection->lot?->lot_number ?? 'lot';
        $notice = new AppNotice(
            'test-result', "{$inspection->inspection_type} inspection {$lot}: ".($bad ? 'FAIL' : 'PASS'),
            "Accepted {$inspection->quantity_accepted}, rejected {$inspection->quantity_rejected}, rework {$inspection->quantity_rework}.",
            $bad ? 'danger' : 'success', route('inspections.show', $inspection),
        );

        return $this->send($bad ? ['quality.manage', 'planning.manage'] : ['planning.manage'], $notice, $by);
    }

    /** @param  list<string>  $permissions */
    private function send(array $permissions, AppNotice $notice, ?User $by): int
    {
        $users = collect($permissions)->flatMap(fn (string $p) => $this->notify->usersWithPermission($p))->unique('id')
            ->reject(fn (User $u) => $by && $u->id === $by->id);
        $users->each(fn (User $u) => $u->notify($notice));

        return $users->count();
    }
}
