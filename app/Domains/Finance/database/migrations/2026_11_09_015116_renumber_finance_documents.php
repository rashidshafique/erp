<?php

use App\Support\DocumentNumbers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPES = ['invoice', 'credit_note', 'receipt', 'tax_certificate', 'supplier_bill', 'supplier_payment'];

    private const VOUCHERS = ['JV', 'CPV', 'BPV', 'CRV', 'BRV', 'OB'];

    /**
     * Finance documents and ledger vouchers move to a prefix and five digits from 10000 (I-10000, B-10000, JV-10000), in date
     * order. Journals posted under a document's number take its new one; memos and stored notices that name an old number are
     * rewritten. The old numbers stay in number_history.
     */
    public function up(): void
    {
        $pairs = [];
        foreach (self::TYPES as $type) {
            $pairs += DocumentNumbers::renumber($type);
        }
        if (Schema::hasTable('dispatches')) {
            foreach (DB::table('dispatches')->whereIn('invoice_number', array_keys($pairs))->get(['id', 'invoice_number']) as $d) {
                DB::table('dispatches')->where('id', $d->id)->update(['invoice_number' => $pairs[$d->invoice_number]]);
            }
        }
        if (Schema::hasTable('ledger_journals')) {
            $pairs += $this->ledger();
        }
        $this->rewrite($pairs);
    }

    public function down(): void
    {
        $flip = DB::table('number_history')->whereIn('table_name', ['invoices', 'receipts', 'withholding_certificates', 'supplier_bills', 'supplier_payments', 'ledger_journals', 'ledger_vouchers'])
            ->pluck('old_number', 'new_number')->all();
        foreach ([['invoices', 'invoice_number'], ['receipts', 'receipt_number'], ['withholding_certificates', 'certificate_number'], ['supplier_bills', 'bill_number'],
            ['supplier_payments', 'payment_number'], ['ledger_journals', 'number'], ['ledger_vouchers', 'number']] as [$table, $column]) {
            if (Schema::hasTable($table)) {
                DocumentNumbers::restore($table, $column);
            }
        }
        if (Schema::hasTable('dispatches')) {
            foreach (DB::table('dispatches')->whereIn('invoice_number', array_keys($flip))->get(['id', 'invoice_number']) as $d) {
                DB::table('dispatches')->where('id', $d->id)->update(['invoice_number' => $flip[$d->invoice_number]]);
            }
        }
        $this->rewrite($flip);
    }

    /**
     * Journals under a document's number take the new one; the rest run per voucher type over posted journals and drafts together.
     *
     * @return array<string, string> old => new
     */
    private function ledger(): array
    {
        $pairs = [];
        $history = [];
        $now = now();
        $docs = ['invoice' => ['invoices', 'invoice_number'], 'supplier_bill' => ['supplier_bills', 'bill_number']];
        $set = [];
        foreach ($docs as $source => [$table, $column]) {
            foreach (DB::table('ledger_journals as j')->join("{$table} as d", 'd.id', '=', 'j.source_id')->where('j.source_type', $source)->whereNull('j.voucher_type')
                ->get(['j.id', 'j.number', "d.{$column} as doc"]) as $j) {
                if ($j->doc && $j->doc !== $j->number) {
                    $set[$j->id] = [$j->number, $j->doc];
                }
            }
        }
        $vouchers = [];
        foreach (self::VOUCHERS as $type) {
            $rows = DB::table('ledger_journals')->where('voucher_type', $type)->get(['id', 'number', 'journal_date as d', 'created_at'])->each(fn ($r) => $r->t = 'ledger_journals')
                ->concat(Schema::hasTable('ledger_vouchers') ? DB::table('ledger_vouchers')->where('voucher_type', $type)->get(['id', 'number', 'voucher_date as d', 'created_at'])->each(fn ($r) => $r->t = 'ledger_vouchers') : [])
                ->sortBy(fn ($r) => [(string) $r->d, (string) $r->created_at, (string) $r->id])->values();
            foreach ($rows as $i => $r) {
                $vouchers[$r->t][$r->id] = [$r->number, DocumentNumbers::format($type, DocumentNumbers::START + $i)];
            }
        }
        $all = ['ledger_journals' => $set + ($vouchers['ledger_journals'] ?? []), 'ledger_vouchers' => $vouchers['ledger_vouchers'] ?? []];
        foreach ($all as $table => $map) {
            foreach ($map as $id => [$old]) {
                DB::table($table)->where('id', $id)->update(['number' => '~'.substr(md5((string) $id), 0, 14)]);
            }
            foreach ($map as $id => [$old, $new]) {
                DB::table($table)->where('id', $id)->update(['number' => $new]);
                $history[] = ['table_name' => $table, 'column_name' => 'number', 'record_id' => (string) $id, 'old_number' => $old, 'new_number' => $new, 'created_at' => $now];
                $pairs[$old] = $new;
            }
        }
        foreach (array_chunk($history, 500) as $chunk) {
            DB::table('number_history')->insert($chunk);
        }

        return $pairs;
    }

    /** Swaps old numbers for new ones in journal memos and stored notices. @param array<string, string> $pairs */
    private function rewrite(array $pairs): void
    {
        if ($pairs === []) {
            return;
        }
        if (Schema::hasTable('ledger_journals')) {
            foreach (DB::table('ledger_journals')->whereNotNull('memo')->get(['id', 'memo']) as $j) {
                $memo = strtr($j->memo, $pairs);
                if ($memo !== $j->memo) {
                    DB::table('ledger_journals')->where('id', $j->id)->update(['memo' => $memo]);
                }
            }
        }
        if (Schema::hasTable('notifications')) {
            foreach (DB::table('notifications')->get(['id', 'data']) as $n) {
                $data = strtr((string) $n->data, $pairs);
                if ($data !== $n->data) {
                    DB::table('notifications')->where('id', $n->id)->update(['data' => $data]);
                }
            }
        }
    }
};
