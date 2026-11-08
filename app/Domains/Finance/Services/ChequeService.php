<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\DocumentPosted;
use App\Domains\Finance\Events\DocumentVoided;
use App\Domains\Finance\Models\Cheque;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Post-dated cheques from customers and to suppliers. HOLD keeps the invoices or bills it is meant for in the cheque and makes the
 * receipt or payment when it clears; SETTLE makes it at once through a holding account (the ledger posts it there), and clearing
 * moves the money into the bank. A bounce voids what the cheque made, with every journal it and its allocations posted.
 */
class ChequeService
{
    public function __construct(private ReceiptService $receipts, private SupplierPaymentService $payments) {}

    /**
     * A cheque dated after the day it changes hands is post-dated.
     *
     * @param  array<string, mixed>  $head  the receipt or payment form: customer_id or supplier_id, received_on or paid_on, amount, method,
     *                                      reference, account_id, notes, sales_order_id or purchase_order_id, cheque_date, drawn_on
     */
    public static function isPostDated(array $head): bool
    {
        $on = $head['received_on'] ?? $head['paid_on'] ?? null;

        return filled($head['cheque_date'] ?? null) && $on !== null && Carbon::parse($head['cheque_date'])->gt(Carbon::parse($on));
    }

    /** @param  array<string, mixed>  $head  as in isPostDated() @param array<string, float|string|null> $allocations */
    public function receive(array $head, array $allocations): Cheque
    {
        return $this->open('RECEIVED', $head, $allocations);
    }

    /** @param  array<string, mixed>  $head  as in isPostDated() @param array<string, float|string|null> $allocations */
    public function issue(array $head, array $allocations): Cheque
    {
        return $this->open('ISSUED', $head, $allocations);
    }

    /** Taken to the bank (when deposits are a step of their own). */
    public function deposit(Cheque $cheque, string $date, ?string $accountId): Cheque
    {
        return DB::transaction(function () use ($cheque, $date, $accountId) {
            $cheque = $this->locked($cheque);
            $this->mustBe($cheque, ['IN_HAND']);
            $this->notBeforeItsDate($cheque, $date);
            $cheque->update(['status' => 'DEPOSITED', 'deposited_on' => $date, 'account_id' => $accountId ?: $cheque->account_id]);

            return $cheque;
        });
    }

    /** The bank has paid it: HOLD makes the receipt or payment now, SETTLE moves the money from the holding account to the bank. */
    public function clear(Cheque $cheque, string $date, ?string $accountId): Cheque
    {
        return DB::transaction(function () use ($cheque, $date, $accountId) {
            $cheque = $this->locked($cheque);
            $this->mustBe($cheque, FinanceRules::chequeSteps() === 'DEPOSIT' ? ['DEPOSITED'] : ['IN_HAND', 'DEPOSITED']);
            $this->notBeforeItsDate($cheque, $date);
            $cheque->update(['status' => 'CLEARED', 'cleared_on' => $date, 'account_id' => $accountId ?: $cheque->account_id]);
            if ($cheque->mode === 'HOLD') {
                $this->make($cheque, $date, $this->stillOwing($cheque));
            } else {
                event(new DocumentPosted($cheque));
            }

            return $cheque->fresh();
        });
    }

    /** Returned unpaid: what the cheque made is voided and the invoices or bills are owed again. */
    public function bounce(Cheque $cheque, string $date, ?string $reason): Cheque
    {
        return DB::transaction(function () use ($cheque, $date, $reason) {
            $cheque = $this->locked($cheque);
            $this->mustBe($cheque, ['IN_HAND', 'DEPOSITED', 'CLEARED']);
            if (Carbon::parse($date)->lt($cheque->handed_on)) {
                throw new InvalidArgumentException('A cheque cannot bounce before it was '.($cheque->isReceived() ? 'received' : 'given').'.');
            }
            if ($cheque->mode === 'SETTLE' && $cheque->status === 'CLEARED') {
                event(new DocumentVoided($cheque, $date));
            }
            if ($document = $cheque->document()) {
                foreach ($document->allocations()->get() as $allocation) {
                    event(new DocumentVoided($allocation, $date));
                }
                event(new DocumentVoided($document, $date));
                $document->allocations()->delete();
                $document->update(['voided_at' => Carbon::parse($date), 'void_reason' => 'Cheque bounced'.($reason ? ': '.$reason : '')]);
            }
            $cheque->update(['status' => 'BOUNCED', 'bounced_on' => $date, 'bounce_reason' => $reason]);

            return $cheque->fresh();
        });
    }

