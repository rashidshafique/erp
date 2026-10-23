<?php

namespace App\Support;

use App\Models\AppSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The days the furnace is lit ("campaign days"), set under Settings → Melting days. Without a rule every day counts, as before.
 * Two kinds of rule: chosen weekdays, or every N days from a known campaign day with some weekdays left out (every other day
 * except Friday). A campaign day that falls on a left-out weekday is simply not a campaign day. Used to say when the next pour can
 * be, and whether an order's due date allows one.
 */
class MeltingCalendar
{
    public const MODES = [
        'EVERY_DAY' => 'Every day (no melting calendar)',
        'WEEKDAYS' => 'On chosen weekdays',
        'INTERVAL' => 'Every few days, leaving some weekdays out',
    ];

    /** Carbon day numbers: 0 Sunday … 6 Saturday. */
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];

    private const LOOK_AHEAD_DAYS = 60;

    /** @return array{mode: string, weekdays: list<int>, interval: int, anchor: ?string, skip: list<int>, after_pour_days: int} */
    public static function settings(): array
    {
        $get = function (string $key, ?string $default = null) {
            try {
                return AppSetting::getValue('melting.'.$key, $default);
            } catch (\Throwable) {
                return $default;
            }
        };
        $days = fn (?string $csv) => array_values(array_filter(array_map('intval', explode(',', (string) $csv)), fn ($d) => $d >= 0 && $d <= 6 && array_key_exists($d, self::DAYS))) ?: [];
        $mode = $get('mode', 'EVERY_DAY');

        return [
            'mode' => isset(self::MODES[$mode]) ? $mode : 'EVERY_DAY',
            'weekdays' => $days($get('weekdays', '')),
            'interval' => max(1, (int) $get('interval_days', '2')),
            'anchor' => $get('anchor_date'),
            'skip' => $days($get('skip_weekdays', '')),
            'after_pour_days' => max(0, (int) $get('after_pour_days', '1')),
        ];
    }

    /** @param  array{mode: string, weekdays: list<int>, interval: int, anchor: ?string, skip: list<int>, after_pour_days: int}  $s */
    public static function save(array $s): void
    {
        AppSetting::setValue('melting.mode', $s['mode'], 'Melting calendar: how campaign days are set');
        AppSetting::setValue('melting.weekdays', implode(',', $s['weekdays']), 'Melting calendar: the weekdays the furnace is lit');
        AppSetting::setValue('melting.interval_days', (string) $s['interval'], 'Melting calendar: days between campaigns');
        AppSetting::setValue('melting.anchor_date', $s['anchor'], 'Melting calendar: a day a campaign runs, the others count from it');
        AppSetting::setValue('melting.skip_weekdays', implode(',', $s['skip']), 'Melting calendar: weekdays with no campaign');
        AppSetting::setValue('melting.after_pour_days', (string) $s['after_pour_days'], 'Melting calendar: days from the pour to dispatch');
    }

    /** Whether a melting rule is set; without one every day is a campaign day and nothing is checked. */
    public static function enabled(): bool
    {
        $s = self::settings();

        return match ($s['mode']) {
            'WEEKDAYS' => $s['weekdays'] !== [],
            'INTERVAL' => filled($s['anchor']),
            default => false,
        };
    }

    public static function isCampaignDay(CarbonInterface $date): bool
    {
        $s = self::settings();
        $day = $date->copy()->startOfDay();

        return match (true) {
            $s['mode'] === 'WEEKDAYS' && $s['weekdays'] !== [] => in_array($day->dayOfWeek, $s['weekdays'], true),
            $s['mode'] === 'INTERVAL' && filled($s['anchor']) => ! in_array($day->dayOfWeek, $s['skip'], true)
                && (int) abs(Carbon::parse($s['anchor'])->startOfDay()->diffInDays($day, false)) % $s['interval'] === 0,
            default => true,
        };
    }

    /** The first campaign day on or after the date (the date itself counts), or null when none falls in the next two months. */
    public static function next(?CarbonInterface $from = null): ?Carbon
    {
        $day = ($from ?? today())->copy()->startOfDay();
        for ($i = 0; $i <= self::LOOK_AHEAD_DAYS; $i++) {
            if (self::isCampaignDay($day)) {
                return Carbon::instance($day);
            }
            $day = $day->copy()->addDay();
        }

        return null;
    }

    /** The next few campaign days from today, for the settings page to show what the rule means. @return list<Carbon> */
    public static function upcoming(int $count = 8): array
    {
        $out = [];
        $day = today();
        while (count($out) < $count && ($next = self::next($day)) !== null) {
            $out[] = $next;
            $day = $next->copy()->addDay();
        }

        return $out;
    }

    /**
     * Whether a pour can still happen in time for the due date: a campaign day from today up to the due date less the days the casting
     * needs after the pour (cooling, shakeout, fettling, dispatch).
     *
     * @return array{ok: bool, next: ?Carbon, latest_pour: Carbon}
     */
    public static function checkDue(CarbonInterface $due, ?CarbonInterface $from = null): array
    {
        $from = ($from ?? today())->copy()->startOfDay();
        $latest = Carbon::instance($due)->startOfDay()->subDays(self::settings()['after_pour_days']);
        $next = self::next($from);

        return ['ok' => $next !== null && $next->lte($latest), 'next' => $next, 'latest_pour' => $latest];
    }

    /** The rule in words, for the pages that show it. */
    public static function describe(): string
    {
        $s = self::settings();
        $names = fn (array $days) => collect(self::DAYS)->only($days)->values()->implode(', ');

        return match (true) {
            ! self::enabled() => 'The furnace is assumed to run every day. Set the melting days under Settings.',
            $s['mode'] === 'WEEKDAYS' => 'The furnace is lit on '.$names($s['weekdays']).'.',
            default => 'The furnace is lit '.($s['interval'] === 1 ? 'every day' : ($s['interval'] === 2 ? 'every other day' : "every {$s['interval']} days"))
                .' from '.Carbon::parse($s['anchor'])->format('D j M').($s['skip'] !== [] ? ', never on '.$names($s['skip']) : '').'.',
        };
    }
}
