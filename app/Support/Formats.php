<?php

namespace App\Support;

use App\Models\AppSetting;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** How dates, times, numbers and money are shown everywhere. Chosen under Administration → Display, so changing it never touches stored data. */
class Formats
{
    /** @var array<string, array{label:string,date:string,short:string}> */
    public const DATES = [
        'dMY' => ['label' => '18 Oct 2026', 'date' => 'd M Y', 'short' => 'd M'],
        'dmy-' => ['label' => '18-10-2026 (dd-mm-yyyy)', 'date' => 'd-m-Y', 'short' => 'd-m'],
        'mdy-' => ['label' => '10-18-2026 (mm-dd-yyyy)', 'date' => 'm-d-Y', 'short' => 'm-d'],
        'ymd-' => ['label' => '2026-10-18 (yyyy-mm-dd)', 'date' => 'Y-m-d', 'short' => 'm-d'],
        'dmy/' => ['label' => '18/10/2026 (dd/mm/yyyy)', 'date' => 'd/m/Y', 'short' => 'd/m'],
        'mdy/' => ['label' => '10/18/2026 (mm/dd/yyyy)', 'date' => 'm/d/Y', 'short' => 'm/d'],
        'dmy.' => ['label' => '18.10.2026 (dd.mm.yyyy)', 'date' => 'd.m.Y', 'short' => 'd.m'],
        'ymd/' => ['label' => '2026/10/18 (yyyy/mm/dd)', 'date' => 'Y/m/d', 'short' => 'm/d'],
        'ymd.' => ['label' => '2026.10.18 (yyyy.mm.dd)', 'date' => 'Y.m.d', 'short' => 'm.d'],
        'dMYc' => ['label' => '18 October 2026', 'date' => 'd F Y', 'short' => 'd M'],
        'MdY' => ['label' => 'Oct 18, 2026', 'date' => 'M d, Y', 'short' => 'M d'],
        'FdY' => ['label' => 'October 18, 2026', 'date' => 'F d, Y', 'short' => 'M d'],
        'DdMY' => ['label' => 'Sun, 18 Oct 2026', 'date' => 'D, d M Y', 'short' => 'D, d M'],
        'dMy' => ['label' => '18-Oct-26', 'date' => 'd-M-y', 'short' => 'd-M'],
        'dmy2' => ['label' => '18/10/26 (dd/mm/yy)', 'date' => 'd/m/y', 'short' => 'd/m'],
    ];

    /** @var array<string, array{label:string,time:string}> */
    public const TIMES = [
        '24' => ['label' => '24-hour (14:30)', 'time' => 'H:i'],
        '12' => ['label' => '12-hour (2:30 PM)', 'time' => 'g:i A'],
        '12l' => ['label' => '12-hour (2:30 pm)', 'time' => 'g:i a'],
        '12z' => ['label' => '12-hour (02:30 PM)', 'time' => 'h:i A'],
        '24s' => ['label' => '24-hour with seconds (14:30:15)', 'time' => 'H:i:s'],
        '12s' => ['label' => '12-hour with seconds (2:30:15 PM)', 'time' => 'g:i:s A'],
        '24d' => ['label' => '24-hour with dot (14.30)', 'time' => 'H.i'],
    ];

    /** @var array<string, array{label:string,dec:string,thou:string}> */
    public const NUMBERS = [
        'us' => ['label' => '1,234,567.89', 'dec' => '.', 'thou' => ','],
        'eu' => ['label' => '1.234.567,89 (European)', 'dec' => ',', 'thou' => '.'],
        'space' => ['label' => '1 234 567,89', 'dec' => ',', 'thou' => ' '],
        'swiss' => ['label' => "1'234'567.89", 'dec' => '.', 'thou' => "'"],
        'plain' => ['label' => '1234567.89 (no grouping)', 'dec' => '.', 'thou' => ''],
    ];

    private static ?array $cache = null;

    /** The most decimal places a typed number keeps, whatever the field; extra places are rounded off. */
    public const DECIMALS = [0, 1, 2, 3, 4, 5, 6];

