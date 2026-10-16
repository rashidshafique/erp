<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per change. Written only through App\Services\Audit\Audit. */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function changeSet(): array
    {
        $raw = $this->attributes['changes'] ?? null;   // not $this->changes: Eloquent keeps its own property of that name

        return $raw ? (json_decode($raw, true) ?: []) : [];
    }
}
