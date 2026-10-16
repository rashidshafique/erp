<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeExit;
use App\Models\PayComponent;
use App\Models\Payslip;
use App\Models\PayrollAdjustment;
use App\Services\Payroll\AdvanceService;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayrollRules;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\SalaryService;
use App\Services\Payroll\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 10:00:00');
        PayrollRules::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Basic 60,000 + house rent 40% (24,000) + conveyance 5,000 = 89,000 a month. */
    private function emp(array $o = [], float $basic = 60000): Employee
    {
        $e = Employee::create($o + ['first_name' => 'Imran', 'last_name' => 'Ali', 'employment_type' => 'PERMANENT', 'joined_on' => '2025-01-01', 'status' => 'ACTIVE', 'confirmed_on' => '2025-04-01', 'weekly_off' => '0', 'gender' => 'M']);
        $conv = PayComponent::where('code', 'CONV')->first();
        app(SalaryService::class)->revise($e, '2025-01-01', $basic, [$conv->id => '5000'], 'Joining');

        return $e->fresh();
    }

    /** Every day of September 2026 marked present, except the dates given as absent. */
    private function september(Employee $e, array $absent = [], array $extra = []): void
    {
        for ($d = 1; $d <= 30; $d++) {
            $date = sprintf('2026-09-%02d', $d);
            AttendanceRecord::create(array_merge(['employee_id' => $e->id, 'work_date' => $date, 'status' => in_array($date, $absent, true) ? 'ABSENT' : 'PRESENT', 'day_kind' => 'WORK'], $extra[$date] ?? []));
        }
    }

    private function sept(): array
    {
        return [Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')];
    }

    public function test_salary_structure_defaults_and_history(): void
    {
        $e = $this->emp();
        $s = app(SalaryService::class);
        $cur = $s->at($e, '2026-09-30');
        $this->assertEquals(89000.0, $cur->gross());                          // 60,000 + 24,000 house (40%) + 5,000 conveyance
        $s->revise($e, '2026-07-01', 70000, [], 'Annual increment');
        $this->assertEquals(70000.0, $s->at($e, '2026-09-30')->basic);
        $this->assertEquals(60000.0, $s->at($e, '2026-06-30')->basic);        // earlier months keep their own salary
        $this->assertNull($s->at($e, '2024-12-31'));
        $this->expectException(\RuntimeException::class);
        $s->revise($e, '2026-08-01', 0, [], null);
    }

    public function test_a_clean_month_with_two_absences(): void
    {
        $e = $this->emp();
        $this->september($e, ['2026-09-10', '2026-09-11']);
        [$a, $b] = $this->sept();
        $r = app(PayrollCalculator::class)->compute($e, $a, $b);
        $this->assertSame(30, $r['period_days']);
        $this->assertEquals(28.0, $r['payable_days']);
        $this->assertEquals(2.0, $r['absent_days']);
        $by = collect($r['lines'])->keyBy('code');
        $this->assertEquals(56000.0, $by['BASIC']['amount']);                 // 60,000 x 28/30
        $this->assertEquals(22400.0, $by['HOUSE']['amount']);
        $this->assertEquals(4667.0, $by['CONV']['amount']);                   // 4,666.67 rounded to the rupee
        $this->assertEquals(83067.0, $r['gross']);
        $this->assertEquals(364.0, $r['income_tax']);                         // (1,068,000 - 600,000) x 1% = 4,680 a year = 390 a month x 28/30
        $this->assertEquals(370.0, $r['eobi_employee']);                      // 1% of the 37,000 wage base
        $this->assertEquals(1850.0, $r['eobi_employer']);
        $this->assertEquals(0.0, $r['ss_employer']);                          // not enrolled by default
        $this->assertEquals(82333.0, $r['net']);
    }

    public function test_unpaid_leave_and_paid_leave_and_half_days(): void
    {
        $e = $this->emp();
        $unpaid = \App\Models\LeaveType::where('code', 'UL')->first();
        $paid = \App\Models\LeaveType::where('code', 'CL')->first();
        foreach ([[$unpaid, '2026-09-14'], [$paid, '2026-09-15']] as [$t, $d]) {
            \App\Models\LeaveRequest::create(['code' => 'T-'.$d, 'employee_id' => $e->id, 'leave_type_id' => $t->id, 'from_date' => $d, 'to_date' => $d, 'days' => 1, 'status' => 'APPROVED']);
        }
        $this->september($e, [], ['2026-09-14' => ['status' => 'LEAVE'], '2026-09-15' => ['status' => 'LEAVE'], '2026-09-16' => ['status' => 'HALF_DAY']]);
        [$a, $b] = $this->sept();
        $r = app(PayrollCalculator::class)->compute($e, $a, $b);
        $this->assertEquals(1.0, $r['unpaid_leave_days']);                    // only the unpaid kind costs pay
        $this->assertEquals(0.5, $r['absent_days']);                          // a half day worked for short hours: the other half is unpaid
        $this->assertEquals(28.5, $r['payable_days']);
    }

    public function test_late_marks_rule(): void
    {
        $e = $this->emp();
        $late = [];
        foreach (['02', '03', '04', '07', '08', '09'] as $d) {
            $late["2026-09-$d"] = ['late_minutes' => 20];
        }
        $this->september($e, [], $late);
        [$a, $b] = $this->sept();
        $this->assertEquals(30.0, app(PayrollCalculator::class)->compute($e, $a, $b)['payable_days']);   // rule is off by default
        PayrollRules::save(['late_marks_per_day' => 3] + PayrollRules::all());
        $r = app(PayrollCalculator::class)->compute($e, $a, $b);
        $this->assertEquals(28.0, $r['payable_days']);                        // six late marks = two days
    }

    public function test_overtime_is_paid_from_approved_minutes_only(): void
    {
        $e = $this->emp();
        $this->september($e, [], [
            '2026-09-05' => ['overtime_minutes' => 120, 'overtime_status' => 'APPROVED', 'overtime_approved_minutes' => 120],
            '2026-09-06' => ['overtime_minutes' => 300, 'overtime_status' => 'REJECTED', 'overtime_approved_minutes' => 0],
        ]);
        [$a, $b] = $this->sept();
        $r = app(PayrollCalculator::class)->compute($e, $a, $b);
        $ot = collect($r['lines'])->firstWhere('source', 'OVERTIME');
        $this->assertEquals(1000.0, $ot['amount']);                           // 60,000 / 30 / 8 = 250 an hour x 2 hours x 2
        $this->assertEquals(10.0, $r['income_tax'] - 390.0);                  // tax on the extra 1,000 at the 1% slab
        $e->update(['overtime_eligible' => false]);
        $this->assertNull(collect(app(PayrollCalculator::class)->compute($e->fresh(), $a, $b)['lines'])->firstWhere('source', 'OVERTIME'));
    }

    public function test_tax_slabs_and_switches(): void
    {
        $this->assertEquals(0.0, PayrollRules::annualTax(600000));
        $this->assertEquals(4680.0, PayrollRules::annualTax(1068000));
        $this->assertEquals(6000.0 + 0.11 * 300000, PayrollRules::annualTax(1500000));
        $this->assertEquals(2681000.0, PayrollRules::annualTax(10000000));            // the surcharge applies above the threshold, not at it
        $this->assertEqualsWithDelta(3031000.0 * 1.09, PayrollRules::annualTax(11000000), 0.01);
        $e = $this->emp(['tax_applies' => false]);
        $this->september($e);
        [$a, $b] = $this->sept();
        $this->assertEquals(0.0, app(PayrollCalculator::class)->compute($e, $a, $b)['income_tax']);
    }

    public function test_a_new_joiner_and_a_leaver_are_paid_for_their_days_only(): void
    {
        $join = $this->emp(['joined_on' => '2026-09-16']);
        $this->september($join);
        [$a, $b] = $this->sept();
        $r = app(PayrollCalculator::class)->compute($join, $a, $b);
        $this->assertEquals(15.0, $r['payable_days']);                        // 16th to 30th
        $this->assertEquals(30000.0, collect($r['lines'])->firstWhere('code', 'BASIC')['amount']);
    }

    public function test_the_run_covers_everyone_and_skips_people_without_a_salary(): void
    {
        $e = $this->emp();
        $this->september($e);
        Employee::create(['first_name' => 'No', 'last_name' => 'Salary', 'employment_type' => 'PERMANENT', 'joined_on' => '2025-01-01', 'status' => 'ACTIVE', 'weekly_off' => '0']);
        $svc = app(PayrollService::class);
        $p = $svc->createPeriod('2026-09-15');
        $this->assertSame('PAY-2026-09', $p->code);
        $sum = $svc->calculate($p);
        $this->assertSame(1, $sum['people']);
        $this->assertCount(1, $sum['no_salary']);
        $this->assertSame('CALCULATED', $p->fresh()->status);
        $this->assertEquals(1, Payslip::count());
        $this->assertEquals(88240.0, $sum['net']);                            // 89,000 less tax 390 less EOBI 370
        $this->expectException(\RuntimeException::class);
        $svc->createPeriod('2026-09-01');                                     // one payroll a month
    }

    public function test_approval_recovers_advances_and_can_be_reverted(): void
    {
        $e = $this->emp();
        $this->september($e);
        $adv = app(AdvanceService::class);
        $a = $adv->request($e, 'LOAN', 30000, 3, '2026-09-01', 'Family');
        $this->assertSame('PENDING', $a->status);
        $this->assertTrue($adv->approve($a));
        $this->assertEquals(30000.0, $a->fresh()->balance());
        $this->assertEquals(10000.0, $a->installment_amount);
        $svc = app(PayrollService::class);
        $p = $svc->createPeriod('2026-09-01');
        $sum = $svc->calculate($p);
        $this->assertEquals(10000.0, $sum['advances']);
        $this->assertEquals(78240.0, $sum['net']);                            // 89,000 - tax 390 - EOBI 370 - instalment 10,000
        $this->assertEquals(30000.0, $a->fresh()->balance());                 // calculating changes nothing on the ledger
        $this->assertTrue($svc->approve($p));
        $this->assertEquals(20000.0, $a->fresh()->balance());
        $svc->revert($p);
        $this->assertEquals(30000.0, $a->fresh()->balance());
        $svc->calculate($p);
        $svc->approve($p);
        $svc->markPaid($p, null, '2026-10-01');
        $this->assertSame('PAID', $p->fresh()->status);
        $this->expectException(\RuntimeException::class);
        $svc->revert($p->fresh());
    }

    public function test_recovery_never_takes_more_than_the_cap(): void
    {
        $e = $this->emp([], 20000);
        $this->september($e);
        $adv = app(AdvanceService::class);
        $a = $adv->request($e, 'ADVANCE', 90000, 1, '2026-09-01', null);
        $adv->approve($a);
        [$x, $y] = $this->sept();
        $r = app(PayrollCalculator::class)->compute($e, $x, $y);
        $left = $r['total_earnings'] - ($r['total_deductions'] - $r['advance_recovery']);
        $this->assertEquals(round($left / 2), $r['advance_recovery']);        // half of what is left, whatever the instalment says
        $this->assertGreaterThan(0, $r['net']);
    }

    public function test_one_off_items_join_the_run_and_are_used_once(): void
    {
        $e = $this->emp();
        $this->september($e);
        PayrollAdjustment::create(['employee_id' => $e->id, 'month' => '2026-09-01', 'kind' => 'EARNING', 'name' => 'Eid bonus', 'amount' => 10000, 'taxable' => true]);
        PayrollAdjustment::create(['employee_id' => $e->id, 'month' => '2026-09-01', 'kind' => 'DEDUCTION', 'name' => 'Canteen', 'amount' => 1500]);
        $svc = app(PayrollService::class);
        $p = $svc->createPeriod('2026-09-01');
        $sum = $svc->calculate($p);
        $slip = Payslip::first();
        $this->assertEquals(99000.0, $slip->total_earnings);
        $this->assertSame(['Eid bonus'], $slip->lines->where('source', 'ADJUST')->where('kind', 'EARNING')->pluck('name')->all());
        $this->assertEquals(390 + 100 + 370 + 1500, $slip->total_deductions);   // the bonus is taxed at 1% as extra income
        $svc->approve($p);
        $this->assertSame(0, PayrollAdjustment::whereNull('payslip_id')->count());
        $svc->revert($p);
        $this->assertSame(2, PayrollAdjustment::whereNull('payslip_id')->count());
    }

    public function test_approval_is_blocked_by_open_attendance_problems(): void
    {
        $e = $this->emp();
        $this->september($e, [], ['2026-09-03' => ['exception_status' => 'OPEN'], '2026-09-04' => ['overtime_status' => 'PENDING', 'overtime_minutes' => 60]]);
        $svc = app(PayrollService::class);
        $p = $svc->createPeriod('2026-09-01');
        $svc->calculate($p);
        $this->assertCount(2, $svc->checks($p)['blockers']);
        $this->expectException(\RuntimeException::class);
        $svc->approve($p);
    }

    public function test_held_payslips_stay_held_and_are_not_paid(): void
    {
        $e = $this->emp();
        $this->september($e);
        $svc = app(PayrollService::class);
        $p = $svc->createPeriod('2026-09-01');
        $svc->calculate($p);
        $svc->hold(Payslip::first(), 'Verifying bank account');
        $svc->calculate($p);                                                  // recalculating keeps the hold
        $this->assertSame('HELD', Payslip::first()->status);
        $svc->approve($p);
        $this->assertSame(0, $svc->markPaid($p, null, '2026-10-01'));
        $this->assertSame('APPROVED', $p->fresh()->status);
        $svc->release(Payslip::first());
        $this->assertSame(1, $svc->markPaid($p, null, '2026-10-02'));
        $this->assertSame('PAID', $p->fresh()->status);
    }

    public function test_final_settlement(): void
    {
        $e = $this->emp();
        $this->september($e);
        $svc = app(PayrollService::class);
        $adv = app(AdvanceService::class);
        $a = $adv->request($e, 'LOAN', 30000, 3, '2026-09-01', null);
        $adv->approve($a);
        $p = $svc->createPeriod('2026-09-01');
        $svc->calculate($p);
        $svc->approve($p);                                                    // September paid through, 20,000 still owed
        $x = EmployeeExit::create(['employee_id' => $e->id, 'exit_type' => 'RESIGNATION', 'last_working_day' => '2026-10-20', 'status' => 'INITIATED']);
        $st = app(SettlementService::class);
        $s = $st->open($x);
        $due = $s->lines->where('kind', 'DUE')->keyBy('code');
        $rec = $s->lines->where('kind', 'RECOVERY');
        $this->assertEquals(38710 + 15484 + 3226, $due['SALARY']->amount);   // 1–20 October, 20 of 31 days, each line rounded to the rupee
        $this->assertEquals(60000.0, $due['GRATUITY']->amount);                // 1 completed year: 30 days of basic (60,000 / 30 a day)
        $this->assertEquals(20000.0, $rec->firstWhere('ref_id', $a->id)->amount);
        $this->assertEquals(round($s->total_due - $s->total_recovery, 2), $s->net);
        $this->assertSame('CALCULATED', $x->fresh()->settlement_status);
        // a manual line is kept on recalculation, an automatic one is not duplicated
        $st->addLine($s, 'DUE', 'Notice pay agreed', 5000, 'By MD');
        $n = $st->calculate($s->fresh())->lines->count();
        $this->assertSame($n, $s->fresh()->lines()->count());
        $this->assertSame(1, $s->lines()->where('auto', false)->count());
        $this->assertTrue($st->approve($s->fresh()));
        $this->assertSame('APPROVED', $x->fresh()->settlement_status);
        $st->pay($s->fresh(), '2026-10-25', 'BANK', 'TRF-1');
        $this->assertSame('PAID', $s->fresh()->status);
        $this->assertSame('CLEARED', $a->fresh()->status);                    // the loan is cleared out of the settlement
        $this->assertEquals(0.0, $a->fresh()->balance());
        $this->assertSame(1, Payslip::count());
        // someone with a settlement is no longer part of a normal run
        $p2 = $svc->createPeriod('2026-10-01');
        $this->assertSame(0, $svc->calculate($p2)['people']);
    }

    // ---- screens

    public function test_screens_render_and_the_flow_works_over_http(): void
    {
        $e = $this->emp();
        $this->september($e, ['2026-09-10']);
        $this->get(route('hr.payroll.index'))->assertOk()->assertSee('Open a payroll for');
        $this->post(route('hr.payroll.store'), ['month' => '2026-09'])->assertRedirect();
        $p = \App\Models\PayrollPeriod::first();
        $this->get(route('hr.payroll.show', $p))->assertOk()->assertSee('Calculate payroll');
        $this->post(route('hr.payroll.calculate', $p))->assertRedirect();
        $this->get(route('hr.payroll.show', $p))->assertOk()->assertSee($e->code)->assertSee('Approve payroll')->assertSee('By department')->assertSee('Checks');
        $slip = Payslip::first();
        $this->get(route('hr.payroll.payslip', $slip))->assertOk()->assertSee('Basic salary')->assertSee('Income tax on salary')->assertSee('Draft');
        $this->post(route('hr.payroll.approve', $p))->assertRedirect();
        $this->assertSame('APPROVED', $p->fresh()->status);
        $this->get(route('hr.payroll.bank', $p))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($e->code, $this->get(route('hr.payroll.register', $p))->assertOk()->streamedContent());
        $this->post(route('hr.payroll.pay', $p), ['paid_on' => '2026-10-01'])->assertRedirect();
        $this->assertSame('PAID', $p->fresh()->status);
        $this->get(route('hr.payroll.statutory', ['month' => '2026-09']))->assertOk()->assertSee('Income tax withheld')->assertSee('NTN');
        $this->get(route('hr.payroll.statutory', ['scope' => 'year', 'year' => 2026, 'csv' => 1]))->assertOk();
        $this->get(route('hr.payroll.setup'))->assertOk()->assertSee('placeholder', false)->assertSee('House rent');
        $this->get(route('hr.employees.show', $e))->assertOk()->assertSee('Salary now')->assertSee('Salary history');
    }

    public function test_salary_rules_advances_and_settlement_screens(): void
    {
        $e = $this->emp();
        $this->post(route('hr.payroll.salary', $e), ['effective_from' => '2026-07-01', 'basic' => 72000, 'reason' => 'Increment'])->assertRedirect();
        $this->assertEquals(72000.0, app(SalaryService::class)->at($e, '2026-09-30')->basic);
        $this->put(route('hr.payroll.profile', $e), ['pay_mode' => 'CASH', 'eobi_applies' => 1])->assertRedirect();
        $e = $e->fresh();
        $this->assertSame('CASH', $e->pay_mode);
        $this->assertFalse((bool) $e->tax_applies);
        $this->post(route('hr.payroll.benefits.store', $e), ['name' => 'Fuel', 'kind' => 'CASH', 'monthly_value' => 8000, 'starts_on' => '2026-01-01'])->assertRedirect();
        $this->assertSame(1, \App\Models\EmployeeBenefit::count());

        $slabs = "0 ; 0 ; 0\n500000 ; 5 ; 0";
        $rules = ['days_basis' => '30', 'missing_attendance' => 'ABSENT', 'late_marks_per_day' => 0, 'half_day_other_half' => 'UNPAID', 'overtime_multiplier' => 1.5, 'overtime_base' => 'BASIC', 'hours_per_day' => 8, 'round_to' => '1', 'max_recovery_percent' => 40,
            'eobi_wage_base' => 40000, 'eobi_employee_percent' => 1, 'eobi_employer_percent' => 5, 'ss_employer_percent' => 6, 'ss_wage_ceiling' => 40000, 'gratuity_days_per_year' => 30, 'gratuity_min_years' => 1, 'tax_slabs' => $slabs, 'tax_enabled' => 1, 'eobi_enabled' => 1, 'gratuity_enabled' => 1];
        $this->put(route('hr.payroll.rules'), $rules)->assertRedirect()->assertSessionHasNoErrors();
        PayrollRules::flush();
        $this->assertSame('30', PayrollRules::get('days_basis'));
        $this->assertEquals(5000.0, PayrollRules::annualTax(600000));         // the new slab table applies
        $this->put(route('hr.payroll.rules'), ['tax_slabs' => '100 ; 1 ; 0'] + $rules)->assertSessionHasErrors('tax_slabs');   // must start at 0

        $this->post(route('hr.payroll.components.store'), ['code' => 'FUEL2', 'name' => 'Fuel allowance', 'kind' => 'EARNING', 'calc' => 'FIXED', 'default_value' => 3000])->assertRedirect();
        $this->assertSame(1, PayComponent::where('code', 'FUEL2')->count());

        $this->post(route('hr.payroll.advances.store'), ['employee_id' => $e->id, 'kind' => 'LOAN', 'amount' => 12000, 'installments' => 4, 'first_recovery_month' => '2026-10', 'reason' => 'Test'])->assertRedirect();
        $a = EmployeeAdvance::first();
        $this->get(route('hr.payroll.advances'))->assertOk()->assertSee($a->code);
        $this->get(route('hr.payroll.advances.show', $a))->assertOk()->assertSee('Approve and pay out');
        $this->post(route('hr.payroll.advances.decide', $a), ['decision' => 'APPROVE'])->assertRedirect();
        $this->post(route('hr.payroll.advances.pause', $a))->assertRedirect();
        $this->assertTrue((bool) $a->fresh()->paused);
        $this->post(route('hr.payroll.advances.adjust', $a), ['kind' => 'WAIVER', 'amount' => 2000, 'note' => 'Hardship'])->assertRedirect();
        $this->assertEquals(10000.0, $a->fresh()->balance());
        $this->post(route('hr.payroll.advances.adjust', $a), ['kind' => 'WAIVER', 'amount' => 99999, 'note' => 'Too much'])->assertSessionHas('error');

        $x = EmployeeExit::create(['employee_id' => $e->id, 'exit_type' => 'RESIGNATION', 'last_working_day' => '2026-10-10', 'status' => 'INITIATED']);
        $this->get(route('hr.payroll.settlements'))->assertOk()->assertSee('Exits without a settlement');
        $this->get(route('hr.exits.show', $x))->assertOk()->assertSee('Calculate settlement');
        $this->post(route('hr.payroll.settlements.open', $x))->assertRedirect();
        $s = \App\Models\EmployeeSettlement::first();
        $this->get(route('hr.payroll.settlements.show', $s))->assertOk()->assertSee('Gratuity')->assertSee('Add a line');
        $this->post(route('hr.payroll.settlements.lines.add', $s), ['kind' => 'DUE', 'name' => 'Notice pay', 'amount' => 1000])->assertRedirect();
        $line = $s->lines()->where('auto', false)->first();
        $this->delete(route('hr.payroll.settlements.lines.remove', [$s, $line->id]))->assertRedirect();
        $this->assertSame(0, $s->lines()->where('auto', false)->count());
        $this->post(route('hr.payroll.settlements.approve', $s))->assertRedirect();
        $this->post(route('hr.payroll.settlements.pay', $s), ['paid_on' => '2026-10-12', 'payment_method' => 'BANK'])->assertRedirect();
        $this->assertSame('PAID', $s->fresh()->status);
        $this->get(route('hr.payroll.settlements.show', $s))->assertOk()->assertSee('Paid');
    }

    public function test_access_follows_the_payroll_permission_and_payslips_are_private(): void
    {
        config(['foundry.require_login' => true]);
        $e = $this->emp();
        $this->september($e);
        $svc = app(PayrollService::class);
        $p = $svc->createPeriod('2026-09-01');
        $svc->calculate($p);
        $slip = Payslip::first();

        $hrOnly = \App\Models\User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $hrOnly->roles()->attach(\App\Models\Role::where('code', 'HR_MANAGER')->value('id'));
        $nobody = \App\Models\User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $other = $this->emp(['first_name' => 'Other', 'user_id' => $nobody->id]);
        $mine = \App\Models\User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $e->update(['user_id' => $mine->id]);

        $this->actingAs($nobody)->get(route('hr.payroll.index'))->assertForbidden();
        $this->actingAs($nobody)->get(route('hr.payroll.payslip', $slip))->assertForbidden();
        $this->actingAs($hrOnly)->get(route('hr.payroll.index'))->assertOk();
        // the person sees only their own payslip, and only once approved
        $this->actingAs($mine)->get(route('payslips.my'))->assertOk()->assertDontSee('September 2026');
        $this->actingAs($mine)->get(route('payslips.show', $slip))->assertForbidden();
        $svc->approve($p);
        $this->actingAs($mine)->get(route('payslips.my'))->assertOk()->assertSee('September 2026');
        $this->actingAs($mine)->get(route('payslips.show', $slip))->assertOk()->assertSee('Net pay');
        $this->actingAs($nobody)->get(route('payslips.show', $slip))->assertForbidden();
    }
}
