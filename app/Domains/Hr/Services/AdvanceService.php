<?php

namespace App\Domains\Hr\Services;

use App\Domains\Hr\Models\AdvanceLedgerEntry;
use App\Domains\Hr\Models\Employee;
use App\Domains\Hr\Models\EmployeeAdvance;
use App\Services\Approvals\ApprovalGate;
use App\Support\Formats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Salary advances and staff loans: asked for, approved, paid out, then recovered a little each month. */
class AdvanceService
{
    public function request(Employee $e, string $kind, float $amount, int $installments, string $firstMonth, ?string $reason): EmployeeAdvance
    {
        if ($amount <= 0 || $installments < 1) {
            throw new RuntimeException('Enter an amount and at least one instalment.');
        }
        if ($e->status === 'EXITED') {
            throw new RuntimeException('This person has left.');
        }

        return EmployeeAdvance::create(['employee_id' => $e->id, 'kind' => $kind, 'amount' => $amount, 'installments' => $installments, 'installment_amount' => ceil($amount / $installments),
            'first_recovery_month' => Carbon::parse($firstMonth)->startOfMonth(), 'reason' => $reason, 'status' => 'PENDING', 'requested_by_name' => Auth::user()?->name]);
    }

    public function approve(EmployeeAdvance $a, ?string &$message = null): bool
    {
        if ($a->status !== 'PENDING') {
            throw new RuntimeException('This request has already been decided.');
        }
        if (! app(ApprovalGate::class)->passes('hr.approve_advance', 'EmployeeAdvance', $a->id, $a->code.' '.$a->employee->full_name.' '.Formats::number($a->amount), $message)) {
            return false;
        }
        DB::transaction(function () use ($a) {
            $a->update(['status' => 'ACTIVE', 'disbursed_on' => today(), 'decided_by_name' => Auth::user()?->name, 'decided_at' => now()]);
            AdvanceLedgerEntry::create(['advance_id' => $a->id, 'kind' => 'DISBURSE', 'amount' => $a->amount, 'entry_date' => today(), 'note' => 'Paid out', 'created_by_name' => Auth::user()?->name]);
        });

        return true;
    }

    public function reject(EmployeeAdvance $a, ?string $why): void
    {
        if ($a->status !== 'PENDING') {
            throw new RuntimeException('This request has already been decided.');
        }
        $a->update(['status' => 'REJECTED', 'decided_by_name' => Auth::user()?->name, 'decided_at' => now(), 'reason' => trim($a->reason.' | Rejected: '.$why, ' |')]);
    }

    public function cancel(EmployeeAdvance $a): void
    {
        if ($a->status !== 'PENDING') {
            throw new RuntimeException('Only a request still waiting can be cancelled.');
        }
        $a->update(['status' => 'CANCELLED']);
    }

    /** Write off part or all of what is owed, or correct the balance. */
    public function adjust(EmployeeAdvance $a, string $kind, float $amount, string $note): void
    {
        if (! in_array($a->status, ['ACTIVE', 'CLEARED'], true)) {
            throw new RuntimeException('Only a paid-out advance can be adjusted.');
        }
        if ($amount <= 0 || trim($note) === '') {
            throw new RuntimeException('Enter an amount and the reason.');
        }
        $bal = $a->balance();
        if ($kind === 'WAIVER' && $amount > $bal) {
            throw new RuntimeException('You cannot write off more than the '.Formats::number($bal).' still owed.');
        }
        $signed = $kind === 'WAIVER' ? -$amount : $amount;
        DB::transaction(function () use ($a, $kind, $signed, $note) {
            AdvanceLedgerEntry::create(['advance_id' => $a->id, 'kind' => $kind, 'amount' => $signed, 'entry_date' => today(), 'note' => $note, 'created_by_name' => Auth::user()?->name]);
            $a->update(['status' => $a->fresh()->balance() <= 0 ? 'CLEARED' : 'ACTIVE']);
        });
    }

    public function outstanding(Employee $e): float
    {
        return round((float) AdvanceLedgerEntry::whereIn('advance_id', EmployeeAdvance::where('employee_id', $e->id)->whereIn('status', ['ACTIVE'])->pluck('id'))->sum('amount'), 2);
    }
}
