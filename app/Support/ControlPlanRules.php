<?php

namespace App\Support;

/** How control plans are checked, set under Settings. */
class ControlPlanRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'control_plan_check' => ['control_plan.check', 'Product revision with no approved control plan', [
            'WARN' => 'Warn in the contract review',
            'BLOCK' => 'Block the contract review',
            'OFF' => 'Do not check',
        ], 'WARN'],
        'pattern_change' => ['control_plan.pattern_change', 'When a pattern revision is approved', [
            'REVIEW' => 'Review: plans flagged, contract review warns until confirmed',
            'PPAP' => 'Full PPAP: blocked until a new revision has an approved plan',
            'OFF' => 'Do nothing',
        ], 'REVIEW'],
    ];
}
