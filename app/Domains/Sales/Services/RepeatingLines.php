<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Models\ProductionOrder;
use App\Services\Audit\Audit;
use App\Services\Orders\ProductionOrderFactory;
use App\Support\Formats;
use App\Support\OrderRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keeps a repeating order line scheduled for customers who take a weekly or monthly quantity without sending a PO each time. Each
 * period up to the repeat-ahead rule adds a delivery of repeat_qty and grows the ordered quantity by it; the first delivery is always
 * added so a new line is never empty. On a live order with the straight-to-production flow the new quantity is released at once.
 * A delivery removed by hand is not added back: repeated_to remembers the last date scheduled.
 *
 * A repeating line's ordered quantity is always its deliveries. Turning a one-off line into a repeating one replaces its quantity
 * (what was already shipped stays as its own delivery); changing the quantity, dates or end of a repeating line plans its deliveries
 * from today again. Each change is written to the order's history.
 */
class RepeatingLines
{
    /** @return int Deliveries added. */
    public function topUp(SalesOrderLine $line): int
    {
        return DB::transaction(function () use ($line) {
            $line = SalesOrderLine::with('salesOrder')->lockForUpdate()->findOrFail($line->id);
            if (! in_array($line->salesOrder->status, SalesOrder::OPEN, true) || $line->salesOrder->status === 'ON_HOLD') {
                return 0;
            }
            $before = (float) $line->ordered_qty;
            $dates = [];
            foreach ($this->dates($line) as $next) {
                $line->deliveries()->create(['due_date' => $next, 'quantity' => $line->repeat_qty, 'notes' => 'Repeat']);
                $line->ordered_qty = (float) $line->ordered_qty + (float) $line->repeat_qty;
                $line->repeated_to = $next;
                $line->save();
                $dates[] = $next->format('j M');
            }
            if ($dates !== []) {
                $this->log($line, 'Line '.$line->line_number.': '.count($dates).' '.str('delivery')->plural(count($dates)).' of '.Formats::number((float) $line->repeat_qty).' added ('.implode(', ', $dates).'); ordered '.Formats::number($before).' → '.Formats::number((float) $line->ordered_qty));
            }
            if ($dates !== [] && $line->salesOrder->status === 'IN_PRODUCTION' && OrderRules::sendsStraightToProduction()) {
                app(ProductionOrderFactory::class)->releaseForLine($line);
            }

            return count($dates);
        });
    }

    /** @return int Deliveries added across every open order. */
    public function topUpAll(): int
    {
        return SalesOrderLine::whereNotNull('repeat_every')
            ->whereHas('salesOrder', fn ($q) => $q->whereIn('status', array_diff(SalesOrder::OPEN, ['ON_HOLD'])))
            ->get()->sum(fn (SalesOrderLine $line) => $this->topUp($line));
    }

    /**
     * What saving a line's new repeat settings will schedule, worked out before anything is written. A line becoming repeating keeps
     * only what was shipped; a repeating line whose quantity, dates or end changed keeps its deliveries due before today. Either way
     * the deliveries from then on are planned again.
     *
     * @param  SalesOrderLine  $line  With the new attributes filled in and not yet saved.
     * @return array{keep: float, repeated_to: ?CarbonImmutable, dates: list<CarbonImmutable>, total: float, converting: bool}
     */
    public function preview(SalesOrderLine $line, float $shipped): array
    {
        $converting = $line->getOriginal('repeat_every') === null;
        $keep = $converting ? $shipped : (float) $line->getOriginal('ordered_qty') - (float) $line->deliveries()->where('due_date', '>=', today())->sum('quantity');
        $repeatedTo = $converting || ! $line->repeat_from || $line->repeat_from->gte(today()) ? null : today()->subDay()->toImmutable();
        $probe = (clone $line)->forceFill(['repeated_to' => $repeatedTo]);
        $dates = $this->dates($probe);

        return ['keep' => round($keep, 3), 'repeated_to' => $repeatedTo, 'dates' => $dates, 'total' => round($keep + count($dates) * (float) $line->repeat_qty, 3), 'converting' => $converting];
    }

