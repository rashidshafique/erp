<?php

namespace App\Domains\Purchasing\Models;

use App\Domains\Parties\Concerns\HasParty;
use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;

/** An approved vendor list entry: who supplies what, whether they may be bought from, and when they are next reviewed. */
class Supplier extends Model
{
    use GeneratesCodes, HasParty, HasUuids, SoftDeletes;

    public string $codeKind = 'supplier';

    public const STATUSES = [
        'PENDING' => 'Pending approval', 'APPROVED' => 'Approved', 'CONDITIONAL' => 'Conditionally approved',
        'SUSPENDED' => 'Suspended', 'DISQUALIFIED' => 'Disqualified',
    ];

    /** Statuses a material lot may not be received from. */
    public const BLOCKED = ['SUSPENDED', 'DISQUALIFIED'];

    protected $guarded = [];

    /**
     * The rules a supplier is saved under, from the form page or the add dialog. Codes are kept in capitals, so the caller
     * upper-cases one before checking it is unused.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(?self $supplier = null): array
    {
        return [
            'code' => ['nullable', 'string', 'max:40', Rule::unique('suppliers', 'code')->ignore($supplier?->id)],
            'name' => ['required', 'string', 'max:150'],
            'supplies' => ['nullable', 'string', 'max:200'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:160'],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'review_interval_days' => ['required', 'integer', 'min:30', 'max:1825'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

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
