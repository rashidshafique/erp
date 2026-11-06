<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\MouldingWorkList;
use App\Models\Pour;
use App\Models\Role;
use App\Models\User;
use App\Services\Production\FloorAlerts;
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
}
