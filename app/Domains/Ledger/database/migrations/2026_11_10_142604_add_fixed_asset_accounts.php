<?php

use App\Domains\Ledger\Services\DefaultChart;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Accounts for fixed assets (design 28): sales tax input owed back by PRA, accumulated depreciation on lab equipment and vehicles,
     * and the gain or loss on selling or scrapping an asset. A chart that already uses the code for something else gets the account
     * at the next free code; PRA input tax is then pointed at it.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ledger_accounts') || ! DB::table('ledger_accounts')->exists()) {
            return;
        }
        foreach (['22-04-02', '21-02-05', '42-01-05'] as $code) {
            [$name, $flags] = DefaultChart::ACCOUNTS[$code];
            $section = DB::table('ledger_accounts')->where('code', substr($code, 0, 5))->first();
            if (! $section) {
                continue;
            }
            $found = DB::table('ledger_accounts')->where('parent_id', $section->id)->where('name', $name)->value('code');
            if ($found === null) {
                $taken = DB::table('ledger_accounts')->where('parent_id', $section->id)->pluck('code')->map(fn ($c) => (int) substr($c, 6))->all();
                $n = in_array((int) substr($code, 6), $taken, true) ? max($taken) + 1 : (int) substr($code, 6);
                $found = $section->code.'-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                DB::table('ledger_accounts')->insert([
                    'id' => (string) Str::uuid(), 'code' => $found, 'name' => $name, 'type' => DefaultChart::typeOf($code), 'parent_id' => $section->id,
                    'description' => $flags['description'] ?? null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($code === '22-04-02' && $found !== $code && ! isset(LedgerSettings::inputTaxMap()['PRA'])) {
                $map = LedgerSettings::inputTaxMap() + ['PRA' => DB::table('ledger_accounts')->where('code', $found)->value('id')];
                AppSetting::setValue(LedgerSettings::INPUT_TAX_ACCOUNTS, json_encode($map), 'Ledger account for the input tax of each authority');
            }
        }
    }

    public function down(): void
    {
        // The accounts stay: they may hold postings.
    }
};
