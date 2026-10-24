<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MouldingRun;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Services\SalesOrderStatus;
use App\Support\OrderRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'CONFIRMED'): SalesOrder
    {
        $customer = Customer::where('code', 'ACME')->first() ?? Customer::create(['code' => 'ACME', 'name' => 'Acme']);
        $product = Product::create(['code' => 'P'.uniqid(), 'name' => 'Part']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = SalesOrder::create(['order_number' => SalesOrder::nextNumber(), 'customer_id' => $customer->id, 'order_date' => today(), 'status' => $status]);
        $order->lines()->create(['product_id' => $product->id, 'product_revision_id' => $revision->id, 'line_number' => 1, 'ordered_qty' => 10, 'uom' => 'piece']);

        return $order;
    }

    private function production(SalesOrder $order, string $status): ProductionOrder
    {
        $line = $order->lines->first() ?? $order->lines()->first();

        return ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'sales_order_line_id' => $line->id, 'product_id' => $line->product_id, 'product_revision_id' => $line->product_revision_id,
            'order_type' => 'CUSTOMER', 'status' => $status, 'priority' => 'NORMAL', 'planned_qty' => 10, 'released_qty' => 0, 'completed_qty' => 0, 'rejected_qty' => 0, 'uom' => 'piece']);
    }

    public function test_an_open_order_can_be_put_on_hold_and_resumed_to_where_it_was(): void
    {
        $order = $this->order('CONFIRMED');
        $this->production($order, 'RELEASED');

        $this->post(route('sales-orders.status', $order), ['status' => 'ON_HOLD'])->assertSessionHasNoErrors();
        $this->assertSame('ON_HOLD', $order->refresh()->status);

        $this->post(route('sales-orders.status', $order), ['status' => 'RESUME']);
        $this->assertSame('IN_PRODUCTION', $order->refresh()->status);
    }

    public function test_cancelling_needs_a_reason_and_cancels_unstarted_production_orders(): void
    {
        $order = $this->order();
        $production = $this->production($order, 'RELEASED');

        $this->post(route('sales-orders.status', $order), ['status' => 'CANCELLED'])->assertSessionHasErrors('reason');
        $this->post(route('sales-orders.status', $order), ['status' => 'CANCELLED', 'reason' => 'Customer withdrew'])->assertSessionHasNoErrors();

        $this->assertSame(['CANCELLED', 'CANCELLED'], [$order->refresh()->status, $production->refresh()->status]);
        $this->assertStringContainsString('Customer withdrew', $order->notes);
    }

    public function test_an_order_with_moulding_started_cannot_be_cancelled(): void
    {
        $order = $this->order();
        $production = $this->production($order, 'RELEASED');
        MouldingRun::create(['run_number' => 'MR-1', 'production_order_id' => $production->id, 'run_date' => today()]);

        $this->post(route('sales-orders.status', $order), ['status' => 'CANCELLED', 'reason' => 'x'])->assertSessionHasErrors('status');
        $this->assertSame(['CONFIRMED', 'RELEASED'], [$order->refresh()->status, $production->refresh()->status]);
    }

    public function test_a_cancelled_order_can_be_reopened_but_its_production_orders_stay_cancelled(): void
    {
        $order = $this->order();
        $production = $this->production($order, 'DRAFT');
        $this->post(route('sales-orders.status', $order), ['status' => 'CANCELLED', 'reason' => 'Mistake']);

        $this->post(route('sales-orders.status', $order), ['status' => 'REOPEN'])->assertSessionHasNoErrors();

        $this->assertSame(['DRAFT', 'CANCELLED'], [$order->refresh()->status, $production->refresh()->status]);
    }

    public function test_releasing_a_production_order_moves_a_confirmed_order_into_production(): void
    {
        config(['foundry.require_contract_review' => false]);
        $order = $this->order('CONFIRMED');
        $production = $this->production($order, 'DRAFT');

        $this->post(route('production-orders.release', $production))->assertSessionHasNoErrors();

        $this->assertSame('IN_PRODUCTION', $order->refresh()->status);
    }

    public function test_an_order_is_dispatched_only_when_every_line_has_gone_out_in_full(): void
    {
        $order = $this->order('IN_PRODUCTION');
        $line = $order->lines()->first();
        \Illuminate\Support\Facades\Schema::table('dispatch_items', function ($table) {
            $table->dropForeign(['dispatch_id']);
            $table->dropForeign(['packing_record_item_id']);
        });
        $dispatch = fn (int $qty) => \Illuminate\Support\Facades\DB::table('dispatch_items')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'dispatch_id' => (string) \Illuminate\Support\Str::uuid(), 'packing_record_item_id' => (string) \Illuminate\Support\Str::uuid(), 'sales_order_line_id' => $line->id, 'quantity_dispatched' => $qty, 'uom' => 'PCS', 'created_at' => now(), 'updated_at' => now()]);
        $status = app(SalesOrderStatus::class);

        $dispatch(4);
        $status->dispatched($order->load('lines'));
        $this->assertSame('IN_PRODUCTION', $order->refresh()->status);

        $dispatch(6);
        $status->dispatched($order->load('lines'));
        $this->assertSame('DISPATCHED', $order->refresh()->status);
    }

    public function test_a_closed_order_cannot_be_held(): void
    {
        $order = $this->order('DISPATCHED');

        $this->post(route('sales-orders.status', $order), ['status' => 'ON_HOLD'])->assertSessionHasErrors('status');
    }

    public function test_the_offered_changes_follow_the_current_status(): void
    {
        $status = app(SalesOrderStatus::class);

        $this->assertSame(['IN_PRODUCTION', 'ON_HOLD', 'CANCELLED'], array_keys($status->allowedFrom($this->order('DRAFT'))));
        $this->assertSame(['ON_HOLD', 'CANCELLED'], array_keys($status->allowedFrom($this->order('IN_PRODUCTION'))));
        $this->assertSame(['RESUME', 'IN_PRODUCTION', 'CANCELLED'], array_keys($status->allowedFrom($this->order('ON_HOLD'))));
        $this->assertSame(['REOPEN'], array_keys($status->allowedFrom($this->order('CANCELLED'))));
        $this->assertSame([], $status->allowedFrom($this->order('DISPATCHED')));
    }

    public function test_going_live_is_refused_while_review_is_required_and_not_approved(): void
    {
        OrderRules::set('contract_review', 'REQUIRED');
        $order = $this->order('DRAFT');

        $this->post(route('sales-orders.status', $order), ['status' => 'IN_PRODUCTION', 'release_all' => 1])->assertSessionHasErrors('status');
        $this->assertSame(['DRAFT', 0], [$order->refresh()->status, ProductionOrder::count()]);
    }

    public function test_going_live_without_review_can_create_and_release_every_production_order(): void
    {
        OrderRules::set('contract_review', 'SKIP');
        $order = $this->order('DRAFT');

        $this->post(route('sales-orders.status', $order), ['status' => 'IN_PRODUCTION', 'release_all' => 1])->assertSessionHasNoErrors();

        $production = ProductionOrder::firstOrFail();
        $this->assertSame(['IN_PRODUCTION', 'RELEASED', '10.000'], [$order->refresh()->status, $production->status, (string) $production->released_qty]);
        $this->post(route('sales-orders.status', $order), ['status' => 'ON_HOLD']);
        $this->post(route('sales-orders.status', $order), ['status' => 'IN_PRODUCTION', 'release_all' => 1]);
        $this->assertSame(1, ProductionOrder::count());
    }

    public function test_going_live_without_releasing_only_confirms_the_order(): void
    {
        OrderRules::set('contract_review', 'SKIP');
        $order = $this->order('DRAFT');

        $this->post(route('sales-orders.status', $order), ['status' => 'IN_PRODUCTION'])->assertSessionHasNoErrors();

        $this->assertSame(['CONFIRMED', 0], [$order->refresh()->status, ProductionOrder::count()]);
    }

    public function test_the_simple_flow_sends_a_saved_order_straight_to_production(): void
    {
        OrderRules::set('order_flow', 'SIMPLE');
        OrderRules::set('contract_review', 'SKIP');
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme']);
        $product = Product::create(['code' => 'P1', 'name' => 'Part']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);

        $this->post(route('sales-orders.store'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'lines' => [
            ['product_revision_id' => $revision->id, 'ordered_qty' => 10],
        ]])->assertSessionHasNoErrors();

        $production = ProductionOrder::sole();
        $this->assertSame(['IN_PRODUCTION', 'RELEASED'], [SalesOrder::sole()->status, $production->status]);
    }

    public function test_the_simple_flow_still_waits_for_the_review_when_one_is_required(): void
    {
        OrderRules::set('order_flow', 'SIMPLE');
        OrderRules::set('contract_review', 'REQUIRED');
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme']);
        $product = Product::create(['code' => 'P1', 'name' => 'Part']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);

        $this->post(route('sales-orders.store'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'lines' => [
            ['product_revision_id' => $revision->id, 'ordered_qty' => 10],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(['DRAFT', 0], [SalesOrder::sole()->status, ProductionOrder::count()]);
    }

    public function test_the_full_flow_leaves_a_saved_order_as_a_draft(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme']);
        $product = Product::create(['code' => 'P1', 'name' => 'Part']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);

        $this->post(route('sales-orders.store'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'lines' => [
            ['product_revision_id' => $revision->id, 'ordered_qty' => 10],
        ]]);

        $this->assertSame(['DRAFT', 0], [SalesOrder::sole()->status, ProductionOrder::count()]);
    }

    public function test_going_live_releases_the_production_orders_by_default_in_the_simple_flow_unless_unticked(): void
    {
        OrderRules::set('order_flow', 'SIMPLE');
        OrderRules::set('contract_review', 'SKIP');
        $released = $this->order('DRAFT');
        $unticked = $this->order('DRAFT');

        $this->post(route('sales-orders.status', $released), ['status' => 'IN_PRODUCTION'])->assertSessionHasNoErrors();
        $this->post(route('sales-orders.status', $unticked), ['status' => 'IN_PRODUCTION', 'release_all' => 0])->assertSessionHasNoErrors();

        $this->assertSame(['IN_PRODUCTION', 'CONFIRMED', 1], [$released->refresh()->status, $unticked->refresh()->status, ProductionOrder::count()]);
    }

    public function test_the_contract_review_rule_is_saved_from_settings(): void
    {
        $this->put(route('settings.orders'), ['contract_review' => 'SKIP'])->assertSessionHasNoErrors();

        $this->assertFalse(OrderRules::reviewRequired());
        $this->get(route('settings.orders.edit'))->assertOk();
        $this->put(route('settings.orders'), ['contract_review' => 'BOGUS'])->assertSessionHasErrors('contract_review');
    }

    public function test_the_order_page_offers_the_status_dropdown(): void
    {
        $this->get(route('sales-orders.show', $this->order('DRAFT')))->assertOk()->assertSee('Change status')->assertSee('Live (in production)');
    }
}
