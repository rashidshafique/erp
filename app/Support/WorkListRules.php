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
        'auto_prepare' => ['worklists.auto_prepare', 'Preparing the day\'s moulding list from open orders', [
            'NEXT_DAY' => 'Each evening (18:00) tomorrow\'s list is prepared as a draft, to change and release',
            'SAME_DAY' => 'Each morning (06:00) the day\'s list is prepared as a draft, to change and release',
            'OFF' => 'Not automatic: prepare it with the button on the list page',
        ], 'NEXT_DAY'],
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
