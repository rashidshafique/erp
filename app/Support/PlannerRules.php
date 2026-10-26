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
        'patterns_per_shift' => ['planner.patterns_per_shift', 'Patterns sharing a station\'s shift (1 = earliest due fills it first)', 1, 10, 1],
        'min_run_moulds' => ['planner.min_run_moulds', 'Minimum run once a pattern is put on (moulds, 0 = none)', 0, 10000, 0],
        'flask_back_hours' => ['planner.flask_back_hours', 'Hours from pouring until flasks held to shakeout are free again (0 = flasks do not limit the plan)', 0, 240, 0],
        'group_same_pattern' => ['planner.group_same_pattern', 'Run later orders on the same pattern right behind the first', 0, 1, 0],
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
