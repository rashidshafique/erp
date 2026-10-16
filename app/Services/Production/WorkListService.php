<?php

namespace App\Services\Production;

use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\MouldingTeam;
use App\Models\MouldingWorkList;
use App\Models\MouldingWorkListItem;
use App\Models\ProductionOrder;
use App\Services\PatternCavityMap;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** The daily moulding list: who makes what, where, and the run that records it. */
class WorkListService
{
    public function __construct(private PatternCavityMap $map)
    {
    }

    public function create(string $date, string $shift, ?string $notes = null): MouldingWorkList
    {
        return DB::transaction(function () use ($date, $shift, $notes) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['worklist-number']);
            $prefix = 'WL-'.str_replace('-', '', $date).'-';
            $n = (MouldingWorkList::where('list_number', 'like', $prefix.'%')->pluck('list_number')->map(fn ($x) => (int) substr($x, strlen($prefix)))->max() ?? 0) + 1;

            return MouldingWorkList::create(['list_number' => $prefix.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'list_date' => $date, 'shift' => $shift, 'status' => 'DRAFT', 'notes' => $notes]);
        });
    }

    /** Moulds still to make for an order, from its open quantity and what the pattern gives per mould. */
    public function mouldsStillNeeded(?ProductionOrder $order, ?string $patternRevisionId): ?int
    {
        if (! $order || ! $patternRevisionId || ! $this->map->hasMap($patternRevisionId)) {
            return null;
        }
        $per = $this->map->expectedProductsPerMould(\App\Models\PatternRevision::findOrFail($patternRevisionId), $order->product_id);
        if ($per <= 0) {
            return null;
        }
        $made = (int) MouldingRun::where('production_order_id', $order->id)->where('status', '!=', 'CANCELLED')->sum('accepted_mould_qty');
        $open = (float) $order->planned_qty - (float) $order->completed_qty - (float) $order->rejected_qty - $made * $per;

        return max(0, (int) ceil($open / $per));
    }

    public function addItem(MouldingWorkList $list, array $d): MouldingWorkListItem
    {
        if (! in_array($list->status, ['DRAFT', 'ISSUED'], true)) {
            throw new InvalidArgumentException("A {$list->status} list cannot take more items.");
        }
        $station = MouldingStation::findOrFail($d['moulding_station_id']);
        $d['sand_system'] = $d['sand_system'] ?? $this->sandFor($list, $station);
        // The team is the moulding line's team; nobody picks it on the work list.
        $d['moulding_team_id'] = $this->teamFor($list, $station, $d['moulding_team_id'] ?? null);
        if ($d['moulding_team_id'] && ($team = MouldingTeam::find($d['moulding_team_id'])) && $team->status !== 'ACTIVE') {
            throw new InvalidArgumentException("The team on {$station->code} ({$team->code}) is inactive. Choose another team for the line in Teams & stations.");
        }
        if ($station->status !== 'ACTIVE') {
            throw new InvalidArgumentException("Station {$station->code} is {$station->status} and cannot be given work.");
        }
        if (! empty($d['production_order_id'])) {
            $order = ProductionOrder::findOrFail($d['production_order_id']);
            if (! in_array($order->status, ['RELEASED', 'IN_PROGRESS'], true)) {
                throw new InvalidArgumentException("Production order {$order->production_order_number} is {$order->status}; release it before moulding.");
            }
            $this->map->assertPatternMakesOrderProduct($d['pattern_revision_id'], $order->id);
            $d['order_link'] = 'MANUAL';
        } else {
            // No order chosen: link the open order that needs this pattern's product, if there is one.
            $order = $this->openOrdersFor($d['pattern_revision_id'])->first();
            $d['production_order_id'] = $order?->id;
            $d['order_link'] = $order ? 'AUTO' : 'NONE';
        }
        // A pattern is made for one sand system; on another it usually needs a different taper allowance. Warn, and let someone accept it knowingly.
        $pattern = \App\Models\PatternRevision::with('pattern')->findOrFail($d['pattern_revision_id'])->pattern;
        $warning = $pattern->sandWarning($d['sand_system']);
        if ($warning && empty($d['sand_override'])) {
            throw new InvalidArgumentException($warning.' Tick "Use anyway" on that row to run it on '.$d['sand_system'].'.');
        }
        $d['sand_override'] = (bool) $warning;
        $dup = $list->items()->where('pattern_revision_id', $d['pattern_revision_id'])->where('moulding_station_id', $station->id)->whereNotIn('status', ['SKIPPED'])
            ->when($d['production_order_id'], fn ($q, $id) => $q->where('production_order_id', $id), fn ($q) => $q->whereNull('production_order_id'))->exists();
        if ($dup) {
            throw new InvalidArgumentException($d['production_order_id'] ? 'That order and pattern is already on this list for the same station.' : 'That pattern is already on this list for the same station.');
        }

        return $list->items()->create($d + ['sequence' => (int) $list->items()->max('sequence') + 1, 'status' => 'PLANNED']);
    }

    /**
     * Add a station's work in one go: the line's team and sand system, then a row per pattern plate with its mould count.
     * All rows are added or none; a problem names the row it is in.
     *
     * @param  array<int, array{pattern_revision_id:string, production_order_id?:?string, planned_moulds:int}>  $rows
     * @return int number of rows added
     */
    public function addRows(MouldingWorkList $list, MouldingStation $station, ?string $sand, array $rows, ?string $note = null): int
    {
        $sand = $sand ?: $this->sandFor($list, $station);

        return DB::transaction(function () use ($list, $station, $sand, $rows, $note) {
            foreach (array_values($rows) as $n => $row) {
                try {
                    $this->addItem($list, [
                        'moulding_station_id' => $station->id, 'sand_system' => $sand,
                        'pattern_revision_id' => $row['pattern_revision_id'], 'production_order_id' => $row['production_order_id'] ?? null,
                        'planned_moulds' => (int) $row['planned_moulds'], 'instructions' => $note, 'sand_override' => ! empty($row['sand_override']),
                    ]);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException('Row '.($n + 1).': '.$e->getMessage());
                } catch (\Illuminate\Validation\ValidationException $e) {
                    throw new InvalidArgumentException('Row '.($n + 1).': '.collect($e->errors())->flatten()->first());
                }
            }

            return count($rows);
        });
    }

    /** The sand a station should start with: what this list already uses on it, else its usual sand, else Sheera. */
    public function sandFor(MouldingWorkList $list, MouldingStation $station): string
    {
        return $list->items()->where('moulding_station_id', $station->id)->orderBy('sequence')->value('sand_system') ?: ($station->default_sand_system ?: (\App\Models\SandSystem::names()[0] ?? 'Sheera'));
    }

    /**
     * Change the sand system for the items of a line that have not started. A plate not suited to the new sand stops the change
     * unless it is accepted ($force); accepted ones are flagged. Returns how many changed.
     */
    public function changeStation(MouldingWorkList $list, MouldingStation $station, ?string $sand, bool $force = false): int
    {
        if (! in_array($list->status, ['DRAFT', 'ISSUED'], true)) {
            throw new InvalidArgumentException("A {$list->status} list cannot be changed.");
        }
        if (! $sand) {
            return 0;
        }
        $items = $list->items()->with('patternRevision.pattern')->where('moulding_station_id', $station->id)->where('status', 'PLANNED')->get();
        $unsuited = $items->filter(fn ($i) => ! $i->patternRevision->pattern->suitsSand($sand));
        if ($unsuited->isNotEmpty() && ! $force) {
            $names = $unsuited->map(fn ($i) => $i->patternRevision->pattern->code.' (made for '.$i->patternRevision->pattern->sand_system.')')->implode(', ');
            throw new InvalidArgumentException("Not every plate suits {$sand}: {$names}. Tick \"Accept where a plate is not suited\" to change it anyway.");
        }
        foreach ($items as $i) {
            $i->update(['sand_system' => $sand, 'sand_override' => ! $i->patternRevision->pattern->suitsSand($sand)]);
        }

        return $items->count();
    }

    /** The team for a line: the line's own team (or one given explicitly), else the team already on it in this list; none if the line has no team. */
    public function teamFor(MouldingWorkList $list, MouldingStation $station, ?string $asked = null): ?string
    {
        return $asked ?: $station->default_moulding_team_id ?: $list->items()->where('moulding_station_id', $station->id)->orderBy('sequence')->value('moulding_team_id');
    }

    /** Start every planned item of a station, as many as it can mould at once (an automated line starts just the first). */
    public function startStation(MouldingWorkList $list, MouldingStation $station): int
    {
        $started = 0;
        foreach ($list->items()->where('moulding_station_id', $station->id)->where('status', 'PLANNED')->get() as $item) {
            if ($station->runningCount() >= $station->capacity()) {
                break;
            }
            $this->startRun($item);
            $started++;
        }
        if ($started === 0) {
            throw new InvalidArgumentException($station->runningCount() >= $station->capacity()
                ? "Station {$station->code} is already moulding {$station->capacity()} pattern(s) at once. Finish one first."
                : "Nothing planned to start on station {$station->code}.");
        }

        return $started;
    }

    /**
     * Open orders that need what a pattern makes, best first: customer orders before stock, then by priority, then by due date.
     * Patterns without a cavity map cannot be matched, so they link to nothing automatically.
     */
    public function openOrdersFor(string $patternRevisionId): \Illuminate\Support\Collection
    {
        $products = \App\Models\PatternRevisionCavity::where('pattern_revision_id', $patternRevisionId)->with('productRevision')->get()->map(fn ($c) => $c->productRevision?->product_id)->filter()->unique();
        if ($products->isEmpty()) {
            return collect();
        }
        $type = ['CUSTOMER' => 0, 'REWORK' => 1, 'STOCK' => 2];
        $prio = ['URGENT' => 0, 'HIGH' => 1, 'NORMAL' => 2, 'LOW' => 3];

        return ProductionOrder::with(['product', 'salesOrderLine.salesOrder.customer'])->whereIn('status', ['RELEASED', 'IN_PROGRESS'])->whereIn('product_id', $products)->get()
            ->filter(fn ($o) => ($this->mouldsStillNeeded($o, $patternRevisionId) ?? 1) > 0)
            ->sortBy(fn ($o) => sprintf('%d%d%s%s', $type[$o->order_type] ?? 3, $prio[$o->priority] ?? 2, $o->required_date?->format('Ymd') ?? '99999999', $o->created_at?->format('YmdHis')))
            ->values();
    }

    /** Link a list item that has no order to the best open order now. Returns the order or null. */
    public function relink(MouldingWorkListItem $item): ?ProductionOrder
    {
        if ($item->production_order_id || $item->status !== 'PLANNED') {
            return $item->productionOrder;
        }
        $order = $this->openOrdersFor($item->pattern_revision_id)->first();
        if ($order) {
            $item->update(['production_order_id' => $order->id, 'order_link' => 'AUTO']);
        }

        return $order;
    }

    /**
     * Make sure an item has an order before its run starts: an open order if one now exists, else a stock order for the pattern's product.
     * The stock order goes through the same release approval as any other order.
     */
    private function ensureOrder(MouldingWorkListItem $item): void
    {
        $item->refresh();
        if ($item->production_order_id) {
            // A stock order raised earlier that was waiting for release approval: try again now.
            $order = $item->productionOrder;
            if ($item->order_link === 'STOCK' && $order?->status === 'DRAFT') {
                $this->releaseStock($order);
            }

            return;
        }
        if ($this->relink($item)) {
            return;
        }
        $cav = \App\Models\PatternRevisionCavity::where('pattern_revision_id', $item->pattern_revision_id)->with('productRevision.product')->orderBy('cavity_number')->get()
            ->first(fn ($c) => in_array($c->productRevision?->status, ['APPROVED', 'ACTIVE'], true));
        if (! $cav) {
            throw new InvalidArgumentException('No open order needs this pattern and its cavity map has no approved product to raise a stock order for. Choose an order.');
        }
        $per = $this->map->expectedProductsPerMould(\App\Models\PatternRevision::findOrFail($item->pattern_revision_id), $cav->productRevision->product_id);
        $order = app(\App\Services\Orders\ProductionOrderFactory::class)->forStock($cav->productRevision, ceil($item->planned_moulds * max(1, $per)),
            'Raised from work list '.$item->workList->list_number.': no open order needed this pattern.');
        $item->update(['production_order_id' => $order->id, 'order_link' => 'STOCK']);
        $this->releaseStock($order);
    }

    private function releaseStock(ProductionOrder $order): void
    {
        if (! app(\App\Services\Approvals\ApprovalGate::class)->passes('production_order.release', 'ProductionOrder', (string) $order->id, $order->production_order_number, $msg)) {
            throw new InvalidArgumentException("Stock order {$order->production_order_number} was raised for this work and cannot start until it is released. {$msg}");
        }
        $order->update(['status' => 'RELEASED', 'released_qty' => $order->planned_qty]);
    }

    public function issue(MouldingWorkList $list, ?string $by): void
    {
        if ($list->status !== 'DRAFT') {
            throw new InvalidArgumentException('Only a draft list can be issued.');
        }
        if (! $list->items()->exists()) {
            throw new InvalidArgumentException('Add at least one item before issuing the list.');
        }
        $list->update(['status' => 'ISSUED', 'issued_by' => $by, 'issued_at' => now()]);
    }

    public function startRun(MouldingWorkListItem $item): MouldingRun
    {
        $item = MouldingWorkListItem::with('workList')->findOrFail($item->id);
        if ($item->workList->status === 'ISSUED' && $item->status === 'PLANNED') {
            $this->ensureOrder($item);
        }

        return DB::transaction(function () use ($item) {
            $item = MouldingWorkListItem::with(['workList', 'team', 'station'])->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($item->workList->status !== 'ISSUED') {
                throw new InvalidArgumentException('Issue the list before starting its runs.');
            }
            if ($item->status !== 'PLANNED' || $item->moulding_run_id) {
                throw new InvalidArgumentException('This item has already been started.');
            }
            if ($item->station->status !== 'ACTIVE') {
                throw new InvalidArgumentException("Station {$item->station->code} is {$item->station->status}.");
            }
            $order = ProductionOrder::findOrFail($item->production_order_id);
            if (! in_array($order->status, ['RELEASED', 'IN_PROGRESS'], true)) {
                throw new InvalidArgumentException("Production order {$order->production_order_number} is {$order->status}; release it before moulding.");
            }
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['station-'.$item->moulding_station_id]);
            $running = $item->station->runningCount($item->id);
            if ($running >= $item->station->capacity()) {
                $what = $item->station->isAutomated() ? 'The automated line moulds one pattern at a time' : "Station {$item->station->code} takes {$item->station->capacity()} pattern(s) at once";

                throw new InvalidArgumentException("{$what} and {$running} is already in progress on {$item->station->code}. Finish a run there first.");
            }
            $date = now()->format('Ymd');
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['run-number-'.$date]);
            $prefix = "MR-{$date}-";
            $n = (MouldingRun::where('run_number', 'like', $prefix.'%')->pluck('run_number')->map(fn ($x) => (int) substr($x, strlen($prefix)))->max() ?? 0) + 1;
            $run = MouldingRun::create([
                'run_number' => $prefix.str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'production_order_id' => $item->production_order_id,
                'pattern_revision_id' => $item->pattern_revision_id, 'run_date' => $item->workList->list_date, 'shift' => $item->workList->shift,
                'started_at' => now(), 'sand_system' => $item->sand_system, 'operator_name' => $item->team?->lead_name, 'team_name' => $item->team?->name,
                'moulding_team_id' => $item->moulding_team_id, 'moulding_station_id' => $item->moulding_station_id, 'moulding_work_list_item_id' => $item->id,
                'planned_mould_qty' => $item->planned_moulds, 'moulds_made_qty' => 0, 'accepted_mould_qty' => 0, 'damaged_mould_qty' => 0, 'status' => 'IN_PROGRESS',
            ]);
            $item->update(['status' => 'IN_PROGRESS', 'moulding_run_id' => $run->id]);

            return $run;
        });
    }

    /** Record what was made; completing the run closes its list item and, when all are done, the list. */
    public function recordOutput(MouldingRun $run, array $d, bool $complete): MouldingRun
    {
        return DB::transaction(function () use ($run, $d, $complete) {
            $run = MouldingRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            if (in_array($run->status, ['COMPLETED', 'CANCELLED'], true)) {
                throw new InvalidArgumentException("The run is {$run->status} and cannot be changed.");
            }
            if ($d['accepted_mould_qty'] + $d['damaged_mould_qty'] > $d['moulds_made_qty']) {
                throw new InvalidArgumentException('Accepted plus damaged moulds cannot exceed the moulds made.');
            }
            $assembled = $run->mouldAssemblies()->count();
            if ($d['accepted_mould_qty'] < $assembled) {
                throw new InvalidArgumentException("{$assembled} mould(s) are already assembled from this run, so accepted cannot be lower.");
            }
            $run->update($d + ($complete ? ['status' => 'COMPLETED', 'completed_at' => now()] : []));
            if ($complete && $run->moulding_work_list_item_id) {
                $item = MouldingWorkListItem::find($run->moulding_work_list_item_id);
                $item?->update(['status' => 'DONE']);
                $list = $item?->workList;
                if ($list && ! $list->items()->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->exists()) {
                    $list->update(['status' => 'COMPLETED']);
                }
            }

            return $run;
        });
    }
}
