<?php

namespace App\Support;

use App\Models\AppSetting;

/** How the foundry handles flasks, set on the flask board. Each rule has a safe default. */
class FlaskRules
{
    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'shortage' => ['flasks.shortage', 'Too few free flasks when assembling moulds', [
            'BLOCK' => 'Block the assembly and list what is free',
            'WARN' => 'Allow it with a warning; the layers without a flask are left empty',
        ], 'BLOCK'],
        'pairing' => ['flasks.pairing', 'Cope and drag pairing', [
            'ANY' => 'Any cope closes any drag of the same size',
            'FIXED' => 'Fixed pairs only: a flask goes only with its partner',
            'MIXED' => 'Pairs where a partner is set, otherwise any: a flask with a partner goes only with it; flasks without one go with any other flask without one',
        ], 'ANY'],
        'release' => ['flasks.release', 'When flasks are freed', [
            'SHAKEOUT' => 'At shakeout of the castings',
            'POUR' => 'As soon as the mould is poured',
            'MANUAL' => 'Only by "Release flasks"',
        ], 'SHAKEOUT'],
        'run_claims' => ['flasks.run_claims', 'Moulding runs without assembled moulds', [
            'ASSEMBLED' => 'Flasks are tracked on assembled moulds only',
            'COUNT' => 'A run claims a set of flasks for each mould it makes; release them from the run',
        ], 'ASSEMBLED'],
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
}
