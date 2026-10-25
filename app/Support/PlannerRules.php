<?php

namespace App\Support;

use App\Models\AppSetting;

/** The production planner's assumptions, set on the planner page. */
class PlannerRules
{
    /** key => [setting, label, min, max, default] */
    public const RULES = [
        'shifts_per_day' => ['planner.shifts_per_day', 'Moulding shifts worked each day', 1, 3, 1],
        'days_after_moulding' => ['planner.days_after_moulding', 'Days from moulding to finished (pour, cool, fettle, inspect)', 0, 60, 3],
    ];

    public static function get(string $rule): int
    {
        [$key, , $min, $max, $default] = self::RULES[$rule];
        try {
            $value = AppSetting::getValue($key);
        } catch (\Throwable) {
            return $default;
        }

        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    public static function set(string $rule, int $value): void
    {
        [$key, $label] = self::RULES[$rule];
        AppSetting::setValue($key, (string) $value, $label);
    }
}
