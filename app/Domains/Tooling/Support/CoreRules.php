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
            'OFF' => 'No check: a core can be used as soon as it is made',
            'REQUIRED' => 'Each lot is checked first: a lot that has not passed cannot be issued',
        ], 'OFF'],
        'location' => ['cores.location', 'Where made cores are kept', [
            'OPTIONAL' => 'A shelf or rack can be recorded, but is not required',
            'REQUIRED' => 'A lot that is not used straight away must be given a place',
        ], 'OPTIONAL'],
    ];
}
