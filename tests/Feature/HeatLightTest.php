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
            ->assertSeeInOrder([$running->heat_number.'<span title="Heat in progress"', 'Induction', '>'.$furnace->code.'</span>', '>Melting', 'Running time', '1:15:'], false);

        $done = Heat::create(['heat_number' => 'H-DONE', 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'CLOSED',
            'actual_start_at' => now()->subHours(5), 'tapped_at' => now()->subHours(5)->addMinutes(50)]);
        $this->get(route('heats.show', $done))->assertOk()->assertSee('Ran for')->assertSee('50 min')
            ->assertSee('>Melting', false)->assertSee($running->heat_number);

        $furnace->update(['status' => 'MAINTENANCE']);
        $planned = $this->heat('PLANNED');
        $planned->furnace->update(['status' => 'MAINTENANCE']);
        $this->get(route('heats.show', $planned))->assertOk()->assertSee('Under maintenance')->assertDontSee('id="heat-run"', false);
    }

    public function test_a_running_heat_shows_a_finish_estimate_from_the_best_melting_rate_available(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $heat = $this->heat('IN_PROGRESS');
        $heat->furnace->update(['nominal_melting_rate_kg_per_hour' => 500]);
        $heat->update(['actual_start_at' => now()->subMinutes(30), 'target_metal_weight_kg' => 2000]);
        $eta = app(\App\Services\Production\HeatEta::class);

        // Nothing measured yet and no history: the rated 500 kg/h, 4 hours from the 09:30 start.
        $rated = $eta->for($heat->fresh('furnace'));
        $this->assertSame(['rated', '13:30'], [$rated['source'], $rated['finish_at']->format('H:i')]);

        // A closed heat on the same furnace melted 800 kg in 1 hour: that recorded rate replaces the rated speed.
        Heat::create(['heat_number' => 'H-OLD', 'furnace_id' => $heat->furnace_id, 'process_type' => 'INDUCTION', 'status' => 'CLOSED',
            'actual_start_at' => now()->subDay(), 'tapped_at' => now()->subDay()->addHour(), 'actual_tapped_weight_kg' => 800]);
        $recorded = $eta->for($heat->fresh('furnace'));
        $this->assertSame(['recorded', '12:00', 800.0], [$recorded['source'], $recorded['finish_at']->format('H:i'), (float) $recorded['rate_kg_per_hour']]);

        // 30 minutes in with 600 kg tapped: the live rate is 1200 kg/h, and 1400 kg remain, so 70 minutes more.
        $heat->update(['actual_tapped_weight_kg' => 600]);
        $live = $eta->for($heat->fresh('furnace'));
        $this->assertSame(['live', '11:10', 1400.0], [$live['source'], $live['finish_at']->format('H:i'), $live['remaining_kg']]);

        $this->getJson(route('heats.eta', $heat))->assertOk()->assertJson(['running' => true, 'eta' => ['time' => '11:10', 'overdue' => false]]);
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Finish ETA')->assertSee('>11:10<', false);
    }

    public function test_there_is_no_finish_estimate_without_a_target_a_running_heat_or_a_rate(): void
    {
        $eta = app(\App\Services\Production\HeatEta::class);
        $planned = $this->heat('PLANNED');
        $planned->update(['target_metal_weight_kg' => 1000]);
        $this->assertNull($eta->for($planned->fresh('furnace')));

        $running = $this->heat('IN_PROGRESS');
        $running->update(['actual_start_at' => now()->subHour()]);
        $this->assertNull($eta->for($running->fresh('furnace')), 'No target metal.');

        $running->update(['target_metal_weight_kg' => 1000]);
        $this->assertNull($eta->for($running->fresh('furnace')), 'No rate: nothing measured, recorded or rated.');
        $this->getJson(route('heats.eta', $running))->assertOk()->assertJson(['running' => true, 'eta' => null]);
    }

    public function test_put_on_hold_is_a_button_beside_back_to_heats_that_opens_a_dialog_and_is_gone_once_held_or_closed(): void
    {
        $heat = $this->heat('IN_PROGRESS');

        $this->get(route('heats.show', $heat))->assertOk()
            ->assertSeeInOrder(['Back to heats', 'data-quick="qd-hold"', 'Put on hold'], false)
            ->assertSee('<dialog id="qd-hold"', false)->assertSee(route('heats.hold', $heat), false)->assertDontSee('<details class="panel">', false);

        $heat->update(['hold_reason' => 'SCRAP', 'hold_note' => 'Wet scrap', 'held_at' => now()]);
        $this->get(route('heats.show', $heat))->assertOk()->assertDontSee('data-quick="qd-hold"', false)->assertSee('Take off hold');

        $closed = $this->heat('CLOSED');
        $this->get(route('heats.show', $closed))->assertOk()->assertDontSee('data-quick="qd-hold"', false);
    }
}
