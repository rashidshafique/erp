<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderCreateScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_starts_at_the_price_it_was_last_sold_at(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $rev = ProductRevision::create(['product_id' => Product::create(['code' => 'FLG-1', 'name' => 'Flange'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        foreach ([120, 135] as $price) {
            $this->post(route('sales-orders.store'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'lines' => [
                ['product_revision_id' => $rev->id, 'ordered_qty' => 10, 'unit_price' => $price],
            ]])->assertSessionHasNoErrors();
            $this->travel(1)->minutes();
        }

        $this->get(route('sales-orders.create'))->assertOk()->assertSee('value="'.$rev->id.'" data-name="Flange" data-sub="FLG-1 · rev A"', false)->assertSee('data-price="135.0000"', false);
    }

    public function test_lines_are_kept_when_the_order_is_refused(): void
    {
        $rev = ProductRevision::create(['product_id' => Product::create(['code' => 'FLG-1', 'name' => 'Flange'])->id, 'revision' => 'A', 'status' => 'APPROVED']);

        $this->from(route('sales-orders.create'))->post(route('sales-orders.store'), ['order_date' => today()->toDateString(), 'lines' => [
            ['product_revision_id' => $rev->id, 'ordered_qty' => 7, 'unit_price' => 50, 'requested_delivery_date' => today()->addWeek()->toDateString()],
        ]])->assertSessionHasErrors('customer_id');

        $this->followingRedirects()->get(route('sales-orders.create'))->assertSee('name="lines[0][ordered_qty]" step="any" min="0.001" required value="7"', false)
            ->assertSee('value="'.today()->addWeek()->toDateString().'"', false);
    }
}
