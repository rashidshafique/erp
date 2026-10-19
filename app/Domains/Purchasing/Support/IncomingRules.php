<?php

namespace App\Domains\Purchasing\Support;

use App\Models\AppSetting;

/** What a received lot needs before it can be accepted, set under Settings. A small foundry can leave both off; a certified one switches them on. */
class IncomingRules
{
    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'certificate' => ['incoming.certificate', 'Supplier certificate needed to accept a lot', [
            'NONE' => 'Not required',
            'ATTACHED' => 'A certificate file must be attached to the lot',
            'EN10204_31' => 'Attached, and the lot must be marked EN 10204 3.1 or better',
            'EN10204_32' => 'Attached, and the lot must be marked EN 10204 3.2',
        ], 'NONE'],
        'inspection' => ['incoming.inspection', 'Incoming inspection needed to accept a lot', [
            'OFF' => 'Not required',
            'REQUIRED' => 'The lot must have a passed inspection, or one accepted by concession',
        ], 'OFF'],
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
