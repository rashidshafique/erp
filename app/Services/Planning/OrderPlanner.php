<?php

namespace App\Services\Planning;

use App\Models\CastingLot;
use App\Models\MouldingStation;
use App\Models\MouldingWorkList;
use App\Models\MouldingWorkListItem;
use App\Models\PatternRevision;
use App\Models\ProductionOrder;
use App\Services\Production\LotService;
use App\Services\Production\WorkListService;
use App\Support\PlannerRules;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Plans the work that completes open production orders: ranks them by when they are due, loads the moulds they still need onto
 * the moulding stations shift by shift, and projects when each order will be finished. The first shift is the suggested work list.
 */
class OrderPlanner
{
    private const PRIORITY = ['URGENT' => 0, 'HIGH' => 1, 'NORMAL' => 2, 'LOW' => 3];

    public function __construct(private WorkListService $lists, private LotService $lots) {}

    /**
     * The plan from a given day: every open order best first with its projection, and the suggested rows for the first shift.
     *
     * @return array{orders: list<array<string, mixed>>, suggestion: array<string, array{station: MouldingStation, rows: list<array<string, mixed>>}>, unrated: Collection<int, MouldingStation>}
     */
    public function plan(CarbonInterface $from): array
    {
        $orders = $this->rank($from);
        $stations = MouldingStation::where('status', 'ACTIVE')->orderBy('code')->get()->keyBy('id');
        $shiftsPerDay = PlannerRules::get('shifts_per_day');
        $after = PlannerRules::get('days_after_moulding');

        // Station load, shift by shift: moulds already on open lists come first.
        $load = [];
        foreach ($this->openListMoulds($from)->groupBy('moulding_station_id') as $stationId => $items) {
            if ($stations->has($stationId)) {
                foreach ($items as $item) {
                    $this->allocate($load, $stations[$stationId], (int) $item->planned_moulds);
                }
            }
        }

        $planned = [];
        $suggestion = [];
        foreach ($orders as $o) {
            $row = $o + ['station' => null, 'finish' => null, 'state' => 'ON_TRACK', 'note' => null];
            if ($o['moulds_left'] > 0) {
                $station = $o['pattern'] ? $this->stationFor($o['pattern'], $stations, $load) : null;
                if (! $o['pattern']) {
                    [$row['state'], $row['note']] = ['NO_PATTERN', 'No approved pattern with a cavity map makes this product.'];
                } elseif (! $station) {
                    [$row['state'], $row['note']] = ['NO_RATE', 'No active station has moulds per shift set.'];
                } else {
                    $row['station'] = $station;
                    $shifts = $this->allocate($load, $station, $o['moulds_left']);
                    $first = $shifts[0];
                    if ($first['shift'] === 0) {
                        $suggestion[$station->id]['station'] = $station;
                        $suggestion[$station->id]['rows'][] = ['order' => $o['order'], 'pattern' => $o['pattern'], 'moulds' => $first['moulds']];
                    }
                    $row['finish'] = $from->copy()->addDays(intdiv(end($shifts)['shift'], $shiftsPerDay) + $after);
                }
            } elseif ($o['downstream'] > 0) {
                $row['finish'] = $from->copy()->addDays($after);
                $row['note'] = 'Moulding done; finish the pieces already poured.';
            }
            if ($row['finish'] && $o['due']) {
                $row['state'] = match (true) {
                    $row['finish']->gt($o['due']) => 'LATE',
                    $row['finish']->diffInDays($o['due'], false) < 2 => 'AT_RISK',
                    default => $row['state'],
                };
            }
            $planned[] = $row;
        }

        return ['orders' => $planned, 'suggestion' => $suggestion, 'unrated' => $stations->filter(fn ($s) => ! $s->moulds_per_shift)->values()];
    }

