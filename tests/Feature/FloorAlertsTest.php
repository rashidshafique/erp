<?php

namespace Tests\Feature;

use App\Models\ChargeBatch;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\Material;
use App\Models\MouldingWorkList;
use App\Models\Pour;
use App\Models\Role;
use App\Models\User;
use App\Services\Production\FloorAlerts;
use App\Support\PouringRules;
use App\Support\WorkListRules;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkLists;
use Tests\TestCase;

class FloorAlertsTest extends TestCase
{
    use BuildsWorkLists;
    use RefreshDatabase;

    private function user(string $roleCode): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function draftList(): MouldingWorkList
    {
        $w = $this->world();
        $this->post(route('work-lists.store'), ['list_date' => today()->toDateString(), 'shift' => 'Morning']);
        $list = MouldingWorkList::firstOrFail();
        $this->post(route('work-lists.items.add', $list), $this->item($w))->assertSessionHasNoErrors();

        return $list;
    }

    public function test_issuing_a_list_tells_the_people_who_mould_but_not_the_issuer_or_others(): void
    {
        $list = $this->draftList();
        $planner = $this->user('PRODUCTION_MANAGER');
        $supervisor = $this->user('MOULDING_SUPERVISOR');
        $salesperson = $this->user('SALES');

        $this->actingAs($planner)->post(route('work-lists.issue', $list))->assertSessionHasNoErrors();

        $notice = $supervisor->notifications()->firstOrFail();
        $this->assertSame('list-issued', $notice->data['kind']);
        $this->assertStringContainsString($list->list_number, $notice->data['title']);
        $this->assertSame(route('work-lists.show', $list), $notice->data['url']);
        $this->assertCount(0, $planner->notifications);
        $this->assertCount(0, $salesperson->notifications);
    }

    public function test_a_one_person_foundry_is_not_told_about_its_own_issue(): void
    {
        $list = $this->draftList();
        $owner = $this->user('PRODUCTION_MANAGER');

        $this->actingAs($owner)->post(route('work-lists.issue', $list))->assertSessionHasNoErrors();

        $this->assertCount(0, $owner->notifications);
    }

    public function test_a_refused_issue_tells_nobody(): void
    {
        $this->world();
        $this->post(route('work-lists.store'), ['list_date' => today()->toDateString(), 'shift' => 'Morning']);
        $list = MouldingWorkList::firstOrFail();
        $supervisor = $this->user('MOULDING_SUPERVISOR');

        $this->actingAs($this->user('PRODUCTION_MANAGER'))->post(route('work-lists.issue', $list))->assertSessionHasErrors('item');

        $this->assertCount(0, $supervisor->notifications);
    }

