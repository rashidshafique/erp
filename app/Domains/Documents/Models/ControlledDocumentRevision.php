<?php

namespace App\Domains\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ControlledDocumentRevision extends Model
{
    use HasUuids;

    public const STATUSES = [
        'DRAFT' => 'Draft', 'IN_REVIEW' => 'In review', 'REJECTED' => 'Rejected', 'APPROVED' => 'Approved',
        'EFFECTIVE' => 'In force', 'SUPERSEDED' => 'Superseded', 'OBSOLETE' => 'Obsolete',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'effective_at' => 'datetime', 'superseded_at' => 'datetime',
            'file_size_bytes' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ControlledDocument::class, 'controlled_document_id')->withTrashed();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApprovalRecord::class)->orderBy('approval_step');
    }

    /** Still being written, reviewed or waiting to take effect. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['DRAFT', 'IN_REVIEW', 'APPROVED'], true);
    }

    public function hasFile(): bool
    {
        return $this->storage_path !== null;
    }
}
