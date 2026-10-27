<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\WithholdingCertificate;
use App\Models\Customer;
use Illuminate\Support\Collection;

/** What customers owe: open invoices by age, and a customer's statement. */
class Receivables
{
    public const BUCKETS = ['current' => 'Not yet due', 'd30' => '1-30 days', 'd60' => '31-60 days', 'd90' => '61-90 days', 'over' => 'Over 90 days'];

    /** @return Collection<int, Invoice> issued invoices with something left to pay, oldest due first */
    public function open(?string $customerId = null): Collection
    {
        return Invoice::with('customer')->where('kind', 'INVOICE')->where('status', 'ISSUED')
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderBy('due_date')->orderBy('invoice_date')->get()->filter(fn (Invoice $i) => $i->balance() > 0)->values();
    }

    public function bucket(Invoice $invoice): string
    {
        $late = $invoice->due_date ? (int) $invoice->due_date->diffInDays(today(), false) : 0;

        return match (true) {
            $late <= 0 => 'current', $late <= 30 => 'd30', $late <= 60 => 'd60', $late <= 90 => 'd90', default => 'over',
        };
    }

    /**
     * One row per customer owing money: the amount in each age bucket, the total, the part of it that is tax they withheld and
     * still owe a certificate for, and what they have paid on account.
     *
     * @return list<array{customer: Customer, buckets: array<string, float>, total: float, withheld: float, on_account: float}>
     */
    public function ageing(): array
    {
        $rows = [];
        foreach ($this->open()->groupBy('customer_id') as $customerId => $invoices) {
            $buckets = array_fill_keys(array_keys(self::BUCKETS), 0.0);
            foreach ($invoices as $invoice) {
                $buckets[$this->bucket($invoice)] += $invoice->balance();
            }
            $rows[] = ['customer' => $invoices->first()->customer, 'buckets' => array_map(fn ($v) => round($v, 2), $buckets), 'total' => round(array_sum($buckets), 2),
                'withheld' => round($invoices->sum(fn (Invoice $i) => $i->withheldOpen()), 2), 'on_account' => $this->onAccount($customerId)];
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $rows;
    }

    /** Money received from a customer and not yet put against an invoice. */
    public function onAccount(string $customerId): float
    {
        return round(Receipt::where('customer_id', $customerId)->with('allocations')->get()->sum(fn (Receipt $r) => $r->unallocated()), 2);
    }

    /**
     * Every invoice, credit note, receipt and tax certificate of a customer in date order with a running balance (positive = they owe us).
     *
     * @return array{rows: list<array<string, mixed>>, balance: float}
     */
    public function statement(Customer $customer): array
    {
        $entries = [];
        foreach (Invoice::where('customer_id', $customer->id)->where('status', 'ISSUED')->get() as $i) {
            $entries[] = ['date' => $i->invoice_date, 'at' => $i->issued_at, 'type' => $i->isCreditNote() ? 'Credit note' : 'Invoice', 'number' => $i->invoice_number, 'model' => $i,
                'debit' => $i->isCreditNote() ? 0.0 : $i->total, 'credit' => $i->isCreditNote() ? $i->total : 0.0];
        }
        foreach (Receipt::where('customer_id', $customer->id)->get() as $r) {
            $entries[] = ['date' => $r->received_on, 'at' => $r->created_at, 'type' => 'Receipt', 'number' => $r->receipt_number, 'model' => $r, 'debit' => 0.0, 'credit' => $r->amount];
        }
        foreach (WithholdingCertificate::where('customer_id', $customer->id)->where('status', 'ACTIVE')->get() as $c) {
            $entries[] = ['date' => $c->certificate_date, 'at' => $c->created_at, 'type' => 'Tax certificate', 'number' => $c->certificate_number, 'model' => $c, 'debit' => 0.0, 'credit' => $c->amount];
        }
        usort($entries, fn ($a, $b) => [$a['date']->timestamp, $a['at']] <=> [$b['date']->timestamp, $b['at']]);
        $balance = 0.0;
        foreach ($entries as &$e) {
            $balance = round($balance + $e['debit'] - $e['credit'], 2);
            $e['balance'] = $balance;
        }

        return ['rows' => $entries, 'balance' => $balance];
    }
}
