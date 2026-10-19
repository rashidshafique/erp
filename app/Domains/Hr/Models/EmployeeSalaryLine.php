<?php

namespace App\Domains\Hr\Models;

use App\Models\PayComponent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryLine extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    public $timestamps = false;

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'component_id');
    }
}
