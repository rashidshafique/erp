<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Support\PouringRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatDropOutcomeTest extends TestCase
{
    use RefreshDatabase;

    /** A cupola at 500 kg/h with a 2,000 kg target: four hours expected from blast on, which was five hours ago. */
    private function cupola(): Heat
    {
        $this->travelTo(now()->setTime(18, 0));
        $furnace = Furnace::create(['code' => 'CU-1', 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'nominal_melting_rate_kg_per_hour' => 500]);

        return Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'TAPPING', 'target_metal_weight_kg' => 2000,
            'iron_grade_id' => IronGrade::where('family', 'GREY')->value('id'), 'actual_start_at' => now()->subHours(6), 'blast_on_at' => now()->subHours(5), 'first_tap_at' => now()->subHours(4)]);
    }

    /** The timeline form as posted, with the bottom drop this many minutes after blast on. */
    private function timeline(Heat $heat, int $minutesAfterBlast, array $extra = []): array
    {
        $at = fn ($v) => $v?->format('Y-m-d\TH:i');

        return $extra + ['actual_start_at' => $at($heat->actual_start_at), 'blast_on_at' => $at($heat->blast_on_at), 'first_tap_at' => $at($heat->first_tap_at),
            'dropped_at' => $at($heat->blast_on_at->copy()->addMinutes($minutesAfterBlast))];
    }

    public function test_a_drop_within_90_percent_of_the_expected_time_is_finished_without_asking(): void
    {
        $heat = $this->cupola();   // 240 minutes expected; 90% is 216

        $this->put(route('heats.timeline', $heat), $this->timeline($heat, 220))->assertSessionHasNoErrors()->assertSessionMissing('drop_check');

        $heat->refresh();
        $this->assertSame(['CLOSED', 'FINISHED', 240, 220, null], [$heat->status, $heat->drop_outcome, $heat->drop_expected_minutes, $heat->drop_actual_minutes, $heat->drop_reason]);
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Finished');
    }

    public function test_a_short_run_asks_and_saves_nothing_until_answered(): void
    {
        $heat = $this->cupola();

        $this->put(route('heats.timeline', $heat), $this->timeline($heat, 100))->assertSessionHas('drop_check', fn ($c) => $c['expected'] === 240 && $c['actual'] === 100 && $c['percent'] === 42 && $c['threshold'] === 90);
        $this->assertSame(['TAPPING', null, null], [$heat->fresh()->status, $heat->fresh()->dropped_at, $heat->fresh()->drop_outcome]);

        $this->from(route('heats.show', $heat))->followingRedirects()->put(route('heats.timeline', $heat), $this->timeline($heat, 100))->assertSee('Was the campaign finished?')->assertSee('42% of the 4 h 00 min expected')->assertSee('Why was it dropped early?');
    }

    public function test_an_early_drop_needs_its_reason_and_is_shown_as_dropped_prematurely(): void
    {
        $heat = $this->cupola();

        $this->put(route('heats.timeline', $heat), $this->timeline($heat, 100, ['drop_outcome' => 'PREMATURE', 'drop_reason' => ' ']))->assertSessionHasErrors('drop_reason')->assertSessionHas('drop_check');
        $this->assertNull($heat->fresh()->drop_outcome);

        $this->put(route('heats.timeline', $heat), $this->timeline($heat, 100, ['drop_outcome' => 'PREMATURE', 'drop_reason' => 'Bridging in the stack']))->assertSessionHasNoErrors();
        $heat->refresh();
        $this->assertSame(['CLOSED', 'PREMATURE', 'Bridging in the stack', 100], [$heat->status, $heat->drop_outcome, $heat->drop_reason, $heat->drop_actual_minutes]);
        $this->assertSame('Dropped prematurely', $heat->outcomeLabel());
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Dropped prematurely')->assertSee('Bridging in the stack');
        $this->get(route('heats.index'))->assertOk()->assertSee('Dropped prematurely');
    }

    public function test_a_short_run_can_be_confirmed_as_finished(): void
    {
        $heat = $this->cupola();

        $this->put(route('heats.timeline', $heat), $this->timeline($heat, 100, ['drop_outcome' => 'FINISHED']))->assertSessionHasNoErrors();
        $this->assertSame(['CLOSED', 'FINISHED', null], [$heat->fresh()->status, $heat->fresh()->drop_outcome, $heat->fresh()->drop_reason]);
    }

    public function test_the_share_is_a_rule_resaving_does_not_ask_again_and_taking_the_drop_back_clears_the_outcome(): void
    {
        $heat = $this->cupola();
        PouringRules::set('drop_finished_percent', '40');
        $this->put(route('heats.timeline', $heat), $this->timeline($heat, 100))->assertSessionMissing('drop_check');   // 42% now counts as finished
        $this->assertSame('FINISHED', $heat->fresh()->drop_outcome);

        PouringRules::set('drop_finished_percent', '90');
        $this->put(route('heats.timeline', $heat), $this->timeline($heat->fresh(), 100, ['actual_tapped_weight_kg' => 1800]))->assertSessionMissing('drop_check');   // the drop did not change
        $this->assertSame('FINISHED', $heat->fresh()->drop_outcome);

        $this->put(route('heats.timeline', $heat), ['dropped_at' => ''] + $this->timeline($heat->fresh(), 100))->assertSessionHasNoErrors();
        $this->assertSame(['TAPPING', null, null], [$heat->fresh()->status, $heat->fresh()->drop_outcome, $heat->fresh()->drop_actual_minutes]);
    }

    public function test_without_a_target_or_a_melting_speed_nothing_is_asked(): void
    {
        $heat = $this->cupola();
        $heat->update(['target_metal_weight_kg' => null]);

        $this->put(route('heats.timeline', $heat), $this->timeline($heat, 10, ['first_tap_at' => null]))->assertSessionMissing('drop_check');
        $this->assertSame(['FINISHED', null], [$heat->fresh()->drop_outcome, $heat->fresh()->drop_expected_minutes]);
    }
}
