<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What is controlled at each step of making a product revision. A revision's plan either follows the foundry's default plan
 * ("Our process") or holds its own rows; a pattern change can mark it as needing a review.
 */
class ProcessControlPlan extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'follows_default' => 'boolean',
        'is_default' => 'boolean',
        'needs_review_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }

    public function basedOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'based_on_plan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcessControlPlanItem::class)->orderBy('sequence');
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['APPROVED', 'EFFECTIVE', 'ACTIVE'], true);
    }
}
