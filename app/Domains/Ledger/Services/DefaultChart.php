<?php

namespace App\Domains\Ledger\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The starting chart of accounts in the client's structure (design 19a): category, section and nominal account, coded 22-06-01.
 * Categories and sections are groups; postings go to nominal accounts. Accounts can be renamed, added or switched off afterwards,
 * and the ledger settings name which account each posting uses.
 */
class DefaultChart
{
    /** @var array<string, string> Category code => name, in the order the chart and reports show them. */
    public const CATEGORIES = [
        '31' => 'Capital & Reserves',
        '11' => 'Non-Current Liabilities',
        '12' => 'Current Liabilities',
        '21' => 'Non-Current Assets',
        '22' => 'Current Assets',
        '41' => 'Revenue',
        '42' => 'Other Income',
        '51' => 'Cost of Sales',
        '61' => 'Administrative Expenses',
        '62' => 'Selling & Distribution',
        '63' => 'Finance Cost',
        '64' => 'Taxation',
    ];

    /** @var array<string, array{0: string, 1: string}> Section code => [name, report line]. */
    public const SECTIONS = [
        '31-01' => ['Capital', 'Share capital'],
        '31-02' => ['General Reserves', 'Reserves'],
        '31-03' => ['Accumulated Reserves', 'Reserves'],
        '11-01' => ['Long-term Loans Payable', 'Long-term financing'],
        '11-02' => ['Lease Liabilities', 'Lease liabilities'],
        '11-03' => ['Staff Retirement Benefits', 'Staff retirement benefits'],
        '11-04' => ['Shareholder Loan Payable', 'Subordinated loan'],
        '12-01' => ['Trade Creditors', 'Trade and other payables'],
        '12-02' => ['Accruals and Payables', 'Trade and other payables'],
        '12-03' => ['Sales Tax Account', 'Taxation payable'],
        '12-04' => ['Withholding Income Tax (Deductions)', 'Taxation payable'],
        '12-05' => ['Provision of Taxation', 'Taxation payable'],
        '12-06' => ['Customer Advances', 'Contract liabilities'],
        '12-07' => ['Staff Payables', 'Accrued staff liabilities'],
        '12-08' => ['Short-term Borrowings', 'Short-term borrowings'],
        '21-01' => ['Property, Plant and Equipment', 'Property, plant and equipment'],
        '21-02' => ['Accumulated Depreciation', 'Property, plant and equipment'],
        '21-03' => ['Patterns and Tooling', 'Patterns and tooling'],
        '21-04' => ['Capital Work in Progress', 'Capital work in progress'],
        '21-05' => ['Long-term Deposits', 'Long-term deposits'],
        '22-01' => ['Cash and Bank', 'Cash and bank balances'],
        '22-02' => ['Trade Debtors', 'Trade debts'],
        '22-03' => ['Advance Income Tax', 'Advance income tax'],
        '22-04' => ['Sales Tax Refundable', 'Sales tax refundable'],
        '22-05' => ['Advances and Prepayments', 'Advances, deposits and prepayments'],
        '22-06' => ['Raw Materials', 'Stock in trade'],
        '22-07' => ['Sand and Binders', 'Stores, spares and loose tools'],
        '22-08' => ['Work in Progress', 'Stock in trade'],
        '22-09' => ['Finished Castings', 'Stock in trade'],
        '22-10' => ['Stores and Spares', 'Stores, spares and loose tools'],
        '41-01' => ['Casting Sales', 'Revenue'],
        '41-02' => ['Services', 'Revenue'],
        '41-03' => ['Sales Deductions', 'Revenue deductions'],
        '42-01' => ['Other Income', 'Other income'],
        '51-01' => ['Materials Consumed', 'Cost of sales'],
        '51-02' => ['Melting Energy', 'Cost of sales'],
        '51-03' => ['Direct Labour', 'Cost of sales'],
        '51-04' => ['Subcontract', 'Cost of sales'],
        '51-05' => ['Quality Losses', 'Cost of sales'],
        '51-06' => ['Factory Overheads', 'Cost of sales'],
        '51-07' => ['Stock Movement', 'Cost of sales'],
        '61-01' => ['Staff Costs', 'Administrative expenses'],
        '61-02' => ['Office', 'Administrative expenses'],
        '61-03' => ['Professional and Compliance', 'Administrative expenses'],
        '61-04' => ['Other Expenses', 'Administrative expenses'],
        '62-01' => ['Distribution', 'Distribution costs'],
        '63-01' => ['Finance Cost', 'Finance cost'],
        '64-01' => ['Taxation', 'Taxation'],
    ];

