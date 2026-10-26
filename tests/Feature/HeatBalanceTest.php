<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\Pour;
use App\Services\Production\HeatBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function heat(array $extra = []): Heat
    {
        $furnace = Furnace::create(['code' => 'CU-'.uniqid(), 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'rated_capacity_kg' => 5000, 'nominal_melting_rate_kg_per_hour' => 3000]);

        return Heat::create($extra + ['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'TAPPING']);
    }

    private function pour(Heat $heat, string $status, ?float $kg, bool $firstIron = false): Pour
    {
        return Pour::create(['pour_number' => 'P-'.uniqid(), 'heat_id' => $heat->id, 'status' => $status, 'started_at' => now()->subMinutes(30), 'total_poured_weight_kg' => $kg, 'is_first_iron' => $firstIron]);
    }

    public function test_the_balance_adds_up_completed_pours_and_ignores_open_ones(): void
    {
        $heat = $this->heat(['first_tap_at' => now()->subHours(2), 'actual_tapped_weight_kg' => 1000]);
        $this->pour($heat, 'COMPLETED', 400, firstIron: true);
        $this->pour($heat, 'COMPLETED', 350);
        $this->pour($heat, 'IN_PROGRESS', null);

        $balance = app(HeatBalance::class)->for($heat->fresh());

        $this->assertSame([750.0, 400.0, 2, 1, 250.0], [$balance['poured_kg'], $balance['first_iron_kg'], $balance['pours'], $balance['open_pours'], $balance['unpoured_kg']]);
        $this->assertSame(375.0, $balance['melt_rate_kg_per_hour']);
        $this->assertSame([], $balance['warnings']);
    }

    public function test_pouring_more_than_was_tapped_or_charged_is_flagged(): void
    {
        $heat = $this->heat(['actual_tapped_weight_kg' => 500]);
        $this->pour($heat, 'COMPLETED', 700);

        $warnings = app(HeatBalance::class)->for($heat->fresh())['warnings'];

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('more metal was poured (700.0000 kg) than was tapped (500.0000 kg)', strtolower($warnings[0]));
    }

    public function test_the_heat_page_and_summary_page_show_the_balance(): void
    {
        $heat = $this->heat(['first_tap_at' => now()->subHour(), 'actual_tapped_weight_kg' => 900]);
        $pour = $this->pour($heat, 'COMPLETED', 800, firstIron: true);
        $pour->update(['containment_note' => 'Chemistry S-9 at 10:00 was out of limits; lots held.', 'ladle_reference' => 'L2']);

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Metal balance')->assertSee('800.0000 kg');
        $this->get(route('heats.summary', $heat))->assertOk()->assertSee($pour->pour_number)->assertSee('First iron')->assertSee('Containment: Chemistry S-9')->assertSee('ladle L2');
    }

    public function test_the_tapped_weight_is_saved_with_the_timeline(): void
    {
        $heat = $this->heat();

        $this->put(route('heats.timeline', $heat), ['actual_tapped_weight_kg' => '1250.5'])->assertSessionHasNoErrors();

        $this->assertEquals(1250.5, $heat->fresh()->actual_tapped_weight_kg);
    }
}
