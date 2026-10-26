<?php

namespace Tests\Feature;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Domains\Sales\Services\SalesOrderStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Support\OrderRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderRepeatingLineTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(array $line): SalesOrder
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme']);
        $product = Product::create(['code' => 'P'.uniqid(), 'name' => 'Part']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);

        $this->post(route('sales-orders.store'), [
            'customer_id' => $customer->id, 'order_date' => today()->toDateString(),
            'lines' => [['product_revision_id' => $revision->id, 'ordered_qty' => 50] + $line],
        ])->assertSessionHasNoErrors();

        return SalesOrder::latest()->firstOrFail();
    }

    public function test_a_weekly_line_schedules_deliveries_up_to_the_repeat_ahead_rule_and_the_daily_run_adds_the_next(): void
    {
        OrderRules::set('repeat_ahead', '4');
        $line = $this->createOrder(['repeat_every' => 'WEEK', 'repeat_from' => today()->toDateString()])->lines()->first();

        $this->assertSame(5, $line->deliveries()->count());
        $this->assertEquals(250, $line->ordered_qty);
        $this->assertEquals(50, $line->repeat_qty);

        $line->deliveries()->reorder('due_date', 'desc')->first()->delete();
        $this->travel(7)->days();
        $this->artisan('sales:repeat-deliveries')->assertSuccessful();

        $this->assertEquals([today()->subDays(7), today(), today()->addDays(7), today()->addDays(14), today()->addDays(28)],
            $line->deliveries()->pluck('due_date')->all());
        $this->assertEquals(300, $line->refresh()->ordered_qty);
    }

    public function test_a_monthly_line_keeps_month_ends_and_stops_at_its_end_date(): void
    {
        $line = new SalesOrderLine(['repeat_every' => 'MONTH', 'repeat_from' => '2027-01-31', 'repeat_until' => '2027-03-31']);

        $this->assertSame('2027-01-31', $line->nextRepeatDate()->toDateString());
        $line->repeated_to = '2027-02-28';
        $this->assertSame('2027-03-31', $line->nextRepeatDate()->toDateString());
        $line->repeated_to = '2027-03-31';
        $this->assertNull($line->nextRepeatDate());
        $this->assertFalse($line->isStillRepeating());
    }

    public function test_an_order_with_a_line_still_repeating_is_not_closed_by_a_dispatch(): void
    {
        $order = $this->createOrder(['repeat_every' => 'MONTH', 'repeat_from' => today()->addYear()->toDateString()]);
        $order->lines()->update(['ordered_qty' => 0]);

        app(SalesOrderStatus::class)->dispatched($order->load('lines'));
        $this->assertNotSame('DISPATCHED', $order->refresh()->status);

        $order->lines()->update(['repeat_until' => today()->addYear()->toDateString(), 'repeated_to' => today()->addYear()->toDateString()]);
        app(SalesOrderStatus::class)->dispatched($order->load('lines'));
        $this->assertSame('DISPATCHED', $order->refresh()->status);
    }

    public function test_editing_a_repeating_line_changes_future_deliveries_and_stopping_keeps_what_is_scheduled(): void
    {
        $order = $this->createOrder(['repeat_every' => 'MONTH', 'repeat_from' => today()->toDateString()]);
        $line = $order->lines()->first();
        $this->get(route('sales-orders.edit', $order))->assertOk()->assertSee('<option value="MONTH" selected>', false);
        $scheduled = (float) $line->ordered_qty;
        $payload = fn (array $row) => ['customer_id' => $order->customer_id, 'order_date' => today()->toDateString(), 'lines' => [['id' => $line->id] + $row]];

        $this->put(route('sales-orders.update', $order), $payload(['ordered_qty' => 80, 'repeat_every' => 'MONTH']))->assertSessionHasNoErrors();
        $this->assertEquals(80, $line->refresh()->repeat_qty);
        $this->assertEquals($scheduled, $line->ordered_qty);
        $this->assertSame(today()->toDateString(), $line->repeat_from->toDateString());

        $this->put(route('sales-orders.update', $order), $payload(['ordered_qty' => 80]))->assertSessionHasNoErrors();
        $this->assertFalse($line->refresh()->repeats());
        $this->assertEquals($scheduled, $line->ordered_qty);
    }
}
