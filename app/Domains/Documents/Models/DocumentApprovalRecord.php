<?php

namespace App\Domains\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One approver's step on a revision. Steps are decided in order. */
class DocumentApprovalRecord extends Model
{
    use HasUuids;

    public const DECISIONS = ['PENDING' => 'Waiting', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'CANCELLED' => 'Not needed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'decided_at' => 'datetime', 'approval_step' => 'integer'];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ControlledDocumentRevision::class, 'controlled_document_revision_id');
    }
}
