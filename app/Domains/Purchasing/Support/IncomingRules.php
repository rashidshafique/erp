<?php

namespace App\Domains\Purchasing\Support;

use App\Support\RuleSet;

/** What a received lot needs before it can be accepted, set under Settings. A small foundry can leave both off; a certified one switches them on. */
class IncomingRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'certificate' => ['incoming.certificate', 'Supplier certificate needed to accept a lot', [
            'NONE' => 'Not required',
            'ATTACHED' => 'A certificate file must be attached',
            'EN10204_31' => 'Attached, marked EN 10204 3.1 or better',
            'EN10204_32' => 'Attached, marked EN 10204 3.2',
        ], 'NONE'],
        'inspection' => ['incoming.inspection', 'Incoming inspection needed to accept a lot', [
            'OFF' => 'Not required',
            'REQUIRED' => 'Passed inspection, or accepted by concession',
        ], 'OFF'],
    ];
}
