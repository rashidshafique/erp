<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\DocumentPosted;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Money received from a customer and the invoices it pays. */
class ReceiptService
{
    public function __construct(private InvoiceService $invoices) {}

    /**
     * @param  array<string, mixed>  $head  customer_id, received_on, amount, method, reference, notes
     * @param  array<string, float|int|string>  $allocations  invoice id => amount paid by this receipt
     */
    public function record(array $head, array $allocations): Receipt
    {
        $allocations = array_filter(array_map(fn ($a) => round((float) $a, 2), $allocations), fn ($a) => $a > 0);
        $amount = round((float) $head['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Enter the amount received.');
        }
        if (round(array_sum($allocations), 2) > $amount) {
            throw new InvalidArgumentException('More is shared out over invoices than was received.');
        }

        return DB::transaction(function () use ($head, $allocations, $amount) {
            $receipt = Receipt::create([
                'receipt_number' => $this->invoices->nextNumber('RC', 'receipts', 'receipt_number'), 'customer_id' => $head['customer_id'],
                'received_on' => $head['received_on'], 'amount' => $amount, 'method' => $head['method'] ?? null, 'reference' => $head['reference'] ?? null, 'notes' => $head['notes'] ?? null,
            ]);
            foreach ($allocations as $invoiceId => $part) {
                $invoice = Invoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail();
                if ($invoice->customer_id !== $head['customer_id'] || $invoice->status !== 'ISSUED' || $invoice->isCreditNote()) {
                    throw new InvalidArgumentException("Invoice {$invoice->invoice_number} cannot take a payment from this customer.");
                }
                if ($part > $invoice->balance()) {
                    throw new InvalidArgumentException("Invoice {$invoice->invoice_number} has only {$invoice->balance()} left to pay.");
                }
                $receipt->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $part]);
            }
            event(new DocumentPosted($receipt));

            return $receipt->fresh('allocations');
        });
    }

    /** Puts what is left of a receipt on more invoices of the same customer. */
    public function allocateRest(Receipt $receipt, array $allocations): Receipt
    {
        $allocations = array_filter(array_map(fn ($a) => round((float) $a, 2), $allocations), fn ($a) => $a > 0);
        if (round(array_sum($allocations), 2) > $receipt->unallocated()) {
            throw new InvalidArgumentException('That is more than what is left of this receipt.');
        }
        DB::transaction(function () use ($receipt, $allocations) {
            foreach ($allocations as $invoiceId => $part) {
                $invoice = Invoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail();
                if ($invoice->customer_id !== $receipt->customer_id || $invoice->status !== 'ISSUED' || $invoice->isCreditNote() || $part > $invoice->balance()) {
                    throw new InvalidArgumentException("Invoice {$invoice->invoice_number} cannot take that payment.");
                }
                $receipt->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $part]);
            }
        });

        return $receipt->fresh('allocations');
    }
}
