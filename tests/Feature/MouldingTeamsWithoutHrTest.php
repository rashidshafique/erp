<?php

namespace Tests\Feature;

use App\Domains\Core\Contracts\PeopleDirectory;
use App\Domains\Core\NoPeopleDirectory;
use App\Models\MouldingTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MouldingTeamsWithoutHrTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_team_screen_asks_for_a_lead_name_and_head_count_when_there_is_no_staff_list(): void
    {
        $this->app->singleton(PeopleDirectory::class, NoPeopleDirectory::class);

        $html = $this->get(route('moulding-teams.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="lead_name"', $html);
        $this->assertStringContainsString('name="member_count"', $html);
        $this->assertStringNotContainsString('employee_ids_sent', $html);
        $this->assertStringNotContainsString('Team members from HR', $html);
    }

    public function test_a_team_can_be_added_by_name_and_head_count_without_the_staff_list(): void
    {
        $this->app->singleton(PeopleDirectory::class, NoPeopleDirectory::class);

        $this->post(route('moulding-teams.teams.store'), ['code' => 'T9', 'name' => 'Night', 'lead_name' => 'Bilal Khan', 'member_count' => 6, 'status' => 'ACTIVE'])
            ->assertSessionHasNoErrors();

        $team = MouldingTeam::where('code', 'T9')->firstOrFail();
        $this->assertSame(['Bilal Khan', 6, null], [$team->lead_name, $team->member_count, $team->lead_employee_id]);
    }

    public function test_with_the_hr_module_on_the_team_screen_still_picks_members_from_the_employee_list(): void
    {
        $html = $this->get(route('moulding-teams.index'))->assertOk()->getContent();

        $this->assertStringContainsString('employee_ids_sent', $html);
        $this->assertStringContainsString('Team members from HR', $html);
    }
}
