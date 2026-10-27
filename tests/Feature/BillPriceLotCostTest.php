<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\LotCostAdjustment;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Services\FinanceRules;
use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\Supplier;
use App\Models\AppSetting;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockLotCostLayer;
use App\Services\Stock\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillPriceLotCostTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrder $order;

    private MaterialLot $lot;

    /** 100 kg ordered and received at 50; 40 kg of it already issued to production. */
    protected function setUp(): void
    {
        parent::setUp();
        $supplier = Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        $material = Material::create(['code' => 'PIG', 'name' => 'Pig iron', 'material_type' => 'METAL', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => true, 'requires_lot_tracking' => true]);
        $this->post(route('purchase-orders.store'), ['supplier_id' => $supplier->id, 'order_date' => '2026-10-01',
            'lines' => [['material_id' => $material->id, 'description' => 'Pig iron', 'ordered_qty' => 100, 'unit_price' => 50, 'tax_percent' => 10, 'uom' => 'kg']]]);
        $this->order = PurchaseOrder::firstOrFail();
        $this->post(route('purchase-orders.issue', $this->order));
        $this->post(route('purchase-orders.receive', $this->order), ['qty' => [$this->order->lines()->first()->id => 100]])->assertSessionHasNoErrors();
        $this->lot = MaterialLot::sole();
        $this->lot->update(['status' => 'ACCEPTED', 'accepted_qty' => 100]);
        $ledger = app(StockLedger::class);
        $ledger->receive($this->lot, 100, 50, 'kg', ['receipt_lot_id' => $this->lot->id]);
        $ledger->issue($this->lot, 40, 'kg');
    }

    /** A bill from the order at this price, posted. */
    private function bill(float $price): SupplierBill
    {
        $line = $this->order->lines()->first();
        $before = SupplierBill::pluck('id');
        $this->post(route('bills.store'), ['supplier_id' => $this->order->supplier_id, 'purchase_order_id' => $this->order->id, 'bill_date' => '2026-10-05', 'supplier_bill_number' => 'S-'.uniqid(),
            'lines' => [['description' => 'Pig iron', 'quantity' => 100, 'unit_price' => $price, 'tax_percent' => 10, 'purchase_order_line_id' => $line->id]]])->assertSessionHasNoErrors();
        $bill = SupplierBill::whereNotIn('id', $before)->firstOrFail();
        $this->post(route('bills.post', $bill))->assertSessionHasNoErrors();

        return $bill;
    }

    public function test_the_billed_price_becomes_the_cost_of_what_is_left_and_the_difference_on_what_was_used_is_recorded(): void
    {
        $bill = $this->bill(52);

        $this->assertEquals(52, (float) $this->lot->fresh()->unit_cost);
        $this->assertEquals([52.0], StockLotCostLayer::where('remaining_qty', '>', 0)->pluck('unit_cost')->map(fn ($c) => (float) $c)->all());
        $change = LotCostAdjustment::sole();
        $this->assertSame([$bill->id, 50.0, 52.0, 60.0, 40.0, 80.0], [$change->supplier_bill_id, $change->from_unit_cost, $change->to_unit_cost, $change->quantity_on_hand, $change->quantity_used, $change->variance_on_used]);
        $this->get(route('bills.show', $bill))->assertOk()->assertSee('Lot cost changes');
    }

    public function test_voiding_the_bill_puts_the_order_price_back(): void
    {
        $bill = $this->bill(52);

        $this->post(route('bills.void', $bill), ['reason' => 'Wrong price'])->assertSessionHasNoErrors();

        $this->assertEquals(50, (float) $this->lot->fresh()->unit_cost);
        $this->assertSame(['BILLED', 'VOIDED'], LotCostAdjustment::orderBy('created_at')->orderBy('reason')->pluck('reason')->all());
    }

    public function test_a_bill_at_the_order_price_or_with_the_rule_off_leaves_the_cost_alone(): void
    {
        $this->bill(50);
        AppSetting::setValue(FinanceRules::BILL_COST, 'OFF');
        $this->bill(60);

        $this->assertEquals(50, (float) $this->lot->fresh()->unit_cost);
        $this->assertSame(0, LotCostAdjustment::count());
    }
}
