<?php

namespace App\Domains\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One controlled document: a procedure, work instruction, form, specification or any other record whose current
 * version must be known and whose changes must be approved. Its revisions carry the files.
 */
class ControlledDocument extends Model
{
    use HasUuids, SoftDeletes;

    public const TYPES = [
        'PROCEDURE' => 'Procedure', 'WORK_INSTRUCTION' => 'Work instruction', 'FORM' => 'Form or template', 'POLICY' => 'Policy',
        'SPECIFICATION' => 'Specification', 'DRAWING' => 'Drawing', 'STANDARD' => 'External standard', 'CUSTOMER' => 'Customer document', 'OTHER' => 'Other',
    ];

    public const STATUSES = ['DRAFT' => 'Draft, not yet in force', 'ACTIVE' => 'In force', 'OBSOLETE' => 'Obsolete'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime', 'obsolete_at' => 'datetime', 'created_for_review_at' => 'datetime',
            'next_review_on' => 'date', 'last_reviewed_on' => 'date', 'review_interval_months' => 'integer',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ControlledDocumentRevision::class)->orderByDesc('created_at')->orderByDesc('revision_code');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(ControlledDocumentRevision::class, 'current_revision_id');
    }

    /** The revision still being written, reviewed or waiting to take effect; only one at a time. */
    public function openRevision(): ?ControlledDocumentRevision
    {
        return $this->revisions->first(fn (ControlledDocumentRevision $r) => $r->isOpen());
    }

    public function isDueForReview(): bool
    {
        return $this->status === 'ACTIVE' && $this->next_review_on !== null && $this->next_review_on->lte(today());
    }
}
