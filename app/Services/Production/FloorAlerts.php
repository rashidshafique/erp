<?php

namespace App\Services\Production;

use App\Models\MouldingWorkList;
use App\Models\User;
use App\Notifications\AppNotice;
use App\Services\Notifications\Notify;

/**
 * Tells the next person on the floor that work has reached them, for the hand-offs between hats. Each notice goes to everyone
 * holding the permission for the next step, and never to the person who just did the step.
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
        ), $by);
    }
}
