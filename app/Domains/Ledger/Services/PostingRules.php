<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\ReceiptAllocation;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Finance\Models\SupplierPaymentAllocation;
use App\Domains\Finance\Models\WithholdingCertificate;
use App\Domains\Ledger\Models\Journal;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The double entry for each Finance document:
 * - invoice: debit receivables with the total, credit sales with each line and the tax owed to each authority (a credit note the other way);
 * - supplier bill: debit each line's account and the tax on it, credit payables with the total, then move any tax we withhold from
 *   payables to what we owe the authority (a supplier credit note the other way);
 * - receipt: debit cash or bank, credit receivables with the part put on invoices and customer advances with the rest;
 * - supplier payment: credit cash or bank, debit payables with the part put on bills and advances to suppliers with the rest;
 * - money on account put on an invoice or bill later: customer advances to receivables, or payables to advances to suppliers.
 * Advances are kept apart from receivables and payables because IAS 1 does not allow netting a liability against an asset: money
 * a customer paid ahead is owed back to them in goods or cash, not a smaller debt (and the same the other way for suppliers).
 * - withholding certificate: debit tax withheld by customers, credit receivables.
 * Tax a customer withholds is not posted with the invoice: receivables keep the full total until the customer's certificate settles it.
 */
class PostingRules
{
    public function __construct(private Ledger $ledger) {}

    /** @return array{type: string, id: string} */
    public static function source(Model $document): array
    {
        return ['type' => match (true) {
            $document instanceof Invoice => 'invoice',
            $document instanceof SupplierBill => 'supplier_bill',
            $document instanceof Receipt => 'receipt',
            $document instanceof SupplierPayment => 'supplier_payment',
            $document instanceof WithholdingCertificate => 'withholding_certificate',
            $document instanceof ReceiptAllocation => 'receipt_allocation',
            $document instanceof SupplierPaymentAllocation => 'supplier_payment_allocation',
            default => throw new InvalidArgumentException('The ledger does not know how to post a '.class_basename($document).'.'),
        }, 'id' => $document->getKey()];
    }

    public function post(Model $document): Journal
    {
        [$date, $lines, $memo] = match (true) {
            $document instanceof Invoice => $this->invoice($document),
            $document instanceof SupplierBill => $this->bill($document),
            $document instanceof Receipt => $this->receipt($document),
            $document instanceof SupplierPayment => $this->payment($document),
            $document instanceof WithholdingCertificate => $this->certificate($document),
            $document instanceof ReceiptAllocation => $this->receiptAllocation($document),
            $document instanceof SupplierPaymentAllocation => $this->paymentAllocation($document),
            default => throw new InvalidArgumentException('The ledger does not know how to post a '.class_basename($document).'.'),
        };

        return $this->ledger->post($date, $lines, self::source($document), $memo);
    }

    /** Reverses a document's journal on the given date. A document that never reached the ledger has nothing to reverse. */
    public function reverse(Model $document, string $date): ?Journal
    {
        $source = self::source($document);
        $journal = Journal::where('source_type', $source['type'])->where('source_id', $source['id'])->where('kind', 'POST')->first();

        return $journal ? $this->ledger->reverse($journal, $date) : null;
    }

    /** @return array{0: string, 1: list<array<string, mixed>>, 2: string} */
    private function invoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines', 'customer']);
        $credit = $invoice->isCreditNote();
        $party = ['party_type' => 'customer', 'party_id' => $invoice->customer_id];
        $income = LedgerSettings::account($credit ? 'sales_returns' : 'sales')->id;
        $lines = [[($credit ? 'credit' : 'debit') => $invoice->total, 'account_id' => LedgerSettings::account('receivables')->id] + $party];
        foreach ($invoice->lines as $l) {
            $lines[] = [($credit ? 'debit' : 'credit') => $l->line_net, 'account_id' => $income];
            $charged = collect($l->taxes ?? [])->reject(fn ($t) => $t['withheld'] ?? false);
            $left = (float) $l->line_tax;
            foreach ($charged as $t) {
                $lines[] = [($credit ? 'debit' : 'credit') => (float) $t['amount'], 'account_id' => LedgerSettings::taxAccount($t['authority'] ?? null)->id];
                $left -= (float) $t['amount'];
            }
            if (round($left, 2) != 0.0) {
                $lines[] = [($credit ? 'debit' : 'credit') => round($left, 2), 'account_id' => LedgerSettings::account('output_tax')->id];
            }
        }

