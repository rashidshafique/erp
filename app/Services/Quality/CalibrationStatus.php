<?php

namespace App\Services\Quality;

use App\Models\Gauge;
use App\Models\GaugeCalibration;
use App\Support\CalibrationRules;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Works out whether an instrument may be trusted today, and what to do when a result names one that may not. */
class CalibrationStatus
{
    public const LABELS = [
        'IN_DATE' => 'In calibration', 'DUE_SOON' => 'Due soon', 'OVERDUE' => 'Overdue', 'NOT_CALIBRATED' => 'Never calibrated',
        'OUT_OF_SERVICE' => 'Out of service', 'RETIRED' => 'Retired', 'NOT_REQUIRED' => 'No calibration needed',
    ];

    public function of(Gauge $gauge, ?Carbon $on = null): string
    {
        $on ??= today();
        if ($gauge->status !== 'IN_SERVICE') {
            return $gauge->status;
        }
        if (! $gauge->requires_calibration) {
            return 'NOT_REQUIRED';
        }
        if ($gauge->next_due_on === null) {
            return 'NOT_CALIBRATED';
        }
        if ($gauge->next_due_on->lt($on)) {
            return 'OVERDUE';
        }

        return $gauge->next_due_on->lte($on->copy()->addDays((int) CalibrationRules::get('due_soon_days'))) ? 'DUE_SOON' : 'IN_DATE';
    }

    /** True when results measured with the instrument today would not stand up to an audit. */
    public function unfit(Gauge $gauge, ?Carbon $on = null): bool
    {
        return in_array($this->of($gauge, $on), ['OVERDUE', 'NOT_CALIBRATED', 'OUT_OF_SERVICE', 'RETIRED'], true);
    }

    /**
     * Applies the "overdue use" rule to an instrument chosen for a result. Returns a warning to show, null when all is well, and
     * throws a validation error under the BLOCK rule.
     */
    public function check(?Gauge $gauge, string $field): ?string
    {
        if (! $gauge || ! $this->unfit($gauge)) {
            return null;
        }
        $rule = CalibrationRules::get('overdue_use');
        if ($rule === 'OFF') {
            return null;
        }
        $message = "{$gauge->code} ({$gauge->name}): ".strtolower(self::LABELS[$this->of($gauge)]).($gauge->next_due_on ? ', due '.$gauge->next_due_on->displayDate() : '').'.';
        if ($rule === 'BLOCK') {
            throw ValidationException::withMessages([$field => $message.' Choose another instrument or calibrate this one first.']);
        }

        return $message.' The result was saved, but it will not stand up to an audit.';
    }

    /** Records a calibration and moves the instrument's dates and status to match. */
    public function record(Gauge $gauge, array $data): GaugeCalibration
    {
        $calibration = $gauge->calibrations()->create($data);
        $latest = $gauge->calibrations()->first();
        $failed = $latest->result === 'FAIL';
        $due = $failed ? null : ($latest->due_on ?? ($gauge->calibration_interval_days ? $latest->calibrated_on->copy()->addDays($gauge->calibration_interval_days) : null));
        $gauge->update([
            'last_calibrated_on' => $latest->calibrated_on,
            'next_due_on' => $due,
            'status' => $failed ? 'OUT_OF_SERVICE' : ($gauge->status === 'OUT_OF_SERVICE' ? 'IN_SERVICE' : $gauge->status),
        ]);

        return $calibration;
    }
}
