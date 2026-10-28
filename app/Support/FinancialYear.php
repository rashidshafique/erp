<?php

namespace App\Support;

use App\Models\AppSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The financial year: it starts on the first day of a chosen month and runs twelve months. Reports and returns open on it,
 * and "close the year" locks the books to its last day. Until set, a Pakistan install starts in July and any other in January.
 */
class FinancialYear
{
    public const START_MONTH = 'books.year_start_month';

    public static function startMonth(): int
    {
        $month = (int) AppSetting::getValue(self::START_MONTH, '0');

        return $month >= 1 && $month <= 12 ? $month : (TaxJurisdiction::code() === 'PK' ? 7 : 1);
    }

    /**
     * The year a date falls in.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public static function containing(?CarbonInterface $date = null): array
    {
        $date = Carbon::instance($date ?? today())->startOfDay();
        $month = self::startMonth();
        $startYear = $date->month >= $month ? $date->year : $date->year - 1;
        $start = Carbon::create($startYear, $month, 1)->startOfDay();

        return ['start' => $start, 'end' => $start->copy()->addYear()->subDay()];
    }

    /** @return array{start: Carbon, end: Carbon} */
    public static function previous(?CarbonInterface $date = null): array
    {
        return self::containing(self::containing($date)['start']->subDay());
    }

    /** "2026" for a calendar year, else "Jul 2025 – Jun 2026". @param array{start: Carbon, end: Carbon} $year */
    public static function label(array $year): string
    {
        return $year['start']->month === 1 ? (string) $year['start']->year : $year['start']->format('M Y').' – '.$year['end']->format('M Y');
    }
}
