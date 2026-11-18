<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\TaxRate;
use App\Domains\Finance\Services\InvoiceService;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerSupplyAndCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_saves_with_country_currency_and_supply(): void
    {
        Livewire::test('customer-list')->call('create')
            ->set('form.name', 'Kovar Pumps')->set('form.supply', 'EXPORT')->set('form.country', 'us')->set('form.currency', 'usd')
            ->call('save')->assertHasNoErrors();

        $customer = Customer::first();
        $this->assertSame(['US', 'USD', 'EXPORT'], [$customer->country, $customer->currency, $customer->supply]);
        $this->assertTrue($customer->isExport());
    }

    public function test_a_new_customer_is_local_in_rupees(): void
    {
        $customer = Customer::create(['name' => 'Ravi Autos']);

        $this->assertSame(['PK', 'PKR', 'LOCAL'], [$customer->fresh()->country, $customer->fresh()->currency, $customer->fresh()->supply]);
    }

    public function test_an_invoice_takes_the_customers_currency_and_an_export_customer_starts_zero_rated(): void
    {
        $rate = TaxRate::create(['name' => 'GST 18%', 'rate_percent' => 18, 'is_default' => true, 'is_active' => true]);
        $export = Customer::create(['name' => 'Kovar Pumps', 'supply' => 'EXPORT', 'country' => 'US', 'currency' => 'USD']);
        $local = Customer::create(['name' => 'Ravi Autos']);
        $service = app(InvoiceService::class);

        $this->assertNull($service->defaultTaxRateId($export));
        $this->assertSame($rate->id, $service->defaultTaxRateId($local));

        $invoice = $service->saveDraft(['customer_id' => $export->id, 'invoice_date' => '2026-11-01'], [['description' => 'Machining', 'quantity' => 1, 'unit_price' => 100]]);
        $this->assertSame('USD', $invoice->currency);
    }
}
