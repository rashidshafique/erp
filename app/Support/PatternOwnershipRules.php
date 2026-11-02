<?php

namespace App\Support;

/** How the foundry treats patterns it does not own, set on the pattern ownership page. Each rule has a safe default. */
class PatternOwnershipRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description) or null for a number, default] */
    public const RULES = [
        'other_customer' => ['patterns.other_customer', "A customer's pattern on another customer's or a stock order", [
            'BLOCK' => 'Block it', 'WARN' => 'Allow it with a warning', 'ALLOW' => 'Allow it',
        ], 'BLOCK'],
        'pay_off' => ['patterns.pay_off', 'When a pattern being paid off is fully paid', [
            'MANUAL' => 'Show it as ready; ownership passes when someone records it',
            'AUTO' => 'Ownership passes to the customer automatically',
        ], 'MANUAL'],
        'idle_months' => ['patterns.idle_months', 'A pattern counts as idle after this many months unused', null, '12'],
        'storage' => ['patterns.storage', "Storing customers' patterns", [
            'FREE' => 'Free of charge', 'CHARGE' => 'Charged per pattern per month after a free period',
        ], 'FREE'],
        'storage_free_months' => ['patterns.storage_free_months', 'Free storage, in months unused', null, '12'],
        'storage_rate' => ['patterns.storage_rate', 'Storage charge per pattern per month (PKR)', null, '0'],
    ];

    /** @return list<string> */
    protected static function numberRule(string $rule): array
    {
        return ['numeric', 'min:0', 'max:1000000'];
    }
}
