<?php

namespace App\Domains\Development\Support;

use App\Support\RuleSet;

/** When an enquiry's pattern developments open, set under Settings. A small foundry opens them on the win; a larger one designs and costs the pattern before it quotes. */
class DevelopmentRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'development_open' => ['development.open', 'Pattern development opens', [
            'WIN' => 'When the enquiry is won',
            'DECIDED' => 'When the pattern is decided: costed before the quote',
        ], 'WIN'],
        'tool_depth' => ['development.tool_depth', 'Tools on a development', [
            'ONE' => 'One pattern: a single block, no register links',
            'LIST' => 'Tool list: a list of tools with their status',
            'FULL' => 'Full: maker, cost, payer and due date per tool',
        ], 'FULL'],
        'tool_gate' => ['development.tool_gate', 'Tools built and trial-passed before moving on', [
            'OFF' => 'Off: moves on whatever the tools show',
            'WARN' => 'Warn: confirm, logged as an override',
            'BLOCK' => 'Block: waits for every tool',
        ], 'WARN'],
    ];

    public static function opensWhenDecided(): bool
    {
        return self::get('development_open') === 'DECIDED';
    }
}
