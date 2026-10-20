<?php

namespace App\Domains\Hr\Services;

use App\Domains\Hr\Models\HrShift;
use App\Models\AppSetting;

/**
 * Company-wide shift and weekly off, used for every employee who has not been given their own.
 * Without a saved choice the shift is the first active one (Day shift) and the weekly off is Sunday.
 */
class HrDefaults
{
    public const SHIFT_KEY = 'hr.default_shift_id';

    public const WEEKLY_OFF_KEY = 'hr.weekly_off';

    public static function shiftId(): ?string
    {
        $saved = AppSetting::getValue(self::SHIFT_KEY);
        if ($saved && HrShift::whereKey($saved)->where('active', true)->exists()) {
            return $saved;
        }

        return HrShift::where('active', true)->orderBy('code')->value('id');
    }

    public static function weeklyOff(): string
    {
        $saved = AppSetting::getValue(self::WEEKLY_OFF_KEY);

        return $saved !== null && array_key_exists($saved, config('hr.weekly_off_options')) ? $saved : '0';
    }

    public static function save(?string $shiftId, string $weeklyOff): void
    {
        AppSetting::setValue(self::SHIFT_KEY, $shiftId, 'Default shift for all employees');
        AppSetting::setValue(self::WEEKLY_OFF_KEY, $weeklyOff, 'Company-wide weekly off');
    }
}
