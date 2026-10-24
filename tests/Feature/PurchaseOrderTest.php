<?php

namespace Tests\Feature;

use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Purchasing\Support\BuyingRules;
use App\Models\Material;
use App\Models\MaterialLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(string $status = 'APPROVED'): Supplier
    {
        return Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => $status]);
    }

    private function material(): Material
    {
        return Material::create(['code' => 'M-'.uniqid(), 'name' => 'Pig iron', 'material_type' => 'METAL', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => true, 'requires_lot_tracking' => true]);
    }

    private function issued(Supplier $supplier, Material $material, float $qty = 100, float $price = 50, float $tax = 10): PurchaseOrder
    {
        $this->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id, 'order_date' => '2026-10-01',
            'lines' => [['material_id' => $material->id, 'description' => 'Pig iron', 'ordered_qty' => $qty, 'unit_price' => $price, 'tax_percent' => $tax, 'uom' => 'kg']],
        ])->assertSessionHasNoErrors();
        $order = PurchaseOrder::where('status', 'DRAFT')->firstOrFail();
        $this->post(route('purchase-orders.issue', $order))->assertSessionHasNoErrors();

        return $order->fresh('lines');
    }

    public function test_an_order_is_priced_taxed_and_numbered_when_issued(): void
    {
        $order = $this->issued($this->supplier(), $this->material(), 100, 50, 10);

        $this->assertSame('ISSUED', $order->status);
        $this->assertMatchesRegularExpression('/^PO-\d{4}-0001$/', $order->po_number);
        $this->assertEquals(5000.0, $order->subtotal);
        $this->assertEquals(500.0, $order->tax_total);
        $this->assertEquals(5500.0, $order->total);
    }

    public function test_a_blocked_supplier_cannot_be_ordered_from(): void
    {
        $this->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier('SUSPENDED')->id, 'order_date' => '2026-10-01',
            'lines' => [['description' => 'Pig iron', 'ordered_qty' => 1, 'unit_price' => 1]],
        ])->assertSessionHasErrors('order');

        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_receiving_makes_a_priced_quarantine_lot_and_moves_the_order_on(): void
    {
        $material = $this->material();
        $order = $this->issued($this->supplier(), $material, 100, 50);
        $line = $order->lines->first();

        $this->post(route('purchase-orders.receive', $order), ['qty' => [$line->id => 40], 'supplier_lot_number' => 'DN-7'])->assertSessionHasNoErrors();

        $lot = MaterialLot::firstOrFail();
        $this->assertSame('QUARANTINE', $lot->status);
        $this->assertSame($material->id, $lot->material_id);
        $this->assertSame($line->id, $lot->purchase_order_line_id);
        $this->assertEquals(40, $lot->received_qty);
        $this->assertEquals(50, $lot->unit_cost);
        $this->assertSame('DN-7', $lot->supplier_lot_number);
        $this->assertSame('PART_RECEIVED', $order->fresh()->status);

        $this->post(route('purchase-orders.receive', $order), ['qty' => [$line->id => 60]])->assertSessionHasNoErrors();
        $this->assertSame('RECEIVED', $order->fresh()->status);
        $this->assertSame(2, MaterialLot::count());
    }

    public function test_receiving_more_than_ordered_follows_the_over_receipt_rule(): void
    {
        $order = $this->issued($this->supplier(), $this->material(), 100);
        $line = $order->lines->first();

        $this->post(route('purchase-orders.receive', $order), ['qty' => [$line->id => 111]])->assertSessionHasErrors('order');
        $this->post(route('purchase-orders.receive', $order), ['qty' => [$line->id => 110]])->assertSessionHasNoErrors();

        BuyingRules::set('over_receipt', 'BLOCK');
        $second = $this->issued($this->supplier(), $this->material(), 100);
        $this->post(route('purchase-orders.receive', $second), ['qty' => [$second->lines->first()->id => 101]])->assertSessionHasErrors('order');
    }

    public function test_a_draft_cannot_receive_and_a_closed_order_stops_receiving(): void
    {
        $this->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier()->id, 'order_date' => '2026-10-01',
            'lines' => [['description' => 'Freight', 'ordered_qty' => 1, 'unit_price' => 10]],
        ]);
        $draft = PurchaseOrder::firstOrFail();
        $this->post(route('purchase-orders.receive', $draft), ['qty' => [$draft->lines()->first()->id => 1]])->assertSessionHasErrors('order');

        $order = $this->issued($this->supplier(), $this->material());
        $this->post(route('purchase-orders.close', $order), ['reason' => 'Supplier cancelled'])->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->post(route('purchase-orders.receive', $order), ['qty' => [$order->lines->first()->id => 1]])->assertSessionHasErrors('order');
    }

    public function test_a_free_line_is_counted_without_making_a_lot(): void
    {
        $this->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier()->id, 'order_date' => '2026-10-01',
            'lines' => [['description' => 'Freight', 'ordered_qty' => 1, 'unit_price' => 10]],
        ]);
        $order = PurchaseOrder::firstOrFail();
        $this->post(route('purchase-orders.issue', $order));

        $this->post(route('purchase-orders.receive', $order), ['qty' => [$order->lines()->first()->id => 1]])->assertSessionHasNoErrors();

        $this->assertSame('RECEIVED', $order->fresh()->status);
        $this->assertSame(0, MaterialLot::count());
    }

    public function test_the_screens_open(): void
    {
        $order = $this->issued($this->supplier(), $this->material());

        $this->get(route('purchase-orders.index'))->assertOk()->assertSee($order->po_number);
        $this->get(route('purchase-orders.create'))->assertOk();
        $this->get(route('purchase-orders.show', $order))->assertOk()->assertSee('Receive goods');
        $this->get(route('purchase-orders.print', $order))->assertOk();
        $this->get(route('purchase-orders.settings.edit'))->assertOk();
    }
}
