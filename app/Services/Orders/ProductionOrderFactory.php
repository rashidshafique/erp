<?php

namespace App\Services\Orders;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Models\ApprovalRequest;
use App\Models\ProductionOrder;
use App\Services\Approvals\ApprovalGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionOrderFactory
{
    /** Create a draft production order for the unscheduled quantity of a sales line. */
    /** $quick skips the contract review and revision approval checks, for orders entered through the quick order page. */
    public function forSalesLine(SalesOrderLine $line, ?float $qty = null, bool $quick = false): ProductionOrder
    {
        return DB::transaction(function () use ($line, $qty, $quick) {
            $line = SalesOrderLine::with(['salesOrder', 'productRevision'])->lockForUpdate()->findOrFail($line->id);

            if (! $quick && \App\Support\OrderRules::reviewRequired() && ! $line->salesOrder->hasApprovedReview()) {
                throw ValidationException::withMessages(['review' => 'The order has no approved contract review. Complete the review and both sign-offs first.']);
            }
            if (! $quick && ! in_array($line->productRevision?->status, ['APPROVED', 'ACTIVE'], true)) {
                throw ValidationException::withMessages(['line' => 'The product revision on this line is not approved.']);
            }

            if ($line->isSoldByKg() && $line->pieceWeightKg() <= 0) {
                throw ValidationException::withMessages(['qty' => "Line {$line->line_number} is sold by kg, but {$line->product?->code} has no casting weight. Set its nominal weight so the pieces to make can be worked out."]);
            }
            // $qty and $open are in the line's unit; the production order gets the whole pieces they need.
            $already = (float) ProductionOrder::where('sales_order_line_id', $line->id)->where('status', '!=', 'CANCELLED')->sum('planned_qty');
            $open = (float) $line->ordered_qty - $line->fromPieces($already);
            $qty = $qty ?? $open;
            if ($qty <= 0 || $qty > $open + 0.0005) {
                throw ValidationException::withMessages(['qty' => 'Only '.\App\Support\Formats::number(max(0, $open), 2).' is left to schedule on this line.']);
            }
            $qty = $line->toPieces($qty);

            return ProductionOrder::create([
                'production_order_number' => $this->nextNumber(),
                'sales_order_line_id' => $line->id,
                'product_id' => $line->product_id,
                'product_revision_id' => $line->product_revision_id,
                'order_type' => 'CUSTOMER',
                'status' => 'DRAFT',
                'priority' => 'NORMAL',
                'planned_qty' => $qty,
                'released_qty' => 0, 'completed_qty' => 0, 'rejected_qty' => 0,
                'uom' => 'piece',
                'required_date' => $line->requested_delivery_date ?? $line->salesOrder->requested_delivery_date,
                'notes' => 'Created from sales order '.$line->salesOrder->order_number.' line '.$line->line_number.'.',
            ]);
        });
    }

    /**
     * Creates and releases a production order for whatever is still unscheduled on the line. Null when the line is fully scheduled.
     * When releasing needs approval, the order stays a draft with the request raised; it is released from its page once approved.
     */
    public function releaseForLine(SalesOrderLine $line): ?ProductionOrder
    {
        $scheduled = (float) ProductionOrder::where('sales_order_line_id', $line->id)->where('status', '!=', 'CANCELLED')->sum('planned_qty');
        if ((float) $line->ordered_qty - $line->fromPieces($scheduled) <= 0.0005) {
            return null;
        }
        $order = $this->forSalesLine($line, null, quick: true);
        if (! app(ApprovalGate::class)->passes('production_order.release', 'ProductionOrder', (string) $order->id, $order->production_order_number)) {
            return $order;
        }
        $order->update(['status' => 'RELEASED', 'released_qty' => $order->planned_qty]);

        return $order;
    }

    /** A note naming the sales order's production orders still in draft while their release waits for approval, or null. */
    public static function awaitingApprovalNote(SalesOrder $salesOrder): ?string
    {
        $drafts = ProductionOrder::whereIn('sales_order_line_id', $salesOrder->lines()->select('id'))->where('status', 'DRAFT')->pluck('production_order_number', 'id');
        $waiting = ApprovalRequest::where('action_key', 'production_order.release')->where('status', 'PENDING')->whereIn('subject_id', $drafts->keys()->map(fn ($id) => (string) $id))->pluck('subject_id');
        $numbers = $drafts->only($waiting->all())->sort()->values();

        return $numbers->isEmpty() ? null
            : $numbers->implode(', ').' '.($numbers->count() === 1 ? 'waits' : 'wait').' for release approval. Release from the production order once it is approved.';
    }

    /** A draft stock order (no customer) for work started without an open order; the caller releases it. */
    public function forStock(\App\Models\ProductRevision $revision, float $qty, string $note): ProductionOrder
    {
        return DB::transaction(fn () => ProductionOrder::create([
            'production_order_number' => $this->nextNumber(),
            'product_id' => $revision->product_id, 'product_revision_id' => $revision->id,
            'order_type' => 'STOCK', 'status' => 'DRAFT', 'priority' => 'NORMAL',
            'planned_qty' => max(1, $qty), 'released_qty' => 0, 'completed_qty' => 0, 'rejected_qty' => 0, 'uom' => 'piece', 'notes' => $note,
        ]));
    }

    private function nextNumber(): string
    {
        DB::select('SELECT pg_advisory_xact_lock(?)', [2026101101]);
        $prefix = 'PO-'.now()->format('Y').'-';
        $last = ProductionOrder::where('production_order_number', 'like', $prefix.'%')->pluck('production_order_number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }
}
