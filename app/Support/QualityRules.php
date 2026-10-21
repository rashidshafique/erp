<?php

namespace App\Support;

use App\Models\AppSetting;

/** How quality records are raised from the quick QC tally, set through the process depth. */
class QualityRules
{
    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'ncr_threshold' => ['quality.ncr_threshold', 'A quick QC tally raises a nonconformance', [
            'ANY' => 'Whenever a piece is rejected or sent to rework',
            'OVER_5' => 'When more than 5% of the pieces checked are rejected or sent to rework',
            'OVER_10' => 'When more than 10% of the pieces checked are rejected or sent to rework',
            'NEVER' => 'Never: the defect tally is the record',
        ], 'ANY'],
    ];

    public static function get(string $rule): string
    {
        [$key, , $options, $default] = self::RULES[$rule];
        try {
            $value = AppSetting::getValue($key, $default);
        } catch (\Throwable) {
            return $default;
        }

        return isset($options[$value]) ? $value : $default;
    }

    public static function set(string $rule, string $value): void
    {
        [$key, $label] = self::RULES[$rule];
        AppSetting::setValue($key, $value, $label);
    }

    /** Whether a tally with this many bad pieces out of the checked total should raise a nonconformance. */
    public static function raisesNcr(int $bad, int $checked): bool
    {
        if ($bad <= 0 || $checked <= 0) {
            return false;
        }

        return match (self::get('ncr_threshold')) {
            'OVER_5' => $bad / $checked > 0.05,
            'OVER_10' => $bad / $checked > 0.10,
            'NEVER' => false,
            default => true,
        };
    }
}
