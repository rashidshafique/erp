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
        $heat->forceDelete();   // the form below creates its own heat; started at 08:00, the bed goes in then and charge 1 is due at 09:00, one hour after light-up
        $form = ['furnace_id' => $furnace->id, 'charge_batch_id' => $recipe->id, 'target_metal_weight_kg' => 900, 'operator_name' => 'Ali', 'planned_start_at' => now()->subHours(2)->format('Y-m-d\TH:i')];

        $this->getJson(route('heats.charge-preview', ['furnace_id' => $furnace->id, 'charge_batch_id' => $recipe->id, 'started_at' => $form['planned_start_at'], 'target_metal_weight_kg' => 900]))
            ->assertOk()->assertJson(['count' => 4, 'interval' => '30 min']);

        $this->post(route('heats.store'), $form + ['already_started' => 1, 'fill_charges' => 1])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'recorded as started') && str_contains($m, '4 charges filled in'));
        $created = Heat::latest('id')->first();
        $this->assertSame(['MELTING', '08:00'], [$created->status, $created->actual_start_at->format('H:i')]);
        $this->assertSame(4, HeatMaterialConsumption::where('heat_id', $created->id)->distinct('charge_round')->count('charge_round'));
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

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Add back charges')->assertSee('Add back 4 charges')->assertSee('Metal charged')->assertSee('20%')->assertSee('of the 900 kg target')
            ->assertSee('a charge of 180 kg every 30 min')->assertSee('Charges are also added automatically every minute');
    }

    public function test_the_weight_of_a_charge_entry_can_be_corrected_and_its_cost_follows_at_the_same_price(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Melter Ali']));
        [$heat, $iron] = $this->cupola();
        $this->charged($heat, $iron, 1, 30, 180);
        $entry = HeatMaterialConsumption::where('heat_id', $heat->id)->sole();

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
        $this->get(route('heats.show', $heat))->assertSeeLivewire('heat-skipped-charges');
        \Livewire\Livewire::test('heat-skipped-charges', ['heatId' => $heat->id])->assertSee('Removed on purpose')->assertSee('Charge 1: restore')->call('restore', 1)
            ->assertDispatched('charges-changed')->assertDontSee('Removed on purpose');
        $this->artisan('heats:add-due-charges')->assertSuccessful();
        $this->assertTrue(HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 1)->exists());
        $this->assertSame([], $heat->fresh()->skippedChargeRounds());
    }

    public function test_entries_cannot_be_changed_on_a_cancelled_heat_or_after_the_charge_is_posted_or_through_another_heat(): void
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

        $heat->update(['status' => 'CANCELLED']);
        $this->put(route('heats.charge-entries.update', [$heat, $entry]), ['actual_qty_kg' => 10])->assertSessionHasErrors('actual_qty_kg');
        $this->delete(route('heats.charge-entries.destroy', [$heat, $entry]))->assertSessionHasErrors('actual_qty_kg');
        $this->assertSame(180.0, (float) $entry->fresh()->actual_qty_kg);
        \Livewire\Livewire::test('heat-charge-history', ['heatId' => $heat->id])->assertDontSee('click a weight to change it')->assertDontSee('wire:click="edit', false);
    }

    public function test_a_weight_is_changed_in_place_from_the_charge_history_without_leaving_the_page(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Melter Ali']));
        [$heat, $iron] = $this->cupola();
        $this->charged($heat, $iron, 1, 30, 180);
        $entry = HeatMaterialConsumption::where('heat_id', $heat->id)->sole();
        $history = fn () => \Livewire\Livewire::test('heat-charge-history', ['heatId' => $heat->id]);

        $this->get(route('heats.show', $heat))->assertOk()->assertSeeLivewire('heat-charge-history')->assertSeeLivewire('heat-status-board')->assertSee('click a weight to change it');

        $list = $history()->assertSee('Material type')->assertSee('Pig Iron')->call('edit', $entry->id)->assertSet('editing', $entry->id)->assertSet('weight', '180');
        $list->set('weight', '150.5')->call('save')->assertSet('editing', null)->assertDispatched('charges-changed')->assertSee('150.500');
        $this->assertSame([150.5, 7525.0], [(float) $entry->fresh()->actual_qty_kg, (float) $entry->fresh()->actual_total_cost]);
        $this->assertStringContainsString('Weight changed from 180.000 kg to 150.500 kg by Melter Ali', $entry->fresh()->notes);

        // Enter and the click away both save; the second finds nothing to do.
        $list->call('save')->assertSet('editing', null);
        $this->assertSame(150.5, (float) $entry->fresh()->actual_qty_kg);

        $history()->call('edit', $entry->id)->set('weight', '0')->call('save')->assertSet('problem', 'Enter a weight above zero.');
        $history()->call('edit', $entry->id)->set('weight', 'abc')->call('save')->assertSet('problem', 'Enter the weight as a number.');
        $history()->call('edit', $entry->id)->call('cancel')->assertSet('editing', null);
        $this->assertSame(150.5, (float) $entry->fresh()->actual_qty_kg);
        \Livewire\Livewire::test('heat-status-board', ['heatId' => $heat->id])->assertSee('150.5')->dispatch('charges-changed')->assertSee('150.5');
    }

    public function test_an_entry_is_removed_in_place_and_a_cancelled_heat_refuses_it(): void
    {
        [$heat, $iron] = $this->cupola();
        $this->charged($heat, $iron, 1, 30, 180);
        $entry = HeatMaterialConsumption::where('heat_id', $heat->id)->sole();

        \Livewire\Livewire::test('heat-charge-history', ['heatId' => $heat->id])->call('remove', $entry->id)->assertDispatched('charges-changed')
            ->assertSet('notice', 'Charge entry removed. Charge 1 will not be added automatically; restore it from the Charge tab if it should be.')->assertSee('No actual charge entries');
        $this->assertSame(0, HeatMaterialConsumption::where('heat_id', $heat->id)->count());

        $this->charged($heat, $iron, 2, 10, 180);
        $heat->update(['status' => 'CANCELLED']);
        \Livewire\Livewire::test('heat-charge-history', ['heatId' => $heat->id])->call('remove', HeatMaterialConsumption::where('heat_id', $heat->id)->value('id'))
            ->assertSet('problem', 'Charges cannot be changed on a cancelled heat.');
        $this->assertSame(1, HeatMaterialConsumption::where('heat_id', $heat->id)->count());
    }

    public function test_the_heat_page_shows_todays_times_in_12_hours_and_earlier_ones_with_their_date(): void
    {
        [$heat, $iron] = $this->cupola();
        $this->travelTo(now()->setTime(15, 5));
        $this->charged($heat, $iron, 1, 30, 180);                                                  // 2:35 PM today
        HeatMaterialConsumption::where('heat_id', $heat->id)->update(['consumed_at' => now()->setTime(14, 35)]);
        $this->charged($heat, $iron, 2, 60 * 26, 180);                                              // yesterday

        $page = \Livewire\Livewire::test('heat-charge-history', ['heatId' => $heat->id])->assertSee('2:35 PM')->assertSee(now()->subDay()->displayShortDate().', ')->assertDontSee('14:35');
    }

    public function test_a_cupolas_first_metal_charge_is_due_an_hour_after_light_up_unless_blast_on_is_recorded(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat] = $this->cupola();
        $heat->update(['actual_start_at' => now()->subHours(3), 'blast_on_at' => null]);   // light-up at 07:00

        $due = fn () => app(ChargeSchedule::class)->for($heat->fresh())['rounds']->mapWithKeys(fn ($r) => [$r['round'] => $r['due_at']->format('H:i')])->take(3)->all();
        $this->assertSame([0 => '07:00', 1 => '08:00', 2 => '08:30'], $due(), 'The bed at light-up, the first metal an hour later, then a charge every 30 minutes.');

        \App\Support\PouringRules::set('lightup_to_first_charge_minutes', '90');
        $this->assertSame('08:30', $due()[1]);

        $heat->update(['blast_on_at' => now()->subHours(2)->subMinutes(20)]);   // blast on at 07:40 wins over the rule
        $this->assertSame('07:40', $due()[1]);
    }

    public function test_the_status_board_shows_charges_added_the_last_charge_the_metal_charged_and_the_next_charge(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat, $iron, $coke] = $this->cupola();
        $coke->update(['material_type' => 'FUEL']);   // fuel is not metal
        $heat->update(['actual_start_at' => now()->subHours(3), 'blast_on_at' => now()->subHours(2), 'target_metal_weight_kg' => 900]);   // 5 charges of 180 kg, one every 30 minutes
        $this->charged($heat, $coke, 0, 120, 100);   // the bed, 08:00
        $this->charged($heat, $iron, 1, 100, 180);   // 08:20
        $this->charged($heat, $coke, 1, 100, 25);
        $this->charged($heat, $iron, 2, 70, 180);    // 08:50

        $board = \Livewire\Livewire::test('heat-status-board', ['heatId' => $heat->id]);
        $board->assertSee('Charges added')->assertSeeInOrder(['Charges added', '2', 'of 5'])->assertSee('Last charge')->assertSeeInOrder(['Last charge', '#2'])
            ->assertSee('8:50 AM')->assertSeeInOrder(['Metal charged', '360', 'kg'])->assertSee('40%')->assertSee('of the 900 kg target')
            ->assertSee('Running time')->assertSee('Time remaining')->assertSee('540 kg to go');   // 900 kg target less the 360 kg of metal charged

        // Charge 3 was due at 09:20, so it is due now; once it is in, the last charge moves on and the board follows.
        $board->assertSee('#3 due');
        $this->charged($heat, $iron, 3, 5, 180);
        $board->dispatch('charges-changed')->assertSeeInOrder(['Charges added', '3', 'of 5'])->assertSeeInOrder(['Last charge', '#3'])->assertSeeInOrder(['Metal charged', '540']);
    }

    public function test_the_heat_heading_shows_max_and_limit_only_when_they_are_set(): void
    {
        [$heat] = $this->cupola();

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Target')->assertSee('Rated')
            ->assertDontSee('title="Furnace operating maximum"', false)->assertDontSee('title="Operating limit set on this heat"', false);

        $heat->furnace->update(['max_operating_power_kw' => 450]);
        $heat->update(['operating_power_limit_kw' => 400]);
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('title="Furnace operating maximum"', false)->assertSee('450.0 kW')
            ->assertSee('title="Operating limit set on this heat"', false)->assertSee('400.0 kW');
    }

    public function test_a_cupolas_time_remaining_counts_the_metal_still_to_charge_and_the_last_charge_still_to_melt(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$heat, $iron] = $this->cupola();   // 360 kg/h and 180 kg a charge: 30 minutes a charge
        $heat->update(['actual_start_at' => now()->subHours(3), 'blast_on_at' => now()->subHours(2), 'target_metal_weight_kg' => 900]);
        $eta = fn () => app(\App\Services\Production\HeatEta::class)->for($heat->fresh());

        $this->charged($heat, $iron, 1, 100, 180);
        $this->charged($heat, $iron, 2, 70, 180);
        $two = $eta();
        $this->assertSame(540.0, $two['remaining_kg']);   // not the whole 900 kg: 360 kg are charged
        $this->assertSame('12:00', $two['finish_at']->format('H:i'), '540 kg at 360 kg/h is 90 minutes, plus 30 for the last charge to melt.');

        foreach ([3, 4, 5] as $round) {
            $this->charged($heat, $iron, $round, 5, 180);   // the last one went in at 09:55
        }
        $all = $eta();
        $this->assertSame(0.0, $all['remaining_kg']);
        $this->assertSame('10:25', $all['finish_at']->format('H:i'), 'Everything is charged: the last charge melts 30 minutes after it went in.');
    }

    public function test_the_charge_tab_has_the_history_on_the_left_and_every_other_panel_on_the_right(): void
    {
        [$heat] = $this->cupola();

        $this->get(route('heats.show', $heat))->assertOk()
            ->assertSeeInOrder(['data-tab-start="Charge"', 'class="charge-cols"', 'class="charge-history-col"', 'wire:name="heat-charge-history"', 'class="charge-other-col"', 'Charge recipe and planned materials', 'Post charge to inventory', 'data-tab-start="Furnace readings"'], false);
    }

    public function test_a_finished_heat_still_posts_its_charge_and_keeps_its_history_editable_until_it_is_posted(): void
    {
        [$heat, $iron] = $this->cupola();
        $this->charged($heat, $iron, 1, 30, 180);
        $heat->update(['status' => 'CLOSED']);
        $entry = HeatMaterialConsumption::where('heat_id', $heat->id)->sole();
        $lot = MaterialLot::where('material_id', $iron->id)->first();
        $entry->update(['material_lot_id' => $lot->id]);   // posting issues the metal from the lot it came from, which has to hold it
        app(\App\Services\Stock\StockLedger::class)->receive($lot, 1000, 50, 'kg');

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Post charge to inventory')->assertSee(route('heats.charge-postings.store', $heat), false)->assertSee('click a weight to change it');
        \Livewire\Livewire::test('heat-charge-history', ['heatId' => $heat->id])->call('edit', $entry->id)->set('weight', '175')->call('save')->assertSet('problem', null);
        $this->assertSame(175.0, (float) $entry->fresh()->actual_qty_kg);

        $this->post(route('heats.charge-postings.store', $heat), ['posted_by' => 'Stores'])->assertSessionHasNoErrors();
        $this->assertTrue($heat->chargePosting()->exists());
        \Livewire\Livewire::test('heat-charge-history', ['heatId' => $heat->id])->assertDontSee('click a weight to change it');

        $heat->update(['status' => 'CANCELLED']);
        $this->get(route('heats.show', $heat))->assertOk()->assertDontSee('Post charge to inventory');
    }

    public function test_the_metal_balance_and_the_board_count_the_same_metal_charged_from_the_recorded_charges(): void
    {
        [$heat, $iron, $coke] = $this->cupola();
        $coke->update(['material_type' => 'FUEL']);
        $this->charged($heat, $coke, 0, 120, 100);
        $this->charged($heat, $iron, 1, 100, 180);
        $this->charged($heat, $coke, 1, 100, 25);

        $this->assertSame(180.0, app(\App\Services\Production\HeatBalance::class)->for($heat->fresh())['charged_kg'], 'Coke is not metal.');
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Metal charged');

        // A weight corrected in the history moves both.
        $entry = HeatMaterialConsumption::where('heat_id', $heat->id)->where('material_id', $iron->id)->sole();
        app(\App\Services\Production\ChargeEntryEditor::class)->reweigh($heat, $entry, 150, 'Ali');
        $this->assertSame(150.0, app(\App\Services\Production\HeatBalance::class)->for($heat->fresh())['charged_kg']);
        $this->assertSame(150.0, $heat->fresh()->chargedMetalKg());
    }
}
