<?php

namespace App\Domains\Maintenance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GaugeCalibration extends Model
{
    use HasUuids;

    public const RESULTS = ['PASS' => 'Passed', 'ADJUSTED' => 'Adjusted, then passed', 'FAIL' => 'Failed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['calibrated_on' => 'date', 'due_on' => 'date', 'external_lab' => 'boolean'];
    }

    public function gauge(): BelongsTo
    {
        return $this->belongsTo(Gauge::class)->withTrashed();
    }
}
