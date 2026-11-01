<?php

namespace Tests\Feature;

use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\TrialBalance;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Design 19d and 19e: the ledger statement by party and the trial balance by category, section and account. */
class LedgerReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        AppSetting::setValue('books.year_start_month', '7');
    }

    private function sell(Customer $customer, string $date, float $price): void
    {
        $invoices = app(InvoiceService::class);
        $invoices->issue($invoices->saveDraft(['customer_id' => $customer->id, 'invoice_date' => $date], [['description' => 'Pump casing', 'quantity' => 1, 'unit_price' => $price]]));
    }

    /** @return array<string, mixed> */
    private function row(array $tb, string $code): array
    {
        foreach ($tb['rows'] as $c) {
            foreach ($c['sections'] as $s) {
                foreach ($s['accounts'] as $a) {
                    if ($a['account']?->code === $code) {
                        return $a;
                    }
                }
            }
        }
        $this->fail("No row for {$code}.");
    }

    public function test_balance_sheet_accounts_open_on_everything_before_and_revenue_on_the_year_to_date(): void
    {
        $kovar = Customer::create(['code' => 'KOV', 'name' => 'Kovar Pumps']);
        $this->sell($kovar, '2026-03-10', 500);   // last financial year
        $this->sell($kovar, '2026-09-10', 1000);
        $this->sell($kovar, '2026-10-05', 300);

        $tb = app(TrialBalance::class)->build('2026-10-01', '2026-10-31');

        $this->assertSame([1500.0, 300.0, 0.0, 1800.0], array_values(array_intersect_key($this->row($tb, '22-02-01'), array_flip(['opening', 'debit', 'credit', 'closing']))));
        $this->assertSame([-1000.0, 0.0, 300.0, -1300.0], array_values(array_intersect_key($this->row($tb, '41-01-01'), array_flip(['opening', 'debit', 'credit', 'closing']))));
        $this->assertSame(['opening' => 0.0, 'debit' => 300.0, 'credit' => 300.0, 'dr' => 1800.0, 'cr' => 1800.0], $tb['totals']);

        $this->get(route('ledger.trial-balance', ['fy' => 2026, 'period' => 10]))->assertOk()
            ->assertSee('Balanced')->assertSee('Profit of earlier years, not yet closed')->assertSee('Opening 1 Oct')
            ->assertSeeInOrder(['Capital &amp; Reserves', 'Current Assets', 'Trade Debtors', 'Trade receivables', 'Revenue'], false);
        $this->get(route('ledger.trial-balance', ['fy' => 2026, 'period' => 10, 'level' => 'categories']))->assertOk()->assertSee('Current Assets')->assertDontSee('Trade Debtors');
        $this->get(route('ledger.trial-balance', ['fy' => 2026, 'period' => 10, 'export' => 1]))->assertOk()->assertDownload();
    }

    public function test_the_ledger_statement_filters_to_one_party_and_can_show_the_other_side_of_each_voucher(): void
    {
        $kovar = Customer::create(['code' => 'KOV', 'name' => 'Kovar Pumps', 'email' => 'accounts@kovar.test']);
        $orbis = Customer::create(['code' => 'ORB', 'name' => 'Orbis']);
        $this->sell($kovar, '2026-09-20', 4000);
        $this->sell($kovar, '2026-10-06', 1960);
        $this->sell($orbis, '2026-10-07', 7773);
        $receivables = LedgerAccount::where('code', '22-02-01')->sole();

        $this->get(route('ledger.accounts.show', [$receivables, 'from' => '2026-10-01', 'to' => '2026-10-31', 'party' => 'customer:'.$kovar->id]))->assertOk()
            ->assertSeeInOrder(['Opening balance', '4,000 Dr', 'Sales invoice', '1,960', '5,960 Dr', 'Closing balance'])
            ->assertDontSee('7,773')->assertSee('Email to customer')->assertSee('mailto:accounts%40kovar.test', false);

        $this->get(route('ledger.accounts.show', [$receivables, 'from' => '2026-10-01', 'to' => '2026-10-31', 'lines' => 1]))->assertOk()
            ->assertSee('7,773')->assertSee('41-01-01')->assertDontSee('Email to customer');
    }
}
