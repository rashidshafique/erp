<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeBenefit;
use App\Models\EmployeeExit;
use App\Models\EmployeeSettlement;
use App\Models\EmployeeTask;
use App\Models\HrChecklistItem;
use App\Models\HrPolicy;
use App\Models\LeaveType;
use App\Models\PayComponent;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\Payroll\PayrollRules;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\SalaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrWorkflowTest extends TestCase
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

    private function employee(): Employee
    {
        $employee = Employee::create(['first_name' => 'Imran', 'last_name' => 'Ali', 'employment_type' => 'PERMANENT', 'joined_on' => '2025-01-01', 'status' => 'ACTIVE', 'confirmed_on' => '2025-04-01', 'weekly_off' => '0', 'gender' => 'M']);
        app(SalaryService::class)->revise($employee, '2025-01-01', 60000, [], 'Joining');

        return $employee->fresh();
    }

    /** @return array{0: PayrollPeriod, 1: Payslip} a calculated September payroll with one payslip */
    private function calculatedPayroll(): array
    {
        $this->employee();
        $period = app(PayrollService::class)->createPeriod('2026-09-01');
        app(PayrollService::class)->calculate($period);

        return [$period->fresh(), Payslip::firstOrFail()];
    }

    public function test_a_payslip_is_held_with_a_reason_released_and_not_held_once_paid(): void
    {
        [, $slip] = $this->calculatedPayroll();

        $this->post(route('hr.payroll.hold', $slip), [])->assertSessionHasErrors('hold_reason');
        $this->post(route('hr.payroll.hold', $slip), ['hold_reason' => 'Bank details missing'])->assertSessionHas('success');
        $this->assertSame('HELD', $slip->fresh()->status);

        $this->post(route('hr.payroll.release', $slip))->assertSessionHas('success');
        $this->assertSame('UNPAID', $slip->fresh()->status);
        $this->assertNull($slip->fresh()->hold_reason);

        $slip->update(['status' => 'PAID']);
        $this->post(route('hr.payroll.hold', $slip), ['hold_reason' => 'Too late'])->assertSessionHas('error');
        $this->assertSame('PAID', $slip->fresh()->status);
    }

    public function test_an_approved_payroll_can_be_sent_back_until_a_payslip_is_paid(): void
    {
        [$period, $slip] = $this->calculatedPayroll();

        $this->post(route('hr.payroll.revert', $period))->assertSessionHas('error');      // not approved yet

        $this->post(route('hr.payroll.approve', $period))->assertSessionHasNoErrors();
        $this->assertSame('APPROVED', $period->fresh()->status);
        $this->post(route('hr.payroll.revert', $period))->assertSessionHas('success');
        $this->assertSame('CALCULATED', $period->fresh()->status);

        $this->post(route('hr.payroll.approve', $period));
        $slip->update(['status' => 'PAID']);
        $this->post(route('hr.payroll.revert', $period))->assertSessionHas('error');
        $this->assertSame('APPROVED', $period->fresh()->status);
    }

    public function test_one_off_payroll_items_are_added_to_open_months_and_removed_before_payout(): void
    {
        $employee = $this->employee();
        $item = ['employee_id' => $employee->id, 'month' => '2026-09', 'kind' => 'EARNING', 'name' => 'Festival bonus', 'amount' => 5000];

        $this->post(route('hr.payroll.adjustments.store'), $item)->assertSessionHas('success');
        $this->post(route('hr.payroll.adjustments.store'), ['amount' => 0] + $item)->assertSessionHasErrors('amount');
        $adjustment = PayrollAdjustment::sole();

        $period = app(PayrollService::class)->createPeriod('2026-09-01');
        app(PayrollService::class)->calculate($period);
        app(PayrollService::class)->approve($period->fresh());
        $this->post(route('hr.payroll.adjustments.store'), $item)->assertSessionHas('error');         // month is approved
        $this->assertSame(1, PayrollAdjustment::count());

        $this->delete(route('hr.payroll.adjustments.destroy', $adjustment))->assertSessionHas('error');   // already on a payslip
        $loose = PayrollAdjustment::create(['employee_id' => $employee->id, 'month' => '2026-10-01', 'kind' => 'DEDUCTION', 'name' => 'Canteen', 'amount' => 300, 'taxable' => false]);
        $this->delete(route('hr.payroll.adjustments.destroy', $loose))->assertSessionHas('success');
        $this->assertFalse(PayrollAdjustment::whereKey($loose->id)->exists());
    }

    public function test_the_basic_pay_component_keeps_its_code_and_kind_when_edited(): void
    {
        $basic = PayComponent::where('is_basic', true)->firstOrFail();

        $this->put(route('hr.payroll.components.update', $basic), ['code' => 'RENAMED', 'name' => 'Basic pay', 'kind' => 'DEDUCTION', 'calc' => 'PERCENT_BASIC', 'default_value' => 0])->assertSessionHasNoErrors();

        $basic->refresh();
        $this->assertSame('Basic pay', $basic->name);
        $this->assertNotSame('RENAMED', $basic->code);
        $this->assertSame('EARNING', $basic->kind);
        $this->assertSame('FIXED', $basic->calc);
        $this->assertTrue((bool) $basic->active);
    }

    public function test_a_benefit_cannot_be_removed_through_another_employee(): void
    {
        $owner = $this->employee();
        $benefit = EmployeeBenefit::create(['employee_id' => $owner->id, 'name' => 'Fuel', 'kind' => 'CASH', 'monthly_value' => 8000, 'starts_on' => '2026-01-01']);
        $other = Employee::create(['first_name' => 'Sana', 'last_name' => 'Khan', 'employment_type' => 'PERMANENT', 'joined_on' => '2025-01-01', 'status' => 'ACTIVE', 'weekly_off' => '0', 'gender' => 'F']);

        $this->delete(route('hr.payroll.benefits.destroy', [$other, $benefit]))->assertNotFound();
        $this->delete(route('hr.payroll.benefits.destroy', [$owner, $benefit]))->assertSessionHas('success');
        $this->assertSame(0, EmployeeBenefit::count());
    }

    public function test_an_exit_is_edited_while_open_and_its_settlement_recalculated_while_draft(): void
    {
        $employee = $this->employee();
        $exit = EmployeeExit::create(['employee_id' => $employee->id, 'exit_type' => 'RESIGNATION', 'last_working_day' => '2026-10-20', 'status' => 'INITIATED']);

        $this->put(route('hr.exits.update', $exit), ['last_working_day' => '2026-10-31', 'reason' => 'Relocating', 'rehire_eligible' => '1'])->assertSessionHas('success');
        $exit->refresh();
        $this->assertSame('2026-10-31', $exit->last_working_day->toDateString());
        $this->assertTrue((bool) $exit->rehire_eligible);
        $this->put(route('hr.exits.update', $exit), ['reason' => 'x'])->assertSessionHasErrors('last_working_day');

        $settlement = app(\App\Services\Payroll\SettlementService::class)->open($exit->fresh());
        $this->post(route('hr.payroll.settlements.calculate', $settlement))->assertSessionHas('success');
        $settlement->update(['status' => 'APPROVED']);
        $this->post(route('hr.payroll.settlements.calculate', $settlement))->assertSessionHas('error');

        $exit->update(['status' => 'COMPLETED']);
        $this->put(route('hr.exits.update', $exit), ['last_working_day' => '2026-11-30'])->assertStatus(422);
    }

    public function test_onboarding_tasks_are_added_and_removed_and_checklist_items_deleted(): void
    {
        $employee = $this->employee();

        $this->post(route('hr.employees.tasks.store', $employee), ['kind' => 'ONBOARDING', 'title' => 'Issue safety shoes', 'owner' => 'STORES'])->assertSessionHas('success');
        $this->post(route('hr.employees.tasks.store', $employee), ['kind' => 'ONBOARDING', 'title' => 'x', 'owner' => 'NOBODY'])->assertSessionHasErrors('owner');
        $task = EmployeeTask::where('title', 'Issue safety shoes')->sole();
        $this->delete(route('hr.tasks.destroy', $task))->assertSessionHas('success');
        $this->assertFalse(EmployeeTask::whereKey($task->id)->exists());

        $item = HrChecklistItem::create(['kind' => 'ONBOARDING', 'title' => 'Open bank account', 'owner' => 'HR']);
        $this->delete(route('hr.checklist.destroy', $item))->assertSessionHas('success');
        $this->assertFalse(HrChecklistItem::whereKey($item->id)->exists());
    }

    public function test_a_policy_page_opens_and_a_retired_policy_can_no_longer_be_acknowledged(): void
    {
        $employee = $this->employee();
        $policy = HrPolicy::create(['code' => 'POL-1', 'title' => 'Safety rules', 'category' => 'GENERAL', 'version' => 1, 'status' => 'PUBLISHED', 'requires_ack' => true, 'body' => 'Wear boots.', 'published_at' => now()]);

        $this->get(route('hr.policies.show', $policy))->assertOk()->assertSee('Safety rules');
        $this->post(route('hr.policies.retire', $policy))->assertSessionHas('success');
        $this->assertSame('RETIRED', $policy->fresh()->status);
        $this->post(route('hr.policies.acknowledge', $policy), ['employee_ids' => [$employee->id]])->assertStatus(422);
    }

    public function test_a_leave_type_is_edited_with_a_unique_upper_cased_code(): void
    {
        $type = LeaveType::create(['code' => 'XCL', 'name' => 'Casual leave', 'annual_days' => 10, 'accrual' => 'UPFRONT', 'carry_forward_max' => 0, 'active' => true, 'paid' => true]);
        $other = LeaveType::create(['code' => 'XAL', 'name' => 'Annual leave', 'annual_days' => 14, 'accrual' => 'MONTHLY', 'carry_forward_max' => 5, 'active' => true, 'paid' => true]);

        $this->put(route('hr.leave.types.update', $type), ['code' => 'xcas', 'name' => 'Casual leave', 'annual_days' => 12, 'accrual' => 'UPFRONT'])->assertSessionHasNoErrors();
        $this->assertSame('XCAS', $type->fresh()->code);
        $this->assertEquals(12, $type->fresh()->annual_days);

        $this->put(route('hr.leave.types.update', $type), ['code' => 'XAL', 'name' => 'Casual leave', 'annual_days' => 12, 'accrual' => 'UPFRONT'])->assertSessionHasErrors('code');
        $this->put(route('hr.leave.types.update', $other), ['code' => 'XAL', 'name' => 'Annual leave', 'annual_days' => 400, 'accrual' => 'MONTHLY'])->assertSessionHasErrors('annual_days');
    }
}
