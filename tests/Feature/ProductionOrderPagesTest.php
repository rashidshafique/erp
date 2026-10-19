<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\MouldingRun;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class ProductionOrderPagesTest extends TestCase
{
    use BuildsCastingLots;
    use RefreshDatabase;

    /** @return array{product: Product, revision: ProductRevision} */
    private function product(): array
    {
        $product = Product::create(['code' => 'FLG-1', 'name' => 'Flange', 'active' => true]);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);

        return compact('product', 'revision');
    }

    private function draft(array $p, array $over = []): ProductionOrder
    {
        return ProductionOrder::create($over + [
            'production_order_number' => 'PO-T-'.uniqid(), 'product_id' => $p['product']->id, 'product_revision_id' => $p['revision']->id,
            'order_type' => 'STOCK', 'status' => 'DRAFT', 'priority' => 'NORMAL', 'planned_qty' => 100, 'uom' => 'piece',
        ]);
    }

    public function test_an_order_is_created_shown_and_edited_while_draft(): void
    {
        $p = $this->product();

        $this->get(route('production-orders.create'))->assertOk();
        $this->post(route('production-orders.store'), [
            'product_revision_id' => $p['revision']->id, 'order_type' => 'STOCK', 'priority' => 'NORMAL', 'planned_qty' => 50, 'uom' => 'piece',
            'planned_start_date' => '2026-10-20', 'planned_finish_date' => '2026-10-25',
        ])->assertSessionHasNoErrors();
        $order = ProductionOrder::sole();
        $this->assertSame('2026-10-25', $order->planned_finish_date->toDateString());      // was silently dropped before

        $this->get(route('production-orders.show', $order))->assertOk()->assertSee($order->production_order_number)->assertSee('Flange');
        $this->get(route('production-orders.edit', $order))->assertOk();
        $this->put(route('production-orders.update', $order), [
            'product_revision_id' => $p['revision']->id, 'order_type' => 'STOCK', 'priority' => 'HIGH', 'planned_qty' => 75, 'uom' => 'piece',
        ])->assertSessionHasNoErrors();
        $this->assertSame(75.0, (float) $order->fresh()->planned_qty);

        $order->update(['status' => 'RELEASED']);
        $this->put(route('production-orders.update', $order), [
            'product_revision_id' => $p['revision']->id, 'order_type' => 'STOCK', 'priority' => 'HIGH', 'planned_qty' => 10, 'uom' => 'piece',
        ])->assertSessionHasErrors('order');
        $this->assertSame(75.0, (float) $order->fresh()->planned_qty);
        $this->get(route('production-orders.index'))->assertOk()->assertSee($order->production_order_number);
    }

    public function test_an_order_can_be_cancelled_with_a_reason_until_castings_are_poured(): void
    {
        $order = $this->draft($this->product(), ['status' => 'RELEASED']);

        $this->post(route('production-orders.cancel', $order))->assertSessionHasErrors('reason');

        $run = MouldingRun::create(['run_number' => 'MR-T1', 'production_order_id' => $order->id, 'run_date' => today(), 'status' => 'IN_PROGRESS', 'moulds_made_qty' => 0, 'accepted_mould_qty' => 0]);
        $this->post(route('production-orders.cancel', $order), ['reason' => 'Customer withdrew'])->assertSessionHasErrors('order');   // open run
        $this->assertSame('RELEASED', $order->fresh()->status);

        $run->update(['status' => 'CANCELLED']);
        $this->post(route('production-orders.cancel', $order), ['reason' => 'Customer withdrew'])->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertStringContainsString('Customer withdrew', $order->fresh()->notes);
        $this->post(route('production-orders.cancel', $order), ['reason' => 'again'])->assertSessionHasErrors('order');
    }

    public function test_an_order_with_poured_castings_cannot_be_cancelled(): void
    {
        $lot = $this->castingLot();

        $this->post(route('production-orders.cancel', $lot->productionOrder), ['reason' => 'Mistake'])->assertSessionHasErrors('order');
        $this->assertSame('IN_PROGRESS', $lot->productionOrder->fresh()->status);
        $this->assertSame(1, CastingLot::count());
        $this->get(route('production-orders.show', $lot->productionOrder))->assertOk()->assertSee($lot->lot_number);
    }
}
