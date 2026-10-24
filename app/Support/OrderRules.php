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
        'order_flow' => ['orders.flow', 'What saving an order does', [
            'SIMPLE' => 'Straight to production: a saved order goes live and a production order is created and released for every line',
            'FULL' => 'Step by step: a saved order stays a draft; review, go live and release each production order separately',
        ], 'FULL'],
        'order_completion' => ['orders.completion', 'A production order is complete', [
            'INSPECTION' => 'When enough good castings have passed final inspection',
            'POUR' => 'When the castings poured reach the planned quantity (no inspection needed)',
        ], 'INSPECTION'],
        'customer_requirement' => ['orders.customer_requirement', 'Customer requirements on an order', [
            'OPTIONAL' => 'Optional: a customer may hold requirement profiles, but an order is not held up when none is selected',
            'ENFORCED' => 'Enforced: contract review fails for a product when the customer holds no requirement profile and has no requirement for that product',
        ], 'OPTIONAL'],
        'delivery_lead_time' => ['orders.delivery_lead_time', 'Requested delivery sooner than the standard lead time', [
            'OFF' => 'No check: accept any delivery date that is not in the past',
            'WARN' => 'Warn: contract review notes it, and the order can still be approved',
            'ENFORCED' => 'Enforced: contract review fails until the delivery date allows the standard lead time',
        ], 'WARN'],
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

    public static function completesAtPour(): bool
    {
        return self::get('order_completion') === 'POUR';
    }

    public static function sendsStraightToProduction(): bool
    {
        return self::get('order_flow') === 'SIMPLE';
    }

    public static function customerRequirementEnforced(): bool
    {
        return self::get('customer_requirement') === 'ENFORCED';
    }

    public static function reviewRequired(): bool
    {
        return self::get('contract_review') === 'REQUIRED';
    }
}
