<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MouldingTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MouldingTeamMembersTest extends TestCase
{
    use RefreshDatabase;

    private function employee(string $firstName, string $status = 'ACTIVE'): Employee
    {
        return Employee::create([
            'first_name' => $firstName,
            'last_name' => 'Moulding',
            'employment_type' => 'PERMANENT',
            'joined_on' => '2025-01-01',
            'status' => $status,
            'weekly_off' => '0',
        ]);
    }

    private function teamPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'MOULD-TEAM-1',
            'name' => 'Moulding Team 1',
            'lead_name' => 'Team Lead',
            'status' => 'ACTIVE',
            'employee_ids_sent' => '1',
        ], $overrides);
    }

    public function test_hr_employees_can_be_linked_and_membership_count_is_kept_in_sync(): void
    {
        $ali = $this->employee('Ali');
        $bilal = $this->employee('Bilal');

        $this->post(route('moulding-teams.teams.store'), $this->teamPayload([
            'employee_ids' => [$ali->id, $bilal->id],
        ]))->assertSessionHasNoErrors();

        $team = MouldingTeam::where('code', 'MOULD-TEAM-1')->firstOrFail();
        $this->assertSame(2, (int) $team->member_count);
        $this->assertEqualsCanonicalizing([$ali->id, $bilal->id], $team->employees->pluck('id')->all());

        $this->put(route('moulding-teams.teams.update', $team), $this->teamPayload([
            'code' => $team->code,
            'name' => $team->name,
            'lead_name' => $team->lead_name,
            'employee_ids' => [$bilal->id],
        ]))->assertSessionHasNoErrors();

        $team->refresh();
        $this->assertSame(1, (int) $team->member_count);
        $this->assertSame([$bilal->id], $team->employees->pluck('id')->all());
    }


    public function test_team_lead_is_saved_as_one_of_the_linked_hr_employees(): void
    {
        $ali = $this->employee('Ali');
        $bilal = $this->employee('Bilal');

        $this->post(route('moulding-teams.teams.store'), $this->teamPayload([
            'code' => 'TEAM-LEAD-1',
            'lead_employee_id' => $bilal->id,
            'employee_ids' => [$ali->id, $bilal->id],
        ]))->assertSessionHasNoErrors();

        $team = MouldingTeam::where('code', 'TEAM-LEAD-1')->firstOrFail();
        $this->assertSame($bilal->id, $team->lead_employee_id);
        $this->assertSame('Bilal Moulding', $team->lead_name);
        $this->assertSame($bilal->id, $team->leadEmployee->id);
    }

    public function test_team_lead_must_be_in_the_selected_team_members(): void
    {
        $ali = $this->employee('Ali');
        $bilal = $this->employee('Bilal');

        $this->from('/moulding-teams')->post(route('moulding-teams.teams.store'), $this->teamPayload([
            'code' => 'TEAM-LEAD-INVALID',
            'lead_employee_id' => $bilal->id,
            'employee_ids' => [$ali->id],
        ]))->assertSessionHasErrors('lead_employee_id');

        $this->assertDatabaseMissing('moulding_teams', ['code' => 'TEAM-LEAD-INVALID']);
    }

    public function test_exited_hr_employees_cannot_be_assigned_to_a_team(): void
    {
        $former = $this->employee('Former', 'EXITED');

        $this->post(route('moulding-teams.teams.store'), $this->teamPayload([
            'code' => 'TEAM-FORMER',
            'employee_ids' => [$former->id],
        ]))->assertSessionHasErrors('employee_ids');

        $this->assertDatabaseMissing('moulding_teams', ['code' => 'TEAM-FORMER']);
    }
}
