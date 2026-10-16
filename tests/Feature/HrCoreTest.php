<?php

namespace Tests\Feature;

use App\Models\ApprovalPolicy;
use App\Models\Employee;
use App\Models\EmployeeTask;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrCoreTest extends TestCase
{
    use RefreshDatabase;

    private function emp(array $o = []): Employee
    {
        $this->post(route('hr.employees.store'), $o + ['first_name' => 'Imran', 'last_name' => 'Ali', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01'])->assertSessionHasNoErrors();

        return Employee::orderByDesc('code')->first();
    }

    public function test_pages_render(): void
    {
        $e = $this->emp();
        foreach ([route('hr.dashboard'), route('hr.employees.index'), route('hr.employees.create'), route('hr.employees.show', $e), route('directory.index'), route('hr.organisation'), route('hr.policies.index'), route('hr.policies.create'), route('hr.exits.index')] as $u) {
            $this->get($u)->assertOk();
        }
    }

    public function test_adding_an_employee_generates_a_code_probation_and_the_joining_checklist(): void
    {
        $e = $this->emp(['cnic' => '3520112345671']);
        $this->assertMatchesRegularExpression('/^EMP-\d{5}$/', $e->code);
        $this->assertSame('ONBOARDING', $e->status);
        $this->assertSame('2026-12-01', $e->probation_ends_on->toDateString());   // 3 months
        $this->assertSame('35201-1234567-1', $e->cnic);
        $this->assertSame(count(config('hr.checklists.ONBOARDING')), $e->tasks()->where('kind', 'ONBOARDING')->count());
        $this->assertSame('JOINED', $e->events()->first()->kind);
        $this->post(route('hr.employees.store'), ['first_name' => 'Dup', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01', 'cnic' => '35201-1234567-1'])->assertSessionHasErrors('cnic');
        $this->post(route('hr.employees.store'), ['first_name' => 'Bad', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01', 'cnic' => '123'])->assertSessionHasErrors('cnic');
    }

    public function test_contract_staff_have_no_probation(): void
    {
        $e = $this->emp(['employment_type' => 'CONTRACT', 'contract_ends_on' => '2027-03-01']);
        $this->assertNull($e->probation_ends_on);
        $this->post(route('hr.employees.start-work', $e));
        $this->assertSame('ACTIVE', $e->fresh()->status);
    }

    public function test_onboarding_to_probation_to_confirmed(): void
    {
        $e = $this->emp();
        $this->post(route('hr.employees.start-work', $e))->assertSessionHas('success');
        $this->assertSame('PROBATION', $e->fresh()->status);
        $this->post(route('hr.employees.start-work', $e))->assertSessionHas('error');                    // only once
        $this->post(route('hr.employees.confirm', $e), ['confirmed_on' => '2026-12-01'])->assertSessionHas('success');
        $e->refresh();
        $this->assertSame('ACTIVE', $e->status);
        $this->assertSame('2026-12-01', $e->confirmed_on->toDateString());
        $this->post(route('hr.employees.confirm', $e), ['confirmed_on' => '2026-12-02'])->assertSessionHas('error');
    }

    public function test_confirmation_waits_for_approval_when_the_policy_is_on(): void
    {
        $e = $this->emp();
        $this->post(route('hr.employees.start-work', $e));
        ApprovalPolicy::updateOrCreate(['action_key' => 'hr.confirm_employee'], ['is_active' => true, 'required_approvals' => 1, 'allow_self_approval' => false, 'permission' => 'approvals.decide']);
        $this->post(route('hr.employees.confirm', $e), ['confirmed_on' => '2026-12-01'])->assertSessionHas('error');
        $this->assertSame('PROBATION', $e->fresh()->status);
        $this->assertDatabaseHas('approval_requests', ['action_key' => 'hr.confirm_employee', 'subject_id' => $e->id, 'status' => 'PENDING']);
    }

    public function test_transfer_records_what_changed_and_blocks_reporting_loops(): void
    {
        $d1 = HrDepartment::create(['name' => 'Moulding']);
        $d2 = HrDepartment::create(['name' => 'Melting']);
        $dg = HrDesignation::create(['name' => 'Moulder']);
        $boss = $this->emp(['first_name' => 'Boss']);
        $e = $this->emp(['first_name' => 'Worker', 'department_id' => $d1->id, 'manager_id' => $boss->id]);
        $this->assertMatchesRegularExpression('/^DEP-\d{5}$/', $d1->code);

        $this->post(route('hr.employees.transfer', $e), ['kind' => 'TRANSFER', 'effective_on' => '2026-10-01', 'department_id' => $d2->id, 'designation_id' => $dg->id, 'manager_id' => $boss->id])->assertSessionHas('success');
        $e->refresh();
        $this->assertSame($d2->id, $e->department_id);
        $ev = $e->events()->where('kind', 'TRANSFER')->first();
        $this->assertSame('Moulding', $ev->changes['department_id']['from']);
        $this->assertSame('Melting', $ev->changes['department_id']['to']);
        $this->assertArrayNotHasKey('manager_id', $ev->changes);                                          // unchanged

        $this->post(route('hr.employees.transfer', $e), ['kind' => 'TRANSFER', 'effective_on' => '2026-10-02', 'department_id' => $d2->id])->assertSessionHas('error');   // nothing changed
        $this->post(route('hr.employees.transfer', $boss), ['kind' => 'TRANSFER', 'effective_on' => '2026-10-02', 'manager_id' => $e->id])->assertSessionHas('error');        // loop
        $this->assertNull($boss->fresh()->manager_id);
        $this->post(route('hr.employees.transfer', $e), ['kind' => 'TRANSFER', 'effective_on' => '2026-10-02', 'manager_id' => $e->id])->assertSessionHas('error');           // self
    }

    public function test_edit_form_cannot_change_position(): void
    {
        $d = HrDepartment::create(['name' => 'Moulding']);
        $d2 = HrDepartment::create(['name' => 'Melting']);
        $e = $this->emp(['department_id' => $d->id]);
        $this->put(route('hr.employees.update', $e), ['first_name' => 'Imran', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01', 'department_id' => $d2->id, 'phone' => '0300'])->assertSessionHasNoErrors();
        $this->assertSame($d->id, $e->fresh()->department_id);
        $this->assertSame('0300', $e->fresh()->phone);
    }

    public function test_exit_needs_clearance_then_marks_the_person_as_left_and_switches_off_the_login(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $e = $this->emp();
        $this->put(route('hr.employees.update', $e), ['first_name' => 'Imran', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01', 'user_id' => $u->id])->assertSessionHasNoErrors();
        $this->post(route('hr.employees.exit', $e), ['exit_type' => 'RESIGNATION', 'last_working_day' => '2026-11-30'])->assertSessionHasNoErrors();
        $e->refresh();
        $x = $e->openExit();
        $this->assertSame('NOTICE', $e->status);
        $this->assertSame(count(config('hr.checklists.EXIT')), $e->tasks()->where('kind', 'EXIT')->count());
        $this->post(route('hr.employees.exit', $e), ['exit_type' => 'RESIGNATION', 'last_working_day' => '2026-11-30'])->assertSessionHas('error');   // already leaving

        $this->post(route('hr.exits.complete', $x))->assertSessionHas('error');                           // clearance open
        $this->assertSame('NOTICE', $e->fresh()->status);
        foreach ($e->tasks()->where('kind', 'EXIT')->get() as $t) {
            $this->post(route('hr.tasks.toggle', $t));
        }
        $this->post(route('hr.exits.complete', $x))->assertSessionHas('success');
        $e->refresh();
        $this->assertSame('EXITED', $e->status);
        $this->assertSame('2026-11-30', $e->exit_on->toDateString());
        $this->assertFalse((bool) $u->fresh()->is_active);
        $this->assertSame('EXITED', $e->events()->first()->kind);
        $this->get(route('hr.employees.index'))->assertDontSee($e->code);                                   // current staff only by default
        $this->get(route('hr.employees.index', ['status' => 'EXITED']))->assertSee($e->code);
    }

    public function test_cancelling_an_exit_restores_status_and_drops_open_clearance_tasks(): void
    {
        $e = $this->emp();
        $this->post(route('hr.employees.start-work', $e));
        $this->post(route('hr.employees.exit', $e), ['exit_type' => 'RESIGNATION', 'last_working_day' => '2026-11-30']);
        $x = $e->fresh()->openExit();
        $this->post(route('hr.exits.cancel', $x))->assertSessionHas('success');
        $this->assertSame('PROBATION', $e->fresh()->status);
        $this->assertSame(0, $e->tasks()->where('kind', 'EXIT')->count());
        $this->assertSame('CANCELLED', $x->fresh()->status);
    }

    public function test_documents_track_expiry_and_files(): void
    {
        Storage::fake(config('foundry.attachments.disk'));
        $e = $this->emp();
        $png = UploadedFile::fake()->createWithContent('cnic.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $this->post(route('hr.employees.documents.store', $e), ['kind' => 'LICENCE', 'number' => 'L-1', 'expires_on' => now()->addDays(10)->toDateString(), 'file' => $png])->assertSessionHasNoErrors();
        $doc = $e->documents()->first();
        $this->assertSame('SOON', $doc->expiryState());
        $this->assertDatabaseHas('attachments', ['attachable_type' => 'employee_document', 'attachable_id' => $doc->id]);
        $this->get(route('hr.dashboard'))->assertSee('Documents expiring');
        $this->get(route('hr.employees.show', $e))->assertSee('Expires soon');
        $this->delete(route('hr.documents.destroy', $doc));
        $this->assertSame(0, $e->documents()->count());
        $this->post(route('hr.employees.documents.store', $e), ['kind' => 'LICENCE', 'issued_on' => '2026-01-01', 'expires_on' => '2025-01-01'])->assertSessionHasErrors('expires_on');
    }

    public function test_dashboard_flags_probation_ending_and_overdue_tasks(): void
    {
        $e = $this->emp(['joined_on' => now()->subMonths(3)->addDays(5)->toDateString()]);
        $this->post(route('hr.employees.start-work', $e));
        $this->get(route('hr.dashboard'))->assertOk()->assertSee('Probation ending')->assertSee($e->code);
        EmployeeTask::where('employee_id', $e->id)->update(['due_on' => now()->subDays(3)->toDateString()]);
        $this->get(route('hr.dashboard'))->assertSee('Overdue checklist tasks');
    }

    public function test_policies_publish_acknowledge_and_new_version_restarts_acknowledgement(): void
    {
        $e = $this->emp();
        $this->post(route('hr.policies.store'), ['title' => 'PPE rules', 'category' => 'SAFETY', 'requires_ack' => 1, 'body' => 'Wear PPE.'])->assertSessionHasNoErrors();
        $p = HrPolicy::first();
        $this->assertMatchesRegularExpression('/^POL-\d{5}$/', $p->code);
        $this->post(route('hr.policies.acknowledge', $p), ['employee_ids' => [$e->id]])->assertStatus(422);   // not published
        $this->post(route('hr.policies.publish', $p))->assertSessionHas('success');
        $this->put(route('hr.policies.update', $p), ['title' => 'PPE rules', 'category' => 'SAFETY', 'body' => 'Changed.'])->assertSessionHas('error');   // locked once published
        $this->post(route('hr.policies.acknowledge', $p), ['employee_ids' => [$e->id]])->assertSessionHas('success');
        $this->post(route('hr.policies.acknowledge', $p), ['employee_ids' => [$e->id]]);                      // twice is harmless
        $this->assertSame(1, $p->acks()->count());
        $this->get(route('hr.employees.show', $e))->assertSee('Acknowledged');

        $this->post(route('hr.policies.revise', $p));
        $this->put(route('hr.policies.update', $p), ['title' => 'PPE rules', 'category' => 'SAFETY', 'requires_ack' => 1, 'body' => 'Wear PPE and boots.'])->assertSessionHasNoErrors();
        $this->post(route('hr.policies.publish', $p));
        $p->refresh();
        $this->assertSame(2, $p->version);
        $this->get(route('hr.dashboard'))->assertSee('Policies waiting for acknowledgement');
        $this->post(route('hr.policies.acknowledge', $p), ['employee_ids' => [$e->id]]);
        $this->assertSame(2, $p->acks()->count());
    }

    public function test_empty_policy_cannot_be_published(): void
    {
        $this->post(route('hr.policies.store'), ['title' => 'Empty', 'category' => 'GENERAL'])->assertSessionHasNoErrors();
        $this->post(route('hr.policies.publish', HrPolicy::first()))->assertSessionHas('error');
    }

    public function test_organisation_setup(): void
    {
        $this->post(route('hr.organisation.store', 'departments'), ['name' => 'Production'])->assertSessionHasNoErrors();
        $d = HrDepartment::first();
        $this->post(route('hr.organisation.store', 'departments'), ['name' => 'Moulding', 'parent_id' => $d->id]);
        $child = HrDepartment::where('name', 'Moulding')->first();
        $this->assertSame('Production › Moulding', $child->path());
        $this->put(route('hr.organisation.update', ['departments', $d->id]), ['name' => 'Production', 'parent_id' => $child->id])->assertStatus(422);   // loop
        $this->post(route('hr.organisation.store', 'grades'), ['name' => 'G1', 'rank' => 1, 'min_salary' => 50000, 'max_salary' => 40000])->assertSessionHasErrors('max_salary');
        $this->post(route('hr.organisation.store', 'grades'), ['name' => 'G1', 'rank' => 1, 'min_salary' => 40000, 'max_salary' => 60000])->assertSessionHasNoErrors();
        $this->post(route('hr.organisation.store', 'locations'), ['name' => 'Main foundry', 'kind' => 'PLANT'])->assertSessionHasNoErrors();
        $this->post(route('hr.organisation.store', 'cost-centres'), ['name' => 'Melt shop', 'code' => 'cc-1'])->assertSessionHasNoErrors();
        $this->post(route('hr.organisation.store', 'cost-centres'), ['name' => 'Dup', 'code' => 'cc-1'])->assertSessionHasErrors('code');
        $this->post(route('hr.organisation.store', 'nonsense'), ['name' => 'x'])->assertNotFound();

        $e = $this->emp(['department_id' => $child->id]);
        $this->delete(route('hr.organisation.destroy', ['departments', $child->id]))->assertSessionHas('error');   // in use
        $this->delete(route('hr.organisation.destroy', ['departments', $d->id]))->assertSessionHas('error');       // has a child
        $this->assertNotNull(HrDepartment::find($child->id));
        $this->get(route('hr.organisation'))->assertOk()->assertSee('Production › Moulding');
    }

    public function test_checklists_are_editable_and_only_affect_new_joiners(): void
    {
        $first = $this->emp(['first_name' => 'First']);
        $this->post(route('hr.checklist.store'), ['kind' => 'ONBOARDING', 'title' => 'Hand over safety shoes', 'owner' => 'STORES', 'due_days' => 2])->assertSessionHasNoErrors();
        $second = $this->emp(['first_name' => 'Second']);
        $this->assertSame($first->tasks()->count() + 1, $second->tasks()->count());
        $this->assertTrue($second->tasks()->where('title', 'Hand over safety shoes')->exists());
        $item = \App\Models\HrChecklistItem::where('title', 'Hand over safety shoes')->first();
        $this->put(route('hr.checklist.update', $item), ['title' => 'Hand over shoes', 'owner' => 'STORES', 'due_days' => 2])->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->active);                                                          // unticked = switched off
        $third = $this->emp(['first_name' => 'Third']);
        $this->assertSame($first->tasks()->count(), $third->tasks()->count());
    }

    public function test_directory_hides_private_details_and_shows_reporting_lines(): void
    {
        $boss = $this->emp(['first_name' => 'Zafar', 'cnic' => '35201-1111111-1', 'bank_account_no' => 'PK00SECRET', 'phone' => '0300111']);
        $w = $this->emp(['first_name' => 'Bilal', 'manager_id' => $boss->id]);
        $r = $this->get(route('directory.index'))->assertOk();
        $r->assertSee('Zafar')->assertSee('Bilal')->assertSee('0300111');
        $r->assertDontSee('35201-1111111-1')->assertDontSee('PK00SECRET');
        $this->get(route('directory.index', ['search' => 'Bilal']))->assertSee('Bilal');
    }

    public function test_hr_pages_need_the_hr_permission_but_the_directory_does_not(): void
    {
        $map = collect(config('permissions.routes'))->firstWhere('pattern', 'hr.*');
        $this->assertSame('hr.manage', $map['permission']);
        $this->assertTrue($map['reads']);
        $this->assertContains('hr.manage', config('permissions.roles.HR_MANAGER.permissions'));
        $this->assertDatabaseHas('roles', ['code' => 'HR_MANAGER']);
        $this->assertNull(collect(config('permissions.routes'))->first(fn ($r) => str_starts_with($r['pattern'], 'directory')));
    }
}
