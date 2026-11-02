<?php

namespace App\Domains\Maintenance\Support;

use App\Support\RuleSet;

/** How strictly calibration is applied, set under Settings. A small foundry can leave it as a warning; a certified one can block. */
class CalibrationRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description) or null for a number, default] */
    public const RULES = [
        'overdue_use' => ['calibration.overdue_use', 'Instrument overdue, uncalibrated or out of service', [
            'OFF' => 'Allow it without comment',
            'WARN' => 'Allow it, with a warning on the screen',
            'BLOCK' => 'Refuse the result',
        ], 'WARN'],
        'due_soon_days' => ['calibration.due_soon_days', 'Days before the due date to show "due soon"', null, '30'],
    ];

    /** @return list<string> */
    protected static function numberRule(string $rule): array
    {
        return ['integer', 'min:0', 'max:365'];
    }
}
