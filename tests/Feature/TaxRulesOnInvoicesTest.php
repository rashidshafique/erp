<?php

namespace Tests\Feature;

use App\Domains\Core\Contracts\TaxCalculator;
use App\Domains\Core\FlatRateTax;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Tax\Models\PartyTaxProfile;
use App\Domains\Tax\Models\TaxAuthority;
use App\Domains\Tax\Models\TaxComponent;
use App\Domains\Tax\Services\TaxRules;
use App\Domains\Tax\Services\TaxSetup;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\TaxJurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class TaxRulesOnInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        app(TaxSetup::class)->loadDefaults(TaxJurisdiction::pack());
        $this->customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $this->profile(['tax_status' => 'REGISTERED', 'region' => 'PB', 'registrations' => ['NTN' => '1234567-8', 'STRN' => '32-77-8761-123-45']]);
    }

    private function profile(array $values): void
    {
        PartyTaxProfile::updateOrCreate(['party_id' => $this->customer->party_id], $values);
    }

    /** @param list<array<string, mixed>> $lines */
    private function invoice(array $lines, array $head = []): Invoice
    {
        return app(InvoiceService::class)->saveDraft($head + ['customer_id' => $this->customer->id, 'invoice_date' => '2025-03-10'],
            array_map(fn ($l) => $l + ['description' => 'Line', 'quantity' => 1, 'uom' => 'pcs'], $lines));
    }

    /** @return array<string, float> tax code => amount */
    private function taxes(Invoice $invoice): array
    {
        return $invoice->taxBreakdown()->mapWithKeys(fn ($t) => [$t['code'] => $t['amount']])->all();
    }

    public function test_goods_to_a_registered_buyer_carry_federal_sales_tax(): void
    {
        $invoice = $this->invoice([['unit_price' => 1000, 'supply_type' => 'GOODS', 'tariff_code' => '7325.1000']]);

        $this->assertSame(['ST_GOODS' => 180.0], $this->taxes($invoice));
        $this->assertSame([180.0, 1180.0, 18.0], [$invoice->tax_total, $invoice->total, $invoice->lines->first()->tax_percent]);
    }

    public function test_a_service_is_taxed_by_the_province_it_is_supplied_in(): void
    {
        $service = [['unit_price' => 1000, 'supply_type' => 'SERVICE']];

        $this->assertSame(['ST_SERVICES_PB' => 160.0], $this->taxes($this->invoice($service)));
        $this->assertSame(['ST_SERVICES_SD' => 150.0], $this->taxes($this->invoice($service, ['place_of_supply' => 'SD'])));
    }

    public function test_a_mixed_invoice_shows_the_federal_and_provincial_taxes_apart(): void
    {
        $invoice = $this->invoice([['unit_price' => 1000, 'supply_type' => 'GOODS', 'tariff_code' => '7325.1000'], ['unit_price' => 500, 'supply_type' => 'SERVICE']]);

        $this->assertSame(['ST_GOODS' => 180.0, 'ST_SERVICES_PB' => 80.0], $this->taxes($invoice));
        $this->assertSame(['FBR', 'PRA'], $invoice->taxBreakdown()->pluck('authority')->all());
        $this->assertSame(1760.0, $invoice->total);
    }

    public function test_a_buyer_not_registered_also_pays_further_tax(): void
    {
        $this->profile(['tax_status' => 'UNREGISTERED']);

        $this->assertSame(['ST_GOODS' => 180.0, 'FURTHER_TAX' => 40.0], $this->taxes($this->invoice([['unit_price' => 1000, 'supply_type' => 'GOODS']])));
    }

    public function test_a_withholding_agent_keeps_back_tax_without_changing_the_invoice_total(): void
    {
        $this->profile(['is_withholding_agent' => true]);
        $invoice = $this->invoice([['unit_price' => 1000, 'supply_type' => 'GOODS', 'tariff_code' => '7325.1000']]);

        // a fifth of the 180 sales tax, and 5% income tax on the 1,180 with tax
        $this->assertSame(['ST_GOODS' => 180.0, 'WHT_ST' => 36.0, 'WHT_IT_GOODS' => 59.0], $this->taxes($invoice));
        $this->assertSame([1180.0, 95.0, 1085.0], [$invoice->total, $invoice->tax_withheld, $invoice->payableToUs()]);

        $invoice = app(InvoiceService::class)->issue($invoice);
        $this->get(route('invoices.print', $invoice))->assertOk()->assertSee('Sales tax invoice')->assertSee('STRN: 32-77-8761-123-45')
            ->assertSee('Less sales tax withheld by the buyer')->assertSee('7325.1000')->assertSee('1,085.00');
    }

    public function test_a_later_rate_change_leaves_earlier_invoices_at_their_rate(): void
    {
        $before = $this->invoice([['unit_price' => 1000, 'supply_type' => 'GOODS']]);
        app(TaxSetup::class)->changeRate(TaxComponent::where('code', 'ST_GOODS')->sole(), 19, Carbon::parse('2025-07-01'));

        $after = $this->invoice([['unit_price' => 1000, 'supply_type' => 'GOODS']], ['invoice_date' => '2025-07-01']);
        $this->assertSame([180.0, 190.0], [$before->fresh()->tax_total, $after->tax_total]);
        $this->assertSame(180.0, $this->invoice([['unit_price' => 1000, 'supply_type' => 'GOODS']], ['invoice_date' => '2025-06-30'])->tax_total);
    }

    public function test_a_line_no_rule_covers_keeps_the_percent_chosen_on_it(): void
    {
        $this->profile(['tax_status' => null]);
        $rate = \App\Domains\Finance\Models\TaxRate::create(['name' => 'Flat', 'rate_percent' => 10, 'is_active' => true]);

        $this->assertSame(['TAX' => 100.0], $this->taxes($this->invoice([['unit_price' => 1000, 'supply_type' => 'GOODS', 'tax_rate_id' => $rate->id]])));
    }

    public function test_tax_of_an_authority_the_company_is_not_registered_with_is_left_off_and_flagged(): void
    {
        TaxAuthority::where('code', 'FBR')->update(['is_registered' => true]);
        $invoice = $this->invoice([['unit_price' => 1000, 'supply_type' => 'SERVICE']], ['place_of_supply' => 'SD']);

        $this->assertSame([], $this->taxes($invoice));
        $this->assertStringContainsString('Sindh Revenue Board', implode(' ', app(InvoiceService::class)->taxChecks($invoice)['warnings']));
    }

    public function test_an_enforced_check_stops_an_invoice_to_a_buyer_with_no_registration_number(): void
    {
        $this->profile(['registrations' => []]);
        $invoice = $this->invoice([['unit_price' => 1000, 'supply_type' => 'GOODS', 'tariff_code' => '7325.1000']]);
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('The buyer has no registration number on file');

        TaxRules::set('buyer_registration', 'ENFORCED');
        $this->expectException(InvalidArgumentException::class);
        app(InvoiceService::class)->issue($invoice);
    }

    public function test_without_the_module_each_line_is_taxed_once_at_its_percent(): void
    {
        $this->app->instance(TaxCalculator::class, new FlatRateTax);
        $rate = \App\Domains\Finance\Models\TaxRate::create(['name' => 'Sales tax', 'rate_percent' => 17, 'is_active' => true]);

        $invoice = $this->invoice([['unit_price' => 1000, 'supply_type' => 'SERVICE', 'tax_rate_id' => $rate->id]]);
        $this->assertSame(['TAX' => 170.0], $this->taxes($invoice));
        $this->assertSame(1170.0, $invoice->total);
    }
}
