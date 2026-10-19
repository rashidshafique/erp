<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\MouldConfiguration;
use App\Models\MouldingRun;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Pour;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Services\Production\MouldAssembler;
use App\Support\PouringRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CupolaPouringTest extends TestCase
{
    use RefreshDatabase;

    private function heat(string $type = 'CUPOLA', bool $withGrade = true): Heat
    {
        $furnace = Furnace::create(['code' => $type.'-'.uniqid(), 'name' => ucfirst(strtolower($type)), 'furnace_type' => $type, 'status' => 'ACTIVE', 'rated_capacity_kg' => 3000, 'nominal_melting_rate_kg_per_hour' => 1500]);

        return Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => $type, 'status' => 'PLANNED', 'iron_grade_id' => $withGrade ? IronGrade::where('code', 'EN-GJS-500-7')->value('id') : null]);
    }

    private function pour(Heat $heat): Pour
    {
        return Pour::create(['pour_number' => 'P-'.uniqid(), 'heat_id' => $heat->id, 'status' => 'PLANNED']);
    }

    public function test_the_default_gate_is_advisory_for_a_cupola_and_enforced_for_induction(): void
    {
        $this->assertSame(['ADVISORY', 'ENFORCED', 'ENFORCED'], [PouringRules::gateMode('CUPOLA'), PouringRules::gateMode('INDUCTION'), PouringRules::gateMode('OTHER')]);
    }

    public function test_an_induction_pour_without_tests_is_still_blocked(): void
    {
        $pour = $this->pour($this->heat('INDUCTION'));

        $this->post(route('pours.start', $pour))->assertSessionHasErrors('gate');

        $this->assertSame('PLANNED', $pour->fresh()->status);
    }

    public function test_a_cupola_pour_starts_without_tests_and_records_what_was_missing(): void
    {
        $pour = $this->pour($this->heat());

        $this->post(route('pours.start', $pour))->assertSessionHasNoErrors()->assertSessionHas('warning');

        $pour->refresh();
        $this->assertSame(['IN_PROGRESS', 'ADVISORY'], [$pour->status, $pour->gate_mode]);
        $this->assertStringContainsString('Started without a clear check', $pour->gate_note);
        $this->get(route('pours.show', $pour))->assertOk()->assertSee('Started without a clear check');
    }

    public function test_an_enforced_cupola_gate_blocks_and_an_off_gate_does_not_check(): void
    {
        $pour = $this->pour($this->heat());

        PouringRules::set('gate_cupola', 'ENFORCED');
        $this->post(route('pours.start', $pour))->assertSessionHasErrors('gate');

        PouringRules::set('gate_cupola', 'OFF');
        $this->post(route('pours.start', $pour))->assertSessionHasNoErrors();
        $pour->refresh();
        $this->assertSame(['IN_PROGRESS', 'OFF', null], [$pour->status, $pour->gate_mode, $pour->gate_note]);
    }

    public function test_the_first_pour_sets_the_first_tap_and_is_first_iron_and_later_ones_are_not(): void
    {
        $heat = $this->heat();
        $first = $this->pour($heat);
        $this->post(route('pours.start', $first));

        $heat->refresh();
        $this->assertNotNull($heat->first_tap_at);
        $this->assertSame('TAPPING', $heat->status);
        $this->assertTrue($first->fresh()->is_first_iron);

        $heat->update(['first_tap_at' => now()->subMinutes(90)]);
        $later = $this->pour($heat);
        $this->post(route('pours.start', $later));
        $this->assertFalse($later->fresh()->is_first_iron);
    }

    public function test_first_iron_can_be_switched_off_and_induction_has_none(): void
    {
        PouringRules::set('first_iron_minutes', '0');
        $pour = $this->pour($this->heat());
        $this->post(route('pours.start', $pour));
        $this->assertFalse($pour->fresh()->is_first_iron);

        PouringRules::set('first_iron_minutes', '30');
        $induction = $this->pour($this->heat('INDUCTION', withGrade: false));
        $this->post(route('pours.start', $induction));
        $this->assertFalse($induction->fresh()->is_first_iron);
    }

    public function test_the_timeline_follows_the_cupola_and_a_bottom_drop_closes_the_heat(): void
    {
        $heat = $this->heat();
        $at = fn (int $minutes) => now()->subMinutes($minutes)->format('Y-m-d\TH:i');

        $this->put(route('heats.timeline', $heat), ['actual_start_at' => $at(120), 'blast_on_at' => $at(90)])->assertSessionHasNoErrors();
        $this->assertSame('MELTING', $heat->fresh()->status);

        $this->put(route('heats.timeline', $heat), ['actual_start_at' => $at(120), 'blast_on_at' => $at(90), 'first_tap_at' => $at(60), 'dropped_at' => $at(5)])->assertSessionHasNoErrors();
        $heat->refresh();
        $this->assertSame('CLOSED', $heat->status);
        $this->assertNotNull($heat->closed_at);
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Cupola timeline');
    }

    public function test_times_out_of_order_are_refused_and_induction_heats_have_no_timeline(): void
    {
        $heat = $this->heat();
        $at = fn (int $minutes) => now()->subMinutes($minutes)->format('Y-m-d\TH:i');

        $this->put(route('heats.timeline', $heat), ['blast_on_at' => $at(30), 'first_tap_at' => $at(60)])->assertSessionHasErrors('first_tap_at');

        $this->put(route('heats.timeline', $this->heat('INDUCTION')), ['blast_on_at' => $at(30)])->assertNotFound();
        $this->get(route('heats.show', $this->heat('INDUCTION')))->assertOk()->assertDontSee('Cupola timeline');
    }

    public function test_pouring_rules_can_be_changed_on_their_screen(): void
    {
        $this->get(route('settings.pouring.edit'))->assertOk()->assertSee('Pouring rules');

        $this->put(route('settings.pouring'), [
            'gate_cupola' => 'ENFORCED', 'gate_induction' => 'ENFORCED', 'gate_other' => 'OFF', 'first_iron_minutes' => 45, 'hold_first_iron' => 'FREE',
            'test_interval_minutes' => 20, 'containment' => 'NOTE',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['ENFORCED', 'OFF', 45, 'FREE', 20, 'NOTE'], [PouringRules::get('gate_cupola'), PouringRules::get('gate_other'), PouringRules::minutes('first_iron_minutes'), PouringRules::get('hold_first_iron'), PouringRules::minutes('test_interval_minutes'), PouringRules::get('containment')]);
        $this->put(route('settings.pouring'), ['gate_cupola' => 'MAYBE'] + PouringRules::RULES)->assertSessionHasErrors('gate_cupola');
    }

    /** A completed pour with one mould of two castings, so lots are made. */
    private function completedPour(bool $firstIron, int $plannedQty = 100): array
    {
        $product = ProductRevision::create(['product_id' => Product::create(['code' => 'RING-'.uniqid(), 'name' => 'Ring'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $pattern = Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        foreach ([1, 2] as $n) {
            $revision->cavityMap()->create(['cavity_number' => $n, 'product_revision_id' => $product->id, 'casting_weight_kg' => 0.5, 'poured_weight_kg' => 0.6]);
        }
        $order = ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'product_id' => $product->product_id, 'product_revision_id' => $product->id, 'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => $plannedQty, 'uom' => 'piece']);
        $run = MouldingRun::create(['run_number' => 'MR-'.uniqid(), 'production_order_id' => $order->id, 'pattern_revision_id' => $revision->id, 'run_date' => today(), 'status' => 'COMPLETED', 'moulds_made_qty' => 1, 'accepted_mould_qty' => 1]);
        $configuration = MouldConfiguration::create(['code' => 'MC-'.uniqid(), 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);
        $assembly = app(MouldAssembler::class)->assemble($run, 1, $configuration->id, 2)->first();

        $heat = $this->heat(withGrade: false);
        $pour = $this->pour($heat);
        $this->post(route('pours.assemblies.attach', $pour), ['assembly_ids' => [$assembly->id]])->assertSessionHasNoErrors();
        $this->post(route('pours.start', $pour))->assertSessionHasNoErrors();
        if (! $firstIron) {
            $pour->update(['is_first_iron' => false]);
        }
        $this->post(route('pours.complete', $pour), ['pour_temperature_c' => 1400, 'total_poured_weight_kg' => 1.2])->assertSessionHasNoErrors();

        $this->order = $order;

        return [$pour->fresh(), CastingLot::where('production_order_id', $order->id)->get()];
    }

    private ProductionOrder $order;

    public function test_a_completed_pour_leaves_the_order_in_progress_and_it_can_be_closed_short(): void
    {
        $this->completedPour(firstIron: false, plannedQty: 2);
        $this->assertSame('IN_PROGRESS', $this->order->fresh()->status);

        $this->post(route('production-orders.close', $this->order))->assertSessionHasErrors('reason');
        $this->post(route('production-orders.close', $this->order), ['reason' => 'Scrap, not remaking'])->assertSessionHasNoErrors();
        $order = $this->order->fresh();
        $this->assertSame('COMPLETED', $order->status);
        $this->assertStringContainsString('Closed short', $order->notes);
        $this->post(route('production-orders.close', $order))->assertSessionHasErrors('order');
    }

    public function test_lots_from_first_iron_are_held_until_qc_passes(): void
    {
        [$pour, $lots] = $this->completedPour(firstIron: true);

        $this->assertTrue($pour->is_first_iron);
        $this->assertNotEmpty($lots);
        $this->assertSame(['ON_HOLD'], $lots->pluck('status')->unique()->all());
        $this->assertStringContainsString('first iron', $lots->first()->notes);
    }

    public function test_lots_from_later_iron_are_not_held_and_the_hold_can_be_switched_off(): void
    {
        [, $later] = $this->completedPour(firstIron: false);
        $this->assertSame(['IN_PRODUCTION'], $later->pluck('status')->unique()->all());

        PouringRules::set('hold_first_iron', 'FREE');
        [, $free] = $this->completedPour(firstIron: true);
        $this->assertSame(['IN_PRODUCTION'], $free->pluck('status')->unique()->all());
    }
}
