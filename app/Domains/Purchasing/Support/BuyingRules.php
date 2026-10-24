<?php

namespace App\Domains\Purchasing\Support;

use App\Models\AppSetting;

/** How buying behaves, set under Settings > Purchase orders. */
class BuyingRules
{
    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'over_receipt' => ['purchasing.over_receipt', 'Receiving more than was ordered', [
            'BLOCK' => 'Not allowed: a line can only be received up to its ordered quantity',
            'TEN' => 'Allowed up to 10% over the ordered quantity',
            'ANY' => 'Allowed by any amount',
        ], 'TEN'],
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

    /** The most that may be received on a line of this ordered quantity, or null for no limit. */
    public static function receiptLimit(float $ordered): ?float
    {
        return match (self::get('over_receipt')) {
            'BLOCK' => $ordered,
            'TEN' => round($ordered * 1.1, 3),
            default => null,
        };
    }
}
