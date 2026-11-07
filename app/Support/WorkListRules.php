<?php

namespace App\Support;

/** How strictly moulding work lists gate the floor, set under Settings. */
class WorkListRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description) or null for a number, default] */
    public const RULES = [
        'start_before_issue' => ['worklists.start_before_issue', 'Starting a run from an unissued list', [
            'BLOCK' => 'Not allowed',
            'ALLOW' => 'Allowed: start from a draft list',
        ], 'BLOCK'],
        'flask_check' => ['worklists.flask_check', 'Planned moulds need more flasks than are free', [
            'WARN' => 'Warn on the list; it can still be issued',
            'BLOCK' => 'Block issuing the list',
            'OFF' => 'Do not check flasks on work lists',
        ], 'WARN'],
        'auto_prepare' => ['worklists.auto_prepare', 'Daily moulding list from open orders', [
            'NEXT_DAY' => 'Each evening (18:00) for tomorrow, as a draft',
            'SAME_DAY' => 'Each morning (06:00) for today, as a draft',
            'OFF' => 'Manual: button on the list page',
        ], 'NEXT_DAY'],
        'remind_evening_hour' => ['worklists.remind_evening_hour', 'Hour to remind the planner of a list still a draft, evening (0-23)', null, '20'],
        'remind_morning_hour' => ['worklists.remind_morning_hour', 'Hour to remind the planner of a list still a draft, morning (0-23)', null, '7'],
    ];

    /** @return list<string> */
    protected static function numberRule(string $rule): array
    {
        return ['integer', 'min:0', 'max:23'];
    }

    /** @return list<int> the hours of the day the planner is reminded of a draft list */
    public static function reminderHours(): array
    {
        return array_values(array_unique([(int) self::get('remind_morning_hour'), (int) self::get('remind_evening_hour')]));
    }

    public static function mayStartFromDraft(): bool
    {
        return self::get('start_before_issue') === 'ALLOW';
    }
}
