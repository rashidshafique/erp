<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendancePanelTest extends TestCase
{
    use RefreshDatabase;

    private function emp(): Employee
    {
        $this->post(route('hr.employees.store'), ['first_name' => 'Bin', 'last_name' => 'Amal', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-01-01'])->assertSessionHasNoErrors();

        return Employee::orderByDesc('code')->first();
    }

    private function rec(Employee $e, string $d, array $o = []): AttendanceRecord
    {
        return AttendanceRecord::create($o + ['employee_id' => $e->id, 'work_date' => $d, 'status' => 'PRESENT', 'day_kind' => 'WORK']);
    }

    public function test_the_sheet_shows_summary_cards_against_the_day_before(): void
    {
        $e = $this->emp();
        $this->rec($e, '2026-10-14', ['late_minutes' => 10]);
        $this->rec($e, '2026-10-15', ['late_minutes' => 0, 'overtime_minutes' => 30, 'overtime_status' => 'PENDING']);
        $html = $this->get(route('hr.attendance', ['date' => '2026-10-15']))->assertOk()->assertSee('Late clock-in')->assertSee('vs prev day')->assertSee('data-person-url', false)->getContent();
        $this->assertStringContainsString('hr-cards', $html);
    }

    public function test_person_panel_draws_working_time_overtime_and_late(): void
    {
        $e = $this->emp();
        $this->rec($e, '2026-10-15', ['check_in' => '2026-10-15 09:20:00', 'check_out' => '2026-10-15 19:00:00', 'late_minutes' => 20, 'worked_minutes' => 540, 'overtime_minutes' => 60, 'overtime_status' => 'PENDING']);
        $this->rec($e, '2026-10-14', ['status' => 'OFF', 'day_kind' => 'OFF']);
        $html = $this->get(route('hr.attendance.person', [$e, 'to' => '2026-10-15', 'days' => 7]))->assertOk()
            ->assertSee('Bin Amal')->assertSee('Working time')->assertSee('9:20 AM')->assertSee('7:00 PM')->assertSee('Last 7 days')->assertSee('Approve overtime')->getContent();
        $this->assertStringContainsString('tl-seg o', $html);   // overtime segment
        $this->assertStringContainsString('tl-seg l', $html);   // late segment
        $this->assertStringContainsString('tl-track off', $html); // the day off
        $this->get(route('hr.attendance.person', [$e, 'to' => '2026-10-15', 'days' => 99]))->assertOk()->assertSee('Last 7 days');
    }

    public function test_person_panel_copes_with_a_person_with_no_records_and_a_night_shift(): void
    {
        $e = $this->emp();
        $this->get(route('hr.attendance.person', [$e, 'to' => '2026-10-15']))->assertOk()->assertSee('No record');
        $this->rec($e, '2026-10-15', ['check_in' => '2026-10-15 21:00:00', 'check_out' => '2026-10-16 05:30:00', 'worked_minutes' => 510]);
        $this->get(route('hr.attendance.person', [$e, 'to' => '2026-10-15']))->assertOk()->assertSee('9:00 PM')->assertSee('5:30 AM');
    }
}
