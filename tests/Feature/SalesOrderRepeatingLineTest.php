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
            'lines' => [$line + ['product_revision_id' => $revision->id, 'ordered_qty' => 50]],
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

    /** @param array<string, mixed> $row */
    private function edit(SalesOrder $order, SalesOrderLine $line, array $row, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->put(route('sales-orders.update', $order), ['customer_id' => $order->customer_id, 'order_date' => $order->order_date->toDateString(), 'lines' => [['id' => $line->id] + $row]] + $extra);
    }

    private function history(SalesOrder $order): string
    {
        return \Illuminate\Support\Facades\DB::table('audit_logs')->where('entity_type', 'SalesOrder')->where('entity_id', $order->id)->pluck('changes')->implode("\n");
    }

    public function test_changing_a_repeating_lines_quantity_resizes_its_future_deliveries_and_stopping_keeps_what_is_scheduled(): void
    {
        OrderRules::set('repeat_ahead', '4');
        $order = $this->createOrder(['repeat_every' => 'WEEK', 'repeat_from' => today()->subWeek()->toDateString()]);
        $line = $order->lines()->first();
        $this->get(route('sales-orders.edit', $order))->assertOk()->assertSee('<option value="WEEK" selected>', false)->assertSee('data-repeat-preview', false);
        $this->assertEquals(300, $line->ordered_qty);

        $this->edit($order, $line, ['ordered_qty' => 80, 'repeat_every' => 'WEEK', 'repeat_from' => $line->repeat_from->toDateString()])->assertSessionHasNoErrors();

        $this->assertEquals([50, 80, 80, 80, 80, 80], $line->deliveries()->pluck('quantity')->map(fn ($q) => (float) $q)->all());
        $this->assertEquals(450, $line->refresh()->ordered_qty);
        $this->assertStringContainsString('replanned: 5 deliveries of 80', $this->history($order));

        $this->edit($order, $line, ['ordered_qty' => 80])->assertSessionHasNoErrors();
        $this->assertFalse($line->refresh()->repeats());
        $this->assertEquals(450, $line->ordered_qty);
    }

    public function test_a_one_off_line_turned_repeating_is_replaced_by_its_deliveries_and_cannot_go_below_released_production(): void
    {
        OrderRules::set('repeat_ahead', '8');
        $order = $this->createOrder(['ordered_qty' => 100]);
        $line = $order->lines()->first();
        $production = app(\App\Services\Orders\ProductionOrderFactory::class)->forSalesLine($line, null, quick: true);
        $weekly = ['ordered_qty' => 6, 'repeat_every' => 'WEEK', 'repeat_from' => today()->addDays(2)->toDateString()];

        $this->edit($order, $line, $weekly)->assertSessionHasErrors(['order' => 'Line 1 would go to 48, but 100 is already released to production on '.$production->production_order_number.'. Cancel or close it first (Production > Production orders), or keep at least 100.']);
        $this->assertEquals([100, 0], [(float) $line->refresh()->ordered_qty, $line->deliveries()->count()]);

        $production->update(['status' => 'CANCELLED']);
        $this->edit($order, $line, $weekly)->assertSessionHasNoErrors();

        $this->assertEquals([8, 48], [$line->deliveries()->count(), (float) $line->refresh()->ordered_qty]);
        $this->assertStringContainsString('Line 1 now repeats: 8 deliveries of 6', $this->history($order));
    }

    public function test_a_line_whose_total_jumps_more_than_three_times_is_saved_only_once_confirmed(): void
    {
        $order = $this->createOrder(['ordered_qty' => 100]);
        $line = $order->lines()->first();

        $this->edit($order, $line, ['ordered_qty' => 900])->assertSessionHasErrors(['confirm_jump' => 'Please check: line 1 goes from 100 to 900. If that is right, tick "Yes, these quantities are right" and save again.']);
        $this->assertEquals(100, $line->refresh()->ordered_qty);

        $this->edit($order, $line, ['ordered_qty' => 900], ['confirm_jump' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(900, $line->refresh()->ordered_qty);
        $this->edit($order, $line, ['ordered_qty' => 1000])->assertSessionHasNoErrors();
    }

    public function test_removing_a_delivery_from_a_repeating_line_lowers_its_total_and_shows_on_the_order_history(): void
    {
        OrderRules::set('repeat_ahead', '4');
        $order = $this->createOrder(['repeat_every' => 'WEEK', 'repeat_from' => today()->toDateString()]);
        $line = $order->lines()->first();
        $last = $line->deliveries()->reorder('due_date', 'desc')->first();

        $this->delete(route('sales-orders.lines.deliveries.destroy', [$order, $line, $last]))->assertSessionHasNoErrors();

        $this->assertEquals([4, 200], [$line->deliveries()->count(), (float) $line->refresh()->ordered_qty]);
        $this->assertStringContainsString('removed; ordered 250 → 200', $this->history($order));
        $this->assertStringContainsString('5 deliveries of 50 added', $this->history($order));

        app(\App\Services\Orders\ProductionOrderFactory::class)->forSalesLine($line, null, quick: true);
        $this->delete(route('sales-orders.lines.deliveries.destroy', [$order, $line, $line->deliveries()->first()]))->assertSessionHasErrors('quantity');
        $this->assertSame(4, $line->deliveries()->count());
    }
}
