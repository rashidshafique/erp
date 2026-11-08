<?php

namespace Tests\Feature;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Models\CastingLot;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

/** The quality tablet (design Q·2 to Q·5): the lot queue, the lot, the counted inspection and the path the customer's rule picks. */
class QualityTabletTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    private function lotFor(bool $partials, ?int $min = null): CastingLot
    {
        $lot = $this->castingLot();
        $customer = Customer::create(['code' => 'GP'.uniqid(), 'name' => 'Golden Pumps', 'allows_partial_shipments' => $partials]);
        $order = SalesOrder::create(['order_number' => 'SO-'.uniqid(), 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'CONFIRMED']);
        $line = SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $lot->product_id, 'product_revision_id' => $lot->product_revision_id, 'line_number' => 1, 'ordered_qty' => 5, 'uom' => 'piece', 'unit_price' => 30, 'min_ship_qty' => $min]);
        $lot->productionOrder->update(['sales_order_line_id' => $line->id]);

        return $lot->fresh();
    }

    public function test_the_screens_open_and_the_lot_shows_the_final_inspection_blocker(): void
    {
        $lot = $this->lotFor(true);

        $this->get(route('floor.quality.lots'))->assertOk()->assertSee($lot->label)->assertSee('Final inspection');
        $this->get(route('floor.quality.lot', $lot))->assertOk()->assertSee('What blocks dispatch')->assertSee('partial shipments allowed')->assertSee('Inspect');
        $this->get(route('floor.quality.lot.inspect', $lot))->assertOk()->assertSee('Accepted');
    }

    public function test_a_customer_taking_partials_has_the_accepted_pieces_released_while_the_ncr_stays_open(): void
    {
        $lot = $this->lotFor(true);
        $pick = ['accepted' => 3, 'rework' => 1, 'rejected' => 1];

        $this->post(route('floor.quality.lot.review', $lot), $pick)->assertOk()->assertSee('Release 3')->assertSee('does not block the 3 accepted');
        $this->post(route('floor.quality.lot.inspect.store', $lot), $pick)->assertSessionHasNoErrors();

        $lot = $lot->fresh();
        $this->assertSame('READY', $lot->status);
        $this->assertSame(1, $lot->ncrs()->where('status', 'OPEN')->count());
        $this->assertTrue(app(\App\Services\QualityGates::class)->lotReadiness($lot)['ok']);
    }

    public function test_a_customer_taking_full_lots_only_has_the_lot_held_and_it_clears_when_the_ncr_closes(): void
    {
        $lot = $this->lotFor(false, 4);
        $pick = ['accepted' => 3, 'rework' => 1, 'rejected' => 1];

        $this->post(route('floor.quality.lot.review', $lot), $pick)->assertOk()->assertSee('Hold lot, waiting for rework')->assertSee('full lots only');
        $this->post(route('floor.quality.lot.inspect.store', $lot), $pick)->assertSessionHasNoErrors();

        $lot = $lot->fresh();
        $this->assertSame('ON_HOLD', $lot->status);
        $this->assertNotNull($lot->quality_hold);
        $ncr = $lot->ncrs()->firstOrFail();
        $ncr->update(['root_cause' => 'Sand', 'corrective_action' => 'Scrapped', 'disposition' => 'SCRAP']);

        $this->post(route('ncrs.close', $ncr), ['closed_by' => 'Bilal'])->assertSessionHasNoErrors();

        $lot = $lot->fresh();
        $this->assertSame('READY', $lot->status);
        $this->assertNull($lot->quality_hold);
    }

    public function test_counts_above_what_is_left_to_check_are_refused(): void
    {
        $lot = $this->lotFor(true);

        $this->post(route('floor.quality.lot.review', $lot), ['accepted' => 6])->assertSessionHasErrors('inspection');
    }

    public function test_only_roles_that_inspect_may_use_the_screens(): void
    {
        config(['foundry.require_login' => true]);
        $lot = $this->lotFor(true);
        $make = function (string $role): User {
            $u = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
            $u->roles()->attach(Role::where('code', $role)->value('id'));

            return $u;
        };

        $this->actingAs($make('SALES'))->get(route('floor.quality.lots'))->assertForbidden();
        $this->actingAs($make('QUALITY_INSPECTOR'))->get(route('floor.quality.lots'))->assertOk();
        $this->actingAs($make('QUALITY_INSPECTOR'))->post(route('floor.quality.lot.inspect.store', $lot), ['accepted' => 5])->assertSessionHasNoErrors();
    }

    public function test_a_save_that_no_longer_fits_goes_back_to_the_counting_screen(): void
    {
        $lot = $this->lotFor(true);

        $this->from(route('floor.quality.lot.review', $lot))->post(route('floor.quality.lot.inspect.store', $lot), ['accepted' => 9])
            ->assertRedirect(route('floor.quality.lot.inspect', $lot))->assertSessionHasErrors('inspection');
    }
}
