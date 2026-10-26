<?php

namespace Tests\Feature;

use App\Domains\Sales\Models\SalesOrder;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderEditTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'DRAFT'): SalesOrder
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme']);
        $product = Product::create(['code' => 'P'.uniqid(), 'name' => 'Part']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = SalesOrder::create(['order_number' => SalesOrder::nextNumber(), 'customer_id' => $customer->id, 'order_date' => today(), 'status' => $status]);
        $order->lines()->create(['product_id' => $product->id, 'product_revision_id' => $revision->id, 'line_number' => 1, 'ordered_qty' => 10, 'uom' => 'piece']);

        return $order;
    }

    private function payload(SalesOrder $order, array $line = [], array $extra = []): array
    {
        $existing = $order->lines()->first();

        return $extra + ['customer_id' => $order->customer_id, 'order_date' => today()->toDateString(), 'lines' => [array_merge(['id' => $existing->id, 'ordered_qty' => 10], $line)]];
    }

    public function test_a_draft_order_is_edited_and_the_change_is_logged_without_a_reason(): void
    {
        $order = $this->order();
        $line = $order->lines()->first();
        $this->get(route('sales-orders.edit', $order))->assertOk()->assertDontSee('Reason for the change')
            ->assertSee('name="lines[0][id]" value="'.$line->id.'"', false)->assertSee('name="lines[0][ordered_qty]" step="any" min="0.001" required value="10"', false)
            ->assertSee('<input type="hidden" name="lines[0][unit_price]" value="">', false)->assertSee('data-remove aria-label="Remove line"', false);

        $this->put(route('sales-orders.update', $order), $this->payload($order, ['ordered_qty' => 25], ['customer_reference' => 'PO-9']))->assertSessionHasNoErrors();

        $this->assertSame('PO-9', $order->refresh()->customer_reference);
        $this->assertEquals(25, $order->lines()->first()->ordered_qty);
        $edit = AuditLog::where('entity_type', 'SalesOrder')->where('entity_id', $order->id)->where('action', 'EDITED')->firstOrFail();
        $this->assertStringContainsString('ordered_qty: 10', $edit->changeSet()['summary']['new']);
        $this->assertTrue(AuditLog::where('entity_type', 'SalesOrderLine')->where('action', 'UPDATED')->exists());
    }

    public function test_a_saved_price_is_kept_when_prices_are_hidden(): void
    {
        $order = $this->order();
        $order->lines()->first()->update(['unit_price' => 42.5]);

        $this->get(route('sales-orders.edit', $order))->assertSee('<input type="hidden" name="lines[0][unit_price]" value="42.5">', false);
        $this->put(route('sales-orders.update', $order), $this->payload($order, ['ordered_qty' => 11, 'unit_price' => '42.5']))->assertSessionHasNoErrors();

        $this->assertEquals(42.5, $order->lines()->first()->unit_price);
    }

    public function test_a_confirmed_order_needs_a_reason_which_is_kept_in_the_log(): void
    {
        $order = $this->order('CONFIRMED');

        $this->put(route('sales-orders.update', $order), $this->payload($order, ['ordered_qty' => 12]))->assertSessionHasErrors('reason');
        $this->put(route('sales-orders.update', $order), $this->payload($order, ['ordered_qty' => 12], ['reason' => 'Customer asked for more']))->assertSessionHasNoErrors();

        $this->assertSame('Customer asked for more', AuditLog::where('entity_id', $order->id)->where('action', 'EDITED')->firstOrFail()->reason);
    }

    public function test_a_line_can_be_added_and_a_line_without_production_removed(): void
    {
        $order = $this->order();
        $second = ProductRevision::create(['product_id' => Product::create(['code' => 'Q1', 'name' => 'Other'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $payload = $this->payload($order);
        $payload['lines'][] = ['product_revision_id' => $second->id, 'ordered_qty' => 4];

        $this->put(route('sales-orders.update', $order), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, $order->lines()->count());

        $payload['lines'][0]['remove'] = 1;
        $payload['lines'][1] = ['id' => $order->lines()->where('line_number', 2)->value('id'), 'ordered_qty' => 4];
        $this->put(route('sales-orders.update', $order), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, $order->lines()->count());
    }

    public function test_a_line_with_production_cannot_drop_below_what_is_scheduled_or_be_removed(): void
    {
        $order = $this->order('IN_PRODUCTION');
        $line = $order->lines()->first();
        ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'sales_order_line_id' => $line->id, 'product_id' => $line->product_id, 'product_revision_id' => $line->product_revision_id,
            'order_type' => 'CUSTOMER', 'status' => 'RELEASED', 'priority' => 'NORMAL', 'planned_qty' => 10, 'released_qty' => 0, 'completed_qty' => 0, 'rejected_qty' => 0, 'uom' => 'piece']);

        $page = $this->get(route('sales-orders.edit', $order))->assertOk()->assertSee('Reason for the change')->assertSee('Has production orders')->getContent();
        $this->assertSame(1, substr_count($page, 'data-remove aria-label="Remove line"'), 'only the blank line template can be removed');
        $this->put(route('sales-orders.update', $order), $this->payload($order, ['ordered_qty' => 5], ['reason' => 'Reduce the order']))->assertSessionHasErrors('order');
        $this->put(route('sales-orders.update', $order), $this->payload($order, ['ordered_qty' => 20], ['reason' => 'Increase the order']))->assertSessionHasNoErrors();
        $this->assertEquals(20, $line->refresh()->ordered_qty);
    }

    public function test_a_closed_order_cannot_be_edited(): void
    {
        $order = $this->order('CANCELLED');

        $this->get(route('sales-orders.edit', $order))->assertRedirect(route('sales-orders.show', $order));
        $this->put(route('sales-orders.update', $order), $this->payload($order, [], ['reason' => 'Trying anyway']))->assertSessionHasErrors('order');
    }
}
