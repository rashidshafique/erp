<?php

namespace App\Domains\Tooling\Support;

use App\Support\RuleSet;

/** How the foundry handles made cores, set under Settings. A small foundry leaves both off; a larger one turns them on. */
class CoreRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'qc' => ['cores.qc', 'Check cores before they are used', [
            'OFF' => 'No check: usable once made',
            'REQUIRED' => 'Each lot must pass before it is issued',
        ], 'OFF'],
        'location' => ['cores.location', 'Where made cores are kept', [
            'OPTIONAL' => 'Optional',
            'REQUIRED' => 'Required for a lot not used straight away',
        ], 'OPTIONAL'],
    ];
}
