<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Ledger\Models\JournalLine;
use App\Domains\Ledger\Models\LedgerAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Profit and loss, balance sheet and the general ledger, read straight from the posted journals. Nothing here writes.
 * Amounts are shown the way an accountant reads them: income, liabilities and equity as positive credits, assets and expenses as positive debits.
 */
class Statements
{
    /**
     * Income and expenses over a period, each top-level account a section. The cost-of-sales section set under Ledger accounts comes
     * straight after income with a gross profit line.
     *
     * @param  array{0: string, 1: string}|null  $compare  [from, to] of a second period shown alongside
     * @return array{income: list<array<string, mixed>>, costOfSales: ?array<string, mixed>, expenses: list<array<string, mixed>>, totals: array<string, array{0: float, 1: ?float}>}
     */
    public function profitAndLoss(string $from, string $to, ?array $compare = null, bool $all = false): array
    {
        $sections = $this->sections(LedgerAccount::tree($to, $from), $compare ? LedgerAccount::tree($compare[1], $compare[0]) : null, ['INCOME', 'EXPENSE'], $all);
        $costId = LedgerSettings::costOfSales()?->id;
        $income = array_values(array_filter($sections, fn ($s) => $s['account']->type === 'INCOME'));
        $costOfSales = collect($sections)->first(fn ($s) => $s['account']->id === $costId);
        $expenses = array_values(array_filter($sections, fn ($s) => $s['account']->type === 'EXPENSE' && $s['account']->id !== $costId));
        $sum = fn (array $list, int $i) => round(array_sum(array_map(fn ($s) => $s['total'][$i] ?? 0, $list)), 2);
        $pair = fn (callable $f) => [$f(0), $compare ? $f(1) : null];
        $totals = [
            'income' => $pair(fn ($i) => $sum($income, $i)),
            'gross' => $pair(fn ($i) => round($sum($income, $i) - ($costOfSales['total'][$i] ?? 0), 2)),
            'expenses' => $pair(fn ($i) => $sum($expenses, $i)),
        ];
        $totals['net'] = $pair(fn ($i) => round($totals['gross'][$i] - $totals['expenses'][$i], 2));

        return ['income' => $income, 'costOfSales' => $costOfSales, 'expenses' => $expenses, 'totals' => $totals];
    }

    /**
     * Assets against liabilities and equity as at a date. Profit not yet closed to retained earnings is shown under equity, so the two
     * sides always agree.
     *
     * @return array{assets: list<array<string, mixed>>, liabilities: list<array<string, mixed>>, equity: list<array<string, mixed>>, totals: array<string, array{0: float, 1: ?float}>}
     */
    public function balanceSheet(string $asAt, ?string $compareAt = null, bool $all = false): array
    {
        $now = LedgerAccount::tree($asAt);
        $then = $compareAt ? LedgerAccount::tree($compareAt) : null;
        $sections = $this->sections($now, $then, ['ASSET', 'LIABILITY', 'EQUITY'], $all);
        $pick = fn (string $type) => array_values(array_filter($sections, fn ($s) => $s['account']->type === $type));
        $sum = fn (array $list, int $i) => round(array_sum(array_map(fn ($s) => $s['total'][$i] ?? 0, $list)), 2);
        $profit = fn (?Collection $tree) => $tree ? round(-$tree->where('depth', 0)->whereIn('type', ['INCOME', 'EXPENSE'])->sum('net'), 2) : null;
        $assets = $pick('ASSET');
        $liabilities = $pick('LIABILITY');
        $equity = $pick('EQUITY');
        $totals = [
            'assets' => [$sum($assets, 0), $then ? $sum($assets, 1) : null],
            'liabilities' => [$sum($liabilities, 0), $then ? $sum($liabilities, 1) : null],
            'profit' => [$profit($now), $profit($then)],
        ];
        $totals['equity'] = [round($sum($equity, 0) + $totals['profit'][0], 2), $then ? round($sum($equity, 1) + $totals['profit'][1], 2) : null];
        $totals['claims'] = [round($totals['liabilities'][0] + $totals['equity'][0], 2), $then ? round($totals['liabilities'][1] + $totals['equity'][1], 2) : null];

        return ['assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'totals' => $totals];
    }

    /**
     * Every account that moved in the period or carries a balance into it, with its opening balance, postings and closing balance
     * (debits less credits).
     *
     * @return list<array{account: LedgerAccount, opening: float, lines: Collection<int, JournalLine>, debit: float, credit: float, closing: float}>
     */
    public function generalLedger(string $from, string $to, ?string $type = null): array
    {
        $opening = LedgerAccount::tree(Carbon::parse($from)->subDay()->toDateString())->keyBy('id');
        $lines = JournalLine::with('journal')->join('ledger_journals', 'ledger_journals.id', '=', 'ledger_journal_lines.journal_id')
            ->whereBetween('ledger_journals.journal_date', [$from, $to])
            ->orderBy('ledger_journals.journal_date')->orderBy('ledger_journals.number')->select('ledger_journal_lines.*')->get()->groupBy('account_id');
        $out = [];
        foreach (LedgerAccount::tree($to)->where('is_group', false) as $account) {
            $open = (float) ($opening[$account->id]->net ?? 0);
            $moves = $lines->get($account->id, collect());
            if (($type && $account->type !== $type) || ($open == 0.0 && $moves->isEmpty())) {
                continue;
            }
            $debit = round($moves->sum('debit'), 2);
            $credit = round($moves->sum('credit'), 2);
            $out[] = ['account' => $account, 'opening' => $open, 'lines' => $moves, 'debit' => $debit, 'credit' => $credit, 'closing' => round($open + $debit - $credit, 2)];
        }

        return $out;
    }

    /**
     * Top-level accounts of the given types as sections, each with its rows below (sub-accounts and groups) and its total. Amounts are
     * turned to the account's normal side. Rows with nothing in either period are left out unless $all.
     *
     * @param  list<string>  $types
     * @return list<array{account: LedgerAccount, total: array{0: float, 1: ?float}, rows: list<array{account: LedgerAccount, amount: array{0: float, 1: ?float}}>}>
     */
    private function sections(Collection $now, ?Collection $then, array $types, bool $all): array
    {
        $then = $then?->keyBy('id');
        $sections = [];
        foreach ($now->whereIn('type', $types) as $account) {
            $sign = $account->isDebitNormal() ? 1 : -1;
            $amount = [round($sign * $account->net, 2), $then ? round($sign * (float) ($then[$account->id]->net ?? 0), 2) : null];
            if ($account->depth === 0) {
                $sections[] = ['account' => $account, 'total' => $amount, 'rows' => []];
            } elseif ($sections !== [] && ($all || $amount[0] != 0.0 || ($amount[1] ?? 0) != 0.0)) {
                $sections[count($sections) - 1]['rows'][] = ['account' => $account, 'amount' => $amount];
            }
        }

        return $sections;
    }
}