    /** @var array<string, array{0: string, 1: array{control?: string, charge?: bool, inactive?: bool, description?: string}}> Account code => [name, flags]. */
    public const ACCOUNTS = [
        '31-01-01' => ['Share capital', []],
        '31-02-01' => ['General reserves', []],
        '31-03-01' => ['Retained earnings', []],
        '31-03-02' => ['Opening balance equity', ['control' => 'Opening balances']],
        '11-01-01' => ['Long-term loan: plant (SBP LTFF)', []],
        '11-02-01' => ['Lease liability: vehicles', []],
        '11-03-01' => ['Gratuity provision', []],
        '11-04-01' => ['Directors\' subordinated loan', []],
        '12-01-01' => ['Trade payables', ['control' => 'Purchase invoices, debit notes, payments']],
        '12-01-02' => ['Goods received not invoiced', ['control' => 'Goods receipts and purchase invoices']],
        '12-02-01' => ['Accrued power and gas', []],
        '12-02-02' => ['Accrued expenses: other', []],
        '12-03-01' => ['Sales tax output (FBR)', []],
        '12-03-02' => ['Sales tax on services (SRB)', []],
        '12-04-01' => ['Withholding tax payable: suppliers', []],
        '12-04-02' => ['Withholding tax payable: salaries', []],
        '12-05-01' => ['Income tax payable', []],
        '12-06-01' => ['Customer advances: tooling', []],
        '12-06-02' => ['Customer advances: orders', []],
        '12-07-01' => ['Salaries and wages payable', []],
        '12-07-02' => ['EOBI payable', []],
        '12-07-03' => ['PESSI payable', []],
        '12-07-04' => ['Workers\' Profit Participation Fund', []],
        '12-07-05' => ['Workers\' Welfare Fund', []],
        '12-08-01' => ['Running finance: HBL', []],
        '21-01-01' => ['Freehold land', []],
        '21-01-02' => ['Factory buildings', []],
        '21-01-03' => ['Furnaces and melting plant', []],
        '21-01-04' => ['Moulding lines and sand plant', []],
        '21-01-05' => ['Fettling and machining equipment', []],
        '21-01-06' => ['Lab and inspection equipment', []],
        '21-01-07' => ['Vehicles and forklifts', []],
        '21-02-01' => ['Acc. depreciation: buildings', []],
        '21-02-02' => ['Acc. depreciation: furnaces', []],
        '21-02-03' => ['Acc. depreciation: moulding', []],
        '21-02-04' => ['Acc. depreciation: fettling', []],
        '21-03-01' => ['Patterns and core boxes (own)', []],
        '21-03-02' => ['Acc. depreciation: patterns', []],
        '21-04-01' => ['Capital work in progress', []],
        '21-05-01' => ['Security deposits: WAPDA, SNGPL', []],
        '22-01-01' => ['Cash in hand', []],
        '22-01-02' => ['Petty cash: factory', []],
        '22-01-03' => ['Meezan Bank current a/c', []],
        '22-01-04' => ['Bank Alfalah current a/c', []],
        '22-01-05' => ['HBL collection a/c', ['inactive' => true, 'description' => 'Closed 31 Mar 2026']],
        '22-02-01' => ['Trade receivables', ['control' => 'Sales invoices, credit notes, receipts']],
        '22-02-02' => ['Allowance for doubtful debts', []],
        '22-02-03' => ['Tooling recoverable from customers', []],
        '22-03-01' => ['Advance tax withheld by customers', []],
        '22-03-02' => ['Advance tax: imports', []],
        '22-04-01' => ['Sales tax input (FBR)', []],
        '22-05-01' => ['Advances to suppliers', []],
        '22-05-02' => ['Advances to employees', []],
        '22-05-03' => ['Prepayments', []],
        '22-06-01' => ['Pig iron', []],
        '22-06-02' => ['Steel scrap', []],
        '22-06-03' => ['Ferroalloys and inoculants', []],
        '22-06-04' => ['Returns and runners', []],
        '22-06-05' => ['Aluminium ingot A356', []],
        '22-06-06' => ['Copper and tin', []],
        '22-06-07' => ['Cast iron borings', []],
        '22-07-01' => ['Silica sand', []],
        '22-07-02' => ['Bentonite and coal dust', []],
        '22-07-03' => ['No-bake resin and catalyst', []],
        '22-07-04' => ['Shell sand, pre-coated', []],
        '22-07-05' => ['Mould and core coatings', []],
        '22-08-01' => ['WIP: moulded, not poured', []],
        '22-08-02' => ['WIP: poured, in fettling', []],
        '22-08-03' => ['WIP: at machining or subcontract', []],
        '22-08-04' => ['WIP: pattern development', []],
        '22-09-01' => ['Finished castings', []],
        '22-09-02' => ['Dispatched, not invoiced', []],
        '22-09-03' => ['Stock with subcontractors', []],
        '22-10-01' => ['Refractories and crucibles', []],
        '22-10-02' => ['Filters, sleeves and chills', []],
        '22-10-03' => ['Grinding wheels and shot', []],
        '22-10-04' => ['Maintenance spares', []],
        '22-10-05' => ['Fuel and lubricants', []],
        '41-01-01' => ['Casting sales', []],
        '41-01-02' => ['Export casting sales (zero-rated)', []],
        '41-02-01' => ['Tooling and pattern income', []],
        '41-02-02' => ['Machining income', []],
        '41-02-03' => ['Heat treatment and testing income', []],
        '41-03-01' => ['Sales returns', []],
        '41-03-02' => ['Trade discount allowed', ['charge' => true, 'description' => 'Deduction box on sales invoices']],
        '42-01-01' => ['Scrap and swarf sales', []],
        '42-01-02' => ['Other income', []],
        '42-01-03' => ['Freight recharged', ['charge' => true, 'description' => 'Freight charged to customers on the invoice']],
        '42-01-04' => ['Packing and loading recharged', ['charge' => true]],
        '51-01-01' => ['Metal consumed', []],
        '51-01-02' => ['Moulding and core materials consumed', []],
        '51-01-03' => ['Refractories consumed', []],
        '51-02-01' => ['Melting power and gas', []],
        '51-02-02' => ['Coke and cupola fuel', []],
        '51-03-01' => ['Direct labour', []],
        '51-03-02' => ['Contract labour', []],
        '51-04-01' => ['Subcontract machining', []],
        '51-04-02' => ['Heat treatment and testing, outsourced', []],
        '51-05-01' => ['Rejections and scrap loss', []],
        '51-05-02' => ['Customer returns and rework', []],
        '51-06-01' => ['Factory overheads', []],
        '51-06-02' => ['Repairs and maintenance: plant', []],
        '51-06-03' => ['Pattern depreciation', []],
        '51-06-04' => ['Plant depreciation', []],
        '51-07-01' => ['Change in WIP and finished stock', []],
        '61-01-01' => ['Admin salaries', []],
        '61-01-02' => ['Directors\' remuneration', []],
        '61-02-01' => ['Office rent and utilities', []],
        '61-02-02' => ['Communication and IT', []],
        '61-02-03' => ['Travel and conveyance', []],
        '61-02-04' => ['Depreciation: office and vehicles', []],
        '61-03-01' => ['Legal, professional and audit', []],
        '61-03-02' => ['ISO, PPAP and certification', []],
        '61-03-03' => ['Insurance', []],
        '61-04-01' => ['Bad debts', []],
        '61-04-02' => ['Exchange loss', []],
        '62-01-01' => ['Freight out', []],
        '62-01-02' => ['Packing materials', []],
        '63-01-01' => ['Bank charges and markup', []],
        '63-01-02' => ['Markup on long-term loan', []],
        '64-01-01' => ['Income tax expense', []],
    ];