    /** @param array<string, mixed> $head @param array<string, float|string|null> $allocations */
    private function open(string $direction, array $head, array $allocations): Cheque
    {
        $received = $direction === 'RECEIVED';
        $allocations = array_filter(array_map(fn ($a) => round((float) $a, 2), $allocations), fn ($a) => $a > 0);
        $amount = round((float) $head['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Enter the amount of the cheque.');
        }
        if (round(array_sum($allocations), 2) > $amount) {
            throw new InvalidArgumentException('More is shared out than the cheque is for.');
        }
        foreach ($allocations as $id => $part) {
            $doc = $received ? Invoice::find($id) : SupplierBill::find($id);
            $party = $received ? $doc?->customer_id === $head['customer_id'] : $doc?->supplier_id === $head['supplier_id'];
            if (! $doc || ! $party || $doc->isCreditNote() || $doc->status !== ($received ? 'ISSUED' : 'POSTED')) {
                throw new InvalidArgumentException('The cheque can only go on this '.($received ? "customer's issued invoices." : "supplier's posted bills."));
            }
            if ($part > $doc->balance()) {
                throw new InvalidArgumentException(($received ? 'Invoice '.$doc->invoice_number : 'Bill '.$doc->bill_number)." has only {$doc->balance()} left to pay.");
            }
        }
        $mode = FinanceRules::pdcMode(! $received);

        return DB::transaction(function () use ($direction, $received, $head, $allocations, $amount, $mode) {
            $cheque = Cheque::create([
                'direction' => $direction, 'mode' => $mode, 'status' => 'IN_HAND',
                'customer_id' => $received ? $head['customer_id'] : null, 'supplier_id' => $received ? null : $head['supplier_id'],
                'cheque_number' => $head['reference'] ?? null, 'method' => $head['method'] ?? null, 'drawn_on' => $head['drawn_on'] ?? null,
                'handed_on' => $head[$received ? 'received_on' : 'paid_on'], 'cheque_date' => $head['cheque_date'], 'amount' => $amount,
                'account_id' => $head['account_id'] ?? null, 'allocations' => $allocations ?: null, 'notes' => $head['notes'] ?? null,
                'sales_order_id' => $head['sales_order_id'] ?? null, 'purchase_order_id' => $head['purchase_order_id'] ?? null, 'user_id' => auth()->id(),
            ]);
            if ($mode === 'SETTLE') {
                $this->make($cheque, $cheque->handed_on->toDateString(), $allocations);
            }

            return $cheque->fresh();
        });
    }

    /** Makes the receipt or supplier payment for the cheque, dated $date. @param array<string, float> $allocations */
    private function make(Cheque $cheque, string $date, array $allocations): void
    {
        $head = ['amount' => $cheque->amount, 'method' => $cheque->method ?: 'Cheque', 'reference' => $cheque->cheque_number, 'notes' => $cheque->notes,
            'account_id' => $cheque->mode === 'HOLD' ? $cheque->account_id : null, 'cheque_id' => $cheque->id];
        if ($cheque->isReceived()) {
            $receipt = $this->receipts->record($head + ['customer_id' => $cheque->customer_id, 'received_on' => $date, 'sales_order_id' => $cheque->sales_order_id], $allocations);
            $cheque->update(['receipt_id' => $receipt->id]);
        } else {
            $payment = $this->payments->record($head + ['supplier_id' => $cheque->supplier_id, 'paid_on' => $date, 'purchase_order_id' => $cheque->purchase_order_id], $allocations);
            $cheque->update(['supplier_payment_id' => $payment->id]);
        }
    }

    /**
     * The planned allocations cut to what each invoice or bill still owes; the rest stays on account.
     *
     * @return array<string, float>
     */
    private function stillOwing(Cheque $cheque): array
    {
        $out = [];
        foreach ($cheque->allocations ?? [] as $id => $part) {
            $doc = $cheque->isReceived() ? Invoice::find($id) : SupplierBill::find($id);
            $left = $doc ? round(min((float) $part, $doc->balance()), 2) : 0.0;
            if ($left > 0) {
                $out[$id] = $left;
            }
        }

        return $out;
    }

    /** The cheque read again under a row lock, so two quick taps cannot both act on it. */
    private function locked(Cheque $cheque): Cheque
    {
        return Cheque::whereKey($cheque->id)->lockForUpdate()->firstOrFail();
    }

    /** @param list<string> $statuses */
    private function mustBe(Cheque $cheque, array $statuses): void
    {
        if (! in_array($cheque->status, $statuses, true)) {
            throw new InvalidArgumentException('This cheque is '.mb_strtolower(Cheque::STATUSES[$cheque->status]).'.');
        }
    }

    private function notBeforeItsDate(Cheque $cheque, string $date): void
    {
        if (Carbon::parse($date)->lt($cheque->cheque_date)) {
            throw new InvalidArgumentException('The cheque is dated '.$cheque->cheque_date->displayDate().'; the bank will not pay it before then.');
        }
    }
}
