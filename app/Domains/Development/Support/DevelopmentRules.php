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
        'tool_depth' => ['development.tool_depth', 'Tools on a development', [
            'ONE' => 'One pattern: a single pattern block, no register links',
            'LIST' => 'Tool list: a list of tools with their status',
            'FULL' => 'Full detail: the list with maker, cost, who pays and due per tool',
        ], 'FULL'],
        'tool_gate' => ['development.tool_gate', 'Tools must be built and trial-passed before a development moves on', [
            'OFF' => 'Off: a development moves on whatever its tools show',
            'WARN' => 'Warn: moving on asks to confirm and is logged as an override',
            'BLOCK' => 'Block: it cannot move on until every tool has reached the stage',
        ], 'WARN'],
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
