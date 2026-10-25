<?php

namespace App\Services\Production;

use App\Models\Heat;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Numbers a heat for its day: H-YYMM-DD for the first, H-YYMM-DD-1, H-YYMM-DD-2 and so on for the others of that day (H-2610-25 is
 * 25 October 2026). Older numbers, H-YYYYMMDD-NNN, stay as they are and count as heats of their day. Call inside a transaction: the
 * day is locked so two heats made together never share a number.
 */
class HeatNumber
{
    public function next(CarbonInterface $day): string
    {
        $base = 'H-'.$day->format('ym').'-'.$day->format('d');
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['heat-number-'.$day->format('Ymd')]);

        $legacy = Heat::query()->where('heat_number', 'like', 'H-'.$day->format('Ymd').'-%')->count();
        $numbers = Heat::query()->where(fn ($q) => $q->where('heat_number', $base)->orWhere('heat_number', 'like', $base.'-%'))->pluck('heat_number');
        $highest = $numbers->map(fn ($n) => $n === $base ? 0 : (int) substr($n, strlen($base) + 1))->max();
        $index = max($highest === null ? 0 : $highest + 1, $legacy + $numbers->count());

        return $index === 0 ? $base : $base.'-'.$index;
    }
}