    /**
     * Put the first shift's suggestion on a moulding work list: the draft list for that day and shift if there is one, else a new one.
     * Rows that the work list refuses (sand, pattern ownership) are left out and named.
     *
     * @return array{list: MouldingWorkList, added: int, skipped: list<string>}
     */
    public function fill(CarbonInterface $day, string $shift): array
    {
        $suggestion = $this->plan($day)['suggestion'];
        if (! $suggestion) {
            throw new InvalidArgumentException('Nothing to suggest: no open order needs moulds on a station with a rate set.');
        }

        return DB::transaction(function () use ($day, $shift, $suggestion) {
            $list = MouldingWorkList::where('status', 'DRAFT')->whereDate('list_date', $day)->where('shift', $shift)->first()
                ?? $this->lists->create($day->toDateString(), $shift, 'Filled by the planner.');
            [$added, $skipped] = [0, []];
            foreach ($suggestion as ['station' => $station, 'rows' => $rows]) {
                foreach ($rows as $r) {
                    try {
                        DB::transaction(fn () => $this->lists->addItem($list, [
                            'moulding_station_id' => $station->id, 'pattern_revision_id' => $r['pattern']->id,
                            'production_order_id' => $r['order']->id, 'planned_moulds' => $r['moulds'],
                        ]));
                        $added++;
                    } catch (InvalidArgumentException $e) {
                        $skipped[] = "{$r['order']->production_order_number} on {$station->code}: {$e->getMessage()}";
                    }
                }
            }

            return ['list' => $list, 'added' => $added, 'skipped' => $skipped];
        });
    }

    /**
     * Open orders best first: earliest due date, then priority, then orders already started, then oldest.
     *
     * @return list<array{order: ProductionOrder, due: ?CarbonInterface, pattern: ?PatternRevision, moulds_left: int, on_lists: int, downstream: int, backlog: array<string, int>}>
     */
    public function rank(CarbonInterface $from): array
    {
        $orders = ProductionOrder::with(['product', 'salesOrderLine.salesOrder.customer', 'salesOrderLine.deliveries'])
            ->whereIn('status', ['RELEASED', 'IN_PROGRESS'])->get();
        $onLists = $this->openListMoulds($from)->groupBy('production_order_id')->map(fn ($items) => (int) $items->sum('planned_moulds'));
        $shipped = DB::table('dispatch_items')->whereIn('sales_order_line_id', $orders->pluck('sales_order_line_id')->filter())
            ->selectRaw('sales_order_line_id, sum(quantity_dispatched) q')->groupBy('sales_order_line_id')->pluck('q', 'sales_order_line_id');
        $backlog = $this->backlog($orders->pluck('id'));

        return $orders->map(function (ProductionOrder $order) use ($onLists, $shipped, $backlog) {
            $pattern = $this->patternFor($order);
            $needed = $pattern ? (int) $this->lists->mouldsStillNeeded($order, $pattern->id) : 0;
            $listed = (int) ($onLists[$order->id] ?? 0);
            $orderBacklog = $backlog[$order->id] ?? [];

            return [
                'order' => $order, 'due' => $this->dueDate($order, (float) ($shipped[$order->sales_order_line_id] ?? 0)), 'pattern' => $pattern,
                'moulds_left' => max(0, $needed - $listed), 'on_lists' => $listed, 'downstream' => array_sum($orderBacklog), 'backlog' => $orderBacklog,
            ];
        })->sortBy(fn ($o) => sprintf('%s%d%d%s', $o['due']?->format('Ymd') ?? '99999999', self::PRIORITY[$o['order']->priority] ?? 2,
            $o['order']->status === 'IN_PROGRESS' ? 0 : 1, $o['order']->created_at?->format('YmdHis')))->values()->all();
    }

    /** The date the order is next due: its earliest delivery not yet shipped, else the order's required date, else the sales line's. */
    public function dueDate(ProductionOrder $order, float $shipped): ?CarbonInterface
    {
        $line = $order->salesOrderLine;
        $next = $line?->deliveryProgress($shipped)->first(fn ($p) => $p['covered'] < (float) $p['delivery']->quantity - 0.0005);

        return $next['delivery']->due_date ?? $order->required_date ?? $line?->requested_delivery_date;
    }

