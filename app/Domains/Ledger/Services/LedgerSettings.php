<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Ledger\Models\LedgerAccount;
use App\Models\AppSetting;
use InvalidArgumentException;

/** Which account each automatic posting uses, how payment methods map to cash or bank, and the date the books are closed to. */
class LedgerSettings
{
    /** @var array<string, array{0: string, 1: string}> Role => [label, default account code]. */
    public const ROLES = [
        'receivables' => ['Customers owe us (receivables)', '1130'],
        'payables' => ['We owe suppliers (payables)', '2110'],
        'sales' => ['Sales', '4100'],
        'sales_returns' => ['Credit notes to customers', '4900'],
        'purchases' => ['Bill lines from a purchase order', '5100'],
        'expense' => ['Other bill lines', '6600'],
        'input_tax' => ['Tax on supplier bills', '1161'],
        'output_tax' => ['Tax on invoices', '2140'],
        'tax_withheld' => ['Tax customers withheld (settled by their certificates)', '1163'],
        'bank' => ['Money in and out (except cash)', '1121'],
        'cash' => ['Cash', '1110'],
        'opening_equity' => ['Opening balances', '3900'],
    ];

    public const CLOSED_TO = 'ledger.closed_to';

    public const METHODS = 'ledger.method_accounts';

    public const TAX_ACCOUNTS = 'ledger.tax_accounts';

    public const COST_OF_SALES = 'ledger.cost_of_sales';

    /** The account for a role: the one set under Settings, else the starting chart's. */
    public static function account(string $role): LedgerAccount
    {
        $id = AppSetting::getValue('ledger.account.'.$role);
        $account = ($id ? LedgerAccount::find($id) : null) ?? LedgerAccount::where('code', self::ROLES[$role][1])->first();
        if (! $account) {
            throw new InvalidArgumentException('Choose the account for "'.self::ROLES[$role][0].'" under Settings > Ledger accounts.');
        }

        return $account;
    }

    /** Where money paid by this method goes: a method set under Settings, cash for "Cash", the bank for anything else. */
    public static function methodAccount(?string $method): LedgerAccount
    {
        $map = self::methodMap();
        $key = mb_strtolower(trim((string) $method));
        if (isset($map[$key]) && ($account = LedgerAccount::find($map[$key]))) {
            return $account;
        }

        return self::account($key === 'cash' ? 'cash' : 'bank');
    }

    /** @return array<string, string> Lower-cased method => account id. */
    public static function methodMap(): array
    {
        return json_decode((string) AppSetting::getValue(self::METHODS, '{}'), true) ?: [];
    }

    /** The account a tax of this authority is owed on: one set under Settings, else the tax-on-invoices account. */
    public static function taxAccount(?string $authority): LedgerAccount
    {
        $id = $authority ? (self::taxMap()[$authority] ?? null) : null;

        return ($id ? LedgerAccount::find($id) : null) ?? self::account('output_tax');
    }

    /** @return array<string, string> Authority code => account id. */
    public static function taxMap(): array
    {
        return json_decode((string) AppSetting::getValue(self::TAX_ACCOUNTS, '{}'), true) ?: [];
    }

    /** The top-level expense account the profit and loss shows before gross profit: the one set under Settings, else 5000 if it is one; none when set to "none". */
    public static function costOfSales(): ?LedgerAccount
    {
        $id = AppSetting::getValue(self::COST_OF_SALES);
        if ($id === 'none') {
            return null;
        }
        $account = $id ? LedgerAccount::find($id) : LedgerAccount::where('code', '5000')->first();

        return $account && $account->type === 'EXPENSE' && $account->parent_id === null ? $account : null;
    }

    /** Nothing can be posted on or before this date. */
    public static function closedTo(): ?string
    {
        return AppSetting::getValue(self::CLOSED_TO) ?: null;
    }
}
