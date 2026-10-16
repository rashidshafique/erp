<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionPlan extends Model
{
    use HasUuids;

    protected $table = 'inspection_plans';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'effective_at' => 'datetime', 'expires_at' => 'datetime', 'superseded_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function items(): HasMany { return $this->hasMany(InspectionPlanItem::class)->orderBy('sequence'); }
}
