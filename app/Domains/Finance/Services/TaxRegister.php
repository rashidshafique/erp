<?php

namespace App\Domains\Finance\Services;

use App\Domains\Core\Contracts\TaxCalculator;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\SupplierBill;
use Illuminate\Support\Collection;

/**
 * The tax on issued invoices (output tax) or posted supplier bills (input tax) over a period, line by line with the other party's
 * registration numbers, and added up per authority and tax. Read only; voided documents are left out and credit notes count
 * against the period they are dated in.
 */
class TaxRegister
{
    public function __construct(private TaxCalculator $tax) {}

    /**
     * One row per document, tax, rate and tariff code. Credit notes are negative. A withheld row that is a share of a charged tax
     * (sales tax kept back by the buyer) has share_of_tax set; withheld income tax does not.
     *
     * @return Collection<int, array{date: string, number: string, kind: string, party: string, registrations: array<string, string>, url: string, tariff: ?string, code: string, label: string, authority: ?string, rate: float, base: float, amount: float, withheld: bool, share_of_tax: bool}>
     */
    public function rows(string $side, string $from, string $to): Collection
    {
        $registrations = [];
        $regs = function (?string $partyId) use (&$registrations): array {
            return $registrations[$partyId ?? ''] ??= $this->tax->registrations($partyId)['party'];
        };
        $rows = $side === 'purchases' ? $this->purchases($from, $to, $regs) : $this->sales($from, $to, $regs);

        return $rows->filter(fn ($r) => $r['base'] != 0.0 || $r['amount'] != 0.0)
            ->sortBy([['date', 'asc'], ['number', 'asc']])->values();
    }

    /**
     * Rows added up per authority, tax and rate, charged taxes first.
     *
     * @return Collection<int, array{authority: ?string, code: string, label: string, rate: float, withheld: bool, base: float, amount: float, documents: int}>
     */
    public function summary(Collection $rows): Collection
    {
        return $rows->groupBy(fn ($r) => implode('|', [$r['authority'] ?? '', $r['code'], $r['rate'], $r['withheld'] ? 1 : 0]))
            ->map(fn ($g) => ['authority' => $g->first()['authority'], 'code' => $g->first()['code'], 'label' => $g->first()['label'], 'rate' => $g->first()['rate'],
                'withheld' => $g->first()['withheld'], 'base' => round($g->sum('base'), 2), 'amount' => round($g->sum('amount'), 2), 'documents' => $g->pluck('number')->unique()->count()])
            ->sortBy([['withheld', 'asc'], ['authority', 'asc'], ['code', 'asc'], ['rate', 'asc']])->values();
    }

    /**
     * Whether a withheld tax was a share of a charged one. Taxes saved before their basis was kept are judged by their base: one
     * that is neither the line value nor the value with tax was another tax's amount.
     *
     * @param  array<string, mixed>  $tax
     * @param  list<array<string, mixed>>  $lineTaxes
     */
    public static function shareOfTax(array $tax, array $lineTaxes, float $net): bool
    {
        if (! ($tax['withheld'] ?? false)) {
            return false;
        }
        if (isset($tax['basis'])) {
            return ! in_array($tax['basis'], ['NET', 'GROSS'], true);
        }
        $charged = array_sum(array_map(fn ($t) => ($t['withheld'] ?? false) ? 0 : (float) $t['amount'], $lineTaxes));

        return abs((float) $tax['base'] - $net) > 0.01 && abs((float) $tax['base'] - ($net + $charged)) > 0.01;
    }

    private function sales(string $from, string $to, callable $regs): Collection
    {
        $invoices = Invoice::with(['lines', 'customer'])->where('status', 'ISSUED')->whereBetween('invoice_date', [$from, $to])->get();
        $rows = collect();
        foreach ($invoices as $invoice) {
            $sign = $invoice->isCreditNote() ? -1 : 1;
            $head = [
                'date' => $invoice->invoice_date->toDateString(), 'number' => (string) $invoice->invoice_number, 'kind' => $invoice->isCreditNote() ? 'Credit note' : 'Invoice',
                'party' => (string) $invoice->customer?->name, 'registrations' => $regs($invoice->customer?->party_id), 'url' => route('invoices.show', $invoice),
            ];
            $grouped = [];
            foreach ($invoice->lines as $line) {
                $taxes = $line->taxes ?? [['code' => 'TAX', 'label' => 'Tax', 'authority' => null, 'rate' => $line->tax_percent, 'base' => $line->line_net, 'amount' => $line->line_tax, 'withheld' => false]];
                foreach ($taxes as $t) {
                    $key = implode('|', [$t['code'], $t['rate'], $line->tariff_code]);
                    $grouped[$key] ??= $head + ['tariff' => $line->tariff_code, 'code' => $t['code'], 'label' => $t['label'], 'authority' => $t['authority'] ?? null,
                        'rate' => (float) $t['rate'], 'base' => 0.0, 'amount' => 0.0, 'withheld' => (bool) ($t['withheld'] ?? false), 'share_of_tax' => self::shareOfTax($t, $taxes, (float) $line->line_net)];
                    $grouped[$key]['base'] = round($grouped[$key]['base'] + $sign * (float) $t['base'], 2);
                    $grouped[$key]['amount'] = round($grouped[$key]['amount'] + $sign * (float) $t['amount'], 2);
                }
            }
            $rows->push(...array_values($grouped));
        }

        return $rows;
    }

    /**
     * Each bill line's tax as the supplier charged it, named by tax and authority where the rules could, and the tax we withheld
     * from them; older lines as one unnamed tax at their percent.
     */
    private function purchases(string $from, string $to, callable $regs): Collection
    {
        $bills = SupplierBill::with(['lines', 'supplier'])->where('status', 'POSTED')->whereBetween('bill_date', [$from, $to])->get();
        $rows = collect();
        foreach ($bills as $bill) {
            $sign = $bill->isCreditNote() ? -1 : 1;
            $head = [
                'date' => $bill->bill_date->toDateString(), 'number' => (string) $bill->bill_number.($bill->supplier_bill_number ? ' ('.$bill->supplier_bill_number.')' : ''),
                'kind' => $bill->isCreditNote() ? 'Credit note' : 'Bill', 'party' => (string) $bill->supplier?->name, 'registrations' => $regs($bill->supplier?->party_id),
                'url' => route('bills.show', $bill), 'tariff' => null,
            ];
            $grouped = [];
            foreach ($bill->lines as $line) {
                $taxes = $line->taxes ?? [['code' => 'TAX', 'label' => 'Input tax', 'authority' => null, 'rate' => $line->tax_percent, 'base' => $line->line_net, 'amount' => $line->line_tax]];
                foreach ($taxes as $t) {
                    $key = $t['code'].'|'.(float) $t['rate'];
                    $grouped[$key] ??= $head + ['code' => $t['code'], 'label' => $t['code'] === 'TAX' ? 'Input tax' : $t['label'], 'authority' => $t['authority'] ?? null,
                        'rate' => (float) $t['rate'], 'base' => 0.0, 'amount' => 0.0, 'withheld' => (bool) ($t['withheld'] ?? false), 'share_of_tax' => self::shareOfTax($t, $taxes, (float) $line->line_net)];
                    $grouped[$key]['base'] = round($grouped[$key]['base'] + $sign * (float) $t['base'], 2);
                    $grouped[$key]['amount'] = round($grouped[$key]['amount'] + $sign * (float) $t['amount'], 2);
                }
            }
            $rows->push(...array_values($grouped));
        }

        return $rows;
    }
}
