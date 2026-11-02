<?php

namespace App\Support;

/** How customer orders are handled, set under Settings. */
class OrderRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'contract_review' => ['orders.contract_review', 'Contract review before an order can go live', [
            'REQUIRED' => 'Required: sales and quality sign off first',
            'AUTO' => 'When something is off: clean orders approve themselves',
            'SKIP' => 'Not required',
        ], 'REQUIRED'],
        'order_flow' => ['orders.flow', 'What saving an order does', [
            'SIMPLE' => 'Straight to production: goes live and releases every line',
            'FULL' => 'Step by step: draft, review, go live, release each',
        ], 'FULL'],
        'order_completion' => ['orders.completion', 'A production order is complete', [
            'INSPECTION' => 'When enough castings pass final inspection',
            'POUR' => 'When enough castings are poured',
        ], 'INSPECTION'],
        'customer_requirement' => ['orders.customer_requirement', 'Customer requirements on an order', [
            'OPTIONAL' => 'Optional: no profile does not hold the order',
            'ENFORCED' => 'Enforced: review fails without a requirement for the product',
        ], 'OPTIONAL'],
        'delivery_lead_time' => ['orders.delivery_lead_time', 'Delivery sooner than the standard lead time', [
            'OFF' => 'No check (past dates are still refused)',
            'WARN' => 'Warn: noted in contract review',
            'ENFORCED' => 'Enforced: review fails until the date allows it',
        ], 'WARN'],
        'prices' => ['orders.prices', 'Prices on sales orders', [
            'HIDE' => 'Hidden: priced at invoicing',
            'SHOW' => 'Shown: unit prices and order total',
        ], 'HIDE'],
        'repeat_ahead' => ['orders.repeat_ahead', 'Repeat orders schedule ahead', [
            '4' => '4 weeks',
            '8' => '8 weeks',
            '13' => '13 weeks: a full quarter ahead',
        ], '8'],
    ];

    protected static function defaultFor(string $rule, string $default): string
    {
        return $rule === 'contract_review' && ! config('foundry.require_contract_review') ? 'SKIP' : $default;
    }

    public static function showsPrices(): bool
    {
        return self::get('prices') === 'SHOW';
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

    /** An approved review is needed before production, whether signed off by hand or approved automatically. */
    public static function reviewRequired(): bool
    {
        return self::get('contract_review') !== 'SKIP';
    }

    /** The review runs on save and approves itself when clean; one sign-off clears warnings. */
    public static function reviewsAutomatically(): bool
    {
        return self::get('contract_review') === 'AUTO';
    }

    /** Days ahead of today that a repeating line keeps its deliveries scheduled. */
    public static function repeatAheadDays(): int
    {
        return (int) self::get('repeat_ahead') * 7;
    }
}
