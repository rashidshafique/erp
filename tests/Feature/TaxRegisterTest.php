<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Finance\Services\TaxRegister;
use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Tax\Models\PartyTaxProfile;
use App\Domains\Tax\Services\TaxSetup;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\Formats;
use App\Support\TaxJurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxRegisterTest extends TestCase
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
        PartyTaxProfile::updateOrCreate(['party_id' => $this->customer->party_id], ['tax_status' => 'REGISTERED', 'region' => 'PB', 'registrations' => ['NTN' => '1234567-8', 'STRN' => '32-77-8761-123-45']]);
    }

    /** @param list<array<string, mixed>> $lines */
    private function issue(array $lines, string $date = '2025-03-10'): \App\Domains\Finance\Models\Invoice
    {
        $invoices = app(InvoiceService::class);

        return $invoices->issue($invoices->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => $date],
            array_map(fn ($l) => $l + ['description' => 'Line', 'quantity' => 1, 'uom' => 'pcs'], $lines)));
    }

    public function test_the_sales_register_adds_up_each_authoritys_tax_by_document_date_with_credit_notes_negative_and_voids_left_out(): void
    {
        $invoice = $this->issue([['unit_price' => 1000, 'supply_type' => 'GOODS', 'tariff_code' => '7325.1000'], ['unit_price' => 500, 'supply_type' => 'SERVICE']]);
        app(InvoiceService::class)->creditNote($invoice->fresh(), [['description' => 'Line', 'quantity' => 1, 'unit_price' => 200, 'supply_type' => 'GOODS', 'tariff_code' => '7325.1000']], 'Short');
        app(InvoiceService::class)->void($this->issue([['unit_price' => 900, 'supply_type' => 'GOODS', 'tariff_code' => '7325.1000']]), 'Raised twice');
        $this->issue([['unit_price' => 700, 'supply_type' => 'GOODS', 'tariff_code' => '7325.1000']], '2025-04-02');

        $register = app(TaxRegister::class);
        $summary = $register->summary($register->rows('sales', '2025-03-01', '2025-03-31'));

        $this->assertSame([['FBR', 'ST_GOODS', 1000.0, 180.0, 1], ['PRA', 'ST_SERVICES_PB', 500.0, 80.0, 1]],
            $summary->map(fn ($s) => [$s['authority'], $s['code'], $s['base'], $s['amount'], $s['documents']])->all());
        $today = today()->toDateString();
        $credit = $register->summary($register->rows('sales', $today, $today));
        $this->assertSame([['FBR', -200.0, -36.0]], $credit->map(fn ($s) => [$s['authority'], $s['base'], $s['amount']])->all());
        $this->get(route('finance.tax-register', ['from' => '2025-03-01', 'to' => '2025-03-31', 'authority' => 'FBR']))->assertOk()
            ->assertSee('NTN 1234567-8')->assertSee('7325.1000')->assertSee(Formats::number(180, 2))
            ->assertViewHas('summary', fn ($summary) => $summary->pluck('authority')->all() === ['FBR']);
        $this->get(route('finance.tax-register', ['from' => $today, 'to' => $today]))->assertOk()->assertSee('('.Formats::number(36, 2).')');
    }

    public function test_the_purchases_register_shows_input_tax_per_bill_and_rate_and_exports_a_csv(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        $this->post(route('bills.store'), ['supplier_id' => $supplier->id, 'bill_date' => '2025-03-05', 'supplier_bill_number' => 'S-9', 'lines' => [
            ['description' => 'Pig iron', 'quantity' => 1, 'unit_price' => 1000, 'tax_percent' => 18],
            ['description' => 'Ferro silicon', 'quantity' => 1, 'unit_price' => 500, 'tax_percent' => 18],
            ['description' => 'Freight', 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 0],
        ]])->assertSessionHasNoErrors();
        $this->post(route('bills.post', SupplierBill::sole()))->assertSessionHasNoErrors();

        $rows = app(TaxRegister::class)->rows('purchases', '2025-03-01', '2025-03-31');

        $this->assertSame([[0.0, 100.0, 0.0], [18.0, 1500.0, 270.0]], $rows->map(fn ($r) => [$r['rate'], $r['base'], $r['amount']])->sortBy(0)->values()->all());
        $csv = $this->get(route('finance.tax-register', ['side' => 'purchases', 'from' => '2025-03-01', 'to' => '2025-03-31', 'export' => 'csv']));
        $csv->assertOk()->assertDownload('tax-register-purchases-2025-03-01-to-2025-03-31.csv');
        $this->assertStringContainsString('Acme Pig Iron', $csv->streamedContent());
        $this->assertStringContainsString('(S-9)', $csv->streamedContent());
    }
}
