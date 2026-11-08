<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Document numbers: a short prefix and five digits from 10000 (I-10000, H-10012), one series per type, no year. Every generator
 * asks here, and the renumbering migrations read the same list. A prefix can be changed by setting `numbers.prefix.<type>`.
 * Numbers a record carried before renumbering are kept in `number_history`, so old papers and stamped parts still find it.
 */
class DocumentNumbers
{
    public const START = 10000;

    /**
     * type => [label, table, column, default prefix, date column the series is ordered by, extra where for a table shared by types]
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5?: array<string, string>}>
     */
    public const TYPES = [
        'invoice' => ['Invoice', 'invoices', 'invoice_number', 'I', 'invoice_date', ['kind' => 'INVOICE']],
        'credit_note' => ['Credit note', 'invoices', 'invoice_number', 'C', 'invoice_date', ['kind' => 'CREDIT_NOTE']],
        'receipt' => ['Receipt', 'receipts', 'receipt_number', 'R', 'received_on'],
        'tax_certificate' => ['Tax certificate', 'withholding_certificates', 'certificate_number', 'T', 'certificate_date'],
        'supplier_bill' => ['Supplier bill', 'supplier_bills', 'bill_number', 'B', 'bill_date'],
        'supplier_payment' => ['Supplier payment', 'supplier_payments', 'payment_number', 'Y', 'paid_on'],
    ];

    public static function prefix(string $type): string
    {
        $set = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) AppSetting::getValue('numbers.prefix.'.$type, '')));

        return $set !== '' ? substr($set, 0, 4) : self::TYPES[$type][3];
    }

    /** "I-10000" */
    public static function format(string $prefix, int $n): string
    {
        return $prefix.'-'.$n;
    }

    /** The next number of a type. Call inside a transaction: the lock holds until it commits. */
    public static function next(string $type): string
    {
        [, $table, $column] = self::TYPES[$type];

        return self::nextIn($table, $column, self::prefix($type));
    }

    /** What the next number would be, without holding it. */
    public static function preview(string $type): string
    {
        [, $table, $column] = self::TYPES[$type];

        return self::format(self::prefix($type), self::last($table, $column, self::prefix($type)) + 1);
    }

    /** The next number in a column for a prefix, for series kept outside TYPES (ledger vouchers). Call inside a transaction. */
    public static function nextIn(string $table, string $column, string $prefix): string
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['number-'.$table.'-'.$prefix]);

        return self::format($prefix, self::last($table, $column, $prefix) + 1);
    }

    /** The highest number used in the series, START - 1 when none. Older numbers in other formats are not counted. */
    public static function last(string $table, string $column, string $prefix): int
    {
        $max = DB::table($table)->whereRaw("{$column} ~ ?", ['^'.preg_quote($prefix, '/').'-[0-9]+$'])
            ->max(DB::raw("CAST(substring({$column} from ".(strlen($prefix) + 2).') AS bigint)'));

        return max(self::START - 1, (int) $max);
    }

    /**
     * Renumbers every numbered row of a type from START in date order (then created_at, id), keeping the old numbers in
     * number_history. Rows with no number (drafts) are left alone. Returns old => new.
     *
     * @return array<string, string>
     */
    public static function renumber(string $type): array
    {
        $def = self::TYPES[$type];
        [, $table, $column, , $date] = $def;
        if (! Schema::hasTable($table)) {
            return [];
        }

        return self::renumberRows($table, $column, self::prefix($type), $date, $def[5] ?? []);
    }

    /**
     * @param  array<string, string>  $where
     * @return array<string, string> old => new
     */
    public static function renumberRows(string $table, string $column, string $prefix, ?string $date, array $where = [], string $key = 'id'): array
    {
        $rows = DB::table($table)->whereNotNull($column)->where($where)
            ->when($date, fn ($q) => $q->orderBy($date))->when(Schema::hasColumn($table, 'created_at'), fn ($q) => $q->orderBy('created_at'))->orderBy($key)
            ->get([$key, $column]);
        $map = [];
        $n = self::START;
        foreach ($rows as $row) {
            $map[(string) $row->{$key}] = [(string) $row->{$column}, self::format($prefix, $n++)];
        }
        // Two passes so a new number never meets an old one under a unique index.
        foreach ($map as $id => [$old]) {
            DB::table($table)->where($key, $id)->update([$column => self::parked($id)]);
        }
        $now = now();
        $history = [];
        $pairs = [];
        foreach ($map as $id => [$old, $new]) {
            DB::table($table)->where($key, $id)->update([$column => $new]);
            $history[] = ['table_name' => $table, 'column_name' => $column, 'record_id' => $id, 'old_number' => $old, 'new_number' => $new, 'created_at' => $now];
            $pairs[$old] = $new;
        }
        foreach (array_chunk($history, 500) as $chunk) {
            DB::table('number_history')->insert($chunk);
        }

        return $pairs;
    }

    /** Puts back the numbers a table's column had before it was renumbered. */
    public static function restore(string $table, string $column, string $key = 'id'): void
    {
        $rows = DB::table('number_history')->where('table_name', $table)->where('column_name', $column)->get();
        foreach ($rows as $h) {
            DB::table($table)->where($key, $h->record_id)->update([$column => self::parked((string) $h->record_id)]);
        }
        foreach ($rows as $h) {
            DB::table($table)->where($key, $h->record_id)->update([$column => $h->old_number]);
        }
        DB::table('number_history')->where('table_name', $table)->where('column_name', $column)->delete();
    }

    /** A short placeholder unique to the row, held between the two passes. */
    private static function parked(string $id): string
    {
        return '~'.substr(md5($id), 0, 14);
    }

    /** The current number for one a record used to carry, or null. */
    public static function current(string $old): ?string
    {
        return DB::table('number_history')->where('old_number', $old)->orderByDesc('id')->value('new_number');
    }
}
