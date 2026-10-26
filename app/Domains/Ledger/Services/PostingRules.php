<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Ledger\Models\Journal;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The double entry for each Finance document:
 * - invoice: debit receivables with the total, credit sales with each line and the tax owed to each authority (a credit note the other way);
 * - supplier bill: debit each line's account and the tax on it, credit payables with the total (a supplier credit note the other way);
 * - receipt: debit cash or bank, credit receivables with the whole amount, even the part left on account;
 * - supplier payment: debit payables, credit cash or bank.
 * Tax a customer withholds is not posted here: receivables keep the full total until it is settled against the certificate.
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

        return [$bill->bill_date->toDateString(), $lines, ($credit ? 'Supplier credit note ' : 'Bill ').$bill->bill_number.' · '.$bill->supplier?->name.' '.$bill->supplier_bill_number];
    }

    /** @return array{0: string, 1: list<array<string, mixed>>, 2: string} */
    private function receipt(Receipt $receipt): array
    {
        $receipt->loadMissing('customer');

        return [$receipt->received_on->toDateString(), [
            ['debit' => $receipt->amount, 'account_id' => LedgerSettings::methodAccount($receipt->method)->id],
            ['credit' => $receipt->amount, 'account_id' => LedgerSettings::account('receivables')->id, 'party_type' => 'customer', 'party_id' => $receipt->customer_id],
        ], 'Receipt '.$receipt->receipt_number.' · '.$receipt->customer?->name.($receipt->reference ? ' '.$receipt->reference : '')];
    }

    /** @return array{0: string, 1: list<array<string, mixed>>, 2: string} */
    private function payment(SupplierPayment $payment): array
    {
        $payment->loadMissing('supplier');

        return [$payment->paid_on->toDateString(), [
            ['debit' => $payment->amount, 'account_id' => LedgerSettings::account('payables')->id, 'party_type' => 'supplier', 'party_id' => $payment->supplier_id],
            ['credit' => $payment->amount, 'account_id' => LedgerSettings::methodAccount($payment->method)->id],
        ], 'Payment '.$payment->payment_number.' · '.$payment->supplier?->name.($payment->reference ? ' '.$payment->reference : '')];
    }

    /**
     * Posts every counted document that has no journal yet, oldest first. Voided documents are skipped: they would only post and reverse.
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
            ->sortBy(fn ($pair) => $pair[0]->toDateString());
        $posted = 0;
        $skipped = [];
        foreach ($documents as [, $document]) {
            try {
                $this->post($document);
                $posted++;
            } catch (InvalidArgumentException $e) {
                $skipped[] = ($document->invoice_number ?? $document->bill_number ?? $document->receipt_number ?? $document->payment_number).': '.$e->getMessage();
            }
        }

        return ['posted' => $posted, 'skipped' => $skipped];
    }
}
