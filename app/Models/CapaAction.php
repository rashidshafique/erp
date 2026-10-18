<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One corrective or preventive action, from any source: a nonconformance report, a customer complaint, an audit finding, a
 * supplier problem. It is open until the action is done and its effectiveness has been checked.
 */
class CapaAction extends Model
{
    use Concerns\GeneratesCodes, HasUuids;

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
