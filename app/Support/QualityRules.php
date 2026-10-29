<?php

namespace App\Support;

use App\Models\AppSetting;

/** How lots are made and how quality records are raised, set through the process depth. */
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
        'lot_grouping' => ['quality.lot_grouping', 'Casting lots made when a pour is completed', [
            'MOULD' => 'One lot per mould and product: full traceability to each mould',
            'POUR' => 'One lot per pour and product: castings from all moulds of the pour are handled together',
        ], 'MOULD'],
        'qc_after_steps' => ['quality.qc_after_steps', 'When castings reach quality control', [
            'STEPS' => 'After the steps before inspection: knock-out, fettling, shot blast and the like are recorded first',
            'POUR' => 'Straight after the pour: the steps before inspection need not be recorded',
        ], 'POUR'],
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

    public static function groupsByPour(): bool
    {
        return self::get('lot_grouping') === 'POUR';
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

    public static function qcWaitsForSteps(): bool
    {
        return self::get('qc_after_steps') === 'STEPS';
    }
}
