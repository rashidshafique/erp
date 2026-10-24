<?php

namespace App\Domains\Maintenance\Models;

use App\Domains\Maintenance\Services\CalibrationStatus;
use App\Domains\Maintenance\Support\CalibrationRules;
use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One measuring instrument: a caliper, thermocouple, balance, spectrometer. It is calibrated on an interval and traced from the results it measured. */
class Gauge extends Model
{
    use GeneratesCodes, HasUuids, SoftDeletes;

    public string $codeKind = 'gauge';

    public const TYPES = [
        'CALIPER' => 'Caliper / vernier', 'MICROMETER' => 'Micrometer', 'GAUGE_BLOCK' => 'Gauge / go-no-go', 'HARDNESS_TESTER' => 'Hardness tester',
        'TENSILE_MACHINE' => 'Tensile / universal tester', 'SPECTROMETER' => 'Spectrometer / analyser', 'THERMOCOUPLE' => 'Thermocouple / pyrometer',
        'THERMAL_ANALYSER' => 'Thermal analysis unit', 'BALANCE' => 'Balance / scale', 'SAND_TEST' => 'Sand test equipment', 'CMM' => 'CMM / scanner', 'OTHER' => 'Other',
    ];

    public const STATUSES = ['IN_SERVICE' => 'In service', 'OUT_OF_SERVICE' => 'Out of service', 'RETIRED' => 'Retired'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requires_calibration' => 'boolean',
            'calibration_interval_days' => 'integer',
            'last_calibrated_on' => 'date',
            'next_due_on' => 'date',
        ];
    }

    /** Instruments in use that are overdue, never calibrated, or due within the due-soon days of Calibration rules. */
    public function scopeCalibrationDue(Builder $query): Builder
    {
        return $query->where('status', 'IN_SERVICE')->where('requires_calibration', true)
            ->where(fn ($q) => $q->whereNull('next_due_on')->orWhereDate('next_due_on', '<=', today()->addDays((int) CalibrationRules::get('due_soon_days'))));
    }

    public function calibrations(): HasMany
    {
        return $this->hasMany(GaugeCalibration::class)->orderByDesc('calibrated_on')->orderByDesc('created_at');
    }

    /** Where the instrument stands for use: in date, due soon, overdue, never calibrated, or not fit to use. */
    public function calibrationStatus(): string
    {
        return app(CalibrationStatus::class)->of($this);
    }
}
