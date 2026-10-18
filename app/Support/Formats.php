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
    ];

    /** @var array<string, array{label:string,time:string}> */
    public const TIMES = [
        '24' => ['label' => '24-hour (14:30)', 'time' => 'H:i'],
        '12' => ['label' => '12-hour (2:30 PM)', 'time' => 'g:i A'],
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

    /** @return array{date:string,time:string,number:string,currency:string,currency_position:string} */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $default = ['date' => 'dMY', 'time' => '24', 'number' => 'us', 'currency' => (string) config('quotation.currency', ''), 'currency_position' => 'after'];
        try {
            $set = [
                'date' => (string) AppSetting::getValue('format.date', $default['date']),
                'time' => (string) AppSetting::getValue('format.time', $default['time']),
                'number' => (string) AppSetting::getValue('format.number', $default['number']),
                'currency' => (string) AppSetting::getValue('format.currency', $default['currency']),
                'currency_position' => (string) AppSetting::getValue('format.currency_position', $default['currency_position']),
            ];
        } catch (\Throwable) {
            return self::$cache = $default;
        }
        $set['date'] = isset(self::DATES[$set['date']]) ? $set['date'] : $default['date'];
        $set['time'] = isset(self::TIMES[$set['time']]) ? $set['time'] : $default['time'];
        $set['number'] = isset(self::NUMBERS[$set['number']]) ? $set['number'] : $default['number'];
        $set['currency_position'] = $set['currency_position'] === 'before' ? 'before' : 'after';

        return self::$cache = $set;
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

    public static function number(float|int|string|null $value, int $decimals = 0): string
    {
        $n = self::NUMBERS[self::all()['number']];

        return number_format((float) $value, $decimals, $n['dec'], $n['thou']);
    }

    public static function money(float|int|string|null $value, int $decimals = 2): string
    {
        $s = self::all();
        $amount = self::number($value, $decimals);

        return match (true) {
            $s['currency'] === '' => $amount,
            $s['currency_position'] === 'before' => $s['currency'].' '.$amount,
            default => $amount.' '.$s['currency'],
        };
    }

    /** Teaches Carbon the display formats: $date->displayDate(), displayDateTime(), displayTime(), displayShortDate(). */
    public static function registerCarbonMacros(): void
    {
        foreach ([Carbon::class, CarbonImmutable::class] as $class) {
            $class::macro('displayDate', fn () => Formats::date($this));
            $class::macro('displayShortDate', fn () => Formats::shortDate($this));
            $class::macro('displayTime', fn () => Formats::time($this));
            $class::macro('displayDateTime', fn () => Formats::dateTime($this));
            $class::macro('displayShortDateTime', fn () => Formats::shortDateTime($this));
        }
    }
}
