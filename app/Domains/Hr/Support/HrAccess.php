<?php

namespace App\Domains\Hr\Support;

use App\Domains\Hr\Models\Employee;
use App\Domains\Hr\Models\LeaveRequest;
use Illuminate\Support\Facades\Auth;

/** Who may do what with leave. HR can do anything; a line manager can decide for the people who report to them. */
class HrAccess
{
    public static function isHr(): bool
    {
        if (! config('foundry.require_login')) {
            return true;
        }

        return (bool) Auth::user()?->hasPermission('hr.manage');
    }

    /** Salaries, payroll, loans and settlements: a separate permission from the rest of HR. */
    public static function isPayroll(): bool
    {
        if (! config('foundry.require_login')) {
            return true;
        }

        return (bool) Auth::user()?->hasPermission('hr.payroll');
    }

    public static function me(): ?Employee
    {
        $u = Auth::user();

        return $u ? Employee::where('user_id', $u->id)->first() : null;
    }

    /** True when the signed-in person sits above this employee in the reporting line. */
    public static function managesEmployee(Employee $e): bool
    {
        $me = self::me();
        if (! $me) {
            return false;
        }
        foreach ($e->chain() as $m) {
            if ($m->id === $me->id) {
                return true;
            }
        }

        return false;
    }

    public static function canDecide(LeaveRequest $r): bool
    {
        if (self::isHr()) {
            return true;
        }
        $me = self::me();

        return $me && $me->id !== $r->employee_id && self::managesEmployee($r->employee);
    }

    public static function canSee(Employee $e): bool
    {
        $me = self::me();

        return self::isHr() || ($me && ($me->id === $e->id || self::managesEmployee($e)));
    }
}
