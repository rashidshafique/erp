<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolingMaintenanceAttachment extends Model
{
    use HasUuids;

    protected $fillable = [
        'tooling_maintenance_record_id',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'caption',
        'uploaded_by',
    ];

    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(
            ToolingMaintenanceRecord::class,
            'tooling_maintenance_record_id'
        );
    }
}