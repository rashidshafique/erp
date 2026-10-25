<?php

namespace Tests\Feature;

use App\Models\ChargeBatch;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\User;
use App\Services\Production\ChargeSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** A cupola at 360 kg/h on a recipe of 180 kg of metal per charge: one charge every 30 minutes. */
    private function cupola(?int $rate = 360): array
    {
        $iron = Material::create(['code' => 'PIG', 'name' => 'Pig iron', 'material_type' => 'PIG_IRON', 'base_uom' => 'kg', 'status' => 'ACTIVE']);
        $coke = Material::create(['code' => 'COKE', 'name' => 'Coke', 'material_type' => 'COKE', 'base_uom' => 'kg', 'status' => 'ACTIVE']);
        foreach ([$iron, $coke] as $m) {
            MaterialLot::create(['lot_number' => 'L-'.$m->code, 'material_id' => $m->id, 'received_date' => today(), 'status' => 'ACCEPTED', 'received_qty' => 99999, 'accepted_qty' => 99999, 'unit_cost' => 50, 'currency_code' => 'PKR']);
        }
        $recipe = ChargeBatch::create(['code' => 'CR-CUP', 'name' => 'Cupola', 'process_type' => 'CUPOLA', 'status' => 'ACTIVE', 'target_metal_weight_kg' => 180, 'target_total_charge_weight_kg' => 205]);
        $recipe->items()->create(['material_id' => $coke->id, 'sequence' => 1, 'component_role' => 'COKE', 'charge_stage' => 'BED', 'target_qty_kg' => 100, 'is_required' => true]);
        $recipe->items()->create(['material_id' => $iron->id, 'sequence' => 2, 'component_role' => 'CHARGE', 'charge_stage' => 'ROUND', 'target_qty_kg' => 180, 'is_required' => true]);
        $recipe->items()->create(['material_id' => $coke->id, 'sequence' => 3, 'component_role' => 'COKE', 'charge_stage' => 'ROUND', 'target_qty_kg' => 25, 'is_required' => true]);
        $furnace = Furnace::create(['code' => 'CU-1', 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'nominal_melting_rate_kg_per_hour' => $rate]);
        $heat = Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'MELTING', 'charge_batch_id' => $recipe->id,
            'target_metal_weight_kg' => 900, 'actual_start_at' => now()->subMinutes(60), 'blast_on_at' => now()->subMinutes(40)]);

        return [$heat, $iron, $coke];
    }

    private function charged(Heat $heat, Material $material, int $round, int $minutesAgo, float $kg): void
    {
        HeatMaterialConsumption::create(['heat_id' => $heat->id, 'material_id' => $material->id, 'sequence' => (int) HeatMaterialConsumption::where('heat_id', $heat->id)->max('sequence') + 1, 'consumption_type' => 'RAW_MATERIAL',
            'actual_qty_kg' => $kg, 'actual_total_cost' => $kg * 50, 'consumed_at' => now()->subMinutes($minutesAgo), 'recorded_by' => 'Ali', 'charge_round' => $round]);
    }

    public function test_each_charge_is_due_one_interval_after_the_last_one_actually_put_in(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat, $iron, $coke] = $this->cupola();
        $heat->update(['actual_start_at' => now()->subMinutes(60), 'blast_on_at' => now()->subMinutes(40)]);   // 09:00 light-up, 09:20 blast on

        $plan = app(ChargeSchedule::class)->for($heat->fresh());
        $this->assertSame(['rated', 30], [$plan['rate']['source'], $plan['interval_minutes']]);
        $times = $plan['rounds']->take(4)->map(fn ($r) => $r['due_at']->format('H:i'))->all();
        $this->assertSame(['09:00', '09:20', '09:50', '10:20'], $times, 'The bed at light-up, charge 1 at blast on, then every 30 minutes.');
        $this->assertSame(['due', 'projected', 'projected', 'projected'], $plan['rounds']->take(4)->pluck('state')->all(), 'Only the first charge not in is due; later times are projections.');

        // The bed went in on time, charge 1 twenty minutes late: charge 2 now counts from 09:40, not from 09:50.
        $this->charged($heat, $coke, 0, 55, 100);
        $this->charged($heat, $iron, 1, 20, 180);
        $plan = app(ChargeSchedule::class)->for($heat->fresh());
        $this->assertSame(['done', 'done', 'upcoming'], $plan['rounds']->take(3)->pluck('state')->all());
        $this->assertSame('10:10', $plan['rounds'][2]['due_at']->format('H:i'));
        $this->assertSame([2, 10], [$plan['next']['round'], $plan['next']['minutes']]);
    }

    public function test_the_schedule_says_why_it_cannot_be_worked_out(): void
    {
        [$noRate] = $this->cupola(null);
        $plan = app(ChargeSchedule::class)->for($noRate);
        $this->assertStringContainsString('no melting rate on record', $plan['reason']);
        $this->assertSame('waiting', $plan['next']['state']);

        $bare = Heat::create(['heat_number' => 'H-BARE', 'furnace_id' => $noRate->furnace_id, 'process_type' => 'CUPOLA', 'status' => 'PLANNED']);
        $this->assertStringContainsString('No charge recipe', app(ChargeSchedule::class)->for($bare)['reason']);

        $noRate->furnace->update(['nominal_melting_rate_kg_per_hour' => 360]);
        $noRate->update(['actual_start_at' => null, 'blast_on_at' => null, 'status' => 'PLANNED']);
        $this->assertSame(['waiting'], array_unique(app(ChargeSchedule::class)->for($noRate->fresh())['rounds']->pluck('state')->all()));
    }

    public function test_one_click_records_the_next_round_from_the_recipe_with_default_lots_and_the_user_as_recorder(): void
    {
        $this->actingAs($user = User::factory()->create(['name' => 'Melter Ali']));
        [$heat, $iron, $coke] = $this->cupola();

        $this->post(route('heats.charge-rounds.confirm', $heat))->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'Start-up charge recorded from the recipe'));
        $bed = HeatMaterialConsumption::where('heat_id', $heat->id)->get();
        $this->assertSame([[0, 'COKE', 100.0, 'Melter Ali']], $bed->map(fn ($c) => [$c->charge_round, $c->material->code, (float) $c->actual_qty_kg, $c->recorded_by])->all());
        $this->assertNotNull($bed->first()->material_lot_id);

        $this->post(route('heats.charge-rounds.confirm', $heat))->assertSessionHasNoErrors();
        $round1 = HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 1)->get()->map(fn ($c) => [$c->material->code, (float) $c->actual_qty_kg])->sortBy(0)->values()->all();
        $this->assertSame([['COKE', 25.0], ['PIG', 180.0]], $round1);
        $this->assertSame(3, HeatMaterialConsumption::where('heat_id', $heat->id)->count());
    }

    public function test_one_click_is_refused_on_a_closed_heat_and_once_every_planned_charge_is_in(): void
    {
        [$heat, $iron, $coke] = $this->cupola();
        $heat->update(['charge_rounds' => 1]);
        $this->charged($heat, $coke, 0, 50, 100);
        $this->charged($heat, $iron, 1, 40, 180);

        $this->post(route('heats.charge-rounds.confirm', $heat))->assertSessionHasErrors('charge_round');
        $this->assertSame(2, HeatMaterialConsumption::where('heat_id', $heat->id)->count());

        $heat->update(['status' => 'CLOSED']);
        $this->post(route('heats.charge-rounds.confirm', $heat))->assertSessionHasErrors('charge_round');
    }

    public function test_the_heat_page_carries_a_charge_due_banner_that_shows_only_when_the_time_has_come(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat, $iron, $coke] = $this->cupola();
        $heat->update(['actual_start_at' => now()->subMinutes(60), 'blast_on_at' => now()->subMinutes(40)]);

        $page = $this->get(route('heats.show', $heat))->assertOk()->assertSee('id="charge-due"', false)->assertSee('The start-up charge is due')
            ->assertSee('Coke 100.0 kg')->assertSee(route('heats.charge-rounds.confirm', $heat), false)->assertSee('A charge is due every');
        $this->assertDoesNotMatchRegularExpression('/id="charge-due" data-due-at="[^"]+"\s+hidden/', $page->getContent());

        $this->charged($heat, $coke, 0, 55, 100);
        $this->charged($heat, $iron, 1, 5, 180);
        $later = $this->get(route('heats.show', $heat))->assertOk()->assertSee('id="charge-due"', false)->assertSee('Charge 2 is due');
        $this->assertMatchesRegularExpression('/id="charge-due" data-due-at="[^"]+"\s+hidden/', $later->getContent());

        $heat->update(['hold_reason' => 'EQUIPMENT', 'hold_note' => 'Blower', 'held_at' => now()]);
        $this->get(route('heats.show', $heat))->assertOk()->assertDontSee('id="charge-due"', false);
    }

    public function test_a_recipe_without_a_metal_weight_uses_the_weight_of_its_metal_materials_per_charge(): void
    {
        [$heat] = $this->cupola();
        $recipe = $heat->chargeBatch;
        $recipe->update(['target_metal_weight_kg' => null]);

        // Round items: 180 kg of pig iron and 25 kg of coke. Coke is not metal, so a charge is 180 kg and a charge is due every 30 minutes.
        $this->assertSame(180.0, $recipe->fresh('items')->metalPerChargeKg());
        $plan = app(ChargeSchedule::class)->for($heat->fresh());
        $this->assertSame([180.0, 30], [$plan['metal_per_round_kg'], $plan['interval_minutes']]);

        $recipe->items()->where('charge_stage', 'ROUND')->delete();
        $this->assertStringContainsString('sets no metal weight', app(ChargeSchedule::class)->for($heat->fresh())['reason']);
    }

    public function test_past_charges_are_filled_in_at_their_scheduled_times_and_marked_as_not_observed(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat, $iron, $coke] = $this->cupola();
        $heat->update(['actual_start_at' => now()->subHours(2), 'blast_on_at' => now()->subHours(2), 'charge_rounds' => 5]);   // 08:00; a charge every 30 minutes

        $plan = app(ChargeSchedule::class)->for($heat->fresh());
        $this->assertSame([0, 1, 2, 3, 4, 5], app(ChargeSchedule::class)->past($plan)->pluck('round')->all(), 'The bed and charges 1 to 5 are due by 10:00.');

        $count = app(ChargeSchedule::class)->backfill($heat->fresh(), 'Melter Ali', app(\App\Services\Production\ChargeRecorder::class));

        $this->assertSame(6, $count);
        $c3 = HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 3)->get();
        $this->assertSame(['09:00'], $c3->map(fn ($c) => $c->consumed_at->format('H:i'))->unique()->values()->all());
        $this->assertSame(2, $c3->count());
        $this->assertStringContainsString('not observed', $c3->first()->notes);
        $this->assertSame(0, app(ChargeSchedule::class)->backfill($heat->fresh(), 'Melter Ali', app(\App\Services\Production\ChargeRecorder::class)), 'Nothing is left to fill in.');
    }

    public function test_the_heat_page_offers_to_fill_in_the_charges_since_the_start_and_the_button_records_them(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Melter Ali']));
        $this->travelTo(now()->setTime(10, 0));
        [$heat] = $this->cupola();
        $heat->update(['actual_start_at' => now()->subHours(2), 'blast_on_at' => now()->subHours(2), 'charge_rounds' => 5]);

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Fill in the 6 charges since the start')->assertSee(route('heats.charge-rounds.backfill', $heat), false);

        $this->post(route('heats.charge-rounds.backfill', $heat))->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, '6 charges filled in'));
        $this->assertSame(6, HeatMaterialConsumption::where('heat_id', $heat->id)->distinct('charge_round')->count('charge_round'));
        $this->get(route('heats.show', $heat))->assertDontSee('charges since the start');
        $this->post(route('heats.charge-rounds.backfill', $heat))->assertSessionHasErrors('charge_round');
    }

    public function test_a_heat_created_after_it_started_can_be_recorded_as_started_with_its_past_charges_filled_in(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat, $iron, $coke] = $this->cupola();
        $recipe = $heat->chargeBatch;
        $furnace = $heat->furnace;
        $heat->forceDelete();   // the form below creates its own heat
        $form = ['furnace_id' => $furnace->id, 'charge_batch_id' => $recipe->id, 'target_metal_weight_kg' => 900, 'operator_name' => 'Ali', 'planned_start_at' => now()->subHours(2)->format('Y-m-d\TH:i')];

        $this->getJson(route('heats.charge-preview', ['furnace_id' => $furnace->id, 'charge_batch_id' => $recipe->id, 'started_at' => $form['planned_start_at'], 'target_metal_weight_kg' => 900]))
            ->assertOk()->assertJson(['count' => 6, 'interval' => '30 min']);

        $this->post(route('heats.store'), $form + ['already_started' => 1, 'fill_charges' => 1])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'recorded as started') && str_contains($m, '6 charges filled in'));
        $created = Heat::latest('id')->first();
        $this->assertSame(['MELTING', '08:00'], [$created->status, $created->actual_start_at->format('H:i')]);
        $this->assertSame(6, HeatMaterialConsumption::where('heat_id', $created->id)->distinct('charge_round')->count('charge_round'));
    }

    public function test_a_heat_created_without_the_question_is_left_planned_and_a_future_start_cannot_be_called_started(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat] = $this->cupola();
        $form = ['furnace_id' => $heat->furnace_id, 'charge_batch_id' => $heat->charge_batch_id, 'target_metal_weight_kg' => 900, 'operator_name' => 'Ali', 'planned_start_at' => now()->subHours(2)->format('Y-m-d\TH:i')];

        $this->post(route('heats.store'), $form)->assertSessionHasNoErrors();
        $planned = Heat::latest('id')->first();
        $this->assertSame(['PLANNED', null], [$planned->status, $planned->actual_start_at]);
        $this->assertSame(0, HeatMaterialConsumption::where('heat_id', $planned->id)->count());

        $this->post(route('heats.store'), array_merge($form, ['already_started' => 1, 'planned_start_at' => now()->addHour()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('planned_start_at');
        $this->post(route('heats.store'), array_merge($form, ['already_started' => 1, 'planned_start_at' => '']))->assertSessionHasErrors('planned_start_at');
    }

    public function test_the_missing_charges_are_added_from_the_total_metal_charged_scaled_to_match_it(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Melter Ali']));
        $this->travelTo(now()->setTime(10, 0));
        [$heat, $iron, $coke] = $this->cupola();
        $heat->update(['actual_start_at' => now()->subHours(2), 'blast_on_at' => now()->subHours(2), 'charge_rounds' => 5]);
        $this->charged($heat, $iron, 1, 100, 180);   // charge 1 is already in: 180 kg of metal

        $this->get(route('heats.show', $heat))->assertOk()->assertDontSee('Total metal charged so far (kg)')->assertDontSee('Heat summary</h2>', false)
            ->assertSee('Add back charges')->assertSee('Add back 4 charges')->assertSee('180.0 kg')->assertSee('1 of 5 charges')->assertSee('a charge of 180 kg every 30 min')->assertSee(route('heats.charge-rounds.backfill', $heat), false);

        // 800 kg is 4 charges of 180 kg (800 / 180 = 4.4). Charges 2 to 4 and the bed are missing; they carry the other 620 kg, so each is scaled up by 620 / 540.
        $this->post(route('heats.charge-rounds.total', $heat), ['total_charged_kg' => 800])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, '4 missing charges added from 800'));

        $metal = HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', '>', 0)->where('material_id', $iron->id)->sum('actual_qty_kg');
        $this->assertEqualsWithDelta(800.0, (float) $metal, 0.01, 'The metal recorded comes to the total.');
        $round3 = HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 3)->get();
        $this->assertEqualsWithDelta(206.667, (float) $round3->firstWhere('material_id', $iron->id)->actual_qty_kg, 0.001);
        $this->assertEqualsWithDelta(28.704, (float) $round3->firstWhere('material_id', $coke->id)->actual_qty_kg, 0.001, 'Coke is scaled with the charge.');
        $this->assertSame(['09:20'], $round3->map(fn ($c) => $c->consumed_at->format('H:i'))->unique()->values()->all(), 'Counted from charge 1, put in at 08:20: charge 2 at 08:50, charge 3 at 09:20.');
        $this->assertStringContainsString('Filled in from the total metal charged', $round3->first()->notes);
        $this->assertSame(100.0, (float) HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 0)->sum('actual_qty_kg'), 'The start-up charge is added at the recipe weight.');
        $this->assertSame(0, HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 5)->count(), 'Nothing is added beyond the charges the total is worth.');

        $this->post(route('heats.charge-rounds.total', $heat), ['total_charged_kg' => 800])->assertSessionHasErrors('total_charged_kg');
    }

    public function test_a_total_is_refused_when_the_heat_is_not_on_has_no_charge_in_it_or_is_below_what_is_recorded(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat, $iron] = $this->cupola();

        $this->post(route('heats.charge-rounds.total', $heat), ['total_charged_kg' => 0])->assertSessionHasErrors('total_charged_kg');
        $this->post(route('heats.charge-rounds.total', $heat), ['total_charged_kg' => 50])->assertSessionHasErrors('total_charged_kg');   // under one charge

        $this->charged($heat, $iron, 1, 30, 360);
        $this->post(route('heats.charge-rounds.total', $heat), ['total_charged_kg' => 360])->assertSessionHasErrors('total_charged_kg');   // two charges' worth is already in round 1
        $this->assertSame(1, HeatMaterialConsumption::where('heat_id', $heat->id)->count());

        $heat->update(['actual_start_at' => null]);
        $this->post(route('heats.charge-rounds.total', $heat), ['total_charged_kg' => 900])->assertSessionHasErrors('total_charged_kg');
        $heat->update(['actual_start_at' => now()->subHour(), 'status' => 'CLOSED']);
        $this->post(route('heats.charge-rounds.total', $heat), ['total_charged_kg' => 900])->assertSessionHasErrors('total_charged_kg');
    }
}
