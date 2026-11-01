<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Ledger\Models\JournalLine;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Support\FinancialYear;
use Illuminate\Support\Carbon;

/**
 * Trial balance by category, section and account for a period (design 19e): opening, debits, credits and closing. Balance sheet
 * accounts open on everything before the period; revenue and cost accounts on the year to date from the start of the financial
 * year. Profit of earlier years not yet closed to reserves shows as its own line under equity, so the columns always agree.
 */
class TrialBalance
{
    /**
     * @return array{rows: list<array<string, mixed>>, totals: array{opening: float, debit: float, credit: float, dr: float, cr: float}, from: string, to: string, fyStart: string}
     */
    public function build(string $from, string $to): array
    {
        $fyStart = FinancialYear::containing(Carbon::parse($from))['start']->toDateString();
        $accounts = LedgerAccount::orderBy('sort_order')->orderBy('code')->get();
        $pl = fn (LedgerAccount $a) => in_array($a->type, ['INCOME', 'EXPENSE'], true);
        $sum = fn (?string $after, ?string $before) => JournalLine::join('ledger_journals', 'ledger_journals.id', '=', 'ledger_journal_lines.journal_id')
            ->when($after, fn ($q) => $q->where('journal_date', '>=', $after))->when($before, fn ($q) => $q->where('journal_date', '<', $before))
            ->groupBy('account_id')->selectRaw('account_id, coalesce(sum(debit), 0) d, coalesce(sum(credit), 0) c')->get()->keyBy('account_id');
        $before = $sum(null, $from);
        $ytdBefore = $sum($fyStart, $from);
        $priorYears = $sum(null, $fyStart);
        $moves = JournalLine::join('ledger_journals', 'ledger_journals.id', '=', 'ledger_journal_lines.journal_id')
            ->whereBetween('journal_date', [$from, $to])->groupBy('account_id')
            ->selectRaw('account_id, coalesce(sum(debit), 0) d, coalesce(sum(credit), 0) c')->get()->keyBy('account_id');

        $leaf = [];
        $earlierProfit = 0.0;
        foreach ($accounts as $a) {
            if (strlen($a->code) <= 5) {
                continue;
            }
            $src = $pl($a) ? $ytdBefore : $before;
            $opening = round((float) ($src[$a->id]->d ?? 0) - (float) ($src[$a->id]->c ?? 0), 2);
            if ($pl($a)) {
                $earlierProfit += (float) ($priorYears[$a->id]->d ?? 0) - (float) ($priorYears[$a->id]->c ?? 0);
            }
            $d = round((float) ($moves[$a->id]->d ?? 0), 2);
            $c = round((float) ($moves[$a->id]->c ?? 0), 2);
            $leaf[$a->id] = ['opening' => $opening, 'debit' => $d, 'credit' => $c, 'closing' => round($opening + $d - $c, 2)];
        }

        $children = $accounts->groupBy(fn ($a) => $a->parent_id ?? '');
        $rows = [];
        $totals = ['opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0, 'dr' => 0.0, 'cr' => 0.0];
        $add = function (array &$into, array $v): void {
            foreach (['opening', 'debit', 'credit', 'closing'] as $k) {
                $into[$k] = round(($into[$k] ?? 0) + $v[$k], 2);
            }
        };
        $earlier = round($earlierProfit, 2);
        $reserves = LedgerSettings::account('opening_equity')->parent?->code;
        foreach ($children->get('', collect()) as $cat) {
            $catRow = ['level' => 'category', 'account' => $cat, 'opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0, 'closing' => 0.0];
            $secRows = [];
            foreach ($children->get($cat->id, collect()) as $sec) {
                $secRow = ['level' => 'section', 'account' => $sec, 'opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0, 'closing' => 0.0, 'accounts' => []];
                foreach ($children->get($sec->id, collect()) as $a) {
                    if (! isset($leaf[$a->id])) {
                        continue;
                    }
                    $secRow['accounts'][] = ['level' => 'account', 'account' => $a] + $leaf[$a->id];
                    $add($secRow, $leaf[$a->id]);
                }
                if ($earlier != 0.0 && $sec->code === $reserves) {
                    $line = ['opening' => $earlier, 'debit' => 0.0, 'credit' => 0.0, 'closing' => $earlier];
                    $secRow['accounts'][] = ['level' => 'account', 'account' => null, 'name' => 'Profit of earlier years, not yet closed'] + $line;
                    $add($secRow, $line);
                    $earlier = 0.0;
                }
                $add($catRow, $secRow);
                $secRows[] = $secRow;
            }
            $catRow['sections'] = $secRows;
            $rows[] = $catRow;
        }
        if ($earlier != 0.0) {
            $rows[] = ['level' => 'category', 'account' => null, 'name' => 'Profit of earlier years, not yet closed', 'opening' => $earlier, 'debit' => 0.0, 'credit' => 0.0, 'closing' => $earlier, 'sections' => []];
        }
        foreach ($rows as $cat) {
            foreach ($cat['sections'] as $sec) {
                foreach ($sec['accounts'] as $a) {
                    $totals['opening'] += $a['opening'];
                    $totals['debit'] += $a['debit'];
                    $totals['credit'] += $a['credit'];
                    $totals[$a['closing'] > 0 ? 'dr' : 'cr'] += abs($a['closing']);
                }
            }
            if ($cat['account'] === null) {
                $totals['opening'] += $cat['opening'];
                $totals[$cat['closing'] > 0 ? 'dr' : 'cr'] += abs($cat['closing']);
            }
        }

        return ['rows' => $rows, 'totals' => array_map(fn ($v) => round($v, 2), $totals), 'from' => $from, 'to' => $to, 'fyStart' => $fyStart];
    }
}