        return [$invoice->invoice_date->toDateString(), $lines, ($credit ? 'Credit note ' : 'Invoice ').$invoice->invoice_number.' · '.$invoice->customer?->name];
    }

    /** @return array{0: string, 1: list<array<string, mixed>>, 2: string} */
    private function bill(SupplierBill $bill): array
    {
        $bill->loadMissing(['lines', 'supplier', 'original.lines']);
        $credit = $bill->isCreditNote();
        $lines = [[($credit ? 'debit' : 'credit') => $bill->total, 'account_id' => LedgerSettings::account('payables')->id, 'party_type' => 'supplier', 'party_id' => $bill->supplier_id]];
        $tax = LedgerSettings::account('input_tax')->id;
        foreach ($bill->lines as $l) {
            // A credit note line with no account goes back to where the same line of the original bill went.
            $from = $l->account_id || ! $bill->original ? $l : ($bill->original->lines->firstWhere('description', $l->description) ?? $l);
            $account = $l->account_id ?: ($from->account_id ?: LedgerSettings::account($from->purchase_order_line_id ? 'purchases' : 'expense')->id);
            $lines[] = [($credit ? 'credit' : 'debit') => $l->line_net, 'account_id' => $account];
            $lines[] = [($credit ? 'credit' : 'debit') => $l->line_tax, 'account_id' => $tax];
        }
        if ((float) $bill->tax_withheld > 0) {
            $lines[] = [($credit ? 'credit' : 'debit') => (float) $bill->tax_withheld, 'account_id' => LedgerSettings::account('payables')->id, 'party_type' => 'supplier', 'party_id' => $bill->supplier_id];
            $lines[] = [($credit ? 'debit' : 'credit') => (float) $bill->tax_withheld, 'account_id' => LedgerSettings::account('withheld_from_suppliers')->id];
        }

        return [$bill->bill_date->toDateString(), $lines, ($credit ? 'Supplier credit note ' : 'Bill ').$bill->bill_number.' · '.$bill->supplier?->name.' '.$bill->supplier_bill_number];
    }

    /** @return array{0: string, 1: list<array<string, mixed>>, 2: string} */
    private function receipt(Receipt $receipt): array
    {
        $receipt->loadMissing('customer');
        $party = ['party_type' => 'customer', 'party_id' => $receipt->customer_id];
        $rest = $receipt->unallocated();

        return [$receipt->received_on->toDateString(), [
            ['debit' => $receipt->amount, 'account_id' => LedgerSettings::methodAccount($receipt->method)->id],
            ['credit' => round($receipt->amount - $rest, 2), 'account_id' => LedgerSettings::account('receivables')->id] + $party,
            ['credit' => $rest, 'account_id' => LedgerSettings::account('customer_advances')->id] + $party,
        ], 'Receipt '.$receipt->receipt_number.' · '.$receipt->customer?->name.($receipt->reference ? ' '.$receipt->reference : '')];
    }

    /** @return array{0: string, 1: list<array<string, mixed>>, 2: string} */
    private function payment(SupplierPayment $payment): array
    {
        $payment->loadMissing('supplier');
        $party = ['party_type' => 'supplier', 'party_id' => $payment->supplier_id];
        $rest = $payment->unallocated();

        return [$payment->paid_on->toDateString(), [
            ['debit' => round($payment->amount - $rest, 2), 'account_id' => LedgerSettings::account('payables')->id] + $party,
            ['debit' => $rest, 'account_id' => LedgerSettings::account('supplier_advances')->id] + $party,
            ['credit' => $payment->amount, 'account_id' => LedgerSettings::methodAccount($payment->method)->id],
        ], 'Payment '.$payment->payment_number.' · '.$payment->supplier?->name.($payment->reference ? ' '.$payment->reference : '')];
    }

    /** @return array{0: string, 1: list<array<string, mixed>>, 2: string} money on account put on an invoice, the day it is put on */
    private function receiptAllocation(ReceiptAllocation $allocation): array
    {
        $allocation->loadMissing(['receipt.customer', 'invoice']);
        $party = ['party_type' => 'customer', 'party_id' => $allocation->receipt->customer_id];

        return [$allocation->created_at->toDateString(), [
            ['debit' => $allocation->amount, 'account_id' => LedgerSettings::account('customer_advances')->id] + $party,
            ['credit' => $allocation->amount, 'account_id' => LedgerSettings::account('receivables')->id] + $party,
        ], 'Receipt '.$allocation->receipt->receipt_number.' put on invoice '.$allocation->invoice?->invoice_number.' · '.$allocation->receipt->customer?->name];
    }

    /** @return array{0: string, 1: list<array<string, mixed>>, 2: string} a supplier advance put on a bill, the day it is put on */
    private function paymentAllocation(SupplierPaymentAllocation $allocation): array
    {
        $allocation->loadMissing(['payment.supplier', 'bill']);
        $party = ['party_type' => 'supplier', 'party_id' => $allocation->payment->supplier_id];

        return [$allocation->created_at->toDateString(), [
            ['debit' => $allocation->amount, 'account_id' => LedgerSettings::account('payables')->id] + $party,
            ['credit' => $allocation->amount, 'account_id' => LedgerSettings::account('supplier_advances')->id] + $party,
        ], 'Payment '.$allocation->payment->payment_number.' put on bill '.$allocation->bill?->bill_number.' · '.$allocation->payment->supplier?->name];
    }

    /** @return array{0: string, 1: list<array<string, mixed>>, 2: string} */
    private function certificate(WithholdingCertificate $certificate): array
    {
        $certificate->loadMissing('customer');

        return [$certificate->certificate_date->toDateString(), [
            ['debit' => $certificate->amount, 'account_id' => LedgerSettings::account('tax_withheld')->id],
            ['credit' => $certificate->amount, 'account_id' => LedgerSettings::account('receivables')->id, 'party_type' => 'customer', 'party_id' => $certificate->customer_id],
        ], 'Tax certificate '.$certificate->certificate_number.' · '.$certificate->customer?->name.' '.$certificate->tax_label.' '.$certificate->reference];
    }

    /**
     * Posts every counted document that has no journal yet, oldest first. Voided documents are skipped: they would only post and reverse.
     * Then moves money on account posted before advances were kept apart into the advance accounts (counted with the posted).
     *
     * @return array{posted: int, skipped: list<string>}
     */
    public function postMissing(): array
    {
        $done = fn (string $type) => Journal::where('source_type', $type)->where('kind', 'POST')->select('source_id');
        $documents = collect()
            ->concat(Invoice::where('status', 'ISSUED')->whereNotIn('id', $done('invoice'))->get()->map(fn ($d) => [$d->invoice_date, $d]))
            ->concat(SupplierBill::where('status', 'POSTED')->whereNotIn('id', $done('supplier_bill'))->get()->map(fn ($d) => [$d->bill_date, $d]))
            ->concat(Receipt::whereNotIn('id', $done('receipt'))->get()->map(fn ($d) => [$d->received_on, $d]))
            ->concat(SupplierPayment::whereNotIn('id', $done('supplier_payment'))->get()->map(fn ($d) => [$d->paid_on, $d]))
            ->concat(WithholdingCertificate::where('status', 'ACTIVE')->whereNotIn('id', $done('withholding_certificate'))->get()->map(fn ($d) => [$d->certificate_date, $d]))
            ->sortBy(fn ($pair) => $pair[0]->toDateString());
        $posted = 0;
        $skipped = [];
        foreach ($documents as [, $document]) {
            try {
                $this->post($document);
                $posted++;
            } catch (InvalidArgumentException $e) {
                $skipped[] = ($document->invoice_number ?? $document->bill_number ?? $document->receipt_number ?? $document->payment_number ?? $document->certificate_number).': '.$e->getMessage();
            }
        }

        try {
            $moved = app(AdvanceReclass::class)->run();
        } catch (InvalidArgumentException $e) {
            [$moved, $skipped[]] = [0, 'Advances: '.$e->getMessage()];
        }

        return ['posted' => $posted + $moved, 'skipped' => $skipped];
    }
}
