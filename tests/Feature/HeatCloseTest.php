<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\Pour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Closing a heat when the furnace is turned off, with the metal left over. */
class HeatCloseTest extends TestCase
{
    use RefreshDatabase;

    private function heat(string $type, array $over = []): Heat
    {
        $furnace = Furnace::create(['code' => $type === 'CUPOLA' ? 'CU1' : 'IF1', 'name' => $type, 'furnace_type' => $type, 'status' => 'ACTIVE', 'rated_capacity_kg' => 3000]);

        return Heat::create($over + ['heat_number' => 'H-'.$type, 'furnace_id' => $furnace->id, 'process_type' => $type, 'status' => 'IN_PROGRESS', 'actual_start_at' => now()->subHours(3)]);
    }

    public function test_an_induction_heat_closes_with_its_leftover_metal_and_not_while_a_pour_runs(): void
    {
        $heat = $this->heat('INDUCTION');
        $pour = Pour::create(['pour_number' => 'P-1', 'heat_id' => $heat->id, 'status' => 'IN_PROGRESS', 'started_at' => now()]);
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Close heat');
        $this->post(route('heats.close', $heat), [])->assertSessionHasErrors('close');

        $heat->update(['actual_tapped_weight_kg' => 500]);
        $pour->update(['status' => 'COMPLETED', 'total_poured_weight_kg' => 380]);
        $this->get(route('heats.show', $heat))->assertSee('Tapped, not poured: 120.0 kg')->assertSeeHtml('name="leftover_metal_kg" min="0" step="1" value="120"');
        $this->post(route('heats.close', $heat), ['leftover_metal_kg' => 120])->assertSessionHasErrors('leftover_use');
        $this->post(route('heats.close', $heat), ['leftover_metal_kg' => 120, 'leftover_use' => 'PIGGED', 'closed_at' => now()->subMinutes(10)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();

        $heat->refresh();
        $this->assertSame('CLOSED', $heat->status);
        $this->assertNotNull($heat->closed_at);
        $this->assertSame('PIGGED', $heat->leftover_use);
        $this->assertNull($heat->light(), 'a closed heat leaves the top bar');
        $this->get(route('heats.show', $heat))->assertSee('120 kg left · Poured into pigs')->assertSee('Left at close (poured into pigs)');
        $this->get(route('quick.heat.index'))->assertDontSee('Heats not closed');
    }

    public function test_a_cupola_closes_at_its_drop_and_then_records_only_the_leftover(): void
    {
        $heat = $this->heat('CUPOLA', ['status' => 'TAPPING']);
        $this->get(route('quick.heat.index'))->assertSee('Heats not closed')->assertSee('H-CUPOLA');
        $this->post(route('heats.close', $heat), ['leftover_metal_kg' => 50, 'leftover_use' => 'RETURNS'])->assertSessionHasErrors('close');

        $heat->update(['status' => 'CLOSED', 'dropped_at' => now(), 'closed_at' => now()]);
        $this->post(route('heats.close', $heat), ['leftover_metal_kg' => 50, 'leftover_use' => 'RETURNS'])->assertSessionHasNoErrors();
        $this->assertSame('RETURNS', $heat->fresh()->leftover_use);
    }
}
