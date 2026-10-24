<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Purchasing\Models\Supplier;
use Illuminate\Support\Collection;

/** What we owe suppliers: open bills by age, and a supplier's statement. */
class Payables
{
    /** @return Collection<int, SupplierBill> posted bills with something left to pay, oldest due first */
    public function open(?string $supplierId = null): Collection
    {
        return SupplierBill::with('supplier')->where('kind', 'BILL')->where('status', 'POSTED')
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderBy('due_date')->orderBy('bill_date')->get()->filter(fn (SupplierBill $b) => $b->balance() > 0)->values();
    }

    public function bucket(SupplierBill $bill): string
    {
        $late = $bill->due_date ? (int) $bill->due_date->diffInDays(today(), false) : 0;

        return match (true) {
            $late <= 0 => 'current', $late <= 30 => 'd30', $late <= 60 => 'd60', $late <= 90 => 'd90', default => 'over',
        };
    }

    /**
     * One row per supplier we owe: the amount in each age bucket, the total, and what has been paid to them in advance.
     *
     * @return list<array{supplier: Supplier, buckets: array<string, float>, total: float, advance: float}>
     */
    public function ageing(): array
    {
        $rows = [];
        foreach ($this->open()->groupBy('supplier_id') as $supplierId => $bills) {
            $buckets = array_fill_keys(array_keys(Receivables::BUCKETS), 0.0);
            foreach ($bills as $bill) {
                $buckets[$this->bucket($bill)] += $bill->balance();
            }
            $rows[] = ['supplier' => $bills->first()->supplier, 'buckets' => array_map(fn ($v) => round($v, 2), $buckets), 'total' => round(array_sum($buckets), 2), 'advance' => $this->advance($supplierId)];
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $rows;
    }

    /** Money paid to a supplier and not yet put against a bill. */
    public function advance(string $supplierId): float
    {
        return round(SupplierPayment::where('supplier_id', $supplierId)->with('allocations')->get()->sum(fn (SupplierPayment $p) => $p->unallocated()), 2);
    }

    /**
     * Every bill, credit note and payment of a supplier in date order with a running balance (positive = we owe them).
     *
     * @return array{rows: list<array<string, mixed>>, balance: float}
     */
    public function statement(Supplier $supplier): array
    {
        $entries = [];
        foreach (SupplierBill::where('supplier_id', $supplier->id)->where('status', 'POSTED')->get() as $b) {
            $entries[] = ['date' => $b->bill_date, 'at' => $b->posted_at, 'type' => $b->isCreditNote() ? 'Credit note' : 'Bill', 'number' => $b->bill_number, 'model' => $b,
                'owed' => $b->isCreditNote() ? 0.0 : $b->total, 'paid' => $b->isCreditNote() ? $b->total : 0.0];
        }
        foreach (SupplierPayment::where('supplier_id', $supplier->id)->get() as $p) {
            $entries[] = ['date' => $p->paid_on, 'at' => $p->created_at, 'type' => 'Payment', 'number' => $p->payment_number, 'model' => $p, 'owed' => 0.0, 'paid' => $p->amount];
        }
        usort($entries, fn ($a, $b) => [$a['date']->timestamp, $a['at']] <=> [$b['date']->timestamp, $b['at']]);
        $balance = 0.0;
        foreach ($entries as &$e) {
            $balance = round($balance + $e['owed'] - $e['paid'], 2);
            $e['balance'] = $balance;
        }

        return ['rows' => $entries, 'balance' => $balance];
    }
}
