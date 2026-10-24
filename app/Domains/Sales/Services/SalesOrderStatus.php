<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\SalesOrder;
use App\Services\Orders\ProductionOrderFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Moves a sales order between statuses. Review, production and dispatch move it forward; hold, resume, cancel and reopen are done by hand. */
class SalesOrderStatus
{
    public const STATUSES = ['DRAFT', 'UNDER_REVIEW', 'CONFIRMED', 'IN_PRODUCTION', 'ON_HOLD', 'DISPATCHED', 'CANCELLED'];

    /** @return array<string, string> The changes offered on the order page, keyed by the value posted. */
    public function allowedFrom(SalesOrder $order): array
    {
        $live = ['IN_PRODUCTION' => 'Live (in production)', 'ON_HOLD' => 'On hold', 'CANCELLED' => 'Cancelled'];

        return match ($order->status) {
            'DRAFT', 'UNDER_REVIEW', 'CONFIRMED' => $live,
            'IN_PRODUCTION' => array_diff_key($live, ['IN_PRODUCTION' => 1]),
            'ON_HOLD' => ['RESUME' => 'Resume where it was'] + array_diff_key($live, ['ON_HOLD' => 1]),
            'CANCELLED' => ['REOPEN' => 'Reopen'],
            default => [],
        };
    }

    /** Applies a change from allowedFrom(). */
    public function change(SalesOrder $order, string $target, bool $releaseAll = false, ?string $reason = null): void
    {
        if (! array_key_exists($target, $this->allowedFrom($order))) {
            $this->refuse('That change is not available for an order that is '.strtolower(str_replace('_', ' ', $order->status)).'.');
        }
        match ($target) {
            'IN_PRODUCTION' => $this->makeLive($order, $releaseAll),
            'ON_HOLD' => $this->hold($order),
            'RESUME' => $this->resume($order),
            'CANCELLED' => $this->cancel($order, (string) $reason),
            'REOPEN' => $this->reopen($order),
        };
    }

    /** Makes the order live. With $releaseAll every line gets a released production order for what is still unscheduled. */
    public function makeLive(SalesOrder $order, bool $releaseAll): void
    {
        DB::transaction(function () use ($order, $releaseAll) {
            $order = SalesOrder::lockForUpdate()->findOrFail($order->id);
            if (\App\Support\OrderRules::reviewRequired() && ! $order->hasApprovedReview() && $this->openStatus($order) !== 'IN_PRODUCTION') {
                $this->refuse('A contract review must be approved first. Run it, or set contract review to not required under Settings > Order rules.');
            }
            if ($releaseAll) {
                $factory = app(ProductionOrderFactory::class);
                $order->lines->each(fn ($line) => $factory->releaseForLine($line));
            }
            $order->update(['status' => $releaseAll || $this->openStatus($order) === 'IN_PRODUCTION' ? 'IN_PRODUCTION' : 'CONFIRMED']);
        });
    }

    public function hold(SalesOrder $order): void
    {
        if (! in_array($order->status, ['DRAFT', 'UNDER_REVIEW', 'CONFIRMED', 'IN_PRODUCTION'], true)) {
            $this->refuse('Only an open order can be put on hold.');
        }
        $order->update(['status' => 'ON_HOLD']);
    }

    public function resume(SalesOrder $order): void
    {
        if ($order->status !== 'ON_HOLD') {
            $this->refuse('Only an order on hold can be resumed.');
        }
        $order->update(['status' => $this->openStatus($order)]);
    }

    /** Cancels the order and its production orders, as long as no moulding has started and nothing has been dispatched. */
    public function cancel(SalesOrder $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order = SalesOrder::lockForUpdate()->findOrFail($order->id);
            if (in_array($order->status, ['DISPATCHED', 'CANCELLED'], true)) {
                $this->refuse('This order is already closed.');
            }
            $lineIds = $order->lines()->pluck('id');
            if (DB::table('dispatch_items')->whereIn('sales_order_line_id', $lineIds)->exists()) {
                $this->refuse('Part of this order has been dispatched, so it cannot be cancelled.');
            }
            $orders = \App\Models\ProductionOrder::whereIn('sales_order_line_id', $lineIds)->where('status', '!=', 'CANCELLED')->get();
            foreach ($orders as $production) {
                if (! in_array($production->status, ['DRAFT', 'RELEASED'], true) || $production->mouldingRuns()->exists()) {
                    $this->refuse("{$production->production_order_number} is already in production. Put the order on hold instead, or close that production order first.");
                }
            }
            $orders->each->update(['status' => 'CANCELLED']);
            $order->update(['status' => 'CANCELLED', 'notes' => trim($order->notes."\nCancelled ".today()->toDateString().': '.$reason)]);
        });
    }

    public function reopen(SalesOrder $order): void
    {
        if ($order->status !== 'CANCELLED') {
            $this->refuse('Only a cancelled order can be reopened.');
        }
        $order->update(['status' => $order->hasApprovedReview() ? 'CONFIRMED' : 'DRAFT']);
    }

    /** Called when a production order for the order is released. */
    public function productionStarted(SalesOrder $order): void
    {
        if (in_array($order->status, ['DRAFT', 'UNDER_REVIEW', 'CONFIRMED'], true)) {
            $order->update(['status' => 'IN_PRODUCTION']);
        }
    }

    /** Called after a dispatch: the order is dispatched once every line has gone out in full. */
    public function dispatched(SalesOrder $order): void
    {
        if (in_array($order->status, ['ON_HOLD', 'CANCELLED', 'DISPATCHED'], true)) {
            return;
        }
        $shipped = DB::table('dispatch_items')->whereIn('sales_order_line_id', $order->lines()->select('id'))->selectRaw('sales_order_line_id, sum(quantity_dispatched) q')->groupBy('sales_order_line_id')->pluck('q', 'sales_order_line_id');
        if ($order->lines->isNotEmpty() && $order->lines->every(fn ($line) => (float) ($shipped[$line->id] ?? 0) >= (float) $line->ordered_qty)) {
            $order->update(['status' => 'DISPATCHED']);
        }
    }

    private function openStatus(SalesOrder $order): string
    {
        $producing = \App\Models\ProductionOrder::whereIn('sales_order_line_id', $order->lines()->select('id'))->whereNotIn('status', ['CANCELLED', 'DRAFT'])->exists();

        return $producing ? 'IN_PRODUCTION' : ($order->hasApprovedReview() ? 'CONFIRMED' : 'DRAFT');
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}
