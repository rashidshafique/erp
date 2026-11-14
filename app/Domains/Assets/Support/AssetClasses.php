<?php

namespace App\Domains\Assets\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** The starting asset classes, one per asset account in the starting chart, with the accounts and method each one depreciates by. */
class AssetClasses
{
    /**
     * Name => [kind, cost account, accumulated depreciation account, expense account, method, life in years, rate % a year].
     *
     * @var array<string, array{0: string, 1: string, 2: string|null, 3: string|null, 4: string, 5: float|null, 6: float|null}>
     */
    public const STARTING = [
        'Freehold land' => ['BUILDING', '21-01-01', null, null, 'NONE', null, null],
        'Factory buildings' => ['BUILDING', '21-01-02', '21-02-01', '51-06-04', 'STRAIGHT_LINE', 30, null],
        'Furnaces and melting plant' => ['PLANT', '21-01-03', '21-02-02', '51-06-04', 'STRAIGHT_LINE', 15, null],
        'Moulding lines and sand plant' => ['PLANT', '21-01-04', '21-02-03', '51-06-04', 'STRAIGHT_LINE', 15, null],
        'Fettling and machining equipment' => ['PLANT', '21-01-05', '21-02-04', '51-06-04', 'STRAIGHT_LINE', 10, null],
        'Lab and inspection equipment' => ['PLANT', '21-01-06', '21-02-05', '51-06-04', 'STRAIGHT_LINE', 10, null],
        'Vehicles and forklifts' => ['VEHICLE', '21-01-07', '21-02-05', '61-02-04', 'REDUCING_BALANCE', null, 20],
        'Patterns and core boxes (own)' => ['PATTERN', '21-03-01', '21-03-02', '51-06-03', 'USAGE', null, null],
    ];

    /** Adds the starting classes not there yet, by name, for the accounts the chart has; a class whose cost account is missing is left out. */
    public static function install(): void
    {
        if (! DB::table('ledger_accounts')->exists()) {
            return;
        }
        $order = 0;
        foreach (self::STARTING as $name => [$kind, $cost, $depreciation, $expense, $method, $life, $rate]) {
            $order++;
            $costId = self::accountId($cost);
            if (! $costId || DB::table('asset_classes')->where('name', $name)->exists()) {
                continue;
            }
            DB::table('asset_classes')->insert([
                'id' => (string) Str::uuid(), 'name' => $name, 'kind' => $kind, 'cost_account_id' => $costId,
                'depreciation_account_id' => $depreciation ? self::accountId($depreciation) : null, 'expense_account_id' => $expense ? self::accountId($expense) : null,
                'method' => $method, 'life_years' => $life, 'rate_percent' => $rate, 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** The account with this code, or the starting chart's account of that name when the code went to something else. */
    private static function accountId(string $code): ?string
    {
        $name = \App\Domains\Ledger\Services\DefaultChart::ACCOUNTS[$code][0] ?? null;

        return DB::table('ledger_accounts')->where('code', $code)->when($name, fn ($q) => $q->where('name', $name))->value('id')
            ?? ($name ? DB::table('ledger_accounts')->where('name', $name)->value('id') : null)
            ?? DB::table('ledger_accounts')->where('code', $code)->value('id');
    }
}
