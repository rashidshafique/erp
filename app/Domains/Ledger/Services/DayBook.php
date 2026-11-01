<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\Voucher;
use Illuminate\Support\Collection;

/**
 * The day book (design 19c): every voucher, posted by a document or by hand, with the drafts, newest first. Each row says what
 * it is, where it came from, what it moved and who entered it.
 */
class DayBook
{
    /** @var array<string, string> filter value => label */
    public const STATUSES = ['posted' => 'Posted', 'draft' => 'Draft', 'reversed' => 'Reversed'];

    /**
     * @param  array{q?: ?string, from?: ?string, to?: ?string, type?: ?string, source?: ?string, status?: ?string, account?: ?string}  $f
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(array $f): Collection
    {
        $q = trim((string) ($f['q'] ?? ''));
        $status = $f['status'] ?? null;
        $type = $f['type'] ?? null;
        $source = $f['source'] ?? null;
        $rows = collect();

        if ($status !== 'draft') {
            $journals = Journal::with(['lines.account', 'user', 'reversal:id,reverses_id,number', 'reverses:id,number'])
                ->when($f['from'] ?? null, fn ($w, $d) => $w->where('journal_date', '>=', $d))
                ->when($f['to'] ?? null, fn ($w, $d) => $w->where('journal_date', '<=', $d))
                ->when($source === 'manual', fn ($w) => $w->whereNull('source_type'))
                ->when($source && $source !== 'manual', fn ($w) => $w->where('source_type', $source))
                ->when($status === 'reversed', fn ($w) => $w->whereHas('reversal'))
                ->when($status === 'posted', fn ($w) => $w->whereDoesntHave('reversal'))
                ->when($f['account'] ?? null, fn ($w, $a) => $w->whereHas('lines', fn ($l) => $l->where('account_id', $a)))
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'ilike', "%{$q}%")->orWhere('memo', 'ilike', "%{$q}%")->orWhere('reference', 'ilike', "%{$q}%")
                    ->orWhereHas('lines', fn ($l) => $l->where('description', 'ilike', "%{$q}%"))))
                ->orderByDesc('journal_date')->orderByDesc('number')->limit(1000)->get();
            $numbers = $this->documentNumbers($journals);
            $parties = self::partyNames($journals->flatMap->lines);
            foreach ($journals as $j) {
                $rows->push([
                    'id' => $j->id, 'draft' => false, 'date' => $j->journal_date, 'number' => $j->number, 'type' => $j->typeLabel(),
                    'source' => $this->sourceText($j, $numbers), 'sourceUrl' => $j->source_type ? $j->sourceUrl() : null,
                    'narration' => $j->narration(), 'amount' => (float) $j->total,
                    'status' => $j->reversal ? 'reversed' : 'posted', 'by' => $j->source_type && $j->source_type !== 'journal' ? 'System' : ($j->user?->name ?? 'System'),
                    'url' => route('ledger.journals.show', $j),
                    'lines' => $j->lines->map(fn ($l) => ['code' => $l->account?->code, 'name' => $l->account?->name.(isset($parties[$l->party_type.':'.$l->party_id]) ? ' · '.$parties[$l->party_type.':'.$l->party_id] : ''), 'detail' => $l->description, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit])->all(),
                ]);
            }
        }

        if (in_array($status, [null, '', 'draft'], true) && in_array($source, [null, '', 'manual'], true) && ! ($f['account'] ?? null)) {
            $drafts = Voucher::with(['user', 'counterAccount'])
                ->when($f['from'] ?? null, fn ($w, $d) => $w->where('voucher_date', '>=', $d))
                ->when($f['to'] ?? null, fn ($w, $d) => $w->where('voucher_date', '<=', $d))
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'ilike', "%{$q}%")->orWhere('reference', 'ilike', "%{$q}%")->orWhereRaw('lines::text ilike ?', ["%{$q}%"])))
                ->get();
            $accounts = \App\Domains\Ledger\Models\LedgerAccount::whereIn('id', $drafts->flatMap(fn ($d) => array_column($d->lines, 'account_id'))->filter()->unique())->get()->keyBy('id');
            foreach ($drafts as $d) {
                $lines = array_map(fn ($l) => ['code' => $accounts[$l['account_id'] ?? '']->code ?? '', 'name' => $accounts[$l['account_id'] ?? '']->name ?? '', 'detail' => $l['narration'] ?? null, 'debit' => (float) ($l['debit'] ?? 0), 'credit' => (float) ($l['credit'] ?? 0)], $d->lines);
                $rows->push([
                    'id' => $d->id, 'draft' => true, 'date' => $d->voucher_date, 'number' => $d->number, 'type' => $d->voucher_type,
                    'source' => $d->reference && $d->voucher_type === 'BPV' ? 'Cheque '.$d->reference : 'Manual', 'sourceUrl' => null,
                    'narration' => collect($d->lines)->pluck('narration')->filter()->first() ?? $d->reference, 'amount' => $d->amount(),
                    'status' => 'draft', 'by' => $d->user?->name ?? '', 'url' => route('ledger.vouchers.edit', $d), 'lines' => $lines,
                ]);
            }
        }

        if ($type) {
            $rows = $rows->filter(fn ($r) => $r['type'] === $type);
        }

        return $rows->sort(fn ($a, $b) => [$b['date']->toDateString(), $b['number']] <=> [$a['date']->toDateString(), $a['number']])->values();
    }

    /** @param array<string, array<string, string>> $numbers */
    private function sourceText(Journal $j, array $numbers): string
    {
        if (! $j->source_type) {
            return $j->voucher_type === 'BPV' && $j->reference ? 'Cheque '.$j->reference : 'Manual';
        }
        if ($j->source_type === 'journal') {
            return 'Reverses '.($j->reverses?->number ?? '');
        }
        $label = Journal::SOURCES[$j->source_type][0] ?? $j->source_type;
        $no = $numbers[$j->source_type][$j->source_id] ?? null;

        return trim($label.' '.$no);
    }

