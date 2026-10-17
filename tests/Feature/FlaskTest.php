<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CastingLot;
use App\Models\Flask;
use App\Models\FlaskSize;
use App\Models\MouldAssembly;
use App\Models\MouldConfiguration;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\MouldLayer;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\PourMouldAssembly;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\ToolingMaintenanceRecord;
use App\Models\ToolingUsageRecord;
use App\Services\Production\FlaskManager;
use App\Services\Production\MouldAssembler;
use App\Support\FlaskRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FlaskTest extends TestCase
{
    use RefreshDatabase;

    private function makeSize(string $name): FlaskSize
    {
        return FlaskSize::create(['code' => FlaskSize::freeCode($name), 'name' => $name, 'status' => 'ACTIVE']);
    }

    /** @return Collection<int, Flask> */
    private function flasks(FlaskSize $size, string $position, int $n)
    {
        return collect(range(1, $n))->map(fn () => Flask::create(['flask_size_id' => $size->id, 'position' => $position, 'status' => 'AVAILABLE']));
    }

    /** A run of $accepted moulds on $station, of a pattern revision that fits $sizes (the first one is the designed-for size). */
    private function makeRun(array $sizes, ?MouldingStation $station = null, int $accepted = 2, string $arrangement = 'COPE_DRAG', int $sections = 2): MouldingRun
    {
        $product = ProductRevision::create(['product_id' => Product::create(['code' => 'P-'.uniqid(), 'name' => 'Casting'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $revision = PatternRevision::create(['pattern_id' => Pattern::create(['code' => 'PT-'.uniqid(), 'name' => 'Plate'])->id, 'status' => 'APPROVED', 'cavities_per_mould' => 1, 'mould_arrangement' => $arrangement, 'layers_per_mould' => $sections]);
        $revision->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $product->id]);
        foreach ($sizes as $i => $size) {
            $revision->flaskSizes()->attach($size->id, ['is_primary' => $i === 0]);
        }
        $order = ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'product_id' => $product->product_id, 'product_revision_id' => $product->id, 'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 100, 'uom' => 'piece']);

        return MouldingRun::create(['run_number' => 'MR-'.uniqid(), 'production_order_id' => $order->id, 'pattern_revision_id' => $revision->id, 'moulding_station_id' => $station?->id, 'run_date' => today(), 'status' => 'COMPLETED', 'moulds_made_qty' => $accepted, 'accepted_mould_qty' => $accepted]);
    }

    private function assemble(MouldingRun $run, int $count, int $layers = 2)
    {
        $configuration = MouldConfiguration::firstOrCreate(['code' => 'MC-1'], ['name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);

        return app(MouldAssembler::class)->assemble($run, $count, $configuration->id, $layers);
    }

    private function station(array $sizes = [], bool $flaskless = false): MouldingStation
    {
        $station = MouldingStation::create(['code' => 'ST-'.uniqid(), 'name' => 'Station', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 4, 'makes_flaskless' => $flaskless]);
        $station->flaskSizes()->sync(collect($sizes)->pluck('id'));

        return $station;
    }

    public function test_flasks_are_added_in_batches_with_listed_or_generated_marks(): void
    {
        $size = $this->makeSize('600 × 500');
        Pattern::create(['code' => 'PT-1', 'name' => 'Plate', 'mark_code' => 'TAKEN1']);

        $this->post(route('flasks.store'), ['flask_size_id' => $size->id, 'position' => 'DRAG', 'quantity' => 2, 'marks' => 'taken1 d002'])
            ->assertSessionHasErrors(['marks' => 'Already used: TAKEN1.']);
        $this->post(route('flasks.store'), ['flask_size_id' => $size->id, 'position' => 'DRAG', 'quantity' => 3, 'marks' => "d001\nd002", 'material' => 'Steel'])
            ->assertSessionHasNoErrors();

        $flasks = Flask::orderBy('code')->get();
        $this->assertSame(['D001', 'D002'], $flasks->take(2)->pluck('mark_code')->all());
        $this->assertNotNull($flasks[2]->mark_code);
        $this->assertSame(['AVAILABLE', 'DRAG', 'Steel'], [$flasks[0]->status, $flasks[0]->position, $flasks[0]->material]);
        $this->assertDatabaseHas('tool_marks', ['tool_type' => 'flask', 'tool_id' => $flasks[0]->id, 'mark_code' => 'D001']);
        $this->get(route('flasks.index'))->assertOk()->assertSee('600 × 500')->assertSee('class="free">3</td>', false);
        $this->get(route('flasks.show', $flasks[0]))->assertOk()->assertSee('D001');
        $this->get(route('labels.show', ['flask', $flasks[0]->id]))->assertOk()->assertSee('D001');
    }

    public function test_assembled_moulds_get_a_drag_and_a_cope_of_the_designed_for_size(): void
    {
        $big = $this->makeSize('800 × 600');
        $small = $this->makeSize('600 × 500');
        $this->flasks($big, 'DRAG', 2);
        $this->flasks($big, 'COPE', 2);
        $this->flasks($small, 'ANY', 4);
        $run = $this->makeRun([$small, $big]);

        $moulds = $this->assemble($run, 2);

        foreach ($moulds as $mould) {
            $this->assertSame($small->id, $mould->fresh()->flask_size_id);
            $layers = MouldLayer::where('mould_assembly_id', $mould->id)->with('flask')->orderBy('layer_number')->get();
            $this->assertSame(['IN_MOULD', 'IN_MOULD'], $layers->map(fn ($l) => $l->flask->status)->all());
            $this->assertSame([$mould->id, $mould->id], $layers->map(fn ($l) => $l->flask->current_mould_assembly_id)->all());
        }
        $this->assertSame(0, Flask::where('flask_size_id', $small->id)->where('status', 'AVAILABLE')->count());
    }

    public function test_the_station_decides_which_sizes_can_be_used(): void
    {
        $big = $this->makeSize('800 × 600');
        $small = $this->makeSize('600 × 500');
        $this->flasks($big, 'DRAG', 1);
        $this->flasks($big, 'COPE', 1);
        $this->flasks($small, 'DRAG', 1);
        $this->flasks($small, 'COPE', 1);
        $run = $this->makeRun([$small, $big], $this->station([$big]));

        $mould = $this->assemble($run, 1)->first();

        $this->assertSame($big->id, $mould->fresh()->flask_size_id, 'the designed-for size is not handled by the station');
    }

    public function test_a_shortage_blocks_or_warns_by_the_rule(): void
    {
        $size = $this->makeSize('600 × 500');
        $this->flasks($size, 'DRAG', 1);
        $this->flasks($size, 'COPE', 1);
        $run = $this->makeRun([$size], accepted: 2);

        $this->post(route('moulding-runs.assemble', $run), ['count' => 2, 'mould_configuration_id' => MouldConfiguration::create(['code' => 'MC-1', 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2])->id, 'layers' => 2])
            ->assertSessionHasErrors(['assemble' => '2 moulds need a set of flasks (drag + cope), but only 1 set is free. 600 × 500: 1 cope, 1 drag free. Release the flasks of knocked-out moulds, or change the rule on the flask board.']);
        $this->assertSame(0, MouldAssembly::count());

        FlaskRules::set('shortage', 'WARN');
        $this->post(route('moulding-runs.assemble', $run), ['count' => 2, 'mould_configuration_id' => MouldConfiguration::first()->id, 'layers' => 2])
            ->assertSessionHasNoErrors()->assertSessionHas('warning', fn ($w) => str_contains($w, 'only 1 set is free') && str_contains($w, 'made with no flask recorded'));
        $this->assertSame(2, MouldAssembly::count());
        $this->assertSame(2, MouldLayer::whereNotNull('flask_id')->count());
    }

    public function test_fixed_pairs_keep_partners_together(): void
    {
        FlaskRules::set('pairing', 'FIXED');
        $size = $this->makeSize('600 × 500');
        [$dragA, $dragB] = $this->flasks($size, 'DRAG', 2)->all();
        [$copeA, $copeB] = $this->flasks($size, 'COPE', 2)->all();
        $dragA->update(['partner_flask_id' => $copeB->id]);
        $copeB->update(['partner_flask_id' => $dragA->id]);
        $run = $this->makeRun([$size], accepted: 2);

        $mould = $this->assemble($run, 1)->first();

        $this->assertSame([$dragA->id, $copeB->id], MouldLayer::where('mould_assembly_id', $mould->id)->orderBy('layer_number')->pluck('flask_id')->all());
        $this->expectException(\InvalidArgumentException::class);
        $this->assemble($run, 1);   // drag B has no partner
    }

    public function test_flaskless_stations_and_vertical_patterns_use_no_flasks(): void
    {
        $size = $this->makeSize('600 × 500');
        $this->flasks($size, 'ANY', 4);

        $disa = $this->assemble($this->makeRun([$size], $this->station([], flaskless: true), 1), 1)->first();
        $vertical = $this->assemble($this->makeRun([$size], null, 1, 'VERTICAL', 1), 1, 1)->first();

        $this->assertTrue($disa->fresh()->is_flaskless);
        $this->assertTrue($vertical->fresh()->is_flaskless);
        $this->assertSame(0, MouldLayer::whereNotNull('flask_id')->count());
        $this->assertSame(4, Flask::where('status', 'AVAILABLE')->count());
    }

    public function test_a_pattern_without_sizes_is_moulded_without_flasks(): void
    {
        $run = $this->makeRun([], accepted: 1);
        $assembler = app(MouldAssembler::class);

        $assembler->assemble($run, 1, MouldConfiguration::create(['code' => 'MC-1', 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2])->id, 2);

        $this->assertSame(1, MouldAssembly::count());
        $this->assertSame("No flask size is set for {$run->patternRevision->code}, so no flasks were recorded for these moulds. Set the sizes it fits on the pattern to track them.", $assembler->warning());
    }

    public function test_flasks_come_back_at_shakeout_pour_scrap_or_by_hand_and_count_a_use(): void
    {
        $size = $this->makeSize('600 × 500');
        $this->flasks($size, 'ANY', 8);
        $run = $this->makeRun([$size], accepted: 4);
        [$poured, $scrapped, $shaken, $manual] = $this->assemble($run, 4)->all();

        $poured->update(['status' => 'POURED']);
        $this->assertSame(2, Flask::where('current_mould_assembly_id', $poured->id)->count(), 'the default rule waits for shakeout');
        FlaskRules::set('release', 'POUR');
        MouldAssembly::whereKey($poured->id)->update(['status' => 'PREPARED']);
        $poured->fresh()->update(['status' => 'POURED']);
        $this->assertSame(0, Flask::where('current_mould_assembly_id', $poured->id)->count());

        $scrapped->update(['status' => 'SCRAPPED']);
        $this->assertSame(0, Flask::where('current_mould_assembly_id', $scrapped->id)->count());

        FlaskRules::set('release', 'SHAKEOUT');
        $lot = new CastingLot;
        $lot->setRelation('pourMouldAssembly', (new PourMouldAssembly)->setRelation('mouldAssembly', $shaken));
        $this->assertSame(2, app(FlaskManager::class)->shakenOut($lot));

        $this->post(route('moulding-runs.assemblies.release', [$run, $manual]))->assertSessionHas('success', "2 flasks of {$manual->assembly_number} released.");

        $this->assertSame(8, Flask::where('status', 'AVAILABLE')->count());
        $this->assertSame(8, (int) Flask::sum('cycles'));
        $this->assertSame(8, ToolingUsageRecord::where('tooling_type', 'FLASK')->count());
    }

    public function test_a_flask_is_swapped_for_the_one_actually_used(): void
    {
        $size = $this->makeSize('600 × 500');
        $other = $this->makeSize('800 × 600');
        [$drag, $spareDrag] = $this->flasks($size, 'DRAG', 2)->all();
        $cope = $this->flasks($size, 'COPE', 1)->first();
        $wrongSize = $this->flasks($other, 'DRAG', 1)->first();
        $run = $this->makeRun([$size], accepted: 1);
        $mould = $this->assemble($run, 1)->first();
        $dragLayer = MouldLayer::where('mould_assembly_id', $mould->id)->where('layer_type', 'DRAG')->first();
        $spareDrag->update(['mark_code' => 'D9']);

        $this->put(route('moulding-runs.layers.flask', [$run, $dragLayer]), ['flask' => $cope->code])->assertSessionHasErrors('flask');
        $this->put(route('moulding-runs.layers.flask', [$run, $dragLayer]), ['flask' => $wrongSize->code])->assertSessionHasErrors('flask');
        $used = $dragLayer->flask_id === $drag->id ? $spareDrag : $drag;
        $this->put(route('moulding-runs.layers.flask', [$run, $dragLayer]), ['flask' => $used->mark_code ?? $used->code])->assertSessionHasNoErrors();

        $this->assertSame($used->id, $dragLayer->fresh()->flask_id);
        $this->assertSame('IN_MOULD', $used->fresh()->status);
        $this->assertSame('AVAILABLE', ($used->id === $drag->id ? $spareDrag : $drag)->fresh()->status);
    }

    public function test_repair_takes_a_flask_out_of_service_with_a_maintenance_record(): void
    {
        $flask = $this->flasks($this->makeSize('600 × 500'), 'DRAG', 1)->first();

        $this->post(route('flasks.status', $flask), ['action' => 'repair', 'problem' => 'Pin bush worn'])->assertSessionHasNoErrors();

        $this->assertSame('REPAIR', $flask->fresh()->status);
        $this->assertSame('Pin bush worn', ToolingMaintenanceRecord::where('flask_id', $flask->id)->sole()->problem_description);
        $this->post(route('flasks.status', $flask), ['action' => 'back'])->assertSessionHasNoErrors();
        $this->assertSame('AVAILABLE', $flask->fresh()->status);
    }

    public function test_runs_claim_flasks_by_count_under_that_rule(): void
    {
        FlaskRules::set('run_claims', 'COUNT');
        $size = $this->makeSize('600 × 500');
        $this->flasks($size, 'ANY', 6);

        $run = $this->makeRun([$size], accepted: 2);
        $this->assertSame(4, Flask::where('current_moulding_run_id', $run->id)->count());

        $run->update(['moulds_made_qty' => 1]);
        $this->assertSame(2, Flask::where('current_moulding_run_id', $run->id)->count());

        $this->post(route('moulding-runs.release-flasks', $run))->assertSessionHas('success', "2 flasks of run {$run->run_number} released.");
        $this->assertSame([6, 2], [Flask::where('status', 'AVAILABLE')->count(), (int) Flask::sum('cycles')]);
    }

    public function test_stations_and_patterns_record_their_flask_sizes(): void
    {
        $big = $this->makeSize('800 × 600');
        $small = $this->makeSize('600 × 500');
        $station = $this->station();
        $run = $this->makeRun([]);
        $revision = $run->patternRevision;

        $this->put(route('moulding-teams.stations.update', $station), ['code' => $station->code, 'name' => 'Station', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'flask_sizes_sent' => 1, 'flask_size_ids' => [$big->id]])
            ->assertSessionHasNoErrors();
        $this->put(route('patterns.revisions.layout', [$revision->pattern, $revision]), ['mould_arrangement' => 'COPE_DRAG', 'flask_sizes_sent' => 1, 'flask_size_ids' => [$big->id, $small->id], 'primary_flask_size_id' => $small->id])
            ->assertSessionHasNoErrors();

        $this->assertSame([$big->id], $station->flaskSizes()->pluck('flask_sizes.id')->all());
        $this->assertSame([$small->id, $big->id], $revision->flaskSizes()->pluck('flask_sizes.id')->all(), 'the designed-for size first');
        $this->assertTrue(AuditLog::where('entity_id', $revision->id)->whereRaw('changes::text like ?', ['%600 × 500 (designed for)%'])->exists());
        $next = $revision->pattern->addRevision(['change_description' => 'New gating'], copyLayout: true);
        $this->assertSame([$small->id, $big->id], $next->flaskSizes()->pluck('flask_sizes.id')->all());
        $this->get(route('moulding-teams.index'))->assertOk()->assertSee('800 × 600');
    }

    public function test_the_rules_are_saved_on_the_board(): void
    {
        $this->put(route('settings.flasks'), ['shortage' => 'WARN', 'pairing' => 'FIXED', 'release' => 'MANUAL', 'run_claims' => 'COUNT'])->assertSessionHasNoErrors();

        $this->assertSame(['WARN', 'FIXED', 'MANUAL', 'COUNT'], [FlaskRules::get('shortage'), FlaskRules::get('pairing'), FlaskRules::get('release'), FlaskRules::get('run_claims')]);
        $this->put(route('settings.flasks'), ['shortage' => 'NONSENSE', 'pairing' => 'ANY', 'release' => 'POUR', 'run_claims' => 'ASSEMBLED'])->assertSessionHasErrors('shortage');
    }

    public function test_the_flask_pages_and_the_run_page_show_flasks(): void
    {
        $size = $this->makeSize('600 × 500');
        $this->flasks($size, 'ANY', 4);
        $run = $this->makeRun([$size], accepted: 2);
        $mould = $this->assemble($run, 1)->first();
        $flask = Flask::where('current_mould_assembly_id', $mould->id)->first();

        $this->get(route('moulding-runs.show', $run))->assertOk()->assertSee($flask->code)->assertSee('1 of 1 set free', false)->assertSee('Release flasks');
        $this->get(route('flasks.show', $flask))->assertOk()->assertSee($mould->assembly_number)->assertSee('Release from mould');
        $this->get(route('flasks.edit', $flask))->assertOk()->assertSee('Fixed partner');
        $this->get(route('flask-sizes.edit', $size))->assertOk()->assertSee('Pin centres');
        $this->get(route('moulding-runs.show', $this->makeRun([$size], $this->station([], flaskless: true), 1)))->assertOk()->assertSee('Flaskless moulds: no flasks are used.');
    }
}
