<?php

namespace App\Services\Planning;

use App\Domains\QuickFlow\Services\NextHeatPlan;
use App\Models\Heat;
use App\Models\MouldingRun;
use App\Models\PlannedHeat;
use App\Models\Pour;
use App\Models\ProductionOrder;
use App\Support\MeltingCalendar;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Everything dated that the calendar shows for a span of days: planned heats, heats actually melted, pours, moulding runs and
 * order due dates, past and future, plus which days are campaign days under the melting calendar.
 */
class CalendarEvents
{
    public const KINDS = [
        'PLANNED' => 'Planned heat',
        'HEAT' => 'Heat',
        'POUR' => 'Pour',
        'MOULDING' => 'Moulding run',
        'DUE' => 'Order due',
    ];

    /**
     * Events by date (Y-m-d), each in time order.
     *
     * @return Collection<string, Collection<int, array{kind: string, label: string, detail: string, url: ?string}>>
     */
    public function between(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $events = collect();

        PlannedHeat::with(['ironGrade', 'furnace'])->where('status', '!=', 'CANCELLED')->whereNull('heat_id')
            ->whereBetween('planned_date', [$from->toDateString(), $to->toDateString()])->get()
            ->each(fn ($p) => $events->push($this->event('PLANNED', $p->planned_date, $p->plan_number, trim($p->ironGrade?->code.' · '.$p->furnace?->code.' · '.round((float) $p->planned_metal_kg).' kg', ' ·'), route('heat-plan.index'))));

        Heat::with(['ironGrade', 'furnace'])->where('status', '!=', 'CANCELLED')->get()
            ->each(function ($h) use ($events, $from, $to) {
                $at = $h->actual_start_at ?? $h->planned_start_at ?? $h->created_at;
                if ($at && $at->betweenIncluded($from, $to)) {
                    $events->push($this->event('HEAT', $at, $h->heat_number, trim($h->ironGrade?->code.' · '.$h->furnace?->code.' · '.strtolower($h->status), ' ·'), route('heats.show', $h)));
                }
            });

        Pour::with('heat')->whereBetween('started_at', [$from, $to])->get()
            ->each(fn ($p) => $events->push($this->event('POUR', $p->started_at, $p->pour_number, trim($p->heat?->heat_number.' · '.strtolower($p->status), ' ·'), route('pours.show', $p))));

        MouldingRun::whereBetween('run_date', [$from->toDateString(), $to->toDateString()])->get()
            ->each(fn ($r) => $events->push($this->event('MOULDING', $r->run_date, $r->run_number, ($r->moulds_made_qty ?? $r->planned_mould_qty).' moulds · '.strtolower($r->status), route('moulding-runs.show', $r))));

        ProductionOrder::with('product')->whereIn('status', ['RELEASED', 'IN_PROGRESS'])
            ->whereBetween('required_date', [$from->toDateString(), $to->toDateString()])->get()
            ->each(fn ($o) => $events->push($this->event('DUE', $o->required_date, $o->production_order_number, (string) $o->product?->code, route('production-orders.show', $o))));

        return $events->sortBy('sort')->groupBy('date');
    }

    /**
     * One bar per open production order that overlaps the span: from when it started (else its planned start, else when it was
     * created) to its due date, with the pieces made and poured against the quantity ordered. An order is late when its due date has
     * passed with pieces still to make or pour, and at risk when no campaign day is left to pour in time.
     *
     * @return Collection<int, array{order: ProductionOrder, customer: string, start: CarbonInterface, due: ?CarbonInterface, ordered: int, made: int, poured: int, late: bool, at_risk: bool}>
     */
    public function orderBars(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        return app(NextHeatPlan::class)->orders()->map(function (array $row) {
            $order = $row['order'];
            $start = ($order->actual_started_at ?? $order->planned_start_date ?? $order->created_at)->copy()->startOfDay();
            $due = $row['due']?->copy()->startOfDay() ?? $order->planned_finish_date?->copy()->startOfDay();
            $open = $row['awaiting'] + $row['to_mould'] > 0;

            return [
                'order' => $order, 'customer' => $row['customer'], 'start' => $start, 'due' => $due, 'ordered' => $row['ordered'], 'made' => $row['made'], 'poured' => $row['poured'],
                'late' => $open && $due !== null && $due->lt(today()),
                'at_risk' => $open && $due !== null && $due->gte(today()) && ! MeltingCalendar::checkDue($due)['ok'],
            ];
        })->filter(fn ($b) => $b['start']->lte($to) && ($b['due'] ?? $b['start'])->gte($from))
            ->sortBy(fn ($b) => ($b['due'] ?? $b['start'])->format('Y-m-d').$b['order']->production_order_number)->values();
    }

    /** Whether the furnace is lit on this day under the melting calendar. */
    public function isCampaignDay(CarbonInterface $day): bool
    {
        return MeltingCalendar::isCampaignDay($day);
    }

    /** @return array{kind: string, label: string, detail: string, url: ?string, date: string, sort: string} */
    private function event(string $kind, CarbonInterface $at, string $label, string $detail, ?string $url): array
    {
        return ['kind' => $kind, 'label' => $label, 'detail' => $detail, 'url' => $url, 'date' => $at->toDateString(), 'sort' => $at->format('Y-m-d H:i:s').$kind];
    }
}
