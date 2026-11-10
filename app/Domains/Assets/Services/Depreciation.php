<?php

namespace App\Domains\Assets\Services;

use App\Domains\Assets\Models\DepreciationRun;
use App\Domains\Assets\Models\FixedAsset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Works out a month's depreciation for each asset (design 28f).
 * - Straight line: (cost − residual) ÷ life in months, a part month from the in-use date.
 * - Reducing balance: the yearly rate on the book value at the start of the month, ÷ 12, a part month from the in-use date.
 * - Usage (patterns and core boxes): rate per fill = (cost − residual) ÷ life fills, charge = fills in the month × rate.
 *   Pattern fills are moulds made on moulding runs of any revision of the pattern; core box fills are cores made ÷ its cavities.
 * No charge takes the book value below the residual value.
 */
class Depreciation
{
    /**
     * Every asset's charge for the month, worked out fresh, or only those of the ids given. Assets in progress, gone before the month,
     * or with nothing left to depreciate are left out.
     *
     * @param  list<string>|null  $only
     * @return Collection<int, array{asset: FixedAsset, fills: int|null, rate: float|null, charge: float, book_before: float, runs: list<string>, capped: bool}>
     */
    public function forMonth(Carbon $period, ?array $only = null): Collection
    {
        [$start, $end] = [$period->copy()->startOfMonth(), $period->copy()->endOfMonth()];
        $assets = FixedAsset::with(['assetClass', 'pattern', 'coreBox', 'equipment'])->where('status', '!=', 'IN_PROGRESS')
            ->whereNotNull('in_use_on')->whereDate('in_use_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('disposed_on')->orWhereDate('disposed_on', '>=', $start))
            ->where('method', '!=', 'NONE')->when($only !== null, fn ($q) => $q->whereIn('id', $only))->orderBy('code')->get();
        $before = $this->postedBefore($start);
        $patternFills = $this->patternFills($assets->whereNotNull('pattern_id')->pluck('pattern_id')->all(), $start, $end);
        $coreFills = $this->coreBoxFills($assets->whereNotNull('core_box_id')->pluck('core_box_id')->all(), $start, $end);

        return $assets->map(function (FixedAsset $asset) use ($start, $end, $before, $patternFills, $coreFills) {
            $book = round($asset->cost - $asset->opening_depreciation - ($before[$asset->id] ?? 0.0), 2);
            $left = round($book - $asset->residual_value, 2);
            $from = $asset->in_use_on->gt($start) ? $asset->in_use_on : $start;
            $to = $asset->disposed_on && $asset->disposed_on->lt($end) ? $asset->disposed_on : $end;
            $share = ((int) round($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay())) + 1) / $end->daysInMonth;
            [$fills, $rate, $runs] = [null, null, []];
            $charge = match ($asset->method) {
                'STRAIGHT_LINE' => $asset->life_years > 0 ? ($asset->cost - $asset->residual_value) / ($asset->life_years * 12) * $share : 0.0,
                'REDUCING_BALANCE' => $book * ($asset->rate_percent / 100) / 12 * $share,
                'USAGE' => (function () use ($asset, $patternFills, $coreFills, $from, $to, &$fills, &$rate, &$runs) {
                    $found = $asset->pattern_id ? ($patternFills[$asset->pattern_id] ?? []) : ($asset->core_box_id ? ($coreFills[$asset->core_box_id] ?? []) : []);
                    $inside = array_filter($found, fn ($f) => $f['date'] >= $from->toDateString() && $f['date'] <= $to->toDateString());
                    $fills = (int) array_sum(array_column($inside, 'fills'));
                    $runs = array_values(array_filter(array_column($inside, 'number')));
                    $rate = $asset->life_fills > 0 ? round(($asset->cost - $asset->residual_value) / $asset->life_fills, 4) : 0.0;

                    return $fills * $rate;
                })(),
                default => 0.0,
            };
            $charge = round(max(0.0, $charge), 2);
            $capped = $charge > $left;

            return ['asset' => $asset, 'fills' => $fills, 'rate' => $rate, 'charge' => max(0.0, min($charge, $left)), 'book_before' => $book, 'runs' => $runs, 'capped' => $capped];
        })->filter(fn ($l) => $l['book_before'] > $l['asset']->residual_value || $l['charge'] > 0)->values();
    }

    /** @return array<string, float> asset id => charges posted in runs before this month */
    private function postedBefore(Carbon $start): array
    {
        return DB::table('depreciation_run_lines')->join('depreciation_runs', 'depreciation_runs.id', '=', 'depreciation_run_lines.depreciation_run_id')
            ->where('depreciation_runs.status', DepreciationRun::POSTED)->where('depreciation_runs.period', '<', $start->toDateString())
            ->groupBy('fixed_asset_id')->selectRaw('fixed_asset_id, sum(charge) as total')->pluck('total', 'fixed_asset_id')->map(fn ($v) => (float) $v)->all();
    }

    /**
     * Moulds made on runs of each pattern in the month, run by run.
     *
     * @param  list<string>  $patternIds
     * @return array<string, list<array{date: string, fills: int, number: string|null}>>
     */
    public function patternFills(array $patternIds, Carbon $start, Carbon $end): array
    {
        if ($patternIds === []) {
            return [];
        }

        return DB::table('moulding_runs')->join('pattern_revisions', 'pattern_revisions.id', '=', 'moulding_runs.pattern_revision_id')
            ->whereIn('pattern_revisions.pattern_id', $patternIds)->where('moulding_runs.status', '!=', 'CANCELLED')
            ->whereBetween('moulding_runs.run_date', [$start->toDateString(), $end->toDateString()])->where('moulding_runs.moulds_made_qty', '>', 0)
            ->orderBy('moulding_runs.run_date')->get(['pattern_revisions.pattern_id', 'moulding_runs.run_date', 'moulding_runs.moulds_made_qty', 'moulding_runs.run_number'])
            ->groupBy('pattern_id')->map(fn ($rows) => $rows->map(fn ($r) => ['date' => substr((string) $r->run_date, 0, 10), 'fills' => (int) $r->moulds_made_qty, 'number' => $r->run_number])->all())->all();
    }

    /**
     * Fills of each core box in the month: cores made on each lot ÷ the box's cavities, rounded up.
     *
     * @param  list<string>  $coreBoxIds
     * @return array<string, list<array{date: string, fills: int, number: string|null}>>
     */
    public function coreBoxFills(array $coreBoxIds, Carbon $start, Carbon $end): array
    {
        if ($coreBoxIds === []) {
            return [];
        }

        return DB::table('core_lots')->join('core_boxes', 'core_boxes.id', '=', 'core_lots.core_box_id')->whereIn('core_lots.core_box_id', $coreBoxIds)
            ->whereBetween('core_lots.made_on', [$start->toDateString(), $end->toDateString()])->where('core_lots.quantity_made', '>', 0)
            ->orderBy('core_lots.made_on')->get(['core_lots.core_box_id', 'core_lots.made_on', 'core_lots.quantity_made', 'core_lots.lot_number', 'core_boxes.cavities'])
            ->groupBy('core_box_id')->map(fn ($rows) => $rows->map(fn ($r) => [
                'date' => substr((string) $r->made_on, 0, 10), 'fills' => (int) ceil($r->quantity_made / max(1, (int) $r->cavities)), 'number' => $r->lot_number,
            ])->all())->all();
    }
}
