<?php

namespace App\Support;

use App\Models\AppSetting;

/** How customer orders are handled, set under Settings. */
class OrderRules
{
    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'contract_review' => ['orders.contract_review', 'Contract review before an order can go live', [
            'REQUIRED' => 'Required: an order goes live only after its contract review is approved',
            'SKIP' => 'Not required: an order can be made live straight from the order page',
        ], 'REQUIRED'],
    ];

    public static function get(string $rule): string
    {
        [$key, , $options, $default] = self::RULES[$rule];
        if ($rule === 'contract_review' && ! config('foundry.require_contract_review')) {
            $default = 'SKIP';
        }
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

    public static function reviewRequired(): bool
    {
        return self::get('contract_review') === 'REQUIRED';
    }
}