    /**
     * Document numbers for the journals' sources, loaded once per kind.
     *
     * @param  Collection<int, Journal>  $journals
     * @return array<string, array<string, string>>
     */
    private function documentNumbers(Collection $journals): array
    {
        $ids = fn (string $t) => $journals->where('source_type', $t)->pluck('source_id')->unique()->all();

        return [
            'invoice' => Invoice::whereIn('id', $ids('invoice'))->pluck('invoice_number', 'id')->all(),
            'supplier_bill' => SupplierBill::whereIn('id', $ids('supplier_bill'))->pluck('bill_number', 'id')->all(),
            'receipt' => Receipt::whereIn('id', $ids('receipt'))->pluck('receipt_number', 'id')->all(),
            'supplier_payment' => SupplierPayment::whereIn('id', $ids('supplier_payment'))->pluck('payment_number', 'id')->all(),
        ];
    }

    /**
     * Customer and supplier names for journal lines that name a party, keyed "customer:<id>".
     *
     * @param  Collection<int, \App\Domains\Ledger\Models\JournalLine>  $lines
     * @return array<string, string>
     */
    public static function partyNames(Collection $lines): array
    {
        $names = [];
        $ids = fn (string $t) => $lines->where('party_type', $t)->pluck('party_id')->filter()->unique()->all();
        foreach (\App\Models\Customer::whereIn('id', $ids('customer'))->get()->pluck('name', 'id') as $id => $n) {
            $names['customer:'.$id] = $n;
        }
        foreach (\App\Domains\Purchasing\Models\Supplier::whereIn('id', $ids('supplier'))->get()->pluck('name', 'id') as $id => $n) {
            $names['supplier:'.$id] = $n;
        }

        return $names;
    }

    /** @return array<string, string> type filter value => label, the voucher types then the document kinds */
    public static function types(): array
    {
        $types = array_combine(array_keys(Vouchers::TYPES), array_keys(Vouchers::TYPES));

        return $types + ['OB' => 'OB', 'Sales invoice' => 'Sales invoice', 'Credit note' => 'Credit note', 'Purchase invoice' => 'Purchase invoice', 'Supplier credit' => 'Supplier credit', 'Tax certificate' => 'Tax certificate'];
    }
}
