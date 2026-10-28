<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\Pour;
use App\Models\PourMouldAssembly;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class PourSetupTest extends TestCase
{
    use BuildsCastingLots;
    use RefreshDatabase;

    private function heat(array $over = []): Heat
    {
        $furnace = Furnace::create(['code' => 'IF-'.uniqid(), 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 1000, 'nominal_melting_rate_kg_per_hour' => 500]);

        return Heat::create($over + ['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED']);
    }

    /** Two assembled moulds of one cavity each, for a released order, waiting to be poured. */
    private function waitingMoulds(): \Illuminate\Support\Collection
    {
        $product = \App\Models\ProductRevision::create(['product_id' => \App\Models\Product::create(['code' => 'P-'.uniqid(), 'name' => 'Part'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $revision = \App\Models\PatternRevision::create(['pattern_id' => \App\Models\Pattern::create(['code' => 'PT-'.uniqid(), 'name' => 'Plate'])->id, 'status' => 'APPROVED', 'cavities_per_mould' => 1]);
        $revision->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $product->id, 'casting_weight_kg' => 5]);
        $order = \App\Models\ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'product_id' => $product->product_id, 'product_revision_id' => $product->id, 'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 10, 'uom' => 'piece']);
        $run = \App\Models\MouldingRun::create(['run_number' => 'MR-'.uniqid(), 'production_order_id' => $order->id, 'pattern_revision_id' => $revision->id, 'run_date' => today(), 'status' => 'COMPLETED', 'moulds_made_qty' => 2, 'accepted_mould_qty' => 2]);
        $configuration = \App\Models\MouldConfiguration::create(['code' => 'MC-1', 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);

        return app(\App\Services\Production\MouldAssembler::class)->assemble($run, 2, $configuration->id);
    }

    public function test_in_one_step_the_ticked_moulds_are_poured_completed_and_given_lots(): void
    {
        \App\Support\FloorRules::set('pour_entry', 'ONE_STEP');
        $moulds = $this->waitingMoulds();
        $heat = $this->heat();

        $this->get(route('pours.create', ['heat' => $heat->id]))->assertOk()->assertSee('Record pour and make lots')->assertSee($moulds->first()->assembly_number);
        $this->post(route('pours.one-step'), ['heat_id' => $heat->id])->assertSessionHasErrors('assembly_ids');

        $this->post(route('pours.one-step'), ['heat_id' => $heat->id, 'assembly_ids' => $moulds->pluck('id')->all(), 'total_poured_weight_kg' => 20])->assertSessionHasNoErrors();

        $pour = Pour::sole();
        $this->assertSame(['COMPLETED', '20.000'], [$pour->status, (string) $pour->total_poured_weight_kg]);
        $this->assertSame(2, (int) \App\Models\CastingLot::sum('quantity_poured'));
    }

    public function test_the_new_pour_form_offers_finished_heats_but_not_cancelled_ones(): void
    {
        $open = $this->heat();
        $closed = $this->heat(['status' => 'CLOSED', 'closed_at' => now()]);
        $cancelled = $this->heat(['status' => 'CANCELLED']);

        $this->get(route('pours.create'))->assertOk()->assertSee($open->heat_number)->assertSee($closed->heat_number)->assertDontSee($cancelled->heat_number);
        $this->post(route('pours.store'), ['heat_id' => $closed->id])->assertSessionHasNoErrors();
        $this->post(route('pours.store'), ['heat_id' => $cancelled->id])->assertSessionHasErrors('heat_id');
    }

    public function test_pours_are_numbered_in_sequence_and_need_a_heat(): void
    {
        $heat = $this->heat();

        $this->post(route('pours.store'), [])->assertSessionHasErrors('heat_id');
        $this->post(route('pours.store'), ['heat_id' => $heat->id, 'ladle_reference' => 'L1'])->assertSessionHasNoErrors();
        $this->post(route('pours.store'), ['heat_id' => $heat->id])->assertSessionHasNoErrors();

        $numbers = Pour::orderBy('pour_number')->pluck('pour_number')->all();
        $this->assertSame(['P-'.now()->format('ymd').'-01', 'P-'.now()->format('ymd').'-02'], $numbers);
        $this->assertSame('PLANNED', Pour::first()->status);
    }

    public function test_a_mould_can_be_removed_from_a_planned_pour_but_not_once_lots_exist(): void
    {
        $lot = $this->castingLot();
        $row = $lot->pourMouldAssembly;
        $pour = $row->pour;

        $this->delete(route('pours.assemblies.detach', [$pour, $row]))->assertSessionHasErrors('assemblies');
        $this->assertTrue(PourMouldAssembly::whereKey($row->id)->exists());

        $lot->delete();
        $pour->update(['status' => 'PLANNED']);
        $this->delete(route('pours.assemblies.detach', [$pour, $row]))->assertSessionHasNoErrors();
        $this->assertFalse(PourMouldAssembly::whereKey($row->id)->exists());
    }

    public function test_a_mould_row_cannot_be_reached_through_another_pour(): void
    {
        $row = $this->castingLot()->pourMouldAssembly;
        $other = Pour::create(['pour_number' => 'P-OTHER', 'heat_id' => $row->pour->heat_id, 'status' => 'PLANNED']);

        $this->delete(route('pours.assemblies.detach', [$other, $row]))->assertNotFound();
        $this->put(route('pours.assemblies.cover', [$other, $row]), ['cover_reference' => 'C1'])->assertNotFound();
    }

    public function test_cover_and_weights_are_saved_and_checked(): void
    {
        $row = $this->castingLot()->pourMouldAssembly;

        $this->put(route('pours.assemblies.cover', [$row->pour, $row]), ['cover_reference' => 'JKT-4', 'weights_count' => 3])->assertSessionHasNoErrors();
        $row->refresh();
        $this->assertSame('JKT-4', $row->cover_reference);
        $this->assertSame(3, (int) $row->weights_count);

        $this->put(route('pours.assemblies.cover', [$row->pour, $row]), ['weights_count' => -1])->assertSessionHasErrors('weights_count');
        $this->assertSame(3, (int) $row->fresh()->weights_count);
    }
}
