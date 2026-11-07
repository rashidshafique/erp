<?php

namespace App\Services\Production;

use App\Models\Heat;
use App\Models\MouldingWorkList;
use App\Models\Pour;
use App\Models\User;
use App\Notifications\AppNotice;
use App\Services\Notifications\Notify;
use App\Support\PouringRules;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Tells the next person on the floor that work has reached them, for the hand-offs between hats. Each notice goes to everyone
 * holding the permission for the next step, and never to the person who just did the step, so a one-person foundry hears nothing.
 */
class FloorAlerts
{
    public function __construct(private Notify $notify) {}

    /** A moulding list was issued: the people who mould are told how much is waiting. */
    public function listIssued(MouldingWorkList $list, ?User $by): int
    {
        $items = $list->items()->get();
        $stations = $items->pluck('moulding_station_id')->unique()->count();

        return $this->notify->toPermissions(['moulding.operate'], new AppNotice(
            'list-issued',
            "Moulding list {$list->list_number} issued",
            $list->list_date->format('D j M').' · '.$list->shift.' shift · '.$items->sum('planned_moulds').' moulds on '.$stations.' '.($stations === 1 ? 'station' : 'stations').'.',
            'info',
            route('work-lists.show', $list),
            link: $list->list_number,
        ), $by);
    }

    /** A list is still a draft when the shift is near: the planner is reminded to issue it. */
    public function listNotIssued(MouldingWorkList $list): int
    {
        return $this->notify->toPermissions(['planning.manage'], new AppNotice(
            'list-not-issued',
            "Moulding list {$list->list_number} is not issued",
            $list->list_date->format('D j M').' · '.$list->shift.' shift · the moulders cannot see it until it is issued.',
            'warning',
            route('work-lists.show', $list),
            link: $list->list_number,
        ));
    }

    /** Moulds were counted before pouring: the melt shop learns how many are ready. */
    public function mouldsCounted(int $moulds, ?User $by): int
    {
        if ($moulds < 1) {
            return 0;
        }

        return $this->notify->toPermissions(['melting.operate'], new AppNotice(
            'moulds-counted',
            "{$moulds} moulds counted ready",
            'Counted before pouring, so the melt for them can go ahead.',
            'success',
            route('mould-readiness.index'),
            link: 'Moulds ready',
        ), $by);
    }

    /** Moulds were written off at the count: the planner sees the orders need them again. */
    public function mouldsShort(int $writtenOff, ?User $by): int
    {
        if ($writtenOff < 1) {
            return 0;
        }

        return $this->notify->toPermissions(['planning.manage'], new AppNotice(
            'moulds-short',
            "{$writtenOff} moulds written off at the count",
            'The orders need these moulds again. They are on the planner for the next list.',
            'warning',
            route('planner.index'),
            link: 'Remake needed',
        ), $by);
    }

    /** A heat started: the moulders are asked to count what is ready for it, a step to do. */
    public function heatStarted(Heat $heat, ?User $by): int
    {
        return $this->notify->toPermissions(['moulding.operate'], new AppNotice(
            'heat-started',
            "Heat {$heat->heat_number} started",
            'Count the moulds that are ready to pour.',
            'warning',
            route('mould-count.index'),
            link: 'Count',
        ), $by);
    }

    /** A charge is late on a running heat. */
    public function chargeOverdue(Heat $heat, int $round, int $minutesLate): int
    {
        return $this->notify->toPermissions(['melting.operate', 'planning.manage'], new AppNotice(
            'charge-overdue',
            "Heat {$heat->heat_number}: charge {$round} is {$minutesLate} min overdue",
            'The next charge on the recipe is past its time.',
            'danger',
            route('heats.show', $heat),
            "heat:{$heat->id}:{$round}",
            'Charge tab',
        ));
    }

    /**
     * Marks unread late-charge notices read once their charge is in, skipped, or the heat is no longer running, so an urgent
     * notice does not wait in the bell after the work is done. Limited to one heat when given.
     */
    public function settleCharges(?Heat $heat = null): int
    {
        $settled = 0;
        DatabaseNotification::query()->whereNull('read_at')->where('data', 'like', '%"kind":"charge-overdue"%')
            ->when($heat, fn ($q) => $q->where('data', 'like', '%"ref":"heat:'.$heat->id.':%'))
            ->get()->groupBy(fn (DatabaseNotification $n) => $n->data['ref'] ?? '')
            ->each(function ($notices, string $ref) use (&$settled) {
                if (! preg_match('/^heat:([^:]+):(\d+)$/', $ref, $m)) {
                    return;
                }
                $heat = Heat::find($m[1]);
                $round = (int) $m[2];
                $done = ! $heat || ! in_array($heat->status, Heat::RUNNING_STATUSES, true) || $heat->dropped_at !== null
                    || in_array($round, $heat->skippedChargeRounds(), true)
                    || $heat->materialConsumptions()->where('charge_round', $round)->exists();
                if ($done) {
                    $notices->each->markAsRead();
                    $settled += $notices->count();
                }
            });

        return $settled;
    }

    /** A pour was completed and made lots: quality has castings to check. */
    public function lotsMade(Pour $pour, int $lots, ?User $by): int
    {
        if ($lots < 1) {
            return 0;
        }

        return $this->notify->toPermissions(['quality.inspect'], new AppNotice(
            'lots-made',
            "Pour {$pour->pour_number}: {$lots} ".($lots === 1 ? 'lot' : 'lots').' to check',
            'Castings from this pour are waiting for their first check.',
            'info',
            route('lots.queue'),
            link: 'Lots queue',
        ), $by);
    }

    /** A heat closed with metal that no pour, leftover or drop explains, over the limits in the pouring rules. */
    public function heatClosed(Heat $heat, ?float $unaccountedKg, ?User $by): int
    {
        if ($unaccountedKg === null || ! PouringRules::unaccountedMatters($unaccountedKg, (float) $heat->actual_tapped_weight_kg)) {
            return 0;
        }

        return $this->notify->toPermissions(['planning.manage'], new AppNotice(
            'heat-unaccounted',
            "Heat {$heat->heat_number} closed with ".round(abs($unaccountedKg)).' kg unaccounted',
            'Tapped metal is not matched by pours and leftover. Look at the metal balance.',
            'warning',
            route('heats.show', $heat),
            link: "{$heat->heat_number} metal balance",
        ), $by);
    }
}
