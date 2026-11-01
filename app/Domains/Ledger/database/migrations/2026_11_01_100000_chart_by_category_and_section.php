<?php

use App\Domains\Ledger\Services\DefaultChart;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The chart moves to the client's structure (design 19a): category, section and nominal account, coded 22-06-01. Postings, bill
 * lines, bank accounts and the ledger settings on an account of the old starting chart move to its new account, then the old
 * accounts go. An account added by hand that has been used is kept, moved into a section of its type with the next free code.
 */
return new class extends Migration
{
    /** @var array<string, string> Old starting-chart code => new account code. */
    private const MAP = [
        '1110' => '22-01-01', '1121' => '22-01-03', '1130' => '22-02-01', '1140' => '22-05-01', '1151' => '22-06-01', '1152' => '22-08-02',
        '1153' => '22-09-01', '1154' => '22-10-04', '1161' => '22-04-01', '1162' => '22-03-02', '1163' => '22-03-01', '1170' => '22-05-03',
        '1510' => '21-01-02', '1520' => '21-01-04', '1530' => '21-01-03', '1540' => '21-03-01', '1550' => '21-01-07', '1560' => '21-01-06',
        '1590' => '21-02-01', '2110' => '12-01-01', '2120' => '12-02-02', '2130' => '12-06-02', '2140' => '12-03-01', '2141' => '12-03-02',
        '2150' => '12-04-01', '2160' => '12-07-01', '2170' => '12-07-02', '2510' => '11-01-01', '3100' => '31-01-01', '3200' => '31-03-01',
        '3300' => '31-01-01', '3900' => '31-03-02', '4100' => '41-01-01', '4110' => '41-01-01', '4120' => '41-02-01', '4200' => '42-01-01',
        '4300' => '42-01-02', '4900' => '41-03-01', '5100' => '51-01-01', '5200' => '51-03-01', '5310' => '51-02-01', '5320' => '51-02-01',
        '5330' => '51-02-02', '5400' => '51-01-02', '5500' => '51-04-01', '5600' => '51-06-01', '6100' => '61-01-01', '6200' => '61-02-01',
        '6300' => '61-02-01', '6400' => '51-06-02', '6500' => '62-01-01', '6600' => '61-02-01', '6700' => '61-03-01', '6800' => '63-01-01',
        '6900' => '51-06-04', '7100' => '63-01-02',
        // groups of the old chart, for settings that named one (cost of sales)
        '1000' => '22', '1100' => '22', '1120' => '22-01', '1150' => '22-06', '1160' => '22-03', '1500' => '21', '2000' => '12', '2100' => '12',
        '2500' => '11', '3000' => '31', '4000' => '41', '5000' => '51', '5300' => '51-02', '6000' => '61', '7000' => '63',
    ];

    /** @var array<string, string> Type => section a used hand-made account moves into. */
    private const FALLBACK = ['ASSET' => '22-05', 'LIABILITY' => '12-02', 'EQUITY' => '31-03', 'INCOME' => '42-01', 'EXPENSE' => '61-04'];

    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->string('report_line', 80)->nullable()->after('description');
            $table->unsignedInteger('sort_order')->default(0)->after('report_line');
            $table->boolean('is_charge')->default(false)->after('sort_order');
            $table->string('control', 120)->nullable()->after('is_charge');
        });

        $old = DB::table('ledger_accounts')->get()->keyBy('code');
        DefaultChart::install();
        $ids = DB::table('ledger_accounts')->pluck('id', 'code')->all();
        $new = array_flip(array_merge(array_keys(DefaultChart::CATEGORIES), array_keys(DefaultChart::SECTIONS), array_keys(DefaultChart::ACCOUNTS)));
        $moves = [];
        foreach ($old as $code => $account) {
            if (isset($new[(string) $code])) {
                continue;
            }
            if (isset(self::MAP[$code], $ids[self::MAP[$code]])) {
                $moves[$account->id] = $ids[self::MAP[$code]];
            }
        }

        foreach ($moves as $from => $to) {
            DB::table('ledger_journal_lines')->where('account_id', $from)->update(['account_id' => $to]);
            if (Schema::hasColumn('supplier_bill_lines', 'account_id')) {
                DB::table('supplier_bill_lines')->where('account_id', $from)->update(['account_id' => $to]);
            }
            if (Schema::hasTable('ledger_bank_accounts') && ! DB::table('ledger_bank_accounts')->where('account_id', $to)->exists()) {
                DB::table('ledger_bank_accounts')->where('account_id', $from)->update(['account_id' => $to]);
            }
        }
        $this->moveSettings($moves);

        // what is left of the old chart: unused accounts go, used hand-made ones move into a section of their type
        $leftover = DB::table('ledger_accounts')->whereNotIn('code', array_keys($new))->get();
        $used = fn (string $id) => DB::table('ledger_journal_lines')->where('account_id', $id)->exists()
            || (Schema::hasTable('ledger_bank_accounts') && DB::table('ledger_bank_accounts')->where('account_id', $id)->exists())
            || (Schema::hasColumn('supplier_bill_lines', 'account_id') && DB::table('supplier_bill_lines')->where('account_id', $id)->exists());
        $keep = $leftover->filter(fn ($a) => $used($a->id));
        foreach ($keep as $a) {
            $section = self::FALLBACK[$a->type] ?? '61-04';
            $last = DB::table('ledger_accounts')->where('code', 'like', $section.'-%')->max('code');
            $code = $section.'-'.str_pad((string) ((int) ($last ? substr($last, 6) : 0) + 1), 2, '0', STR_PAD_LEFT);
            DB::table('ledger_accounts')->where('id', $a->id)->update(['code' => $code, 'parent_id' => $ids[$section], 'type' => DefaultChart::typeOf($section)]);
        }
        $gone = $leftover->reject(fn ($a) => $keep->contains('id', $a->id))->pluck('id');
        DB::table('ledger_accounts')->whereIn('id', $gone)->update(['parent_id' => null]);
        DB::table('ledger_accounts')->whereIn('id', $gone)->delete();
    }

    /** @param array<string, string> $moves old account id => new account id */
    private function moveSettings(array $moves): void
    {
        if (! Schema::hasTable('app_settings') || $moves === []) {
            return;
        }
        foreach (DB::table('app_settings')->where('key', 'like', 'ledger.%')->get() as $row) {
            $value = (string) $row->value;
            if (isset($moves[$value])) {
                $value = $moves[$value];
            } elseif (Str::startsWith(ltrim($value), '{')) {
                $map = json_decode($value, true) ?: [];
                $value = json_encode(array_map(fn ($id) => is_string($id) ? ($moves[$id] ?? $id) : $id, $map));
            }
            if ($value !== (string) $row->value) {
                DB::table('app_settings')->where('key', $row->key)->update(['value' => $value]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropColumn(['report_line', 'sort_order', 'is_charge', 'control']);
        });
    }
};
