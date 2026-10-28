<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Services\CreditLimits;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Domains\Sales\Services\ContractReviewer;
use App\Domains\Sales\Services\PriceLists;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineDiscountTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        $this->customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
    }

    /** An order of 10 pieces at 100 less the given discount. */
    private function order(float $discount): SalesOrder
    {
        $product = Product::create(['code' => 'P-'.uniqid(), 'name' => 'Pump casing']);
        $rev = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = SalesOrder::create(['order_number' => 'SO-'.uniqid(), 'customer_id' => $this->customer->id, 'order_date' => today(), 'status' => 'DRAFT']);
        SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $product->id, 'product_revision_id' => $rev->id, 'line_number' => 1, 'ordered_qty' => 10,
            'uom' => 'piece', 'unit_price' => 100, 'discount_percent' => $discount]);

        return $order->load('lines');
    }

    public function test_an_invoice_line_is_worked_out_after_its_discount_and_tax_follows_the_net(): void
    {
        $service = app(InvoiceService::class);
        $rate = \App\Domains\Finance\Models\TaxRate::create(['name' => 'GST 10', 'rate_percent' => 10, 'is_active' => true]);

        $invoice = $service->saveDraft(['customer_id' => $this->customer->id], [
            ['description' => 'Casting', 'quantity' => 10, 'unit_price' => 100, 'discount_percent' => 12.5, 'tax_rate_id' => $rate->id],
            ['description' => 'Cover', 'quantity' => 1, 'unit_price' => 50, 'tax_rate_id' => $rate->id],
        ]);

        $line = $invoice->lines->first();
        $this->assertEquals([875.0, 87.5, 12.5], [$line->line_net, $line->line_tax, $line->discount_percent]);
        $this->assertEquals([925.0, 92.5, 1017.5], [$invoice->subtotal, $invoice->tax_total, $invoice->total]);
        $this->assertEquals(0, $invoice->lines->last()->discount_percent);
    }

    public function test_a_discount_is_kept_between_nothing_and_a_hundred_percent(): void
    {
        $this->assertSame([0.0, 100.0, 25.0], [InvoiceService::discount(['discount_percent' => -5]), InvoiceService::discount(['discount_percent' => 140]), InvoiceService::discount(['discount_percent' => '25'])]);
    }

    public function test_lines_filled_from_an_order_carry_its_discount_and_a_credit_note_credits_the_discounted_net(): void
    {
        $service = app(InvoiceService::class);
        $order = $this->order(10);

        $lines = $service->linesFromOrder($order);
        $this->assertEquals(10.0, $lines[0]['discount_percent']);

        $invoice = $service->issue($service->saveDraft(['customer_id' => $this->customer->id], $lines));
        $this->assertEquals(900.0, $invoice->total);

        $note = $service->creditNote($invoice, [['description' => 'Casting', 'quantity' => 2, 'unit_price' => 100, 'discount_percent' => 10]], 'Rejected');
        $this->assertEquals(180.0, $note->total);
    }

    public function test_order_value_for_credit_and_review_is_after_the_discount(): void
    {
        $order = $this->order(20);

        $this->assertSame(800.0, $order->lines->first()->netValue(10));
        $this->assertEquals(800.0, app(CreditLimits::class)->exposure($this->customer->id)['orders']);
    }

    public function test_contract_review_flags_a_discount_over_the_limit_only_when_a_limit_is_set(): void
    {
        $order = $this->order(15);
        $discountCodes = fn () => collect(app(ContractReviewer::class)->checkLine($order, $order->lines->first()))->where(0, 'DISCOUNT');

        $this->assertCount(0, $discountCodes(), 'no limit set, so any discount is allowed');

        AppSetting::setValue(PriceLists::DISCOUNT_LIMIT, '10');
        $found = $discountCodes();
        $this->assertCount(1, $found);
        $this->assertSame('WARN', $found->first()[1]);

        AppSetting::setValue(PriceLists::DISCOUNT_LIMIT, '15');
        $this->assertCount(0, $discountCodes());
    }

    public function test_the_setting_and_limit_are_saved_from_the_price_list_page_and_the_box_shows_on_the_order_form(): void
    {
        OrderRules::set('prices', 'SHOW');
        $this->order(0);   // the form needs a product to offer
        $this->get(route('sales-orders.create'))->assertOk()->assertDontSee('Discount percent');

        $this->put(route('price-lists.settings'), ['check' => 'WARN', 'line_discounts' => 1, 'discount_limit' => 12])->assertSessionHasNoErrors();
        $this->assertTrue(PriceLists::discountsOn());
        $this->assertSame(12.0, PriceLists::discountLimit());
        $this->get(route('price-lists.index'))->assertOk()->assertSee('Lines can have a discount');
        $this->get(route('sales-orders.create'))->assertOk()->assertSee('Discount percent');

        $this->put(route('price-lists.settings'), ['check' => 'WARN', 'discount_limit' => 12])->assertSessionHasNoErrors();
        $this->assertFalse(PriceLists::discountsOn());
    }

    public function test_an_order_with_a_discount_still_shows_the_box_after_the_setting_is_switched_off(): void
    {
        OrderRules::set('prices', 'SHOW');
        $order = $this->order(5);

        $this->get(route('sales-orders.edit', $order))->assertOk()->assertSee('Discount percent');
    }
}