    /** The pattern that makes the order: an approved revision with a cavity map for the order's product, its own revision first. */
    public function patternFor(ProductionOrder $order): ?PatternRevision
    {
        return PatternRevision::with(['pattern.preferredStation', 'cavityMap.productRevision'])->where('status', 'APPROVED')
            ->whereHas('pattern', fn ($q) => $q->whereNull('patterns.deleted_at'))
            ->whereHas('cavityMap.productRevision', fn ($q) => $q->where('product_id', $order->product_id))->get()
            ->sortBy(fn ($p) => $p->cavityMap->contains('product_revision_id', $order->product_revision_id) ? 0 : 1)->first();
    }

    /**
     * Pieces poured for each order and not yet finished, by where they are: waiting for the first stage, after a stage, or ready to pack.
     *
     * @param  Collection<int, string>  $orderIds
     * @return array<string, array<string, int>>
     */
    private function backlog(Collection $orderIds): array
    {
        $out = [];
        $lots = CastingLot::with('stageEvents.stage')->whereIn('production_order_id', $orderIds)->whereIn('status', ['IN_PRODUCTION', 'ON_HOLD', 'READY'])->get();
        foreach ($lots as $lot) {
            $last = $lot->stageEvents->sortBy(fn ($e) => $e->stage?->sequence)->last()?->stage;
            $where = match (true) {
                $lot->status === 'READY' => 'Ready to pack',
                $last !== null => 'After '.$last->name,
                default => 'Waiting',
            };
            $out[$lot->production_order_id][$where] = ($out[$lot->production_order_id][$where] ?? 0) + $this->lots->pieces($lot);
        }

        return $out;
    }

    /**
     * Moulds planned on open lists from the given day on and not yet started, for orders still open (or no order).
     * Lists left from earlier days and work for finished orders hold no station time.
     *
     * @return Collection<int, MouldingWorkListItem>
     */
    private function openListMoulds(CarbonInterface $from): Collection
    {
        return MouldingWorkListItem::where('status', 'PLANNED')->whereNull('moulding_run_id')
            ->whereHas('workList', fn ($q) => $q->whereIn('status', ['DRAFT', 'ISSUED'])->whereDate('list_date', '>=', $from))
            ->where(fn ($q) => $q->whereNull('production_order_id')->orWhereHas('productionOrder', fn ($o) => $o->whereIn('status', ['RELEASED', 'IN_PROGRESS'])))->get();
    }

    /**
     * The station for a pattern: its preferred one when that has a rate, else the rated station that frees up first,
     * preferring stations whose sand suits the pattern.
     *
     * @param  Collection<string, MouldingStation>  $stations
     */
    private function stationFor(PatternRevision $pattern, Collection $stations, array $load): ?MouldingStation
    {
        $rated = $stations->filter(fn ($s) => $s->moulds_per_shift > 0);
        $preferred = $pattern->pattern->preferred_moulding_station_id;
        if ($preferred && $rated->has($preferred)) {
            return $rated[$preferred];
        }

        return $rated->sortBy(fn ($s) => [$pattern->pattern->sandWarning($s->defaultSand()) ? 1 : 0, count($load[$s->id] ?? [])])->first();
    }

    /**
     * Load moulds onto a station from its first shift with room, up to its moulds per shift. Patterns at once is how many it
     * moulds side by side, not a limit per shift: a line changes patterns during a shift, so only the mould count caps a shift.
     *
     * @param  array<string, list<int>>  $load  station id => moulds used in each shift
     * @return list<array{shift: int, moulds: int}>
     */
    private function allocate(array &$load, MouldingStation $station, int $moulds): array
    {
        $rate = (int) $station->moulds_per_shift;
        if ($rate <= 0) {
            return [];
        }
        $shifts = [];
        for ($i = 0; $moulds > 0; $i++) {
            $used = $load[$station->id][$i] ?? 0;
            if ($used < $rate) {
                $take = min($moulds, $rate - $used);
                $used += $take;
                $moulds -= $take;
                $shifts[] = ['shift' => $i, 'moulds' => $take];
            }
            $load[$station->id][$i] = $used;
        }

        return $shifts;
    }
}
