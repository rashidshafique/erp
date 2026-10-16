<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeEvent;
use App\Models\EmployeeExit;
use App\Models\EmployeeTask;
use App\Models\HrChecklistItem;
use App\Services\Approvals\ApprovalGate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Joining, confirmation, transfer and promotion, and leaving. Every step leaves a dated record on the employee. */
class Lifecycle
{
    public const TRACKED = ['department_id' => 'Department', 'designation_id' => 'Designation', 'grade_id' => 'Grade', 'location_id' => 'Location', 'cost_centre_id' => 'Cost centre', 'manager_id' => 'Reports to'];

    public function event(Employee $e, string $kind, $on, ?array $changes = null, ?string $note = null): EmployeeEvent
    {
        return EmployeeEvent::create(['employee_id' => $e->id, 'kind' => $kind, 'effective_on' => $on, 'changes' => $changes, 'note' => $note, 'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name]);
    }

    /** Called once when someone is added: the first record, the probation date and the joining checklist. */
    public function joined(Employee $e): void
    {
        DB::transaction(function () use ($e) {
            $this->event($e, 'JOINED', $e->joined_on, null, 'Joined as '.($e->designation?->name ?? 'employee'));
            $this->makeTasks($e, 'ONBOARDING', $e->joined_on);
        });
    }

    public function makeTasks(Employee $e, string $kind, CarbonInterface $from): int
    {
        $n = 0;
        foreach (HrChecklistItem::where('kind', $kind)->where('active', true)->orderBy('sort')->get() as $i) {
            EmployeeTask::create(['employee_id' => $e->id, 'kind' => $kind, 'title' => $i->title, 'owner' => $i->owner, 'due_on' => $from->copy()->addDays((int) $i->due_days), 'sort' => $i->sort]);
            $n++;
        }

        return $n;
    }

    /** Ends onboarding and puts new permanent staff on probation (or straight to active when no probation applies). */
    public function startWork(Employee $e): void
    {
        if ($e->status !== 'ONBOARDING') {
            throw new RuntimeException('Only someone still onboarding can be moved on.');
        }
        $probation = $e->employment_type === 'PERMANENT' && $e->probation_ends_on && ! $e->confirmed_on;
        $e->update(['status' => $probation ? 'PROBATION' : 'ACTIVE', 'confirmed_on' => $probation ? null : ($e->confirmed_on ?? $e->joined_on)]);
        $this->event($e, 'STATUS', now()->toDateString(), ['status' => ['from' => 'ONBOARDING', 'to' => $e->status]], 'Onboarding finished');
    }

    public function confirm(Employee $e, string $on, ?string $note, ?string &$message = null): bool
    {
        if ($e->status !== 'PROBATION') {
            throw new RuntimeException('Only someone on probation can be confirmed.');
        }
        if (! app(ApprovalGate::class)->passes('hr.confirm_employee', 'Employee', $e->id, $e->code.' '.$e->full_name, $message)) {
            return false;
        }
        DB::transaction(function () use ($e, $on, $note) {
            $e->update(['status' => 'ACTIVE', 'confirmed_on' => $on]);
            $this->event($e, 'CONFIRMED', $on, ['status' => ['from' => 'PROBATION', 'to' => 'ACTIVE']], $note);
        });

        return true;
    }

    /** @param array<string,?string> $to  keys from self::TRACKED */
    public function transfer(Employee $e, array $to, string $on, string $kind, ?string $note, ?string &$message = null): bool
    {
        if (! $e->isCurrent()) {
            throw new RuntimeException('This person has left.');
        }
        $changes = [];
        foreach (self::TRACKED as $col => $label) {
            if (! array_key_exists($col, $to)) {
                continue;
            }
            $new = $to[$col] ?: null;
            if ($new !== $e->$col) {
                $changes[$col] = ['label' => $label, 'from' => $this->name($col, $e->$col), 'to' => $this->name($col, $new), 'from_id' => $e->$col, 'to_id' => $new];
            }
        }
        if (isset($changes['manager_id']) && $changes['manager_id']['to_id']) {
            $this->assertNoLoop($e, $changes['manager_id']['to_id']);
        }
        if (! $changes) {
            throw new RuntimeException('Nothing is different from the current position.');
        }
        if (! app(ApprovalGate::class)->passes('hr.transfer_employee', 'Employee', $e->id, $e->code.' '.$e->full_name.' ('.implode(', ', array_column($changes, 'label')).')', $message)) {
            return false;
        }
        DB::transaction(function () use ($e, $changes, $on, $kind, $note) {
            $e->update(collect($changes)->map(fn ($c) => $c['to_id'])->all());
            $this->event($e, $kind, $on, $changes, $note);
        });

        return true;
    }

    public function assertNoLoop(Employee $e, string $managerId): void
    {
        if ($managerId === $e->id) {
            throw new RuntimeException('Nobody can report to themselves.');
        }
        $m = Employee::find($managerId);
        $guard = 0;
        while ($m && $guard++ < 20) {
            if ($m->id === $e->id) {
                throw new RuntimeException('That would make a reporting loop: '.$e->full_name.' is already above that manager.');
            }
            $m = $m->manager;
        }
    }

    private function name(string $col, ?string $id): ?string
    {
        if (! $id) {
            return null;
        }
        $m = match ($col) {
            'department_id' => \App\Models\HrDepartment::find($id)?->name,
            'designation_id' => \App\Models\HrDesignation::find($id)?->name,
            'grade_id' => \App\Models\HrGrade::find($id)?->name,
            'location_id' => \App\Models\HrLocation::find($id)?->name,
            'cost_centre_id' => \App\Models\HrCostCentre::find($id)?->name,
            'manager_id' => Employee::find($id)?->full_name,
            default => null,
        };

        return $m;
    }

    public function startExit(Employee $e, array $d): EmployeeExit
    {
        if (! $e->isCurrent() || $e->openExit()) {
            throw new RuntimeException('This person is already leaving or has left.');
        }

        return DB::transaction(function () use ($e, $d) {
            $x = EmployeeExit::create($d + ['employee_id' => $e->id, 'status' => 'INITIATED', 'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name]);
            $prev = $e->status;
            $e->update(['status' => 'NOTICE']);
            $this->makeTasks($e, 'EXIT', now());
            $this->event($e, 'EXIT_STARTED', $d['notice_given_on'] ?? now()->toDateString(), ['status' => ['from' => $prev, 'to' => 'NOTICE']], ($d['exit_type'] ?? '').' · last day '.$d['last_working_day']);

            return $x;
        });
    }

    public function cancelExit(EmployeeExit $x, ?string $note): void
    {
        DB::transaction(function () use ($x, $note) {
            $e = $x->employee;
            $x->update(['status' => 'CANCELLED']);
            EmployeeTask::where('employee_id', $e->id)->where('kind', 'EXIT')->whereNull('done_at')->delete();
            $back = $e->confirmed_on || $e->employment_type !== 'PERMANENT' ? 'ACTIVE' : 'PROBATION';
            $e->update(['status' => $back]);
            $this->event($e, 'EXIT_CANCELLED', now()->toDateString(), ['status' => ['from' => 'NOTICE', 'to' => $back]], $note);
        });
    }

    /** What is still blocking the exit. */
    public function exitBlockers(EmployeeExit $x): array
    {
        $out = [];
        $open = EmployeeTask::where('employee_id', $x->employee_id)->where('kind', 'EXIT')->whereNull('done_at')->count();
        if ($open) {
            $out[] = "$open clearance task".($open === 1 ? '' : 's').' not done yet';
        }

        return $out;
    }

    public function completeExit(EmployeeExit $x, ?string &$message = null): bool
    {
        if ($x->status !== 'INITIATED') {
            throw new RuntimeException('This exit is already closed.');
        }
        if ($b = $this->exitBlockers($x)) {
            throw new RuntimeException(implode('; ', $b).'.');
        }
        $e = $x->employee;
        if (! app(ApprovalGate::class)->passes('hr.finalise_exit', 'Employee', $e->id, $e->code.' '.$e->full_name.' leaving', $message)) {
            return false;
        }
        DB::transaction(function () use ($x, $e) {
            $x->update(['status' => 'COMPLETED', 'completed_at' => now()]);
            $e->update(['status' => 'EXITED', 'exit_on' => $x->last_working_day]);
            if ($e->user_id) {
                \App\Models\User::whereKey($e->user_id)->update(['is_active' => false]);
            }
            $this->event($e, 'EXITED', $x->last_working_day, ['status' => ['from' => 'NOTICE', 'to' => 'EXITED']], $x->exit_type);
        });

        return true;
    }
}
