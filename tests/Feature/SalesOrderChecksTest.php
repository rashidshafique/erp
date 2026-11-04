<?php

namespace Tests\Feature;

use App\Domains\Sales\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Support\OrderRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderChecksTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Customer, 1: ProductRevision} */
    private function setUpOrder(): array
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $rev = ProductRevision::create(['product_id' => Product::create(['code' => 'FLG-1', 'name' => 'Flange'])->id, 'revision' => 'A', 'status' => 'APPROVED']);

        return [$customer, $rev];
    }

    public function test_the_panel_waits_for_a_customer_and_a_product(): void
    {
        $this->postJson(route('sales-orders.checks'), [])->assertOk()->assertJson(['ready' => false, 'rows' => []]);
    }

    public function test_typed_lines_are_checked_without_saving_anything(): void
    {
        [$customer, $rev] = $this->setUpOrder();
        OrderRules::set('contract_review', 'MANUAL');

        $r = $this->postJson(route('sales-orders.checks'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'requested_delivery_date' => today()->addDays(60)->toDateString(),
            'lines' => [['product_revision_id' => $rev->id, 'ordered_qty' => 10, 'uom' => 'piece']]])->assertOk()->assertJson(['ready' => true]);

        $names = collect($r->json('rows'))->pluck('status', 'name');
        $this->assertSame('warn', $names['Contract review']);
        $this->assertSame('block', $names['Product and spec']);
        $this->assertSame('warn', $names['Pattern']);
        $this->assertGreaterThan(0, $r->json('blocks'));
        $this->assertStringStartsWith('Fix', $r->json('primary'));
        $this->assertSame(0, SalesOrder::count());
        $this->assertDatabaseCount('contract_reviews', 0);
    }

    public function test_with_the_review_off_only_credit_price_and_delivery_are_asked_for(): void
    {
        [$customer, $rev] = $this->setUpOrder();
        OrderRules::set('contract_review', 'SKIP');

        $r = $this->postJson(route('sales-orders.checks'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'requested_delivery_date' => today()->addDays(60)->toDateString(),
            'lines' => [['product_revision_id' => $rev->id, 'ordered_qty' => 10, 'uom' => 'piece']]])->assertOk();

        $this->assertSame(['Contract review', 'Delivery'], collect($r->json('rows'))->pluck('name')->all());
        $this->assertSame(0, $r->json('blocks'));
        $this->assertSame('Create order', $r->json('primary'));
    }

    public function test_save_draft_keeps_the_order_unreviewed(): void
    {
        [$customer, $rev] = $this->setUpOrder();
        OrderRules::set('contract_review', 'AUTO');

        $this->post(route('sales-orders.store'), ['draft' => 1, 'customer_id' => $customer->id, 'order_date' => today()->toDateString(),
            'lines' => [['product_revision_id' => $rev->id, 'ordered_qty' => 10]]])->assertSessionHasNoErrors();

        $this->assertSame('DRAFT', SalesOrder::first()->status);
        $this->assertDatabaseCount('contract_reviews', 0);
    }

    public function test_a_product_without_a_revision_is_checked_without_creating_one(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $product = Product::create(['code' => 'BRK-1', 'name' => 'Bracket']);
        OrderRules::set('contract_review', 'MANUAL');

        $this->postJson(route('sales-orders.checks'), ['customer_id' => $customer->id, 'lines' => [['product_revision_id' => 'product:'.$product->id, 'ordered_qty' => 5]]])
            ->assertOk()->assertJson(['ready' => true]);

        $this->assertSame(0, ProductRevision::count());
    }

    public function test_the_edit_screen_sends_the_order_id_so_it_is_not_counted_twice(): void
    {
        [$customer, $rev] = $this->setUpOrder();
        $this->post(route('sales-orders.store'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'lines' => [['product_revision_id' => $rev->id, 'ordered_qty' => 3]]]);
        $order = SalesOrder::first();

        $this->get(route('sales-orders.edit', $order))->assertOk()->assertSee('name="order_id" value="'.$order->id.'"', false);
        $this->postJson(route('sales-orders.checks'), ['order_id' => $order->id, 'customer_id' => $customer->id, 'lines' => [['product_revision_id' => $rev->id, 'ordered_qty' => 3]]])->assertOk();
    }
}
