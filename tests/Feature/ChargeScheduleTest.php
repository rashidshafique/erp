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
        $heat->update(['target_metal_weight_kg' => 180]);   // one charge of 180 kg is all the target needs
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

    public function test_the_scheduler_records_each_due_charge_at_its_time_from_the_furnace_speed_and_only_once(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat, $iron] = $this->cupola();
        $heat->update(['actual_start_at' => now()->subHours(2), 'blast_on_at' => now()->subHours(2)]);   // 08:00 light-up and blast; a charge every 30 minutes
        $this->charged($heat, $iron, 1, 100, 180);   // charge 1 went in by hand at 08:20

        $this->artisan('heats:add-due-charges')->assertSuccessful();

        $times = HeatMaterialConsumption::where('heat_id', $heat->id)->where('material_id', $iron->id)->orderBy('charge_round')->get()->mapWithKeys(fn ($c) => [$c->charge_round => $c->consumed_at->format('H:i')]);
        $this->assertSame([1 => '08:20', 2 => '08:50', 3 => '09:20', 4 => '09:50'], $times->all());
        $this->assertSame(100.0, (float) HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 0)->sum('actual_qty_kg'), 'The start-up charge goes in at the start.');
        $this->assertSame('Auto (furnace speed)', HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 3)->first()->recorded_by);
        $this->assertStringContainsString('not observed', HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 3)->first()->notes);

        $count = HeatMaterialConsumption::where('heat_id', $heat->id)->count();
        $this->artisan('heats:add-due-charges')->assertSuccessful();
        $this->assertSame($count, HeatMaterialConsumption::where('heat_id', $heat->id)->count(), 'Nothing is due the second time.');

        $this->travelTo(now()->addMinutes(31));   // 10:31: charge 5 comes due at 10:20
        $this->artisan('heats:add-due-charges')->assertSuccessful();
        $this->assertSame('10:20', HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 5)->first()->consumed_at->format('H:i'));
    }

    public function test_the_scheduler_leaves_heats_on_hold_closed_or_not_started_and_obeys_the_rule(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat] = $this->cupola();
        $heat->update(['actual_start_at' => now()->subHours(2), 'blast_on_at' => now()->subHours(2)]);
        $count = fn () => HeatMaterialConsumption::where('heat_id', $heat->id)->count();

        \App\Support\PouringRules::set('auto_charge', 'MANUAL');
        $this->artisan('heats:add-due-charges')->assertSuccessful();
        $this->assertSame(0, $count(), 'Switched to manual, nothing is added.');

        \App\Support\PouringRules::set('auto_charge', 'AUTO');
        foreach ([['hold_reason' => 'RED'], ['status' => 'CLOSED'], ['actual_start_at' => null]] as $change) {
            $heat->update($change + ['hold_reason' => null, 'status' => 'MELTING', 'actual_start_at' => now()->subHours(2)]);
            $heat->update($change);
            $this->artisan('heats:add-due-charges')->assertSuccessful();
            $this->assertSame(0, $count(), 'No charge is added: '.json_encode($change));
        }
    }

    public function test_the_page_offers_to_add_back_charges_and_shows_the_metal_charged_from_them(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat, $iron] = $this->cupola();
        $heat->update(['actual_start_at' => now()->subHours(2), 'blast_on_at' => now()->subHours(2)]);
        $this->charged($heat, $iron, 1, 100, 180);

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Add back charges')->assertSee('Add back 4 charges')->assertSee('180.0 kg')->assertSee('1 of 5 charges')
            ->assertSee('a charge of 180 kg every 30 min')->assertSee('Charges are also added automatically every minute');
    }

    public function test_the_weight_of_a_charge_entry_can_be_corrected_and_its_cost_follows_at_the_same_price(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Melter Ali']));
        [$heat, $iron] = $this->cupola();
        $this->charged($heat, $iron, 1, 30, 180);
        $entry = HeatMaterialConsumption::where('heat_id', $heat->id)->sole();

        $this->get(route('heats.show', $heat))->assertOk()->assertSee(route('heats.charge-entries.update', [$heat, $entry]), false);
        $this->put(route('heats.charge-entries.update', [$heat, $entry]), ['actual_qty_kg' => 150.5])->assertSessionHasNoErrors();

        $entry->refresh();
        $this->assertSame([150.5, 7525.0], [(float) $entry->actual_qty_kg, (float) $entry->actual_total_cost]);   // 150.5 kg at 50 a kg
        $this->assertStringContainsString('Weight changed from 180.000 kg to 150.500 kg by Melter Ali', $entry->notes);
        $this->assertEqualsWithDelta(150.5, $heat->fresh()->chargedMetalKg(), 0.001);
        $this->put(route('heats.charge-entries.update', [$heat, $entry]), ['actual_qty_kg' => 0])->assertSessionHasErrors('actual_qty_kg');
    }

    public function test_deleting_the_last_entry_of_a_charge_marks_it_skipped_so_it_is_not_added_back_until_restored(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat, $iron, $coke] = $this->cupola();
        $heat->update(['actual_start_at' => now()->subHours(2), 'blast_on_at' => now()->subHours(2), 'target_metal_weight_kg' => 360]);   // two charges, due 08:30 and 09:00
        $this->charged($heat, $iron, 1, 100, 180);
        $this->charged($heat, $coke, 1, 100, 25);
        $entries = HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 1)->get();

        $this->delete(route('heats.charge-entries.destroy', [$heat, $entries[0]]))->assertSessionHasNoErrors();
        $this->assertSame([], $heat->fresh()->skippedChargeRounds(), 'One line of the charge is still in.');
        $this->delete(route('heats.charge-entries.destroy', [$heat, $entries[1]]))->assertSessionHas('success', fn ($m) => str_contains($m, 'Charge 1 will not be added automatically'));
        $this->assertSame([1], $heat->fresh()->skippedChargeRounds());

        $this->artisan('heats:add-due-charges')->assertSuccessful();
        $this->assertSame([0, 2], HeatMaterialConsumption::where('heat_id', $heat->id)->pluck('charge_round')->unique()->sort()->values()->all(), 'The skipped charge stays out; the others come in.');
        $this->get(route('heats.show', $heat))->assertSee('Removed on purpose')->assertSee(route('heats.charge-rounds.restore', [$heat, 1]), false);

        $this->post(route('heats.charge-rounds.restore', [$heat, 1]))->assertSessionHasNoErrors();
        $this->artisan('heats:add-due-charges')->assertSuccessful();
        $this->assertTrue(HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 1)->exists());
        $this->assertSame([], $heat->fresh()->skippedChargeRounds());
    }

    public function test_entries_cannot_be_changed_on_a_closed_heat_or_after_the_charge_is_posted_or_through_another_heat(): void
    {
        [$heat, $iron] = $this->cupola();
        $other = Heat::create(['heat_number' => 'H-OTHER', 'furnace_id' => $heat->furnace_id, 'process_type' => 'CUPOLA', 'status' => 'MELTING']);
        $this->charged($heat, $iron, 1, 30, 180);
        $entry = HeatMaterialConsumption::where('heat_id', $heat->id)->sole();

        $this->delete(route('heats.charge-entries.destroy', [$other, $entry]))->assertNotFound();

        \App\Models\HeatChargePosting::create(['heat_id' => $heat->id, 'total_quantity_kg' => 180, 'total_cost_pkr' => 9000, 'posted_by' => 'Ali', 'posted_at' => now()]);
        $this->put(route('heats.charge-entries.update', [$heat, $entry]), ['actual_qty_kg' => 10])->assertSessionHasErrors('actual_qty_kg');
        $this->get(route('heats.show', $heat))->assertOk()->assertDontSee(route('heats.charge-entries.destroy', [$heat, $entry]), false);
        \App\Models\HeatChargePosting::where('heat_id', $heat->id)->delete();

        $heat->update(['status' => 'CLOSED']);
        $this->put(route('heats.charge-entries.update', [$heat, $entry]), ['actual_qty_kg' => 10])->assertSessionHasErrors('actual_qty_kg');
        $this->delete(route('heats.charge-entries.destroy', [$heat, $entry]))->assertSessionHasErrors('actual_qty_kg');
        $this->assertSame(180.0, (float) $entry->fresh()->actual_qty_kg);
        $this->get(route('heats.show', $heat))->assertOk()->assertDontSee(route('heats.charge-entries.update', [$heat, $entry]), false);
    }
}
