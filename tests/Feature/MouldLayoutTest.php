<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MouldCavity;
use App\Models\MouldConfiguration;
use App\Models\MouldingRun;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Services\PatternCavityMap;
use App\Services\Planning\HeatPlanner;
use App\Services\Production\MouldAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MouldLayoutTest extends TestCase
{
    use RefreshDatabase;

    /** A pattern revision with a layout of $cavities identical piston-ring-like castings, 0.5 kg each with 0.1 kg own gating. */
    private function revision(string $type, int $cavities, ?ProductRevision $product = null): PatternRevision
    {
        $product ??= ProductRevision::create(['product_id' => Product::create(['code' => 'RING-'.uniqid(), 'name' => 'Ring'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $pattern = Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Ring plate', 'pattern_type' => $type]);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => $cavities]);
        for ($n = 1; $n <= $cavities; $n++) {
            $revision->cavityMap()->create(['cavity_number' => $n, 'product_revision_id' => $product->id, 'casting_weight_kg' => 0.5, 'poured_weight_kg' => 0.6]);
        }

        return $revision;
    }

    private function mouldingRun(PatternRevision $revision, int $accepted): MouldingRun
    {
        $productRevision = $revision->cavityMap()->first()->productRevision;
        $order = ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'product_id' => $productRevision->product_id, 'product_revision_id' => $productRevision->id, 'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 1000, 'uom' => 'piece']);

        return MouldingRun::create(['run_number' => 'MR-'.uniqid(), 'production_order_id' => $order->id, 'pattern_revision_id' => $revision->id, 'run_date' => today(), 'status' => 'COMPLETED', 'moulds_made_qty' => $accepted, 'accepted_mould_qty' => $accepted]);
    }

    public function test_a_stack_pattern_takes_its_arrangement_from_the_pattern_type(): void
    {
        $revision = $this->revision('STACK_PLATE', 4);

        $this->assertSame(['STACK', 1], [$revision->fresh()->mould_arrangement, $revision->fresh()->castingLevels()]);
    }

    public function test_a_stack_of_eight_sections_holds_the_layout_seven_times_and_shares_its_sprue(): void
    {
        $revision = $this->revision('STACK_PLATE', 4);

        $this->put(route('patterns.revisions.layout', [$revision->pattern, $revision]), [
            'mould_arrangement' => 'STACK', 'layers_per_mould' => 8, 'casting_levels' => '', 'cavities_per_mould' => 4,
            'runner_weight_per_level_kg' => 2, 'sprue_weight_kg' => 10,
        ])->assertSessionHasNoErrors();

        $revision = $revision->fresh();
        $metal = app(PatternCavityMap::class)->metalPerMould($revision);
        $this->assertSame(7, $revision->castingLevels());
        $this->assertSame(28.0, app(PatternCavityMap::class)->expectedProductsPerMould($revision, $revision->cavityMap()->first()->productRevision->product_id));
        $this->assertSame([40.8, 14.0, 2.8, 14.0, 10.0, 28, 34.3, true], [$metal['kg'], $metal['castings_kg'], $metal['own_gating_kg'], $metal['runners_kg'], $metal['sprue_kg'], $metal['castings'], $metal['yield'], $metal['complete']]);
        $this->assertEqualsWithDelta(40.8 / 28, app(HeatPlanner::class)->metalPerProduct($revision->cavityMap()->first()->productRevision)['kg'], 0.001);
        $this->get(route('patterns.show', $revision->pattern))->assertOk()->assertSee('28 castings per mould')->assertSee('40.8 kg');
    }

    public function test_a_weighed_stack_replaces_the_sum(): void
    {
        $revision = $this->revision('STACK_PLATE', 4);
        $revision->update(['layers_per_mould' => 8, 'casting_levels' => 7, 'sprue_weight_kg' => 10, 'poured_weight_per_mould_kg' => 45]);

        $metal = app(PatternCavityMap::class)->metalPerMould($revision->fresh());

        $this->assertSame([45.0, true, 31.1], [$metal['kg'], $metal['weighed'], $metal['yield']]);
    }

    public function test_casting_levels_cannot_exceed_the_joints_between_flasks(): void
    {
        $revision = $this->revision('STACK_PLATE', 4);

        $this->put(route('patterns.revisions.layout', [$revision->pattern, $revision]), ['mould_arrangement' => 'STACK', 'layers_per_mould' => 6, 'casting_levels' => 6])
            ->assertSessionHasErrors(['casting_levels' => 'A mould of 6 flask sections has at most 5 joints holding castings: the bottom and top flasks close it.']);
        $this->put(route('patterns.revisions.layout', [$revision->pattern, $revision]), ['mould_arrangement' => 'VERTICAL', 'layers_per_mould' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, $revision->fresh()->castingLevels());
    }

    public function test_a_cope_and_drag_mould_holds_one_set_of_castings_and_a_stack_one_per_joint(): void
    {
        $configuration = MouldConfiguration::create(['code' => 'MC-1', 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);
        $copeDrag = $this->revision('COPE_DRAG', 2);
        $stack = $this->revision('STACK_PLATE', 4);
        $stack->update(['layers_per_mould' => 8, 'casting_levels' => 7]);

        $one = app(MouldAssembler::class)->assemble($this->mouldingRun($copeDrag, 1), 1, $configuration->id, 2)->first();
        $full = app(MouldAssembler::class)->assemble($this->mouldingRun($stack->fresh(), 2), 1, $configuration->id, 8)->first();
        $short = app(MouldAssembler::class)->assemble($this->mouldingRun($stack->fresh(), 2), 1, $configuration->id, 4)->first();

        $count = fn ($assembly) => MouldCavity::whereHas('layer', fn ($q) => $q->where('mould_assembly_id', $assembly->id))->count();
        $this->assertSame([2, 28, 12], [$count($one), $count($full), $count($short)]);
    }

    public function test_a_layout_corrected_after_assembly_needs_a_reason_and_assembled_moulds_keep_their_castings(): void
    {
        $configuration = MouldConfiguration::create(['code' => 'MC-1', 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);
        $revision = $this->revision('COPE_DRAG', 2);
        app(MouldAssembler::class)->assemble($this->mouldingRun($revision, 1), 1, $configuration->id, 2);
        $layout = ['mould_arrangement' => 'STACK', 'layers_per_mould' => 6, 'cavities_per_mould' => 2];

        $this->put(route('patterns.revisions.layout', [$revision->pattern, $revision]), $layout)
            ->assertSessionHasErrors(['correction_reason' => "1 mould was assembled with the old layout of {$revision->code}. Say why the layout is being corrected; those moulds keep the castings they recorded."]);
        $this->assertSame('COPE_DRAG', $revision->fresh()->mould_arrangement);

        $this->put(route('patterns.revisions.layout', [$revision->pattern, $revision]), $layout + ['correction_reason' => 'Plate is a stack plate, entered wrongly'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', "Mould layout of {$revision->code} corrected. The 1 mould already assembled keep the castings they recorded.");

        $this->assertSame(['STACK', 5], [$revision->fresh()->mould_arrangement, $revision->fresh()->castingLevels()]);
        $this->assertSame(2, MouldCavity::where('pattern_revision_id', $revision->id)->count(), 'the assembled mould keeps its two castings');
        $log = AuditLog::where('entity_type', 'PatternRevision')->where('entity_id', $revision->id)->where('reason', 'like', 'Layout corrected after 1 assembled mould: Plate is a stack plate%')->sole();
        $this->assertEquals(['old' => 'COPE_DRAG', 'new' => 'STACK'], json_decode($log->changes, true)['mould_arrangement']);
    }

    public function test_a_new_revision_carries_the_layout_unless_changed(): void
    {
        $revision = $this->revision('STACK_PLATE', 4);
        $revision->update(['layers_per_mould' => 8, 'casting_levels' => 7, 'sprue_weight_kg' => 10]);

        $this->post(route('patterns.revisions.store', $revision->pattern), ['change_description' => 'Taller stack', 'mould_arrangement' => 'STACK', 'layers_per_mould' => 10, 'cavities_per_mould' => 4, 'copy_layout' => '1'])
            ->assertSessionHasNoErrors();

        $new = $revision->pattern->currentRevision()->sole();
        $this->assertSame(['STACK', 10, 9, '10.0000', 4], [$new->mould_arrangement, $new->layers_per_mould, $new->casting_levels, $new->sprue_weight_kg, $new->cavityMap()->count()]);
    }

    public function test_the_pattern_page_leads_with_the_name_and_draws_the_mould(): void
    {
        $revision = $this->revision('STACK_PLATE', 4);
        $revision->update(['layers_per_mould' => 8, 'casting_levels' => 7]);
        $bare = Pattern::create(['code' => 'P-0999', 'name' => 'Unrevised plate']);

        $page = $this->get(route('patterns.show', $revision->pattern));

        $page->assertOk()->assertSeeInOrder(['<h1>Ring plate</h1>', 'Code: <strong>'.$revision->pattern->code.'</strong>'], false)
            ->assertSee('aria-label="Stack mould (sections stacked on one sprue): 7 levels of 4 cavities, 28 castings per mould"', false)
            ->assertSee('28 castings per mould');
        $this->get(route('patterns.show', $bare))->assertOk()->assertSee('no layout yet')->assertSee('Recorded with the first cavity map');
    }
}
