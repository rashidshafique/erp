<?php

namespace Tests\Feature;

use App\Domains\Tooling\Support\CoreRules;
use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\CoreLot;
use App\Models\CoreRequirement;
use App\Models\MouldingRun;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
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

    /** A run of $moulds moulds of a pattern with two cavities for one product that needs 2 of the box's core per casting. */
    private function mouldingRun(int $moulds, array $cavityNumbers = []): MouldingRun
    {
        $product = ProductRevision::create(['product_id' => Product::create(['code' => 'P-'.uniqid(), 'name' => 'Casting'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        CoreRequirement::create(['core_definition_id' => $this->core->id, 'product_revision_id' => $product->id, 'quantity_per_casting' => 2]);
        $revision = PatternRevision::create(['pattern_id' => Pattern::create(['code' => 'PT-'.uniqid(), 'name' => 'Plate'])->id, 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        foreach ([1, 2] as $n) {
            $revision->cavityMap()->create(['cavity_number' => $n, 'product_revision_id' => $product->id]);
        }
        $order = ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'product_id' => $product->product_id, 'product_revision_id' => $product->id, 'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 100, 'uom' => 'piece']);

        return MouldingRun::create(['run_number' => 'MR-'.uniqid(), 'production_order_id' => $order->id, 'pattern_revision_id' => $revision->id, 'run_date' => today(), 'status' => 'PLANNED', 'planned_mould_qty' => $moulds, 'cavity_numbers' => $cavityNumbers ?: null]);
    }

    public function test_a_run_shows_the_cores_its_moulds_need_and_what_is_short(): void
    {
        $run = $this->mouldingRun(3);
        $this->make(['quantity_made' => 5, 'location' => 'Rack 1']);

        $need = app(\App\Domains\Tooling\Services\CoreLotService::class)->needsForRun($run)[0];

        $this->assertSame([4, 12, 0, 5, 7], [$need['per_mould'], $need['needed'], $need['issued'], $need['on_hand'], $need['short']]);
        $this->get(route('moulding-runs.show', $run))->assertOk()->assertSee('7 short');
    }

    public function test_a_run_that_uses_some_cavities_needs_cores_for_those_only(): void
    {
        $run = $this->mouldingRun(2, [1]);

        $this->assertSame(4, app(\App\Domains\Tooling\Services\CoreLotService::class)->needsForRun($run)[0]['needed']);
    }

    public function test_cores_issued_to_a_run_come_from_the_oldest_lots_first(): void
    {
        $run = $this->mouldingRun(2);
        $old = $this->make(['quantity_made' => 3, 'made_on' => today()->subDays(2)->format('Y-m-d')]);
        $new = $this->make(['quantity_made' => 10]);

        $this->post(route('core-lots.issue-to-run', $run), ['core_definition_id' => $this->core->id, 'quantity' => 5])->assertSessionHasNoErrors();

        $this->assertSame(0, $old->remaining());
        $this->assertSame(8, $new->remaining());
        $this->assertSame(5, (int) \App\Models\CoreLotUse::where('moulding_run_id', $run->id)->sum('quantity'));
        $this->assertSame(5, app(\App\Domains\Tooling\Services\CoreLotService::class)->needsForRun($run)[0]['issued']);
    }

    public function test_issuing_more_than_can_be_used_issues_nothing(): void
    {
        $run = $this->mouldingRun(2);
        $lot = $this->make(['quantity_made' => 4]);

        $this->post(route('core-lots.issue-to-run', $run), ['core_definition_id' => $this->core->id, 'quantity' => 6])->assertSessionHasErrors('quantity');

        $this->assertSame(4, $lot->remaining());
    }

    public function test_core_stock_is_a_button_on_the_cores_page_not_a_menu_entry(): void
    {
        $html = $this->get(route('cores.index'))->assertOk()->assertSee('Core stock')->assertSee(route('core-lots.index'), false)->getContent();
        $menu = substr($html, 0, strpos($html, '<main') ?: strlen($html));

        $this->assertStringNotContainsString('Cores &amp; core boxes', $html);
        $this->assertStringNotContainsString('href="'.route('core-lots.index').'" class="nav-link', $menu);
        $this->get(route('core-lots.index'))->assertOk()->assertSee('nav-link active', false);
    }
}
