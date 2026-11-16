<?php

namespace App\Domains\Tooling\Support;

use App\Support\RuleSet;

/** How the foundry handles made cores, set under Settings. A small foundry leaves both off; a larger one turns them on. */
class CoreRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'stock' => ['cores.stock', 'Track core stock', [
            'ON' => 'Record cores made, keep lots and count what is on hand',
            'OFF' => 'No core stock: runs only list the cores they need',
        ], 'ON'],
        'qc' => ['cores.qc', 'Check cores before they are used', [
            'OFF' => 'No check: usable once made',
            'REQUIRED' => 'Each lot must pass before it is issued',
        ], 'OFF'],
        'location' => ['cores.location', 'Where made cores are kept', [
            'OPTIONAL' => 'Optional',
            'REQUIRED' => 'Required for a lot not used straight away',
        ], 'OPTIONAL'],
    ];

    /** Whether cores made are recorded as lots with stock on hand. Off, the core stock pages are closed. */
    public static function stockOn(): bool
    {
        return static::get('stock') === 'ON';
    }
}