    /**
     * Writes a preview: removes the deliveries it replaces, keeps what was shipped as one delivery when a line starts repeating, and
     * schedules the new ones.
     *
     * @param  array{keep: float, repeated_to: ?CarbonImmutable, dates: list<CarbonImmutable>, total: float, converting: bool}  $plan
     */
    public function apply(SalesOrderLine $line, array $plan): void
    {
        DB::transaction(function () use ($line, $plan) {
            $line = SalesOrderLine::lockForUpdate()->findOrFail($line->id);
            if ($plan['converting']) {
                $line->deliveries()->delete();
                if ($plan['keep'] > 0) {
                    $first = $line->repeat_from && $line->repeat_from->lt(today()) ? $line->repeat_from : today();
                    $line->deliveries()->create(['due_date' => $first->copy()->subDay(), 'quantity' => $plan['keep'], 'notes' => 'Shipped before repeating']);
                }
            } else {
                $line->deliveries()->where('due_date', '>=', today())->delete();
            }
            $line->update(['ordered_qty' => $plan['keep'], 'repeated_to' => $plan['repeated_to']]);
        });
        $this->topUp($line);
    }

    /**
     * Sets the quantity of each period still to come on a repeating line, as the customer's forecast moves. A period set to nothing is
     * removed and not added back. The line's total follows its periods, so it cannot go below released production or shipments, and a
     * period that grows or shrinks more than SalesOrderEditor::JUMP times is saved only once confirmed. On a live order with the
     * straight-to-production flow, a higher total is released at once.
     *
     * @param  array<string, mixed>  $quantities  Delivery id => new quantity.
     * @return int Periods changed.
     */
    public function forecast(SalesOrderLine $line, array $quantities, bool $confirmed): int
    {
        return DB::transaction(function () use ($line, $quantities, $confirmed) {
            $line = SalesOrderLine::with('salesOrder')->lockForUpdate()->findOrFail($line->id);
            if (! $line->repeats() || ! in_array($line->salesOrder->status, SalesOrder::OPEN, true)) {
                throw ValidationException::withMessages(['forecast' => 'Only a repeating line on an open order has a forecast to change.']);
            }
            $upcoming = $line->deliveries()->where('due_date', '>=', today())->get()->keyBy('id');
            $before = (float) $line->ordered_qty;
            $total = $before;
            $changes = [];
            $jumps = [];
            foreach ($quantities as $id => $quantity) {
                $delivery = $upcoming->get($id) ?? throw ValidationException::withMessages(['forecast' => 'A period on the form is no longer to come on line '.$line->line_number.'. Reload the page and try again.']);
                $was = (float) $delivery->quantity;
                $quantity = round((float) $quantity, 3);
                if (abs($quantity - $was) < 0.0005) {
                    continue;
                }
                $changes[] = [$delivery, $was, $quantity];
                $total += $quantity - $was;
                if ($was > 0 && $quantity > 0 && ($quantity > $was * SalesOrderEditor::JUMP || $quantity < $was / SalesOrderEditor::JUMP)) {
                    $jumps[] = $delivery->due_date->format('j M').' goes from '.Formats::number($was).' to '.Formats::number($quantity);
                }
            }
            if ($changes === []) {
                return 0;
            }
            $total = round($total, 3);
            if ($message = $this->belowFloor($line, $total)) {
                throw ValidationException::withMessages(['forecast' => $message]);
            }
            if ($jumps !== [] && ! $confirmed) {
                throw ValidationException::withMessages(['confirm_jump' => 'Please check: on line '.$line->line_number.' '.implode('; ', $jumps).'. If that is right, tick "Yes, these quantities are right" and save again.']);
            }

            $notes = [];
            foreach ($changes as [$delivery, $was, $quantity]) {
                $quantity > 0 ? $delivery->update(['quantity' => $quantity]) : $delivery->delete();
                $notes[] = $delivery->due_date->format('j M').' '.Formats::number($was).' → '.($quantity > 0 ? Formats::number($quantity) : 'removed');
            }
            $line->update(['ordered_qty' => $total]);
            $this->log($line, 'Line '.$line->line_number.' forecast changed: '.implode(', ', $notes).'; ordered '.Formats::number($before).' → '.Formats::number($total));
            if ($total > $before && $line->salesOrder->status === 'IN_PRODUCTION' && OrderRules::sendsStraightToProduction()) {
                app(ProductionOrderFactory::class)->releaseForLine($line);
            }

            return count($changes);
        });
    }