    /** @return array{date:string,time:string,number:string,currency:string,currency_position:string,decimals:int} */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $default = ['date' => 'dMY', 'time' => '24', 'number' => 'us', 'currency' => (string) config('quotation.currency', ''), 'currency_position' => 'after', 'decimals' => 4];
        try {
            $set = [
                'date' => (string) AppSetting::getValue('format.date', $default['date']),
                'time' => (string) AppSetting::getValue('format.time', $default['time']),
                'number' => (string) AppSetting::getValue('format.number', $default['number']),
                'currency' => (string) AppSetting::getValue('format.currency', $default['currency']),
                'currency_position' => (string) AppSetting::getValue('format.currency_position', $default['currency_position']),
                'decimals' => (int) AppSetting::getValue('format.decimals', (string) $default['decimals']),
            ];
        } catch (\Throwable) {
            return self::$cache = $default;
        }
        $set['date'] = isset(self::DATES[$set['date']]) ? $set['date'] : $default['date'];
        $set['time'] = isset(self::TIMES[$set['time']]) ? $set['time'] : $default['time'];
        $set['number'] = isset(self::NUMBERS[$set['number']]) ? $set['number'] : $default['number'];
        $set['currency_position'] = $set['currency_position'] === 'before' ? 'before' : 'after';
        $set['decimals'] = in_array($set['decimals'], self::DECIMALS, true) ? $set['decimals'] : $default['decimals'];

        return self::$cache = $set;
    }

    /** How many decimal places a typed number keeps. */
    public static function decimals(): int
    {
        return self::all()['decimals'];
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function date(?CarbonInterface $d): string
    {
        return $d ? $d->format(self::DATES[self::all()['date']]['date']) : '';
    }

    /** Day and month only, in the same order as the full date. */
    public static function shortDate(?CarbonInterface $d): string
    {
        return $d ? $d->format(self::DATES[self::all()['date']]['short']) : '';
    }

    public static function time(?CarbonInterface $d): string
    {
        return $d ? $d->format(self::TIMES[self::all()['time']]['time']) : '';
    }

    /** Date without the year, then the time. */
    public static function shortDateTime(?CarbonInterface $d): string
    {
        return $d ? self::shortDate($d).' '.self::time($d) : '';
    }

    public static function dateTime(?CarbonInterface $d): string
    {
        return $d ? self::date($d).' '.self::time($d) : '';
    }

    /** A moment as the heat screens show it: today's as the time alone ("2:05 PM"), earlier ones with their date as well. */
    public static function at(?CarbonInterface $d): string
    {
        return $d === null ? '' : ($d->isToday() ? '' : self::shortDate($d).', ').self::time($d);
    }

    /** Seconds as a running clock, "1:05:09". */
    public static function clock(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return intdiv($seconds, 3600).':'.str_pad((string) (intdiv($seconds, 60) % 60), 2, '0', STR_PAD_LEFT).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    /** A length of time in minutes as "45 min" or "1 h 05 min". */
    public static function duration(int $minutes): string
    {
        $minutes = max(0, $minutes);

        return $minutes < 60 ? $minutes.' min' : intdiv($minutes, 60).' h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).' min';
    }

    /** A whole number when $decimals is 0; any decimal number shows the places set under Display, whatever $decimals asks for. */
    public static function number(float|int|string|null $value, int $decimals = 0): string
    {
        $n = self::NUMBERS[self::all()['number']];

        return number_format((float) $value, $decimals > 0 ? self::decimals() : 0, $n['dec'], $n['thou']);
    }

    /** The currency set under Display as a label ending, " (PKR)", or nothing when none is set; for "Cost (PKR)" style labels. */
    public static function currencySuffix(): string
    {
        $currency = self::all()['currency'];

        return $currency === '' ? '' : ' ('.$currency.')';
    }

    public static function money(float|int|string|null $value, int $decimals = 2): string
    {
        return self::withCurrency(self::number($value, $decimals));
    }

    /** An amount already written out, such as "1.24M", with the currency set under Display before or after it. */
    public static function withCurrency(string $amount): string
    {
        $s = self::all();

        return match (true) {
            $s['currency'] === '' => $amount,
            $s['currency_position'] === 'before' => $s['currency'].' '.$amount,
            default => $amount.' '.$s['currency'],
        };
    }

    /** Teaches Carbon the display formats: $date->displayDate(), displayDateTime(), displayTime(), displayShortDate(), displayAt(). */
    public static function registerCarbonMacros(): void
    {
        foreach ([Carbon::class, CarbonImmutable::class] as $class) {
            $class::macro('displayDate', fn () => Formats::date($this));
            $class::macro('displayShortDate', fn () => Formats::shortDate($this));
            $class::macro('displayTime', fn () => Formats::time($this));
            $class::macro('displayDateTime', fn () => Formats::dateTime($this));
            $class::macro('displayShortDateTime', fn () => Formats::shortDateTime($this));
            $class::macro('displayAt', fn () => Formats::at($this));
        }
    }
}