    /** @var array<string, string> First digit of a category code => account type. */
    public const CLASSES = ['1' => 'LIABILITY', '2' => 'ASSET', '3' => 'EQUITY', '4' => 'INCOME', '5' => 'EXPENSE', '6' => 'EXPENSE'];

    /** The account type of a category, section or account code, from its first digit. */
    public static function typeOf(string $code): string
    {
        return self::CLASSES[substr($code, 0, 1)] ?? 'EXPENSE';
    }

    /**
     * The report lines a section in this category can sit on: those the starting chart uses in it.
     *
     * @return list<string>
     */
    public static function reportLines(string $category): array
    {
        $lines = [];
        foreach (self::SECTIONS as $code => [, $line]) {
            if (str_starts_with($code, $category.'-') && ! in_array($line, $lines, true)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /** Adds the categories, sections and accounts that are not there yet, by code; existing ones are left as they are. */
    public static function install(): void
    {
        $now = now();
        $ids = DB::table('ledger_accounts')->pluck('id', 'code')->all();
        $add = function (string $code, string $name, ?string $parent, array $extra) use (&$ids, $now): void {
            if (isset($ids[$code])) {
                return;
            }
            $ids[$code] = (string) Str::uuid();
            DB::table('ledger_accounts')->insert(array_merge([
                'id' => $ids[$code], 'code' => $code, 'name' => $name, 'type' => self::typeOf($code), 'parent_id' => $parent ? $ids[$parent] : null,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ], $extra));
        };
        $order = 0;
        foreach (self::CATEGORIES as $code => $name) {
            $add((string) $code, $name, null, ['sort_order' => ++$order]);
        }
        foreach (self::SECTIONS as $code => [$name, $line]) {
            $add($code, $name, substr($code, 0, 2), ['report_line' => $line, 'sort_order' => (int) substr($code, 3, 2)]);
        }
        foreach (self::ACCOUNTS as $code => [$name, $flags]) {
            $add($code, $name, substr($code, 0, 5), [
                'control' => $flags['control'] ?? null, 'is_charge' => $flags['charge'] ?? false,
                'is_active' => ! ($flags['inactive'] ?? false), 'description' => $flags['description'] ?? null,
            ]);
        }
    }
}