    /**
     * What a line cannot go below: production released for it (not cancelled) and what has been shipped.
     *
     * @return array{floor: float, released: float, shipped: float, orders: list<string>}
     */
    public function committed(SalesOrderLine $line): array
    {
        $orders = ProductionOrder::where('sales_order_line_id', $line->id)->where('status', '!=', 'CANCELLED')->get(['production_order_number', 'planned_qty']);

        return $this->committedFrom($line, (float) $orders->sum('planned_qty'), (float) (SalesOrderLine::shippedByLine([$line->id])[$line->id] ?? 0), $orders->pluck('production_order_number')->all());
    }

    /**
     * The floor from the pieces released and what was shipped in the line's unit. A kg line can go down to just above the weight
     * one piece fewer would make, since the released pieces are what that quantity still needs.
     *
     * @param  list<string>  $orders
     * @return array{floor: float, released: float, shipped: float, orders: list<string>}
     */
    public function committedFrom(SalesOrderLine $line, float $pieces, float $shipped, array $orders): array
    {
        $released = $line->fromPieces($pieces);
        $needs = $line->isSoldByKg() && $pieces > 0 ? $line->fromPieces($pieces - 1) + 0.001 : $released;

        return ['floor' => max($needs, $shipped), 'released' => $released, 'shipped' => $shipped, 'orders' => $orders];
    }

    /** Why a line cannot go to $quantity, naming the production orders to cancel or close first; null when it can. */
    public function belowFloor(SalesOrderLine $line, float $quantity, ?array $committed = null): ?string
    {
        $c = $committed ?? $this->committed($line);
        if ($quantity >= $c['floor'] - 0.0005) {
            return null;
        }
        $label = 'Line '.$line->line_number.' would go to '.Formats::number($quantity);
        if ($c['released'] >= $c['shipped'] && $c['orders'] !== []) {
            return $label.', but '.Formats::number($c['released']).' is already released to production on '.implode(', ', $c['orders'])
                .'. Cancel or close '.(count($c['orders']) > 1 ? 'them' : 'it').' first (Production > Production orders), or keep at least '.Formats::number($c['floor']).'.';
        }

        return $label.', but '.Formats::number($c['shipped']).' has already been shipped. Keep at least '.Formats::number($c['floor']).'.';
    }

    /** Writes one line of the order's history about its deliveries. */
    public function log(SalesOrderLine $line, string $summary, ?string $reason = null): void
    {
        $order = $line->relationLoaded('salesOrder') ? $line->salesOrder : $line->salesOrder()->first();
        app(Audit::class)->record('EDITED', 'SalesOrder', $order->id, $order->order_number, ['summary' => ['new' => $summary]], $reason);
    }

    /**
     * The delivery dates a top-up would add: every period up to the repeat-ahead horizon, and always the first one.
     *
     * @return list<CarbonImmutable>
     */
    private function dates(SalesOrderLine $line): array
    {
        $probe = clone $line;
        $horizon = today()->addDays(OrderRules::repeatAheadDays());
        $dates = [];
        while (($next = $probe->nextRepeatDate()) && ($next->lte($horizon) || ! $probe->repeated_to)) {
            $dates[] = $next;
            $probe->repeated_to = $next;
        }

        return $dates;
    }
}