    private function heat(array $over = []): Heat
    {
        $furnace = Furnace::firstOrCreate(['code' => 'IF1'], ['name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 3000]);

        return Heat::create($over + ['heat_number' => 'HT-1', 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'IN_PROGRESS', 'actual_start_at' => now()->subHour()]);
    }

    public function test_a_started_heat_asks_the_moulders_to_count_but_a_planned_one_does_not(): void
    {
        $planner = $this->user('PRODUCTION_MANAGER');
        $supervisor = $this->user('MOULDING_SUPERVISOR');
        $melter = $this->user('MELT_SUPERVISOR');

        $this->actingAs($planner);
        $planned = $this->heat(['status' => 'PLANNED', 'actual_start_at' => null]);
        $this->assertCount(0, $supervisor->notifications);

        $planned->update(['status' => 'IN_PROGRESS', 'actual_start_at' => now()]);
        $notice = $supervisor->notifications()->firstOrFail();
        $this->assertSame('heat-started', $notice->data['kind']);
        $this->assertCount(0, $planner->notifications);
        $this->assertCount(0, $melter->notifications);

        $planned->update(['notes' => 'edited']);
        $this->assertCount(1, $supervisor->fresh()->notifications);
    }

    public function test_closing_a_heat_with_unaccounted_metal_tells_the_planner_but_a_balanced_one_does_not(): void
    {
        $planner = $this->user('PRODUCTION_MANAGER');
        $melter = $this->user('MELT_SUPERVISOR');
        $heat = $this->heat(['actual_tapped_weight_kg' => 500]);
        Pour::create(['pour_number' => 'P-1', 'heat_id' => $heat->id, 'status' => 'COMPLETED', 'total_poured_weight_kg' => 380, 'started_at' => now()]);

        $this->actingAs($melter)->post(route('heats.close', $heat), ['leftover_metal_kg' => 120, 'leftover_use' => 'PIGGED'])->assertSessionHasNoErrors();
        $this->assertCount(0, $planner->notifications->where('data.kind', 'heat-unaccounted'), 'tapped, poured and leftover agree');

        $heat2 = $this->heat(['heat_number' => 'HT-2', 'actual_tapped_weight_kg' => 500]);
        Pour::create(['pour_number' => 'P-2', 'heat_id' => $heat2->id, 'status' => 'COMPLETED', 'total_poured_weight_kg' => 380, 'started_at' => now()]);
        $this->actingAs($melter)->post(route('heats.close', $heat2), ['leftover_metal_kg' => 20, 'leftover_use' => 'PIGGED'])->assertSessionHasNoErrors();

        $notice = $planner->fresh()->notifications->firstWhere('data.kind', 'heat-unaccounted');
        $this->assertNotNull($notice);
        $this->assertSame('heat-unaccounted', $notice->data['kind']);
        $this->assertStringContainsString('HT-2', $notice->data['title']);
        $this->assertStringContainsString('100 kg', $notice->data['title']);
    }

    public function test_the_count_pour_and_charge_notices_go_to_the_next_hat_and_skip_empty_ones(): void
    {
        $alerts = app(FloorAlerts::class);
        $planner = $this->user('PRODUCTION_MANAGER');
        $melter = $this->user('MELT_SUPERVISOR');
        $inspector = $this->user('QUALITY_INSPECTOR');
        $heat = $this->heat();
        $pour = Pour::create(['pour_number' => 'P-9', 'heat_id' => $heat->id, 'status' => 'COMPLETED', 'started_at' => now()]);

        $this->assertSame(0, $alerts->mouldsCounted(0, null));
        $this->assertSame(0, $alerts->mouldsShort(0, null));
        $this->assertSame(0, $alerts->lotsMade($pour, 0, null));

        $alerts->mouldsCounted(93, $planner);
        $alerts->mouldsShort(3, $melter);
        $alerts->lotsMade($pour, 2, $planner);
        $alerts->chargeOverdue($heat, 3, 12);

        $kinds = fn (User $user): array => $user->notifications->pluck('data.kind')->reject(fn (string $kind) => $kind === 'heat-started')->sort()->values()->all();
        $this->assertSame(['charge-overdue', 'moulds-counted'], $kinds($melter));
        $this->assertSame(['charge-overdue', 'moulds-short'], $kinds($planner));
        $this->assertSame(['lots-made'], $inspector->notifications->pluck('data.kind')->all());
    }

    public function test_the_planner_is_reminded_once_a_day_of_an_unissued_list(): void
    {
        $list = $this->draftList();
        $planner = $this->user('PRODUCTION_MANAGER');

        $this->artisan('moulding:remind-unissued')->assertSuccessful();
        $this->artisan('moulding:remind-unissued')->assertSuccessful();

        $notices = $planner->notifications;
        $this->assertCount(1, $notices);
        $this->assertSame('list-not-issued', $notices->first()->data['kind']);

        $list->update(['status' => 'ISSUED']);
        $other = $this->user('PRODUCTION_MANAGER');
        $this->artisan('moulding:remind-unissued')->assertSuccessful();
        $this->assertCount(0, $other->notifications);
    }

    public function test_the_overdue_charge_check_runs_on_heats_with_no_recipe_without_telling_anyone(): void
    {
        $melter = $this->user('MELT_SUPERVISOR');
        $this->heat();

        $this->artisan('heats:warn-overdue-charges')->assertSuccessful();

        $this->assertCount(0, $melter->notifications);
    }

    public function test_the_unaccounted_metal_limit_comes_from_the_pouring_rules(): void
    {
        $planner = $this->user('PRODUCTION_MANAGER');
        $melter = $this->user('MELT_SUPERVISOR');
        PouringRules::set('unaccounted_percent', '25');
        $heat = $this->heat(['actual_tapped_weight_kg' => 500]);
        Pour::create(['pour_number' => 'P-1', 'heat_id' => $heat->id, 'status' => 'COMPLETED', 'total_poured_weight_kg' => 380, 'started_at' => now()]);

        $this->actingAs($melter)->post(route('heats.close', $heat), ['leftover_metal_kg' => 20, 'leftover_use' => 'PIGGED'])->assertSessionHasNoErrors();
        $this->assertCount(0, $planner->notifications->where('data.kind', 'heat-unaccounted'), '100 kg is 20% of tapped, under the 25% set');

        PouringRules::set('unaccounted_percent', '0');
        PouringRules::set('unaccounted_min_kg', '50');
        $this->assertTrue(PouringRules::unaccountedMatters(60, 500));
        $this->assertFalse(PouringRules::unaccountedMatters(-40, 500), 'metal over or under counts the same, below the kg floor is quiet');
    }

    public function test_a_late_charge_is_told_only_after_the_minutes_set_in_the_rules(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $melter = $this->user('MELT_SUPERVISOR');
        PouringRules::set('auto_charge', 'MANUAL');
        $iron = Material::create(['code' => 'PIG', 'name' => 'Pig iron', 'material_type' => 'PIG_IRON', 'base_uom' => 'kg', 'status' => 'ACTIVE']);
        $recipe = ChargeBatch::create(['code' => 'CR-CUP', 'name' => 'Cupola', 'process_type' => 'CUPOLA', 'status' => 'ACTIVE', 'target_metal_weight_kg' => 180, 'target_total_charge_weight_kg' => 180]);
        $recipe->items()->create(['material_id' => $iron->id, 'sequence' => 1, 'component_role' => 'CHARGE', 'charge_stage' => 'ROUND', 'target_qty_kg' => 180, 'is_required' => true]);
        $furnace = Furnace::create(['code' => 'CU-1', 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'nominal_melting_rate_kg_per_hour' => 360]);
        Heat::create(['heat_number' => 'HT-7', 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'MELTING', 'charge_batch_id' => $recipe->id,
            'target_metal_weight_kg' => 900, 'actual_start_at' => now()->subMinutes(60), 'blast_on_at' => now()->subMinutes(40)]);   // charge 1 due at blast on, 40 min ago

        PouringRules::set('charge_late_minutes', '45');
        $this->artisan('heats:warn-overdue-charges')->assertSuccessful();
        $this->assertCount(0, $melter->fresh()->notifications, '40 min late is inside the 45 set');

        PouringRules::set('charge_late_minutes', '30');
        $this->artisan('heats:warn-overdue-charges')->assertSuccessful();
        $this->assertSame(['charge-overdue'], $melter->fresh()->notifications->pluck('data.kind')->all());
    }

    public function test_the_draft_list_reminder_runs_at_the_hours_set_in_the_rules(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn (Event $e) => str_contains($e->command ?? '', 'moulding:remind-unissued'));
        $runsAt = function (int $hour) use ($event): bool {
            $this->travelTo(today()->setTime($hour, 0));

            return $event->filtersPass($this->app);
        };

        $this->assertSame([true, true, false], [$runsAt(7), $runsAt(20), $runsAt(18)], 'defaults 07:00 and 20:00');

        WorkListRules::set('remind_evening_hour', '18');
        $this->assertSame([true, false], [$runsAt(18), $runsAt(20)]);
    }
}
