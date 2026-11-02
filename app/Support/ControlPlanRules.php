<?php

namespace App\Support;

/** How control plans are checked, set under Settings. */
class ControlPlanRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'control_plan_check' => ['control_plan.check', 'A product revision with no approved control plan', [
            'WARN' => 'Warn in the contract review',
            'BLOCK' => 'Block the contract review until a plan is approved',
            'OFF' => 'Do not check',
        ], 'WARN'],
        'pattern_change' => ['control_plan.pattern_change', 'When a pattern revision is approved', [
            'REVIEW' => 'Mark the plans of the products it makes as needing a review; the contract review warns until someone confirms',
            'PPAP' => 'Full PPAP: the contract review blocks until the product has a new revision with an approved plan',
            'OFF' => 'Do nothing',
        ], 'REVIEW'],
    ];
}
