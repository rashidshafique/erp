<?php

namespace App\Domains\Finance\Services;

use Illuminate\Support\Collection;

/**
 * What is owed to each authority for a period, worked out from the tax registers: tax charged on invoices, less tax suppliers
 * charged us, less the share of it customers kept back and paid themselves, plus the share we kept back from suppliers and must
 * pay. Income tax withheld either way is listed apart: what we withheld is owed; what customers withheld is ours to claim.
 * Read only; a negative figure is carried forward or claimed under the authority's own rules.
 */
class TaxReturn
{
    public function __construct(private TaxRegister $register) {}

    /**
     * @return Collection<string, array{authority: ?string, output: float, input: float, withheld_by_customers: float, withheld_by_us: float, payable: float, income_tax_withheld_by_us: float, income_tax_withheld_by_customers: float}>
     *         keyed by authority code ('' for tax with no authority named)
     */
    public function forPeriod(string $from, string $to): Collection
    {
        $sales = $this->register->rows('sales', $from, $to);
        $purchases = $this->register->rows('purchases', $from, $to);
        $sum = fn (Collection $rows) => round((float) $rows->sum('amount'), 2);
        $authorities = $sales->concat($purchases)->pluck('authority')->map(fn ($a) => (string) $a)->unique()->sort()->values();

        return $authorities->mapWithKeys(function (string $code) use ($sales, $purchases, $sum) {
            $mine = fn (Collection $rows) => $rows->filter(fn ($r) => (string) $r['authority'] === $code);
            [$s, $p] = [$mine($sales), $mine($purchases)];
            $row = [
                'authority' => $code === '' ? null : $code,
                'output' => $sum($s->where('withheld', false)),
                'input' => $sum($p->where('withheld', false)),
                'withheld_by_customers' => $sum($s->where('withheld', true)->where('share_of_tax', true)),
                'withheld_by_us' => $sum($p->where('withheld', true)->where('share_of_tax', true)),
                'income_tax_withheld_by_us' => $sum($p->where('withheld', true)->where('share_of_tax', false)),
                'income_tax_withheld_by_customers' => $sum($s->where('withheld', true)->where('share_of_tax', false)),
            ];
            $row['payable'] = round($row['output'] - $row['input'] - $row['withheld_by_customers'] + $row['withheld_by_us'], 2);

            return [$code => $row];
        });
    }
}
