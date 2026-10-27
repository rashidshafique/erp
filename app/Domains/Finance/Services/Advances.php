<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Sales\Models\SalesOrderLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Money a customer paid before its invoice, or we paid a supplier before its bill, put on that invoice or bill. Money that names
 * an order goes on that order's documents first and never on another order's; money that names none can go on any.
 * Nothing is posted: both the money and the document already sit in receivables or payables.
 */
class Advances
{
    /** @return Collection<int, Receipt> the customer's receipts with money left that may go on this invoice, the invoice's own orders first */
    public function forInvoice(Invoice $invoice): Collection
    {
        $orders = $this->salesOrders($invoice);

        return Receipt::where('customer_id', $invoice->customer_id)
            ->where(fn ($q) => $q->whereNull('sales_order_id')->orWhereIn('sales_order_id', $orders))
            ->orderBy('received_on')->orderBy('created_at')->get()
            ->filter(fn (Receipt $r) => $r->unallocated() > 0)
            ->sortBy(fn (Receipt $r) => $r->sales_order_id ? 0 : 1)->values();
    }

    /** @return Collection<int, SupplierPayment> the supplier's payments with money left that may go on this bill, its own order's first */
    public function forBill(SupplierBill $bill): Collection
    {
        return SupplierPayment::where('supplier_id', $bill->supplier_id)
            ->where(fn ($q) => $q->whereNull('purchase_order_id')->when($bill->purchase_order_id, fn ($q) => $q->orWhere('purchase_order_id', $bill->purchase_order_id)))
            ->orderBy('paid_on')->orderBy('created_at')->get()
            ->filter(fn (SupplierPayment $p) => $p->unallocated() > 0)
            ->sortBy(fn (SupplierPayment $p) => $p->purchase_order_id ? 0 : 1)->values();
    }

    /** Puts the customer's money on account on the invoice, up to its balance. Returns how much went on. */
    public function applyToInvoice(Invoice $invoice): float
    {
        if ($invoice->status !== 'ISSUED' || $invoice->isCreditNote()) {
            return 0.0;
        }

        return DB::transaction(function () use ($invoice) {
            $applied = 0.0;
            foreach ($this->forInvoice($invoice) as $receipt) {
                $part = round(min($receipt->unallocated(), $invoice->cashDue()), 2);
                if ($part <= 0) {
                    break;
                }
                $receipt->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $part]);
                $applied += $part;
            }

            return round($applied, 2);
        });
    }

    /** Puts the supplier advances on the bill, up to its balance. Returns how much went on. */
    public function applyToBill(SupplierBill $bill): float
    {
        if ($bill->status !== 'POSTED' || $bill->isCreditNote()) {
            return 0.0;
        }

        return DB::transaction(function () use ($bill) {
            $applied = 0.0;
            foreach ($this->forBill($bill) as $payment) {
                $part = round(min($payment->unallocated(), $bill->balance()), 2);
                if ($part <= 0) {
                    break;
                }
                $payment->allocations()->create(['supplier_bill_id' => $bill->id, 'amount' => $part]);
                $applied += $part;
            }

            return round($applied, 2);
        });
    }

    /** @return list<string> the sales orders the invoice was raised from, or whose lines it bills */
    private function salesOrders(Invoice $invoice): array
    {
        $lines = $invoice->lines()->whereNotNull('sales_order_line_id')->pluck('sales_order_line_id');
        $orders = $lines->isEmpty() || ! class_exists(SalesOrderLine::class) ? collect() : SalesOrderLine::whereIn('id', $lines)->pluck('sales_order_id');
        if ($invoice->source_type === 'sales_order' && $invoice->source_id) {
            $orders->push($invoice->source_id);
        }

        return $orders->unique()->values()->all();
    }
}
