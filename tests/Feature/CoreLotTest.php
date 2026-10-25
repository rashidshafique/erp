<?php

namespace Tests\Feature;

use App\Domains\Tooling\Support\CoreRules;
use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\CoreLot;
use App\Models\ToolingUsageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreLotTest extends TestCase
{
    use RefreshDatabase;

    private CoreDefinition $core;

    private CoreBox $box;

    protected function setUp(): void
    {
        parent::setUp();
        $this->core = CoreDefinition::create(['name' => 'Jacket core', 'core_type' => 'STANDARD']);
        $this->box = CoreBox::create(['core_definition_id' => $this->core->id, 'name' => 'Jacket box', 'cavities' => 2]);
    }

    private function make(array $over = []): CoreLot
    {
        $this->post(route('core-lots.store'), $over + ['core_definition_id' => $this->core->id, 'core_box_id' => $this->box->id, 'quantity_made' => 10, 'made_on' => today()->format('Y-m-d')])->assertSessionHasNoErrors();

        return CoreLot::orderByDesc('lot_number')->firstOrFail();
    }

    public function test_cores_made_become_a_numbered_lot_and_count_against_the_box(): void
    {
        $lot = $this->make();

        $this->assertSame('MADE', $lot->status);
        $this->assertSame('CL-'.today()->format('ymd').'-01', $lot->lot_number);
        $this->assertSame(10, $lot->remaining());
        $usage = ToolingUsageRecord::where('core_box_id', $this->box->id)->firstOrFail();
        $this->assertSame(10, (int) $usage->usage_quantity);
        $this->assertSame('CORES', $usage->usage_unit);
        $this->assertSame('CL-'.today()->format('ymd').'-02', $this->make()->lot_number);
    }

    public function test_a_box_can_only_make_cores_it_has_a_cavity_for(): void
    {
        $other = CoreDefinition::create(['name' => 'Other core', 'core_type' => 'STANDARD']);

        $this->post(route('core-lots.store'), ['core_definition_id' => $other->id, 'core_box_id' => $this->box->id, 'quantity_made' => 5, 'made_on' => today()->format('Y-m-d')])->assertSessionHasErrors('core_box_id');

        $this->assertSame(0, CoreLot::count());
    }

    public function test_cores_used_straight_away_leave_nothing_on_the_shelf(): void
    {
        $lot = $this->make(['use_now' => 1]);

        $this->assertSame('USED_UP', $lot->status);
        $this->assertSame(0, $lot->remaining());
        $this->assertSame(10, $lot->uses()->where('kind', 'USED')->sum('quantity'));
    }

    public function test_cores_kept_for_later_are_stored_and_used_in_parts(): void
    {
        $lot = $this->make(['location' => 'Rack 3']);
        $this->assertSame('STORED', $lot->status);

        $this->post(route('core-lots.take', $lot), ['quantity' => 4, 'kind' => 'USED', 'used_on' => today()->format('Y-m-d')])->assertSessionHasNoErrors();
        $this->assertSame(6, $lot->remaining());
        $this->assertSame('STORED', $lot->fresh()->status);

        $this->post(route('core-lots.take', $lot), ['quantity' => 6, 'kind' => 'SCRAPPED', 'used_on' => today()->format('Y-m-d')]);
        $this->assertSame('USED_UP', $lot->fresh()->status);
    }

    public function test_more_cannot_be_taken_than_is_left_or_before_the_cores_were_made(): void
    {
        $lot = $this->make();

        $this->post(route('core-lots.take', $lot), ['quantity' => 11, 'kind' => 'USED', 'used_on' => today()->format('Y-m-d')])->assertSessionHasErrors('quantity');
        $this->post(route('core-lots.take', $lot), ['quantity' => 1, 'kind' => 'USED', 'used_on' => today()->subDay()->format('Y-m-d')])->assertSessionHasErrors('used_on');

        $this->assertSame(10, $lot->remaining());
    }

    public function test_when_checks_are_on_a_lot_must_pass_before_use_but_can_still_be_scrapped(): void
    {
        CoreRules::set('qc', 'REQUIRED');
        $lot = $this->make();
        $this->assertSame('PENDING', $lot->qc_status);
        $take = ['quantity' => 2, 'kind' => 'USED', 'used_on' => today()->format('Y-m-d')];

        $this->post(route('core-lots.take', $lot), $take)->assertSessionHasErrors('quantity');
        $this->put(route('core-lots.check', $lot), ['result' => 'FAILED'])->assertSessionHasNoErrors();
        $this->post(route('core-lots.take', $lot), $take)->assertSessionHasErrors('quantity');
        $this->post(route('core-lots.take', $lot), ['kind' => 'SCRAPPED'] + $take)->assertSessionHasNoErrors();

        $this->put(route('core-lots.check', $lot), ['result' => 'PASSED']);
        $this->post(route('core-lots.take', $lot), $take)->assertSessionHasNoErrors();
        $this->assertSame(6, $lot->remaining());
    }

    public function test_when_a_place_is_required_a_lot_not_used_at_once_needs_one(): void
    {
        CoreRules::set('location', 'REQUIRED');

        $this->post(route('core-lots.store'), ['core_definition_id' => $this->core->id, 'core_box_id' => $this->box->id, 'quantity_made' => 5, 'made_on' => today()->format('Y-m-d')])->assertSessionHasErrors('location');
        $this->assertSame(0, CoreLot::count());

        $this->make(['use_now' => 1]);
        $this->assertSame(1, CoreLot::count());
    }

    public function test_stock_pages_show_cores_on_hand_without_unchecked_lots(): void
    {
        $this->make(['location' => 'Rack 1', 'quantity_made' => 7]);
        CoreRules::set('qc', 'REQUIRED');
        $this->make(['quantity_made' => 3]);

        $this->get(route('core-lots.index'))->assertOk()->assertSee('Jacket core');
        $this->assertSame([$this->core->id => 7], app(\App\Domains\Tooling\Services\CoreLotService::class)->onHand());
        $this->get(route('cores.index'))->assertOk();
        $this->get(route('core-lots.create'))->assertOk();
        $this->get(route('settings.cores.edit'))->assertOk();
    }
}
