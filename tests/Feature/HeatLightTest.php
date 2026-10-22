<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatLightTest extends TestCase
{
    use RefreshDatabase;

    private function heat(string $status): Heat
    {
        $f = Furnace::create(['code' => 'IF-'.uniqid(), 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => $status]);
    }

    public function test_orange_while_in_progress_and_none_when_planned(): void
    {
        $this->assertSame('orange', $this->heat('IN_PROGRESS')->light());
        $this->assertNull($this->heat('PLANNED')->light());
        $this->assertSame('orange', $this->heat('TAPPING')->light());
        $this->assertSame('orange', $this->heat('MELTING')->light());
        $this->assertNull($this->heat('CLOSED')->light());
    }

    public function test_hold_turns_the_light_red_and_resume_clears_it(): void
    {
        $heat = $this->heat('IN_PROGRESS');
        $this->post(route('heats.hold', $heat), ['hold_reason' => 'EQUIPMENT', 'hold_note' => 'Tap hole blocked'])->assertRedirect();
        $this->assertSame('red', $heat->fresh()->light());
        $this->get(route('heats.show', $heat))->assertSee('On hold: equipment problem')->assertSee('Tap hole blocked');

        $this->post(route('heats.resume', $heat))->assertRedirect();
        $this->assertSame('orange', $heat->fresh()->light());
    }

    public function test_a_hold_needs_a_physical_reason_and_note(): void
    {
        $heat = $this->heat('IN_PROGRESS');
        $this->post(route('heats.hold', $heat), ['hold_reason' => 'PAPERWORK', 'hold_note' => 'x'])->assertSessionHasErrors('hold_reason');
        $this->post(route('heats.hold', $heat), ['hold_reason' => 'SCRAP', 'hold_note' => ''])->assertSessionHasErrors('hold_note');
    }

    public function test_top_bar_shows_pouring_and_held_heats_and_nothing_when_idle(): void
    {
        $this->get(route('dashboard'))->assertDontSee('heat-lights', false);

        $pouring = $this->heat('IN_PROGRESS');
        \App\Models\Pour::create(['pour_number' => 'P-1', 'heat_id' => $pouring->id, 'status' => 'IN_PROGRESS', 'started_at' => now()]);
        $held = $this->heat('IN_PROGRESS');
        $held->update(['hold_reason' => 'SCRAP', 'hold_note' => 'Wet scrap']);

        $html = $this->get(route('dashboard'))->assertSee($pouring->heat_number)->assertSee($held->heat_number)->getContent();
        $this->assertLessThan(strpos($html, $pouring->heat_number), strpos($html, $held->heat_number), 'red before green');
        $this->assertStringContainsString('#1e8e3e', $html);
        $this->assertStringContainsString('#d93025', $html);
    }

    public function test_the_heat_page_shows_the_furnace_type_status_and_running_time_beside_the_heat_number(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $running = $this->heat('IN_PROGRESS');
        $running->update(['actual_start_at' => now()->subMinutes(75)]);
        $furnace = $running->furnace;

        $this->get(route('heats.show', $running))->assertOk()
            ->assertSeeInOrder(['Heat '.$running->heat_number, 'Induction', '>'.$furnace->code.'</span>', '>Melting', 'Running 1 h 15 min'], false);

        $done = Heat::create(['heat_number' => 'H-DONE', 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'CLOSED',
            'actual_start_at' => now()->subHours(5), 'tapped_at' => now()->subHours(5)->addMinutes(50)]);
        $this->get(route('heats.show', $done))->assertOk()->assertSee('Ran 50 min')
            ->assertSee('>Melting', false)->assertSee($running->heat_number);

        $furnace->update(['status' => 'MAINTENANCE']);
        $planned = $this->heat('PLANNED');
        $planned->furnace->update(['status' => 'MAINTENANCE']);
        $this->get(route('heats.show', $planned))->assertOk()->assertSee('Under maintenance')->assertSee('Not started');
    }
}
