<?php

namespace App\Support;

/** How strictly moulding work lists gate the floor, set under Settings. */
class WorkListRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description), default] */
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
    ];

    public static function mayStartFromDraft(): bool
    {
        return self::get('start_before_issue') === 'ALLOW';
    }
}
