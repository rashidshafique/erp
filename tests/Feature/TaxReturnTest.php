<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Finance\Services\TaxReturn;
use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Tax\Models\PartyTaxProfile;
use App\Domains\Tax\Services\TaxSetup;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\TaxJurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        app(TaxSetup::class)->loadDefaults(TaxJurisdiction::pack());
        TaxSetup::saveCompany('PB', ['NTN' => '7654321-0'], true);

        // Sold 1,000 of goods to a withholding agent: 180 sales tax, of which they keep back 36, and 59 income tax
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        PartyTaxProfile::updateOrCreate(['party_id' => $customer->party_id], ['tax_status' => 'REGISTERED', 'region' => 'PB', 'is_withholding_agent' => true, 'registrations' => ['STRN' => '32-77-8761-123-45']]);
        $invoices = app(InvoiceService::class);
        $invoices->issue($invoices->saveDraft(['customer_id' => $customer->id, 'invoice_date' => '2025-03-10'],
            [['description' => 'Pump casing', 'quantity' => 1, 'uom' => 'pcs', 'unit_price' => 1000, 'supply_type' => 'GOODS', 'tariff_code' => '7325.1000']]));

        // Bought 500 of goods from a registered supplier: 90 input tax, of which we keep back 18, and 29.50 income tax on the 590
        $supplier = Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        PartyTaxProfile::updateOrCreate(['party_id' => $supplier->party_id], ['tax_status' => 'REGISTERED', 'region' => 'PB']);
        $this->post(route('bills.store'), ['supplier_id' => $supplier->id, 'bill_date' => '2025-03-12', 'supplier_bill_number' => 'S-1',
            'lines' => [['description' => 'Pig iron', 'quantity' => 1, 'unit_price' => 500, 'tax_percent' => 18, 'supply_type' => 'GOODS']]])->assertSessionHasNoErrors();
        $this->post(route('bills.post', SupplierBill::sole()))->assertSessionHasNoErrors();
    }

    public function test_sales_tax_payable_is_output_less_input_less_what_customers_kept_back_plus_what_we_kept_back(): void
    {
        $fbr = app(TaxReturn::class)->forPeriod('2025-03-01', '2025-03-31')['FBR'];

        $this->assertSame([180.0, 90.0, 36.0, 18.0, 72.0], [$fbr['output'], $fbr['input'], $fbr['withheld_by_customers'], $fbr['withheld_by_us'], $fbr['payable']]);
        $this->assertSame([29.5, 59.0], [$fbr['income_tax_withheld_by_us'], $fbr['income_tax_withheld_by_customers']]);
        $this->assertSame([], app(TaxReturn::class)->forPeriod('2025-04-01', '2025-04-30')->all());
    }

    public function test_the_return_page_shows_the_month(): void
    {
        $this->get(route('finance.tax-return', ['month' => '2025-03']))->assertOk()
            ->assertSee('Payable')->assertSee('72.00')->assertSee('We withheld from suppliers');
    }
}
