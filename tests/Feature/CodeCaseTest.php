<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use App\Models\MouldingStation;
use App\Models\MouldingTeam;
use App\Models\PayComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Codes are kept in capitals, so typing one in lower case must be refused as a duplicate, not fail in the database. */
class CodeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lower_case_duplicate_code_is_refused_with_a_message(): void
    {
        MouldingTeam::create(['code' => 'T1', 'name' => 'Alpha', 'lead_name' => 'A', 'member_count' => 1, 'status' => 'ACTIVE']);
        $this->post(route('moulding-teams.teams.store'), ['code' => 't1', 'name' => 'Beta', 'lead_name' => 'B', 'member_count' => 1, 'status' => 'ACTIVE'])->assertSessionHasErrors('code');

        MouldingStation::create(['code' => 'FL-1', 'name' => 'Floor', 'station_type' => 'FLOOR', 'status' => 'ACTIVE']);
        $this->post(route('moulding-teams.stations.store'), ['code' => 'fl-1', 'name' => 'Floor 2', 'station_type' => 'FLOOR', 'status' => 'ACTIVE'])->assertSessionHasErrors('code');

        LeaveType::create(['code' => 'XSL', 'name' => 'Sick', 'annual_days' => 8, 'accrual' => 'UPFRONT', 'carry_forward_max' => 0, 'active' => true, 'paid' => true]);
        $this->post(route('hr.leave.types.store'), ['code' => 'xsl', 'name' => 'Sick 2', 'annual_days' => 5, 'accrual' => 'UPFRONT'])->assertSessionHasErrors('code');

        PayComponent::create(['code' => 'XBONUS', 'name' => 'Bonus', 'kind' => 'EARNING', 'calc' => 'FIXED', 'default_value' => 0, 'sort' => 90, 'taxable' => true, 'prorate' => false, 'active' => true]);
        $this->post(route('hr.payroll.components.store'), ['code' => 'xbonus', 'name' => 'Bonus 2', 'kind' => 'EARNING', 'calc' => 'FIXED'])->assertSessionHasErrors('code');

        $this->assertSame([1, 1, 1], [MouldingTeam::count(), MouldingStation::count(), LeaveType::where('code', 'like', 'X%')->count()]);
    }
}
