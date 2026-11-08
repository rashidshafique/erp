<?php

namespace App\Services\Dashboard;

use App\Http\Middleware\EnforceAccess;
use App\Models\MouldingWorkList;
use App\Models\MouldingWorkListItem;
use App\Models\PlannedHeat;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Production\DailyMouldingList;
use App\Services\Production\FlaskLoad;
use App\Services\Production\WorkListService;
use App\Support\WorkListRules;

/**
 * What the planner has to settle before the morning: the next moulding list, remakes after a shortfall, flask sizes that run short,
 * and a melting plan older than the list. Each row carries one button, and a row is left out when the person may not use that button.
 */
class PlannerWaiting
{
    public function __construct(private DailyMouldingList $lists, private WorkListService $workLists, private FlaskLoad $flasks) {}

    /**
     * @return list<array{title:string, detail:string, tone:string, action:string, url:string, post:array<string,string>|null}>
     */
    public function rows(User $user): array
    {
        $day = WorkListRules::get('auto_prepare') === 'SAME_DAY' ? today() : today()->addDay();
        $dayName = $day->isToday() ? 'Today' : ($day->isTomorrow() ? 'Tomorrow' : $day->format('D j M'));
        $rows = [];
        $list = $this->lists->current($day);
        $late = now()->hour >= (int) WorkListRules::get('remind_evening_hour') && ! $day->isToday() || ($day->isToday() && now()->hour >= (int) WorkListRules::get('remind_morning_hour'));

        if (! $list && ProductionOrder::open()->exists()) {
            $rows[] = ['title' => "{$dayName}'s moulding list not prepared", 'detail' => 'Open orders are waiting for moulds', 'tone' => $late ? 'bad' : 'warn', 'action' => 'Prepare list', 'url' => route('work-lists.prepare'), 'post' => ['day' => $day->toDateString()], 'need' => 'work-lists.prepare'];
        } elseif ($list && $list->status === 'DRAFT') {
            $rows[] = ['title' => "{$dayName}'s moulding list not issued", 'detail' => $list->list_number.' · '.$list->items()->count().' rows · '.(int) $list->items()->sum('planned_moulds').' moulds', 'tone' => $late ? 'bad' : 'warn', 'action' => 'Open list', 'url' => route('work-lists.show', $list), 'post' => null, 'need' => 'work-lists.show'];
        }

        $remakes = $this->workLists->runsNeedingRemake();
        if ($remakes->isNotEmpty()) {
            $names = $remakes->take(3)->map(fn ($r) => ($r->productionOrder?->production_order_number ?? $r->run_number).' ('.$this->workLists->shortfall($r).')')->implode(' · ');
            $rows[] = ['title' => 'Moulds needed again after shortfall', 'detail' => $names.($remakes->count() > 3 ? ' · +'.($remakes->count() - 3).' more' : ''), 'tone' => 'warn', 'action' => 'Add to list', 'url' => $list ? route('work-lists.show', $list) : route('work-lists.index'), 'post' => null, 'need' => 'work-lists.index'];
        }

        foreach ($this->flasks->bySize(today())->where('short', '>', 0) as $row) {
            $rows[] = ['title' => 'Flask short · '.$row['size']->name, 'detail' => "{$row['wanted']} sets needed · {$row['free']} free", 'tone' => 'warn', 'action' => 'Resolve', 'url' => route('flasks.index'), 'post' => null, 'need' => 'flasks.index'];
        }

        $builtAt = PlannedHeat::where('status', 'PLANNED')->max('created_at');
        $changedAt = $builtAt ? MouldingWorkListItem::whereHas('workList', fn ($q) => $q->whereIn('status', ['DRAFT', 'ISSUED']))->max('updated_at') : null;
        if ($builtAt && $changedAt && $changedAt > $builtAt) {
            $rows[] = ['title' => 'Melting plan older than the moulding list', 'detail' => 'The list changed after the plan was built', 'tone' => 'warn', 'action' => 'Rebuild plan', 'url' => route('heat-plan.rebuild'), 'post' => ['from' => today()->toDateString()], 'need' => 'heat-plan.rebuild'];
        }

        return array_values(array_filter($rows, fn ($r) => EnforceAccess::allows($user, $r['need'])));
    }
}
