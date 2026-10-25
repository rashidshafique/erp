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
        $this->assertSame(['P-'.now()->format('Ymd').'-001', 'P-'.now()->format('Ymd').'-002'], $numbers);
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
