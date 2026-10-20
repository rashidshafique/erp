<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\HrShift;
use App\Services\Hr\DayPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrCompanyDefaultsTest extends TestCase
{
    use RefreshDatabase;

    // 2026-10-12 is a Monday, 2026-10-16 a Friday, 2026-10-18 a Sunday.
    private function emp(array $o = []): Employee
    {
        $this->post(route('hr.employees.store'), $o + ['first_name' => 'Imran', 'last_name' => 'Ali', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01'])->assertSessionHasNoErrors();

        return Employee::orderByDesc('code')->first();
    }

    public function test_new_employees_follow_the_day_shift_and_sunday_off_without_choosing(): void
    {
        $e = $this->emp();
        $this->assertNull($e->shift_id);
        $this->assertNull($e->weekly_off);
        $plans = new DayPlan;
        $this->assertSame('Day shift', $plans->one($e, '2026-10-12')['shift']->name);
        $this->assertSame('OFF', $plans->one($e, '2026-10-18')['kind']);
    }

    public function test_changing_the_company_defaults_moves_everyone_who_has_no_own_setting(): void
    {
        $follower = $this->emp();
        $own = $this->emp(['shift_id' => HrShift::where('name', 'Evening shift')->value('id'), 'weekly_off' => '6,0']);
        $evening = HrShift::where('name', 'Evening shift')->first();

        $this->put(route('hr.defaults.update'), ['default_shift_id' => $evening->id, 'default_weekly_off' => '5'])->assertSessionHasNoErrors();

        $plans = new DayPlan;
        $this->assertSame($evening->id, $plans->one($follower, '2026-10-12')['shift']->id);
        $this->assertSame('OFF', $plans->one($follower, '2026-10-16')['kind']);
        $this->assertSame('WORK', $plans->one($follower, '2026-10-18')['kind']);
        $this->assertSame('WORK', $plans->one($own, '2026-10-16')['kind']);      // own Saturday and Sunday off
        $this->assertSame('OFF', $plans->one($own, '2026-10-18')['kind']);
    }

    public function test_an_employee_can_be_set_to_no_weekly_off_and_defaults_reject_an_inactive_shift(): void
    {
        $e = $this->emp(['weekly_off' => 'NONE']);
        $this->assertSame('', $e->weekly_off);
        $this->assertSame('WORK', (new DayPlan)->one($e, '2026-10-18')['kind']);

        $shift = HrShift::first();
        $shift->update(['active' => false]);
        $this->put(route('hr.defaults.update'), ['default_shift_id' => $shift->id, 'default_weekly_off' => '0'])->assertSessionHasErrors('default_shift_id');
        $this->get(route('hr.roster', ['tab' => 'shifts']))->assertOk()->assertSee('Company defaults');
    }
}
