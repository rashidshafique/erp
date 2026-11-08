<?php

namespace Tests\Feature;

use App\Domains\Dispatch\Services\ShipmentService;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Models\CastingLot;
use App\Models\Customer;
use App\Models\NonconformanceReport;
use App\Services\Quality\ShipRule;
use App\Services\QualityGates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

/** A customer's partial-shipment rule: an open NCR holds only its own pieces when the customer allows partials, and the order line sets the minimum. */
class PartialShipmentTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    /** @return array{0: CastingLot, 1: SalesOrderLine, 2: NonconformanceReport} 5 poured: 3 accepted, 1 to rework, 1 rejected, one NCR for the 2 */
    private function heldLot(bool $customerAllows, ?bool $override = null, ?int $min = null): array
    {
        $lot = $this->castingLot();
        $customer = Customer::create(['code' => 'GP'.uniqid(), 'name' => 'Golden Pumps', 'allows_partial_shipments' => $customerAllows]);
        $order = SalesOrder::create(['order_number' => 'SO-'.uniqid(), 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'CONFIRMED']);
        $line = SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $lot->product_id, 'product_revision_id' => $lot->product_revision_id, 'line_number' => 1, 'ordered_qty' => 5,
            'uom' => 'piece', 'unit_price' => 30, 'partial_shipments' => $override, 'min_ship_qty' => $min]);
        $lot->productionOrder->update(['sales_order_line_id' => $line->id]);
        $lot->update(['status' => 'READY', 'quantity_good' => 4, 'quantity_rejected' => 1]);
        $inspection = DB::table('quality_inspections')->insertGetId(['id' => $id = (string) \Illuminate\Support\Str::uuid(), 'inspection_number' => 'QI-'.uniqid(), 'casting_lot_id' => $lot->id, 'inspection_type' => 'FINAL',
            'status' => 'COMPLETED', 'quantity_inspected' => 5, 'quantity_accepted' => 3, 'quantity_rejected' => 1, 'quantity_rework' => 1, 'quantity_pending' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $ncr = NonconformanceReport::create(['ncr_number' => 'NCR-'.uniqid(), 'casting_lot_id' => $lot->id, 'quality_inspection_id' => $id, 'title' => 'Blowhole', 'description' => 'x', 'severity' => 'MAJOR', 'status' => 'OPEN', 'quantity_affected' => 2, 'reported_at' => now()]);

        return [$lot->fresh(), $line, $ncr];
    }

    private function ready(CastingLot $lot): array
    {
        return app(QualityGates::class)->lotReadiness($lot->fresh());
    }

    public function test_by_default_an_open_ncr_still_holds_the_whole_lot(): void
    {
        [$lot] = $this->heldLot(false);

        $this->assertFalse($this->ready($lot)['ok']);
        $this->assertSame(0, app(ShipRule::class)->shippable($lot));
        $this->expectException(InvalidArgumentException::class);
        app(ShipmentService::class)->pack([$lot->id => 1], []);
    }

    public function test_when_partials_are_allowed_the_accepted_pieces_ship_and_no_more(): void
    {
        [$lot] = $this->heldLot(true);

        $r = $this->ready($lot);
        $this->assertTrue($r['ok'], implode(' ', $r['reasons']));
        $this->assertStringContainsString('does not block the 3 accepted', collect($r['checks'])->firstWhere('label', 'Nonconformance reports')['detail']);
        $this->assertSame(3, app(ShipRule::class)->shippable($lot));

        $this->assertSame(3, (int) app(ShipmentService::class)->pack([$lot->id => 3], [])->items()->sum('quantity_packed'));
        $this->expectException(InvalidArgumentException::class);
        app(ShipmentService::class)->pack([$lot->id => 1], []);
    }

    public function test_the_accepted_pieces_cannot_be_packed_beyond_the_accepted_count_in_one_go(): void
    {
        [$lot] = $this->heldLot(true);

        $this->expectException(InvalidArgumentException::class);
        app(ShipmentService::class)->pack([$lot->id => 4], []);
    }

    public function test_below_the_order_lines_minimum_the_lot_waits_and_the_reason_says_how_many(): void
    {
        [$lot] = $this->heldLot(true, null, 4);

        $r = $this->ready($lot);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('need 4', implode(' ', $r['reasons']));

        [$lot] = $this->heldLot(true, null, 3);
        $this->assertTrue($this->ready($lot)['ok']);
    }

    public function test_closing_the_ncr_releases_the_rework_pieces_so_the_minimum_can_be_met(): void
    {
        [$lot, , $ncr] = $this->heldLot(false, null, 4);
        $this->assertFalse($this->ready($lot)['ok']);

        $ncr->update(['status' => 'CLOSED', 'closed_at' => now()]);

        $this->assertTrue($this->ready($lot)['ok']);
        $this->assertSame(4, app(ShipRule::class)->shippable($lot->fresh()));
    }

    public function test_an_order_line_override_beats_the_customer_setting(): void
    {
        [$lot] = $this->heldLot(true, false);
        $this->assertFalse($this->ready($lot)['ok']);

        [$lot] = $this->heldLot(false, true);
        $this->assertTrue($this->ready($lot)['ok']);
    }

    public function test_a_lot_with_no_sales_line_keeps_the_plain_rule(): void
    {
        [$lot] = $this->heldLot(true);
        $lot->productionOrder->update(['sales_order_line_id' => null]);

        $this->assertFalse($this->ready($lot)['ok']);
    }

    public function test_the_order_page_saves_a_lines_shipping_rule(): void
    {
        [, $line] = $this->heldLot(false);
        $order = $line->salesOrder;

        $this->get(route('sales-orders.show', $order))->assertOk()->assertSee('Shipping rule');
        $this->put(route('sales-orders.lines.shipping', [$order, $line]), ['partial_shipments' => 'yes', 'min_ship_qty' => 103])->assertSessionHasNoErrors();
        $this->assertTrue($line->fresh()->partial_shipments);
        $this->assertSame(103, $line->fresh()->min_ship_qty);

        $this->put(route('sales-orders.lines.shipping', [$order, $line]), ['partial_shipments' => '', 'min_ship_qty' => ''])->assertSessionHasNoErrors();
        $this->assertNull($line->fresh()->partial_shipments);
        $this->assertNull($line->fresh()->min_ship_qty);
    }
}
