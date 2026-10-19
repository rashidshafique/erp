<?php

namespace App\Domains\Purchasing\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** An approved vendor list entry: who supplies what, whether they may be bought from, and when they are next reviewed. */
class Supplier extends Model
{
    use GeneratesCodes, HasUuids, SoftDeletes;

    public string $codeKind = 'supplier';

    public const STATUSES = [
        'PENDING' => 'Pending approval', 'APPROVED' => 'Approved', 'CONDITIONAL' => 'Conditionally approved',
        'SUSPENDED' => 'Suspended', 'DISQUALIFIED' => 'Disqualified',
    ];

    /** Statuses a material lot may not be received from. */
    public const BLOCKED = ['SUSPENDED', 'DISQUALIFIED'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'approved_on' => 'date',
            'next_review_on' => 'date',
            'review_interval_days' => 'integer',
            'latest_score' => 'decimal:1',
        ];
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class)->orderByDesc('evaluated_on')->orderByDesc('created_at');
    }

    public function isBlocked(): bool
    {
        return in_array($this->approval_status, self::BLOCKED, true);
    }

    public function isReviewOverdue(): bool
    {
        return $this->next_review_on !== null && $this->next_review_on->lt(today()) && ! $this->isBlocked();
    }
}
