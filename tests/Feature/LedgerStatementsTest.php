<?php

namespace Tests\Feature;

use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Domains\Ledger\Services\Statements;
use App\Domains\Purchasing\Models\Supplier;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\Formats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerStatementsTest extends TestCase
{
    use RefreshDatabase;

    private ?Supplier $supplier = null;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
    }

    private function account(string $code): LedgerAccount
    {
        return LedgerAccount::where('code', $code)->firstOrFail();
    }

    /** Sales of 1000 and a bill of 400 electricity (cost of sales) and 100 stationery, all on the given date. */
    private function trade(string $date): void
    {
        $customer = Customer::firstOrCreate(['code' => 'ACME'], ['name' => 'Acme Pumps']);
        $invoices = app(InvoiceService::class);
        $invoices->issue($invoices->saveDraft(['customer_id' => $customer->id, 'invoice_date' => $date], [['description' => 'Pump casing', 'quantity' => 2, 'unit_price' => 500]]));
        $supplier = $this->supplier ??= Supplier::create(['name' => 'Wapda', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        $this->post(route('bills.store'), ['supplier_id' => $supplier->id, 'bill_date' => $date, 'supplier_bill_number' => 'E-'.$date, 'lines' => [
            ['description' => 'Electricity', 'quantity' => 1, 'unit_price' => 400, 'tax_percent' => 0, 'account_id' => $this->account('5310')->id],
            ['description' => 'Stationery', 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 0],
        ]])->assertSessionHasNoErrors();
        $this->post(route('bills.post', SupplierBill::where('supplier_bill_number', 'E-'.$date)->sole()))->assertSessionHasNoErrors();
    }

    public function test_profit_and_loss_shows_gross_profit_after_cost_of_sales_and_compares_with_the_period_before(): void
    {
        $this->trade('2026-09-10');
        $this->trade('2026-10-10');
        $this->trade('2026-10-20');

        $pl = app(Statements::class)->profitAndLoss('2026-10-01', '2026-10-31', ['2026-09-01', '2026-09-30']);

        $this->assertSame(['income' => [2000.0, 1000.0], 'gross' => [1200.0, 600.0], 'expenses' => [200.0, 100.0], 'net' => [1000.0, 500.0]], $pl['totals']);
        $this->assertSame('5000', $pl['costOfSales']['account']->code);
        $this->assertSame(['6000', '7000'], array_map(fn ($s) => $s['account']->code, $pl['expenses']));
        $n = fn (float $v) => Formats::number($v, 2);
        $this->get(route('ledger.profit-and-loss', ['from' => '2026-10-01', 'to' => '2026-10-31', 'compare' => 'previous']))->assertOk()
            ->assertSeeInOrder(['Sales: castings', $n(2000), $n(1000), 'Electricity', $n(800), $n(400), 'Gross profit', $n(1200), $n(600), 'Net profit', $n(1000), $n(500)]);
    }

    public function test_without_a_cost_of_sales_group_there_is_no_gross_profit_and_only_a_top_level_expense_can_be_chosen(): void
    {
        $this->trade('2026-10-10');

        $this->put(route('ledger.settings.update'), ['cost_of_sales' => $this->account('5310')->id])->assertSessionHasErrors('cost_of_sales');
        $this->put(route('ledger.settings.update'), ['cost_of_sales' => 'none'])->assertSessionHasNoErrors();

        $pl = app(Statements::class)->profitAndLoss('2026-10-01', '2026-10-31');
        $this->assertNull($pl['costOfSales']);
        $this->assertSame([[1000.0, null], [500.0, null]], [$pl['totals']['income'], $pl['totals']['expenses']]);
        $this->get(route('ledger.profit-and-loss', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertDontSee('Gross profit');
    }

    public function test_the_balance_sheet_balances_with_profit_not_yet_closed_under_equity(): void
    {
        $this->trade('2026-10-10');
        $line = fn (string $code, float $debit, float $credit) => ['account_id' => $this->account($code)->id, 'debit' => $debit, 'credit' => $credit];
        $this->post(route('ledger.journals.store'), ['journal_date' => '2026-10-01', 'memo' => 'Capital', 'lines' => [$line('1121', 5000, 0), $line('3100', 0, 5000)]])->assertSessionHasNoErrors();

        $bs = app(Statements::class)->balanceSheet('2026-10-31', '2026-09-30');

        $this->assertSame([
            'assets' => [6000.0, 0.0], 'liabilities' => [500.0, 0.0], 'profit' => [500.0, 0.0], 'equity' => [5500.0, 0.0], 'claims' => [6000.0, 0.0],
        ], $bs['totals']);
        $this->get(route('ledger.balance-sheet', ['as_at' => '2026-10-31']))->assertOk()
            ->assertSeeInOrder(['Trade receivables', 'Total assets', Formats::number(6000, 2), 'Trade payables', "Owner's capital", 'not yet closed', 'Total liabilities and equity', Formats::number(6000, 2)], false)
            ->assertDontSee('differ by');
    }

    public function test_the_general_ledger_carries_opening_balances_and_lists_only_accounts_that_moved_or_hold_a_balance(): void
    {
        $this->trade('2026-09-10');
        $customer = Customer::sole();
        $this->post(route('receipts.store'), ['customer_id' => $customer->id, 'received_on' => '2026-10-05', 'amount' => 300, 'method' => 'Bank transfer'])->assertSessionHasNoErrors();

        $gl = collect(app(Statements::class)->generalLedger('2026-10-01', '2026-10-31'))->keyBy(fn ($r) => $r['account']->code);

        $this->assertEqualsCanonicalizing(['1121', '1130', '2110', '4100', '5310', '6600'], $gl->keys()->map(fn ($k) => (string) $k)->all());
        $this->assertSame([1000.0, 0.0, 300.0, 700.0], [$gl['1130']['opening'], $gl['1130']['debit'], $gl['1130']['credit'], $gl['1130']['closing']]);
        $this->assertSame(['1121', '1130'], array_map(fn ($r) => $r['account']->code, app(Statements::class)->generalLedger('2026-10-01', '2026-10-31', 'ASSET')));
        $this->get(route('ledger.general-ledger', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()
            ->assertSeeInOrder(['1130 Trade receivables', 'Opening balance', Formats::number(1000, 2).' Dr', Formats::number(700, 2).' Dr']);
    }
}
