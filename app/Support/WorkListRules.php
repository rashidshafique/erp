<?php

namespace App\Support;

use App\Models\AppSetting;

/** How strictly moulding work lists gate the floor, set under Settings. */
class WorkListRules
{
    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'start_before_issue' => ['worklists.start_before_issue', 'Starting a run from a list that has not been issued', [
            'BLOCK' => 'Not allowed: the list must be issued before its runs can start',
            'ALLOW' => 'Allowed: moulders can start items from a draft list while the planner finishes it',
        ], 'BLOCK'],
        'flask_check' => ['worklists.flask_check', 'Planned moulds needing more flask sets than are free', [
            'WARN' => 'Warn on the list; it can still be issued',
            'BLOCK' => 'Not allowed: the list cannot be issued until enough sets are free or rows are cut',
            'OFF' => 'Do not check flasks on work lists',
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

    public static function mayStartFromDraft(): bool
    {
        return self::get('start_before_issue') === 'ALLOW';
    }
}
