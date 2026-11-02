<?php

namespace App\Support;

/** How the foundry handles flasks, set on the flask board. Each rule has a safe default. */
class FlaskRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'shortage' => ['flasks.shortage', 'Too few free flasks when assembling moulds', [
            'BLOCK' => 'Block the assembly and list what is free',
            'WARN' => 'Warn: layers without a flask are left empty',
        ], 'BLOCK'],
        'pairing' => ['flasks.pairing', 'Cope and drag pairing', [
            'ANY' => 'Any cope closes any drag of the same size',
            'FIXED' => 'Fixed pairs only',
            'MIXED' => 'Mixed: set partners stay paired, the rest pair freely',
        ], 'ANY'],
        'release' => ['flasks.release', 'Flasks are freed (unless set elsewhere)', [
            'SHAKEOUT' => 'At shakeout of the castings',
            'POUR' => 'As soon as the mould is poured',
            'MANUAL' => 'Only by "Release flasks"',
        ], 'SHAKEOUT'],
        'run_claims' => ['flasks.run_claims', 'Moulding runs without assembled moulds', [
            'ASSEMBLED' => 'Flasks are tracked on assembled moulds only',
            'COUNT' => 'A run claims flasks per mould; release from the run',
        ], 'ASSEMBLED'],
    ];
}
