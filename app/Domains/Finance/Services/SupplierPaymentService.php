<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\DocumentPosted;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Money paid to a supplier and the bills it settles. */
class SupplierPaymentService
{
    public function __construct(private InvoiceService $numbers) {}

    /**
     * @param  array<string, mixed>  $head  supplier_id, paid_on, amount, method, reference, notes
     * @param  array<string, float|int|string>  $allocations  bill id => amount settled by this payment
     */
    public function record(array $head, array $allocations): SupplierPayment
    {
        $allocations = array_filter(array_map(fn ($a) => round((float) $a, 2), $allocations), fn ($a) => $a > 0);
        $amount = round((float) $head['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Enter the amount paid.');
        }
        if (round(array_sum($allocations), 2) > $amount) {
            throw new InvalidArgumentException('More is shared out over bills than was paid.');
        }

        return DB::transaction(function () use ($head, $allocations, $amount) {
            $payment = SupplierPayment::create([
                'payment_number' => $this->numbers->nextNumber('SP', 'supplier_payments', 'payment_number'), 'supplier_id' => $head['supplier_id'],
                'paid_on' => $head['paid_on'], 'amount' => $amount, 'method' => $head['method'] ?? null, 'reference' => $head['reference'] ?? null, 'notes' => $head['notes'] ?? null,
            ]);
            $this->allocate($payment, $allocations);
            event(new DocumentPosted($payment));

            return $payment->fresh('allocations');
        });
    }

    /** Puts what is left of a payment on more bills of the same supplier. */
    public function allocateRest(SupplierPayment $payment, array $allocations): SupplierPayment
    {
        $allocations = array_filter(array_map(fn ($a) => round((float) $a, 2), $allocations), fn ($a) => $a > 0);
        if (round(array_sum($allocations), 2) > $payment->unallocated()) {
            throw new InvalidArgumentException('That is more than what is left of this payment.');
        }
        DB::transaction(fn () => $this->allocate($payment, $allocations));

        return $payment->fresh('allocations');
    }

    /** @param array<string, float> $allocations */
    private function allocate(SupplierPayment $payment, array $allocations): void
    {
        foreach ($allocations as $billId => $part) {
            $bill = SupplierBill::whereKey($billId)->lockForUpdate()->firstOrFail();
            if ($bill->supplier_id !== $payment->supplier_id || $bill->status !== 'POSTED' || $bill->isCreditNote()) {
                throw new InvalidArgumentException("Bill {$bill->bill_number} cannot take a payment to this supplier.");
            }
            if ($part > $bill->balance()) {
                throw new InvalidArgumentException("Bill {$bill->bill_number} has only {$bill->balance()} left to pay.");
            }
            $payment->allocations()->create(['supplier_bill_id' => $bill->id, 'amount' => $part]);
        }
    }
}
