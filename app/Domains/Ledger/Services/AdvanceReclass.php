<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Ledger\Models\Journal;
use Illuminate\Support\Carbon;

/**
 * Receipts and supplier payments posted before advances were kept apart credited or debited their whole amount to receivables or
 * payables. For each one with money still not put on an invoice or bill, this moves that money once into customer advances or
 * advances to suppliers, dated today (or the day after the books are closed to), so later matching moves it back correctly.
 * Safe to run again: a document already moved, or posted the new way, is left alone.
 */
class AdvanceReclass
{
    public function __construct(private \App\Domains\Ledger\Services\Ledger $ledger) {}

    /** @return int how many documents were moved */
    public function run(): int
    {
        $date = $this->date();
        $moved = 0;
        $advances = LedgerSettings::account('customer_advances')->id;
        foreach (Receipt::with('customer')->whereIn('id', $this->postedTheOldWay('receipt', $advances))->get() as $r) {
            if (($rest = $r->unallocated()) > 0) {
                $party = ['party_type' => 'customer', 'party_id' => $r->customer_id];
                $this->ledger->post($date, [
                    ['debit' => $rest, 'account_id' => LedgerSettings::account('receivables')->id] + $party,
                    ['credit' => $rest, 'account_id' => $advances] + $party,
                ], ['type' => 'advance_reclass', 'id' => $r->id], 'Receipt '.$r->receipt_number.' not on an invoice moved to customer advances · '.$r->customer?->name);
                $moved++;
            }
        }
        $prepaid = LedgerSettings::account('supplier_advances')->id;
        foreach (SupplierPayment::with('supplier')->whereIn('id', $this->postedTheOldWay('supplier_payment', $prepaid))->get() as $p) {
            if (($rest = $p->unallocated()) > 0) {
                $party = ['party_type' => 'supplier', 'party_id' => $p->supplier_id];
                $this->ledger->post($date, [
                    ['debit' => $rest, 'account_id' => $prepaid] + $party,
                    ['credit' => $rest, 'account_id' => LedgerSettings::account('payables')->id] + $party,
                ], ['type' => 'advance_reclass', 'id' => $p->id], 'Payment '.$p->payment_number.' not on a bill moved to advances to suppliers · '.$p->supplier?->name);
                $moved++;
            }
        }

        return $moved;
    }

    /** @return \Illuminate\Support\Collection<int, string> documents of this type whose journal has no line on the advance account and that were not moved yet */
    private function postedTheOldWay(string $type, string $advanceAccountId): \Illuminate\Support\Collection
    {
        return Journal::where('source_type', $type)->where('kind', 'POST')
            ->whereDoesntHave('lines', fn ($q) => $q->where('account_id', $advanceAccountId))
            ->whereNotIn('source_id', Journal::where('source_type', 'advance_reclass')->select('source_id'))
            ->pluck('source_id');
    }

    private function date(): string
    {
        $closed = LedgerSettings::closedTo();

        return $closed && $closed >= today()->toDateString() ? Carbon::parse($closed)->addDay()->toDateString() : today()->toDateString();
    }
}
