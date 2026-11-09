<?php

use App\Domains\Ledger\Services\DefaultChart;
use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Holding accounts for post-dated cheques settled at once: cheques in hand (an asset, beside trade debtors) and cheques issued
     * (a liability, beside trade creditors). Kept out of the cash and bank section so they are never offered as a bank. A chart
     * that already uses the code for something else gets the account at the next free code, and the ledger role points at it.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ledger_accounts') || ! DB::table('ledger_accounts')->exists()) {
            return;
        }
        foreach (['cheques_received' => '22-02-04', 'cheques_issued' => '12-01-03'] as $role => $code) {
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
                    'control' => $flags['control'] ?? null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($found !== $code) {
                AppSetting::setValue('ledger.account.'.$role, DB::table('ledger_accounts')->where('code', $found)->value('id'), 'Ledger account for: '.$name);
            }
        }
    }

    public function down(): void
    {
        // The accounts stay: they may hold postings.
    }
};
