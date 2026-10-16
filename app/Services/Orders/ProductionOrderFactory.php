<?php

namespace App\Services\Orders;

use App\Models\ProductionOrder;
use App\Models\SalesOrderLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionOrderFactory
{
    /** Create a draft production order for the unscheduled quantity of a sales line. */
    public function forSalesLine(SalesOrderLine $line, ?float $qty = null): ProductionOrder
    {
        return DB::transaction(function () use ($line, $qty) {
            $line = SalesOrderLine::with(['salesOrder', 'productRevision'])->lockForUpdate()->findOrFail($line->id);

            if (config('foundry.require_contract_review') && ! $line->salesOrder->hasApprovedReview()) {
                throw ValidationException::withMessages(['review' => 'The order has no approved contract review. Complete the review and both sign-offs first.']);
            }
            if (! in_array($line->productRevision?->status, ['APPROVED', 'ACTIVE'], true)) {
                throw ValidationException::withMessages(['line' => 'The product revision on this line is not approved.']);
            }

            $already = (float) ProductionOrder::where('sales_order_line_id', $line->id)->where('status', '!=', 'CANCELLED')->sum('planned_qty');
            $open = (float) $line->ordered_qty - $already;
            $qty = $qty ?? $open;
            if ($qty <= 0 || $qty > $open + 0.0005) {
                throw ValidationException::withMessages(['qty' => 'Only '.rtrim(rtrim(number_format(max(0, $open), 3, '.', ''), '0'), '.').' is left to schedule on this line.']);
            }

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
                'uom' => $line->uom ?: 'piece',
                'required_date' => $line->requested_delivery_date ?? $line->salesOrder->requested_delivery_date,
                'notes' => 'Created from sales order '.$line->salesOrder->order_number.' line '.$line->line_number.'.',
            ]);
        });
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
