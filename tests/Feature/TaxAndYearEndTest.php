<?php

namespace Tests\Feature;

use App\Domains\Ledger\Services\LedgerSettings;
use App\Domains\Ledger\Services\Ledger;
use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use App\Support\FinancialYear;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxAndYearEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_year_runs_twelve_months_from_the_chosen_month(): void
    {
        AppSetting::setValue(FinancialYear::START_MONTH, '7');

        $in = FinancialYear::containing(Carbon::parse('2026-10-28'));
        $this->assertSame(['2026-07-01', '2027-06-30'], [$in['start']->toDateString(), $in['end']->toDateString()]);
        $early = FinancialYear::containing(Carbon::parse('2026-03-05'));
        $this->assertSame(['2025-07-01', '2026-06-30'], [$early['start']->toDateString(), $early['end']->toDateString()]);
        $this->assertSame('2025-06-30', FinancialYear::previous(Carbon::parse('2025-12-01'))['end']->toDateString());
        $this->assertSame('Jul 2025 – Jun 2026', FinancialYear::label($early));

        AppSetting::setValue(FinancialYear::START_MONTH, '1');
        $this->assertSame('2026', FinancialYear::label(FinancialYear::containing(Carbon::parse('2026-10-28'))));
    }

    public function test_an_install_with_no_choice_starts_in_july_for_pakistan_and_january_elsewhere(): void
    {
        $this->assertSame(7, FinancialYear::startMonth());
        AppSetting::setValue(\App\Support\TaxJurisdiction::SETTING, 'XX');
        $this->assertSame(1, FinancialYear::startMonth());
    }

    public function test_the_page_holds_the_year_the_closing_the_rates_and_the_taxation_and_invoicing_no_longer_carries_the_rates(): void
    {
        $this->post(route('finance.tax-rates.store'), ['name' => 'Concessionary', 'rate_percent' => 18, 'is_default' => 1])->assertSessionHasNoErrors();

        $this->get(route('finance.tax-year'))->assertOk()->assertSee('Financial year')->assertSee('Closing the books')->assertSee('Tax rates')->assertSee('Concessionary')->assertSee('Country and registration');
        $this->get(route('finance.settings'))->assertOk()->assertDontSee('<h2>Tax rates</h2>', false)->assertDontSee('Concessionary');
        $this->get(route('tax.settings'))->assertRedirect(route('finance.tax-year'));
    }

    public function test_the_start_month_is_saved_and_the_reports_open_on_the_financial_year(): void
    {
        $this->put(route('finance.tax-year.update'), ['year_start_month' => 13])->assertSessionHasErrors('year_start_month');
        $this->put(route('finance.tax-year.update'), ['year_start_month' => 4])->assertSessionHasNoErrors();
        $this->assertSame(4, FinancialYear::startMonth());

        Carbon::setTestNow('2026-10-28');
        $this->get(route('ledger.profit-and-loss'))->assertOk()->assertSee('2026-04-01');
    }

    public function test_closing_sets_the_date_by_hand_or_to_the_end_of_the_last_year_and_blocks_posting_up_to_it(): void
    {
        AppSetting::setValue(FinancialYear::START_MONTH, '7');
        Carbon::setTestNow('2026-10-28');

        $this->put(route('ledger.settings.closing'), ['closed_to' => '2026-03-31'])->assertSessionHasNoErrors();
        $this->assertSame('2026-03-31', LedgerSettings::closedTo());

        $this->put(route('ledger.settings.closing'), ['close_year' => 1])->assertSessionHasNoErrors();
        $this->assertSame('2026-06-30', LedgerSettings::closedTo());

        $this->put(route('ledger.settings.closing'), ['closed_to' => ''])->assertSessionHasNoErrors();
        $this->assertNull(LedgerSettings::closedTo(), 'emptied, the books are open again');
    }

    public function test_saving_the_ledger_accounts_page_leaves_the_closing_date_alone(): void
    {
        AppSetting::setValue(LedgerSettings::CLOSED_TO, '2026-06-30');

        $this->put(route('ledger.settings.update'), ['cost_of_sales' => 'none'])->assertSessionHasNoErrors();

        $this->assertSame('2026-06-30', LedgerSettings::closedTo());
        $this->get(route('ledger.settings'))->assertOk()->assertDontSee('Books closed to');
    }
}
