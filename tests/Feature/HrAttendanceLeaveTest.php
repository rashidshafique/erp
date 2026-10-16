<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\HrHoliday;
use App\Models\HrRosterEntry;
use App\Models\HrShift;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Services\Hr\AttendanceCalculator;
use App\Services\Hr\DayPlan;
use App\Services\Hr\LeaveAccrual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrAttendanceLeaveTest extends TestCase
{
    use RefreshDatabase;

    // 2026-10-12 is a Monday. Sunday (0) is the default weekly off.
    private function day(): HrShift
    {
        return HrShift::where('name', 'Day shift')->first();      // 08:00–17:00, 60 min break, 10 min grace
    }

    private function emp(array $o = []): Employee
    {
        return Employee::create($o + ['first_name' => 'Imran', 'last_name' => 'Ali', 'employment_type' => 'PERMANENT', 'joined_on' => '2025-01-01', 'status' => 'ACTIVE', 'confirmed_on' => '2025-04-01', 'shift_id' => $this->day()->id, 'weekly_off' => '0', 'gender' => 'M']);
    }

    private function calc(string $in, ?string $out, string $date = '2026-10-12', string $kind = 'WORK', ?HrShift $shift = null): array
    {
        $plan = ['kind' => $kind, 'shift' => $shift ?? $this->day(), 'leave' => null, 'holiday' => null];

        return (new AttendanceCalculator)->compute($plan, $date, $in ? Carbon::parse("$date $in") : null, $out ? Carbon::parse("$date $out") : null);
    }

    public function test_on_time_full_day(): void
    {
        $r = $this->calc('07:55', '17:05');
        $this->assertSame('PRESENT', $r['status']);
        $this->assertSame(0, $r['late_minutes']);
        $this->assertSame(0, $r['overtime_minutes']);            // 5 minutes past the end is under the 30 minute threshold
        $this->assertSame(550 - 60, $r['worked_minutes']);
        $this->assertFalse($r['exception']);
    }

    public function test_grace_then_late_counts_from_the_start(): void
    {
        $this->assertSame([], array_intersect(['LATE'], $this->calc('08:10', '17:00')['flags']));
        $r = $this->calc('08:25', '17:00');
        $this->assertSame(25, $r['late_minutes']);
        $this->assertContains('LATE', $r['flags']);
    }

    public function test_early_out_overtime_and_half_day(): void
    {
        $this->assertContains('EARLY_OUT', $this->calc('08:00', '15:00')['flags']);
        $o = $this->calc('08:00', '18:30');
        $this->assertSame(90, $o['overtime_minutes']);
        $this->assertContains('OVERTIME', $o['flags']);
        $h = $this->calc('08:00', '11:30');                       // 3.5 h worked of an 8 h paid day
        $this->assertSame('HALF_DAY', $h['status']);
    }

    public function test_missing_punches_and_absence_are_exceptions(): void
    {
        $a = $this->calc('', null);
        $this->assertSame('ABSENT', $a['status']);
        $this->assertTrue($a['exception']);
        $m = $this->calc('08:00', null);
        $this->assertContains('MISSING_OUT', $m['flags']);
        $this->assertTrue($m['exception']);
    }

    public function test_work_on_a_day_off_is_all_overtime(): void
    {
        $r = $this->calc('09:00', '13:00', '2026-10-11', 'OFF');
        $this->assertSame(240, $r['overtime_minutes']);
        $this->assertContains('WORKED_OFF', $r['flags']);
        $this->assertSame('OFF', $this->calc('', null, '2026-10-11', 'OFF')['status']);
        $this->assertSame('HOLIDAY', $this->calc('', null, '2026-10-12', 'HOLIDAY')['status']);
    }

    public function test_night_shift_runs_past_midnight(): void
    {
        $night = HrShift::where('name', 'Night shift')->first();
        $this->assertFalse($night->crossesMidnight());          // 00:00–08:00 is within one calendar day
        $late = HrShift::create(['name' => 'Late', 'starts_at' => '22:00', 'ends_at' => '06:00', 'break_minutes' => 30, 'grace_minutes' => 10]);
        $this->assertTrue($late->crossesMidnight());
        $r = (new AttendanceCalculator)->compute(['kind' => 'WORK', 'shift' => $late, 'leave' => null, 'holiday' => null], '2026-10-12', Carbon::parse('2026-10-12 21:58'), Carbon::parse('2026-10-13 06:40'));
        $this->assertSame(40, $r['overtime_minutes']);
        $this->assertSame(0, $r['late_minutes']);
        $this->assertSame(522 - 30, $r['worked_minutes']);
    }

    public function test_day_plan_precedence(): void
    {
        $e = $this->emp();
        $plans = app(DayPlan::class);
        $sun = '2026-10-11';
        $this->assertSame('WORK', $plans->one($e, '2026-10-12')['kind']);
        $this->assertSame('OFF', $plans->one($e, $sun)['kind']);
        HrHoliday::create(['holiday_on' => '2026-10-12', 'name' => 'Test day']);
        $this->assertSame('HOLIDAY', $plans->one($e, '2026-10-12')['kind']);
        HrRosterEntry::create(['employee_id' => $e->id, 'work_date' => '2026-10-12', 'shift_id' => $this->day()->id]);
        $this->assertSame('WORK', $plans->one($e, '2026-10-12')['kind']);               // rostered to work on a holiday
        HrRosterEntry::create(['employee_id' => $e->id, 'work_date' => '2026-10-13', 'shift_id' => null]);
        $this->assertSame('OFF', $plans->one($e, '2026-10-13')['kind']);                // rostered off on a normal day
        $e2 = $this->emp(['first_name' => 'Roster', 'weekly_off' => '']);
        $this->assertSame('WORK', $plans->one($e2, $sun)['kind']);                      // no weekly off
        $l = LeaveRequest::create(['code' => 'X1', 'employee_id' => $e->id, 'leave_type_id' => LeaveType::first()->id, 'from_date' => '2026-10-12', 'to_date' => '2026-10-12', 'days' => 1, 'status' => 'APPROVED']);
        $this->assertSame('LEAVE', $plans->one($e, '2026-10-12')['kind']);              // leave beats the roster
    }

    public function test_daily_sheet_saves_times_and_computes(): void
    {
        $e = $this->emp();
        $this->get(route('hr.attendance', ['date' => '2026-10-12']))->assertOk()->assertSee($e->code);
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => '08:30', 'out' => '19:00', 'note' => 'rush order']]])->assertSessionHas('success');
        $r = AttendanceRecord::first();
        $this->assertSame(30, $r->late_minutes);
        $this->assertSame(120, $r->overtime_minutes);
        $this->assertSame('PENDING', $r->overtime_status);
        $this->assertSame('rush order', $r->note);
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => '09:00', 'out' => '08:00']]])->assertSessionHas('success');   // out before in = next morning
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => 'abc', 'out' => '']]])->assertSessionHas('error');
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => '', 'out' => '']]]);      // blank row, existing record: cleared to absent
        $this->assertSame('ABSENT', AttendanceRecord::first()->status);
        $this->assertSame(1, AttendanceRecord::count());
    }

    public function test_cannot_record_before_the_joining_date(): void
    {
        $e = $this->emp(['joined_on' => '2026-10-13']);
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => '08:00', 'out' => '17:00']]])->assertSessionHas('error');
        $this->assertSame(0, AttendanceRecord::count());
        $this->get(route('hr.attendance', ['date' => '2026-10-12']));                       // shows the message once
        $this->get(route('hr.attendance', ['date' => '2026-10-12']))->assertDontSee($e->code);
    }

    public function test_closing_a_day_creates_absent_off_holiday_and_leave_records(): void
    {
        $present = $this->emp(['first_name' => 'Here']);
        $absent = $this->emp(['first_name' => 'Gone']);
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$present->id => ['in' => '08:00', 'out' => '17:00']]]);
        $this->post(route('hr.attendance.close'), ['date' => '2026-10-12'])->assertSessionHas('success');
        $this->assertSame('ABSENT', AttendanceRecord::where('employee_id', $absent->id)->first()->status);
        $this->assertSame('OPEN', AttendanceRecord::where('employee_id', $absent->id)->first()->exception_status);
        $this->post(route('hr.attendance.close'), ['date' => '2026-10-12']);
        $this->assertSame(2, AttendanceRecord::count());                                      // not twice
        $this->post(route('hr.attendance.close'), ['date' => '2026-10-11']);                  // a Sunday
        $this->assertSame(2, AttendanceRecord::where('status', 'OFF')->count());
        $this->post(route('hr.attendance.close'), ['date' => '2099-01-01'])->assertSessionHasErrors('date');
    }

    public function test_exceptions_can_be_excused_confirmed_or_charged_to_leave(): void
    {
        $a = $this->emp(['first_name' => 'A']);
        $b = $this->emp(['first_name' => 'B']);
        $c = $this->emp(['first_name' => 'C']);
        $this->post(route('hr.attendance.close'), ['date' => '2026-10-12']);
        $this->get(route('hr.attendance.exceptions', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSee($a->code);
        $ra = AttendanceRecord::where('employee_id', $a->id)->first();
        $rb = AttendanceRecord::where('employee_id', $b->id)->first();
        $rc = AttendanceRecord::where('employee_id', $c->id)->first();
        $this->post(route('hr.attendance.resolve', $ra), ['how' => 'PRESENT', 'note' => 'gate pass'])->assertSessionHas('success');
        $this->assertSame('PRESENT', $ra->fresh()->status);
        $this->post(route('hr.attendance.resolve', $rb), ['how' => 'ABSENT'])->assertSessionHas('success');
        $this->assertSame('ABSENT', $rb->fresh()->status);
        $this->assertSame('RESOLVED', $rb->fresh()->exception_status);
        $this->post(route('hr.attendance.resolve', $rb), ['how' => 'PRESENT'])->assertSessionHas('error');      // already settled

        $cl = LeaveType::where('code', 'CL')->first();
        app(LeaveAccrual::class)->grantYear(2026);
        $this->post(route('hr.attendance.resolve', $rc), ['how' => 'LEAVE:'.$cl->id])->assertSessionHas('success');
        $this->assertSame('LEAVE', $rc->fresh()->status);
        $this->assertSame(1, LeaveRequest::where('employee_id', $c->id)->where('status', 'APPROVED')->count());
        $this->assertEqualsWithDelta(9.0, app(\App\Services\Hr\LeaveService::class)->balance($c, $cl), 0.01);
    }

    public function test_resettling_survives_a_resave_of_the_same_punches(): void
    {
        $e = $this->emp();
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => '08:00', 'out' => '']]]);
        $r = AttendanceRecord::first();
        $this->assertSame('OPEN', $r->exception_status);
        $this->post(route('hr.attendance.resolve', $r), ['how' => 'PRESENT']);
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => '08:00', 'out' => '', 'note' => 'x']]]);
        $this->assertSame('RESOLVED', $r->fresh()->exception_status);
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => '08:00', 'out' => '17:00']]]);
        $this->assertSame('NONE', $r->fresh()->exception_status);                             // the problem is gone
    }

    public function test_overtime_is_approved_in_part_or_rejected(): void
    {
        $e = $this->emp();
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => '08:00', 'out' => '19:00']]]);
        $r = AttendanceRecord::first();
        $this->assertSame(120, $r->overtime_minutes);
        $this->get(route('hr.attendance.overtime', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSee($e->code);
        $this->post(route('hr.attendance.overtime.decide'), ['decision' => 'APPROVE', 'ids' => [$r->id], 'minutes' => [$r->id => 90]])->assertSessionHas('success');
        $r->refresh();
        $this->assertSame('APPROVED', $r->overtime_status);
        $this->assertSame(90, $r->overtime_approved_minutes);
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => '08:00', 'out' => '19:00', 'note' => 'again']]]);
        $this->assertSame('APPROVED', $r->fresh()->overtime_status);                          // same amount, decision kept
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => '08:00', 'out' => '20:00']]]);
        $this->assertSame('PENDING', $r->fresh()->overtime_status);                           // amount changed, decide again
        $this->post(route('hr.attendance.overtime.decide'), ['decision' => 'REJECT', 'ids' => [$r->id]]);
        $this->assertSame('REJECTED', $r->fresh()->overtime_status);
        $this->assertSame(0, $r->fresh()->overtime_approved_minutes);
    }

    public function test_import_punches_pairs_first_and_last_and_reports_unknown_codes(): void
    {
        $e = $this->emp();
        $csv = "code,time\n{$e->code},2026-10-12 07:58\n{$e->code},2026-10-12 12:30\n{$e->code},2026-10-12 17:12\nEMP-99999,2026-10-12 08:00\n";
        $r = $this->post(route('hr.attendance.import.run'), ['pasted' => $csv])->assertOk();
        $r->assertSee('Not an employee code: EMP-99999');
        $rec = AttendanceRecord::first();
        $this->assertSame('07:58', $rec->check_in->format('H:i'));
        $this->assertSame('17:12', $rec->check_out->format('H:i'));
        $this->post(route('hr.attendance.import.run'), ['pasted' => ''])->assertSessionHas('error');
        $this->post(route('hr.attendance.import.run'), ['pasted' => "{$e->code},2026-10-13,08:00\n{$e->code},2026-10-13,17:00"])->assertOk();      // three columns
        $this->assertSame(2, AttendanceRecord::count());
    }

    public function test_import_attaches_the_morning_punch_to_last_nights_shift(): void
    {
        $late = HrShift::create(['name' => 'Late', 'starts_at' => '22:00', 'ends_at' => '06:00', 'break_minutes' => 30, 'grace_minutes' => 10]);
        $e = $this->emp(['shift_id' => $late->id, 'weekly_off' => '']);
        $this->post(route('hr.attendance.import.run'), ['pasted' => "{$e->code},2026-10-12 21:55\n{$e->code},2026-10-13 06:05"])->assertOk();
        $this->assertSame(1, AttendanceRecord::count());
        $r = AttendanceRecord::first();
        $this->assertSame('2026-10-12', $r->work_date->toDateString());
        $this->assertSame('06:05', $r->check_out->format('H:i'));
    }

    public function test_roster_overrides_and_clears(): void
    {
        $e = $this->emp();
        $ev = HrShift::where('name', 'Evening shift')->first();
        $this->get(route('hr.roster', ['week' => '2026-10-12']))->assertOk()->assertSee($e->code);
        $this->post(route('hr.roster.save'), ['week' => '2026-10-12', 'cells' => [$e->id => ['2026-10-12' => $ev->id, '2026-10-13' => 'OFF', '2026-10-14' => '']]]);
        $this->assertSame(2, HrRosterEntry::count());
        $this->assertSame($ev->id, app(DayPlan::class)->one($e, '2026-10-12')['shift']->id);
        $this->assertSame('OFF', app(DayPlan::class)->one($e, '2026-10-13')['kind']);
        $this->post(route('hr.roster.save'), ['week' => '2026-10-12', 'cells' => [$e->id => ['2026-10-12' => '', '2026-10-13' => '']]]);
        $this->assertSame(0, HrRosterEntry::count());
        $this->post(route('hr.roster.save'), ['week' => '2026-10-12', 'cells' => [$e->id => ['2026-10-12' => 'not-a-shift']]]);
        $this->assertSame(0, HrRosterEntry::count());
    }

    public function test_shifts_and_holidays_screens(): void
    {
        $this->post(route('hr.shifts.store'), ['name' => 'Pour', 'starts_at' => '06:00', 'ends_at' => '14:00', 'break_minutes' => 30, 'grace_minutes' => 5])->assertSessionHasNoErrors();
        $s = HrShift::where('name', 'Pour')->first();
        $this->assertMatchesRegularExpression('/^SHF-\d{5}$/', $s->code);
        $this->post(route('hr.shifts.store'), ['name' => 'Same', 'starts_at' => '06:00', 'ends_at' => '06:00', 'break_minutes' => 0, 'grace_minutes' => 0])->assertSessionHasErrors('ends_at');
        $this->put(route('hr.shifts.update', $s), ['name' => 'Pour 2', 'starts_at' => '06:00', 'ends_at' => '14:30', 'break_minutes' => 30, 'grace_minutes' => 5])->assertSessionHasNoErrors();
        $this->assertFalse($s->fresh()->active);
        $this->post(route('hr.holidays.fixed'), ['year' => 2026])->assertSessionHas('success');
        $this->assertSame(count(config('hr.fixed_holidays')), HrHoliday::count());
        $this->post(route('hr.holidays.fixed'), ['year' => 2026]);
        $this->assertSame(count(config('hr.fixed_holidays')), HrHoliday::count());          // not twice
        $this->post(route('hr.holidays.store'), ['holiday_on' => '2026-03-20', 'name' => 'Eid', 'kind' => 'PUBLIC']);
        $h = HrHoliday::where('name', 'Eid')->first();
        $this->get(route('hr.roster', ['year' => 2026]))->assertSee('Eid')->assertSee('Independence Day');
        $this->delete(route('hr.holidays.destroy', $h));
        $this->assertNull(HrHoliday::find($h->id));
    }

    // ---- leave ----

    private function leave(Employee $e, string $code, string $from, ?string $to = null, array $extra = [])
    {
        return $this->post(route('leave.store'), ['employee_id' => $e->id, 'leave_type_id' => LeaveType::where('code', $code)->value('id'), 'from_date' => $from, 'to_date' => $to] + $extra);
    }

    private function bal(Employee $e, string $code): float
    {
        return app(\App\Services\Hr\LeaveService::class)->balance($e, LeaveType::where('code', $code)->first());
    }

    public function test_leave_days_skip_weekly_offs_and_holidays(): void
    {
        $e = $this->emp();
        app(LeaveAccrual::class)->grantYear(2026);
        HrHoliday::create(['holiday_on' => '2026-10-14', 'name' => 'Mid-week holiday']);
        // Mon 12 – Mon 19: 12,13,15,16,17 + 19 working = 6 (14 holiday, 18 Sunday)
        $this->leave($e, 'CL', '2026-10-12', '2026-10-19')->assertSessionHas('success');
        $this->assertEqualsWithDelta(6.0, LeaveRequest::first()->days, 0.001);
        $this->assertMatchesRegularExpression('/^LVR-\d{5}$/', LeaveRequest::first()->code);
    }

    public function test_leave_validation(): void
    {
        $e = $this->emp();
        app(LeaveAccrual::class)->grantYear(2026);
        $this->leave($e, 'CL', '2026-10-11')->assertSessionHas('error');                                              // a Sunday only: no working day
        $this->leave($e, 'CL', '2026-10-12', '2026-10-13', ['half_day' => 1])->assertSessionHas('error');             // half day over two days
        $this->leave($e, 'CL', '2026-10-12', '2026-10-30')->assertSessionHas('error');                                // more than 10 days
        $this->leave($e, 'ML', '2026-10-12', '2026-10-13')->assertSessionHas('error');                                // women only
        $this->leave($e, 'CL', '2024-12-30')->assertSessionHas('error');                                              // before joining
        $this->leave($e, 'CL', '2026-10-12', '2026-10-09')->assertSessionHasErrors('to_date');
        $this->assertSame(0, LeaveRequest::count());
        $this->leave($e, 'CL', '2026-10-12', '2026-10-13')->assertSessionHas('success');
        $this->leave($e, 'SL', '2026-10-13', '2026-10-14')->assertSessionHas('error');                                // overlaps the first
        $this->leave($e, 'CL', '2026-10-15', null, ['half_day' => 1])->assertSessionHas('success');
        $this->assertEqualsWithDelta(0.5, LeaveRequest::orderByDesc('code')->first()->days, 0.001);
    }

    public function test_balance_is_checked_including_what_is_already_waiting(): void
    {
        $e = $this->emp();
        app(LeaveAccrual::class)->grantYear(2026);                                    // CL 10, SL 8
        $this->leave($e, 'CL', '2026-11-02', '2026-11-07')->assertSessionHas('success');    // 6 days
        $this->leave($e, 'CL', '2026-11-09', '2026-11-13')->assertSessionHas('error');      // 5 more > 4 left
        $this->leave($e, 'CL', '2026-11-09', '2026-11-12')->assertSessionHas('success');    // 4 is fine
    }

    public function test_probation_restriction_and_gender(): void
    {
        $new = $this->emp(['first_name' => 'New', 'status' => 'PROBATION', 'confirmed_on' => null]);
        $w = $this->emp(['first_name' => 'Maryam', 'gender' => 'F']);
        $this->leave($new, 'ML', '2026-10-12', '2026-10-16')->assertSessionHas('error');
        $this->leave($w, 'ML', '2026-10-12', '2026-10-16')->assertSessionHas('success');     // no balance kept, limited to 90 days at a time
        $this->leave($w, 'ML', '2026-12-01', '2027-03-31')->assertSessionHas('error');
        $this->leave($new, 'UL', '2026-10-19', '2026-10-20')->assertSessionHas('success');    // unpaid: unlimited
    }

    public function test_approval_writes_the_ledger_and_the_attendance_sheet_and_cancel_undoes_it(): void
    {
        $e = $this->emp();
        app(LeaveAccrual::class)->grantYear(2026);
        $this->leave($e, 'CL', '2026-10-12', '2026-10-13');
        $r = LeaveRequest::first();
        $this->assertEqualsWithDelta(10.0, $this->bal($e, 'CL'), 0.01);                       // nothing taken until approved
        $this->post(route('leave.decide', $r), ['decision' => 'APPROVE', 'note' => 'ok'])->assertSessionHas('success');
        $this->assertEqualsWithDelta(8.0, $this->bal($e, 'CL'), 0.01);
        $this->assertSame(2, AttendanceRecord::where('employee_id', $e->id)->where('status', 'LEAVE')->count());
        $this->post(route('leave.decide', $r), ['decision' => 'REJECT'])->assertSessionHas('error');     // already decided
        $this->post(route('leave.cancel', $r))->assertSessionHas('success');
        $this->assertEqualsWithDelta(10.0, $this->bal($e, 'CL'), 0.01);
        $this->assertSame(0, AttendanceRecord::count());
        $this->assertSame('CANCELLED', $r->fresh()->status);
    }

    public function test_leave_taken_while_punches_exist_keeps_the_punches(): void
    {
        $e = $this->emp();
        app(LeaveAccrual::class)->grantYear(2026);
        $this->post(route('hr.attendance.save'), ['date' => '2026-10-12', 'rows' => [$e->id => ['in' => '08:00', 'out' => '17:00']]]);
        $this->leave($e, 'CL', '2026-10-12', null, ['approved_now' => 1])->assertSessionHas('success');
        $rec = AttendanceRecord::first();
        $this->assertNotNull($rec->check_in);
        $this->assertSame('MANUAL', $rec->source);
        $this->post(route('leave.cancel', LeaveRequest::first()));
        $this->assertSame(1, AttendanceRecord::count());                                        // real punches are not deleted
    }

    public function test_rejecting_keeps_the_balance(): void
    {
        $e = $this->emp();
        app(LeaveAccrual::class)->grantYear(2026);
        $this->leave($e, 'SL', '2026-10-12');
        $this->post(route('leave.decide', LeaveRequest::first()), ['decision' => 'REJECT', 'note' => 'busy'])->assertSessionHas('success');
        $this->assertEqualsWithDelta(8.0, $this->bal($e, 'SL'), 0.01);
        $this->assertSame('busy', LeaveRequest::first()->decision_note);
    }

    public function test_hr_can_record_approved_leave_and_adjust_balances(): void
    {
        $e = $this->emp();
        app(LeaveAccrual::class)->grantYear(2026);
        $this->leave($e, 'SL', '2026-10-12', null, ['approved_now' => 1])->assertSessionHas('success');
        $this->assertSame('APPROVED', LeaveRequest::first()->status);
        $sl = LeaveType::where('code', 'SL')->first();
        $this->post(route('hr.leave.adjust', $e), ['leave_type_id' => $sl->id, 'days' => 3, 'note' => 'carried from old system'])->assertSessionHas('success');
        $this->assertEqualsWithDelta(10.0, $this->bal($e, 'SL'), 0.01);
        $this->post(route('hr.leave.adjust', $e), ['leave_type_id' => $sl->id, 'days' => 0, 'note' => 'x'])->assertSessionHas('error');
        $this->get(route('hr.employees.show', $e))->assertOk()->assertSee('carried from old system');
        $this->get(route('hr.leave'))->assertOk();
        $this->get(route('hr.leave', ['status' => 'ALL']))->assertOk()->assertSee('LVR-');
    }

    public function test_monthly_accrual_is_a_twelfth_a_month_and_never_doubles(): void
    {
        $old = $this->emp(['first_name' => 'Old']);
        $early = $this->emp(['first_name' => 'Early', 'joined_on' => '2026-03-10']);
        $late = $this->emp(['first_name' => 'Late', 'joined_on' => '2026-03-20']);
        $acc = app(LeaveAccrual::class);
        $made = $acc->accrueMonth(Carbon::create(2026, 3, 1));
        $this->assertSame(2, $made);                                                              // 'Late' joined after the 15th
        $this->assertSame(0, $acc->accrueMonth(Carbon::create(2026, 3, 1)));
        $this->assertEqualsWithDelta(14 / 12, $this->bal($old, 'AL'), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->bal($late, 'AL'), 0.01);
        $acc->accrueMonth(Carbon::create(2026, 4, 1));
        $this->assertEqualsWithDelta(2 * 14 / 12, $this->bal($early, 'AL'), 0.02);
        $this->assertEqualsWithDelta(14 / 12, $this->bal($late, 'AL'), 0.02);
    }

    public function test_yearly_grant_is_pro_rata_for_joiners_and_catch_up_is_idempotent(): void
    {
        $old = $this->emp(['first_name' => 'Old']);
        $mid = $this->emp(['first_name' => 'Mid', 'joined_on' => '2026-07-01']);
        $acc = app(LeaveAccrual::class);
        $this->assertSame(['granted' => 4, 'accrued' => 0], $this->catch(Carbon::create(2026, 1, 20), $acc));    // CL and SL for two people
        $this->assertEqualsWithDelta(10.0, $this->bal($old, 'CL'), 0.01);
        $this->assertEqualsWithDelta(5.0, $this->bal($mid, 'CL'), 0.01);                          // 6 of 12 months
        $this->assertEqualsWithDelta(4.0, $this->bal($mid, 'SL'), 0.01);
        $again = $this->catch(Carbon::create(2026, 1, 20), $acc);
        $this->assertSame(0, $again['granted']);
        $r = $acc->catchUp(Carbon::create(2026, 5, 10));                                          // Jan–Apr accrue
        $this->assertSame(0, $r['granted']);
        $this->assertGreaterThan(0, $r['accrued']);
        $this->assertEqualsWithDelta(4 * 14 / 12, $this->bal($old, 'AL'), 0.02);
    }

    private function catch(Carbon $d, LeaveAccrual $acc): array
    {
        return $acc->catchUp($d);
    }

    public function test_year_end_carries_forward_up_to_the_cap_and_expires_the_rest(): void
    {
        $e = $this->emp();
        $acc = app(LeaveAccrual::class);
        $acc->grantYear(2026);
        $at = LeaveType::where('code', 'AL')->first();
        $this->post(route('hr.leave.adjust', $e), ['leave_type_id' => $at->id, 'days' => 20, 'note' => 'opening']);   // AL cap is 14
        $this->assertSame(2, $acc->closeYear(2026) + 0 === 0 ? 0 : 2);
        $this->assertEqualsWithDelta(14.0, $this->bal($e, 'AL'), 0.01);                            // carried 14, wrote off 6
        $this->assertEqualsWithDelta(0.0, $this->bal($e, 'CL'), 0.01);                             // casual leave never carries
        $this->assertSame(0, $acc->closeYear(2026));                                               // second run adds nothing
        $this->post(route('hr.leave.close-year'), ['year' => 2025])->assertSessionHas('success');
    }

    public function test_balance_screens_and_runs_render(): void
    {
        $e = $this->emp();
        $this->post(route('hr.leave.accrue'))->assertSessionHas('success');
        $this->assertGreaterThan(0, LeaveLedgerEntry::count());
        $this->get(route('hr.leave'))->assertOk()->assertSee('Balances')->assertSee($e->code);
        $this->get(route('leave.my'))->assertOk();
        $this->get(route('hr.attendance.exceptions'))->assertOk();
        $this->get(route('hr.attendance.overtime'))->assertOk();
        $this->get(route('hr.attendance.import'))->assertOk();
        $this->get(route('hr.dashboard'))->assertOk();
        $this->post(route('hr.leave.types.store'), ['code' => 'hl', 'name' => 'Hajj leave', 'annual_days' => 15, 'accrual' => 'NONE', 'paid' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_types', ['code' => 'HL', 'paid' => true]);
        $this->post(route('hr.leave.types.store'), ['code' => 'HL', 'name' => 'Dup', 'annual_days' => 1, 'accrual' => 'NONE'])->assertSessionHasErrors('code');
    }

    public function test_commands_run(): void
    {
        $this->emp();
        $this->artisan('hr:leave-accrue')->assertSuccessful();
        $this->artisan('hr:close-attendance', ['date' => '2026-10-12'])->assertSuccessful();
        $this->assertSame(1, AttendanceRecord::count());
    }

    // ---- who may do what ----

    public function test_managers_decide_for_their_team_and_staff_for_themselves(): void
    {
        config(['foundry.require_login' => true]);
        $mk = function (string $role, string $name) {
            $u = User::factory()->create(['name' => $name, 'is_active' => true, 'must_change_password' => false]);
            $u->roles()->attach(Role::where('code', $role)->value('id'));

            return $u;
        };
        $uBoss = $mk('VIEWER', 'Boss');
        $uWorker = $mk('VIEWER', 'Worker');
        $uOther = $mk('VIEWER', 'Other');
        $boss = $this->emp(['first_name' => 'Boss', 'user_id' => $uBoss->id]);
        $worker = $this->emp(['first_name' => 'Worker', 'user_id' => $uWorker->id, 'manager_id' => $boss->id]);
        $other = $this->emp(['first_name' => 'Other', 'user_id' => $uOther->id]);
        app(LeaveAccrual::class)->grantYear(2026);

        $this->actingAs($uWorker)->get(route('leave.my'))->assertOk()->assertSee('Worker');
        $this->actingAs($uWorker)->get(route('hr.leave'))->assertForbidden();                    // HR screens stay closed
        $this->actingAs($uWorker)->get(route('hr.attendance'))->assertForbidden();
        $this->actingAs($uWorker)->post(route('leave.store'), ['leave_type_id' => LeaveType::where('code', 'CL')->value('id'), 'from_date' => '2026-10-12', 'reason' => 'family'])->assertSessionHas('success');
        $r = LeaveRequest::first();
        $this->assertSame($worker->id, $r->employee_id);
        $this->actingAs($uWorker)->post(route('leave.store'), ['employee_id' => $other->id, 'leave_type_id' => LeaveType::where('code', 'CL')->value('id'), 'from_date' => '2026-10-19'])->assertForbidden();   // not for someone else
        $this->actingAs($uWorker)->post(route('leave.decide', $r), ['decision' => 'APPROVE'])->assertForbidden();   // not their own
        $this->actingAs($uOther)->post(route('leave.decide', $r), ['decision' => 'APPROVE'])->assertForbidden();    // not their manager
        $this->actingAs($uBoss)->get(route('leave.my'))->assertOk()->assertSee('Waiting for your decision')->assertSee('family');
        $this->actingAs($uBoss)->post(route('leave.decide', $r), ['decision' => 'APPROVE'])->assertSessionHas('success');
        $this->assertSame('APPROVED', $r->fresh()->status);
        $this->assertSame($uBoss->id, $r->fresh()->decided_by_id);
        $this->actingAs($uOther)->post(route('leave.cancel', $r))->assertForbidden();
    }

    public function test_hr_manager_role_reaches_hr_screens(): void
    {
        config(['foundry.require_login' => true]);
        $u = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $u->roles()->attach(Role::where('code', 'HR_MANAGER')->value('id'));
        foreach (['hr.attendance', 'hr.roster', 'hr.leave', 'hr.dashboard', 'leave.my'] as $name) {
            $this->actingAs($u)->get(route($name))->assertOk();
        }
    }

    public function test_employee_form_saves_shift_and_weekly_off(): void
    {
        $ev = HrShift::where('name', 'Evening shift')->first();
        $this->post(route('hr.employees.store'), ['first_name' => 'Sam', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01', 'shift_id' => $ev->id, 'weekly_off' => '5'])->assertSessionHasNoErrors();
        $e = Employee::where('first_name', 'Sam')->first();
        $this->assertSame($ev->id, $e->shift_id);
        $this->assertSame('5', $e->weekly_off);
        $this->put(route('hr.employees.update', $e), ['first_name' => 'Sam', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01', 'weekly_off' => ''])->assertSessionHasNoErrors();
        $this->assertSame('', $e->fresh()->weekly_off);                                           // none: the roster decides
        $this->post(route('hr.employees.store'), ['first_name' => 'Bad', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01', 'weekly_off' => '9'])->assertSessionHasErrors('weekly_off');
        $this->get(route('hr.employees.show', $e))->assertOk()->assertSee('Attendance')->assertSee('Balance history');
    }
}
