<?php

namespace App\Domains\Development\Support;

use App\Models\AppSetting;

/** When an enquiry's pattern developments open, set under Settings. A small foundry opens them on the win; a larger one designs and costs the pattern before it quotes. */
class DevelopmentRules
{
    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'development_open' => ['development.open', 'When an enquiry opens its pattern development', [
            'WIN' => 'When the enquiry is won: the pattern is worked out after the customer orders',
            'DECIDED' => 'When the pattern is decided on the enquiry: design discussion and pattern costing run before the quote',
        ], 'WIN'],
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

    public static function opensWhenDecided(): bool
    {
        return self::get('development_open') === 'DECIDED';
    }
}
