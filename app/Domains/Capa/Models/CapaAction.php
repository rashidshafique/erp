<?php

namespace App\Domains\Capa\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One corrective or preventive action, from any source: a nonconformance report, a customer complaint, an audit finding, a
 * supplier problem. It is open until the action is done and its effectiveness has been checked.
 */
class CapaAction extends Model
{
    use GeneratesCodes, HasUuids;

    public string $codeKind = 'capa';

    public const SOURCES = [
        'NCR' => 'Nonconformance report', 'COMPLAINT' => 'Customer complaint', 'AUDIT' => 'Audit finding', 'SUPPLIER' => 'Supplier problem',
        'PROCESS' => 'Process control event', 'REVIEW' => 'Management review', 'OTHER' => 'Other',
    ];

    public const SEVERITIES = ['MINOR' => 'Minor', 'MAJOR' => 'Major', 'CRITICAL' => 'Critical'];

    public const STATUSES = [
        'OPEN' => 'Open', 'IN_PROGRESS' => 'Action in progress', 'AWAITING_VERIFICATION' => 'Awaiting effectiveness check',
        'CLOSED' => 'Closed', 'CANCELLED' => 'Cancelled',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_on' => 'date', 'completed_on' => 'date', 'verification_due_on' => 'date', 'verified_on' => 'date', 'closed_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    /**
     * The eight disciplines of an 8D report and whether the record already holds each one.
     *
     * @return array<string, array{label: string, done: bool}>
     */
    public function eightD(): array
    {
        $filled = fn (?string $value): bool => trim((string) $value) !== '';

        return [
            'D1' => ['label' => 'Team', 'done' => $filled($this->owner)],
            'D2' => ['label' => 'Problem', 'done' => $filled($this->title) && $filled($this->description)],
            'D3' => ['label' => 'Containment', 'done' => $filled($this->containment_action)],
            'D4' => ['label' => 'Root cause', 'done' => $filled($this->root_cause)],
            'D5' => ['label' => 'Corrective action', 'done' => $filled($this->corrective_action)],
            'D6' => ['label' => 'Implement', 'done' => $this->completed_on !== null],
            'D7' => ['label' => 'Prevent', 'done' => $filled($this->preventive_action)],
            'D8' => ['label' => 'Close', 'done' => $this->status === 'CLOSED'],
        ];
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['CLOSED', 'CANCELLED'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && (
            ($this->status !== 'AWAITING_VERIFICATION' && $this->due_on !== null && $this->due_on->lt(today()))
            || ($this->status === 'AWAITING_VERIFICATION' && $this->verification_due_on !== null && $this->verification_due_on->lt(today()))
        );
    }
}
