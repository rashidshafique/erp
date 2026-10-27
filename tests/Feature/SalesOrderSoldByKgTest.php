<?php

namespace Tests\Feature;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Services\Orders\ProductionOrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderSoldByKgTest extends TestCase
{
    use RefreshDatabase;

    private \Illuminate\Testing\TestResponse $saved;

    /** Saves an order of 100 kg of a 3.4 kg casting and returns its line. */
    private function kgLine(?float $weight = 3.4): SalesOrderLine
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $product = Product::create(['code' => 'GB-1', 'name' => 'Grinding ball', 'base_uom' => 'kg', 'nominal_weight_kg' => $weight]);
        $rev = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $this->saved = $this->post(route('sales-orders.store'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'lines' => [
            ['product_revision_id' => $rev->id, 'uom' => 'kg', 'ordered_qty' => 100, 'unit_price' => 2.1],
        ]]);

        return SalesOrderLine::first() ?? new SalesOrderLine;
    }

    private function editLine(SalesOrderLine $line, array $row): \Illuminate\Testing\TestResponse
    {
        $order = SalesOrder::findOrFail($line->sales_order_id);

        return $this->put(route('sales-orders.update', $order), ['customer_id' => $order->customer_id, 'order_date' => $order->order_date->toDateString(), 'reason' => 'Customer revised the weight',
            'lines' => [$row + ['id' => $line->id, 'uom' => 'kg', 'unit_price' => 2.1]]]);
    }

    public function test_a_kg_line_is_made_as_the_whole_pieces_its_weight_needs(): void
    {
        $line = $this->kgLine();
        $this->assertSame('kg', $line->uom);

        $po = app(ProductionOrderFactory::class)->releaseForLine($line);

        $this->assertSame([30.0, 'piece'], [(float) $po->planned_qty, $po->uom], '100 kg / 3.4 kg is 29.4, rounded up');
        $this->assertNull(app(ProductionOrderFactory::class)->releaseForLine($line->fresh()), '30 pieces make 102 kg, so nothing is left to schedule');
        $this->get(route('sales-orders.show', $line->sales_order_id))->assertOk()->assertSee('≈ 102 kg')->assertDontSee('+ Production Order');
    }

    public function test_a_kg_line_needs_the_casting_weight(): void
    {
        $this->kgLine(null);

        $this->saved->assertSessionHasErrors(['lines' => 'Line 1 is sold by kg, but Grinding ball has no casting weight. Set its nominal weight on the product, or sell it by pieces.']);
        $this->assertSame(0, SalesOrder::count());
    }

    public function test_released_pieces_set_how_far_a_kg_line_can_go_down_and_lock_its_unit(): void
    {
        $line = $this->kgLine();
        app(ProductionOrderFactory::class)->releaseForLine($line);

        $this->editLine($line, ['ordered_qty' => 99])->assertSessionHasNoErrors();   // still needs all 30 pieces
        $this->editLine($line, ['ordered_qty' => 98])->assertSessionHasErrors('order'); // would need only 29
        $this->editLine($line, ['ordered_qty' => 99, 'uom' => 'piece'])->assertSessionHasErrors('order');

        $this->assertSame([99.0, 'kg'], [(float) $line->fresh()->ordered_qty, $line->fresh()->uom]);
        $this->assertSame(1, ProductionOrder::count());
    }
}
