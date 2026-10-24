<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderProductWithoutRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_with_no_revision_is_offered_and_gets_revision_0_when_ordered(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $bare = Product::create(['code' => 'CAP-1', 'name' => 'Cap']);

        $this->get(route('sales-orders.create'))->assertOk()->assertSee('value="product:'.$bare->id.'"', false)->assertSee('data-sub="CAP-1 · no revision yet"', false);

        $this->post(route('sales-orders.store'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'lines' => [
            ['product_revision_id' => 'product:'.$bare->id, 'ordered_qty' => 25],
        ]])->assertSessionHasNoErrors();

        $revision = ProductRevision::where('product_id', $bare->id)->sole();
        $line = SalesOrder::sole()->lines()->sole();
        $this->assertSame(['0', $bare->id, $revision->id], [$revision->revision, $line->product_id, $line->product_revision_id]);
        $this->get(route('sales-orders.create'))->assertDontSee('value="product:'.$bare->id.'"', false);
    }

    public function test_an_unknown_product_choice_is_refused(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);

        $this->post(route('sales-orders.store'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'lines' => [
            ['product_revision_id' => 'product:'.fake()->uuid(), 'ordered_qty' => 25],
        ]])->assertSessionHasErrors('lines.0.product_revision_id');

        $this->assertSame(0, SalesOrder::count());
    }
}
