<?php

namespace App\Support;

use App\Models\AppSetting;

/** How strictly calibration is applied, set under Settings. A small foundry can leave it as a warning; a certified one can block. */
class CalibrationRules
{
    /** key => [setting, label, options (value => description) or null for a number, default] */
    public const RULES = [
        'overdue_use' => ['calibration.overdue_use', 'Using an instrument that is overdue, uncalibrated or out of service', [
            'OFF' => 'Allow it without comment',
            'WARN' => 'Allow it, with a warning on the screen',
            'BLOCK' => 'Refuse the result until another instrument is chosen',
        ], 'WARN'],
        'due_soon_days' => ['calibration.due_soon_days', 'Days before the due date to show "due soon"', null, '30'],
    ];

    public static function get(string $rule): string
    {
        [$key, , $options, $default] = self::RULES[$rule];
        try {
            $value = AppSetting::getValue($key, $default);
        } catch (\Throwable) {
            return $default;
        }
        if ($options === null) {
            return ctype_digit((string) $value) ? (string) $value : $default;
        }

        return isset($options[$value]) ? $value : $default;
    }

    public static function set(string $rule, string $value): void
    {
        [$key, $label] = self::RULES[$rule];
        AppSetting::setValue($key, $value, $label);
    }
}
