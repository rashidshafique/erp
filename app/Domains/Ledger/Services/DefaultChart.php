<?php

namespace App\Domains\Ledger\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The starting chart of accounts for a foundry, in the usual local layout: five classes, groups under them and accounts to post to.
 * Every account can be renamed, given sub-accounts or switched off afterwards; the ledger settings name which account each posting uses.
 */
class DefaultChart
{
    /**
     * Code => [name, parent code]. The class of a top-level code comes from its first digit.
     *
     * @var array<string, array{0: string, 1: ?string}>
     */
    public const ACCOUNTS = [
        '1000' => ['Assets', null],
        '1100' => ['Current assets', '1000'],
        '1110' => ['Cash in hand', '1100'],
        '1120' => ['Bank accounts', '1100'],
        '1121' => ['Main bank account', '1120'],
        '1130' => ['Trade receivables', '1100'],
        '1140' => ['Advances to suppliers', '1100'],
        '1150' => ['Inventory', '1100'],
        '1151' => ['Raw materials', '1150'],
        '1152' => ['Work in progress', '1150'],
        '1153' => ['Finished castings', '1150'],
        '1154' => ['Stores and spares', '1150'],
        '1160' => ['Tax receivable', '1100'],
        '1161' => ['Sales tax input', '1160'],
        '1162' => ['Advance income tax', '1160'],
        '1163' => ['Tax withheld by customers', '1160'],
        '1170' => ['Prepayments and deposits', '1100'],
        '1500' => ['Non-current assets', '1000'],
        '1510' => ['Land and buildings', '1500'],
        '1520' => ['Plant and machinery', '1500'],
        '1530' => ['Furnaces', '1500'],
        '1540' => ['Patterns and tooling', '1500'],
        '1550' => ['Vehicles', '1500'],
        '1560' => ['Office equipment', '1500'],
        '1590' => ['Accumulated depreciation', '1500'],
        '2000' => ['Liabilities', null],
        '2100' => ['Current liabilities', '2000'],
        '2110' => ['Trade payables', '2100'],
        '2120' => ['Accrued expenses', '2100'],
        '2130' => ['Customer advances', '2100'],
        '2140' => ['Sales tax payable (federal)', '2100'],
        '2141' => ['Provincial sales tax on services', '2100'],
        '2150' => ['Withholding tax payable', '2100'],
        '2160' => ['Salaries and wages payable', '2100'],
        '2170' => ['EOBI and social security payable', '2100'],
        '2500' => ['Long-term loans', '2000'],
        '2510' => ['Bank loans', '2500'],
        '3000' => ['Equity', null],
        '3100' => ["Owner's capital", '3000'],
        '3200' => ['Retained earnings', '3000'],
        '3300' => ['Drawings', '3000'],
        '3900' => ['Opening balance equity', '3000'],
        '4000' => ['Income', null],
        '4100' => ['Sales: castings', '4000'],
        '4110' => ['Sales: trading goods', '4000'],
        '4120' => ['Pattern and tooling charges', '4000'],
        '4200' => ['Scrap sales', '4000'],
        '4300' => ['Other income', '4000'],
        '4900' => ['Sales returns and discounts', '4000'],
        '5000' => ['Cost of sales', null],
        '5100' => ['Raw material purchases', '5000'],
        '5200' => ['Direct wages', '5000'],
        '5300' => ['Power and fuel', '5000'],
        '5310' => ['Electricity', '5300'],
        '5320' => ['Gas', '5300'],
        '5330' => ['Coke and fuel', '5300'],
        '5400' => ['Consumables: sand, binders, inoculants', '5000'],
        '5500' => ['Outside processing and fettling', '5000'],
        '5600' => ['Freight in', '5000'],
        '6000' => ['Operating expenses', null],
        '6100' => ['Salaries', '6000'],
        '6200' => ['Rent', '6000'],
        '6300' => ['Utilities', '6000'],
        '6400' => ['Repairs and maintenance', '6000'],
        '6500' => ['Freight out', '6000'],
        '6600' => ['Office and admin', '6000'],
        '6700' => ['Professional fees', '6000'],
        '6800' => ['Bank charges', '6000'],
        '6900' => ['Depreciation', '6000'],
        '7000' => ['Finance costs', null],
        '7100' => ['Markup and interest on loans', '7000'],
    ];

    /** @var array<string, string> First digit of a top-level code => account type. */
    public const CLASSES = ['1' => 'ASSET', '2' => 'LIABILITY', '3' => 'EQUITY', '4' => 'INCOME', '5' => 'EXPENSE', '6' => 'EXPENSE', '7' => 'EXPENSE'];

    /** Adds the accounts that are not there yet, by code; existing ones are left as they are. */
    public static function install(): void
    {
        $now = now();
        $ids = DB::table('ledger_accounts')->pluck('id', 'code')->all();
        foreach (self::ACCOUNTS as $code => [$name, $parent]) {
            $code = (string) $code;
            if (isset($ids[$code])) {
                continue;
            }
            $ids[$code] = (string) Str::uuid();
            DB::table('ledger_accounts')->insert([
                'id' => $ids[$code], 'code' => $code, 'name' => $name, 'type' => self::CLASSES[substr($code, 0, 1)],
                'parent_id' => $parent ? $ids[$parent] : null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
