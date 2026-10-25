<?php

namespace Tests\Feature;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderDeliveryScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function line(string $status = 'CONFIRMED'): SalesOrderLine
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme']);
        $product = Product::create(['code' => 'P'.uniqid(), 'name' => 'Part']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = SalesOrder::create(['order_number' => SalesOrder::nextNumber(), 'customer_id' => $customer->id, 'order_date' => today(), 'status' => $status]);

        return $order->lines()->create(['product_id' => $product->id, 'product_revision_id' => $revision->id, 'line_number' => 1, 'ordered_qty' => 100, 'uom' => 'piece']);
    }

    public function test_a_line_is_split_into_dated_deliveries_up_to_its_ordered_quantity(): void
    {
        $line = $this->line();
        $url = route('sales-orders.lines.deliveries.store', [$line->sales_order_id, $line]);

        $this->post($url, ['due_date' => today()->addDays(10)->toDateString(), 'quantity' => 60])->assertSessionHasNoErrors();
        $this->post($url, ['due_date' => today()->addDays(40)->toDateString(), 'quantity' => 40])->assertSessionHasNoErrors();
        $this->post($url, ['due_date' => today()->addDays(70)->toDateString(), 'quantity' => 1])->assertSessionHasErrors('quantity');

        $this->assertSame(2, $line->deliveries()->count());
        $this->get(route('sales-orders.show', $line->sales_order_id))->assertOk()->assertSee('Lines and delivery schedule');
    }

    public function test_shipped_quantity_covers_the_oldest_delivery_first_and_flags_a_late_one(): void
    {
        $line = $this->line();
        $line->deliveries()->create(['due_date' => today()->subDays(3), 'quantity' => 60]);
        $line->deliveries()->create(['due_date' => today()->addDays(30), 'quantity' => 40]);

        $progress = $line->load('deliveries')->deliveryProgress(70);

        $this->assertEquals([60.0, 10.0], $progress->pluck('covered')->all());
        $this->assertEquals([false, false], $progress->pluck('overdue')->all());
        $this->assertTrue($line->deliveryProgress(20)->first()['overdue']);
    }

    public function test_a_closed_order_keeps_its_schedule(): void
    {
        $line = $this->line('DISPATCHED');

        $this->post(route('sales-orders.lines.deliveries.store', [$line->sales_order_id, $line]), ['due_date' => today()->toDateString(), 'quantity' => 5])->assertSessionHasErrors('quantity');
        $this->assertSame(0, $line->deliveries()->count());
    }
}
