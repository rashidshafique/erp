<?php

namespace App\Domains\Ledger\Http\Controllers;

use App\Domains\Ledger\Models\JournalLine;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\Statements;
use App\Domains\Ledger\Services\TrialBalance;
use App\Domains\Ledger\Services\Vouchers;
use App\Support\FinancialYear;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LedgerReportController extends Controller
{
    /**
     * Trial balance by category, section and account for a month or a whole financial year (design 19e). `fy` is the year the
     * financial year starts in, `period` a month number or "year", `level` categories, sections or accounts.
     */
    public function trialBalance(Request $request, TrialBalance $tb): View|StreamedResponse
    {
        $current = FinancialYear::containing();
        $fy = (int) $request->query('fy', $current['start']->year);
        $year = FinancialYear::containing(Carbon::create($fy, FinancialYear::startMonth(), 1));
        $period = $request->query('period', $fy === $current['start']->year ? (string) today()->month : 'year');
        if ($period === 'year' || ! ctype_digit((string) $period) || (int) $period < 1 || (int) $period > 12) {
            [$period, $from, $to] = ['year', $year['start'], $year['end']];
        } else {
            $month = (int) $period;
            $from = Carbon::create($month >= $year['start']->month ? $year['start']->year : $year['start']->year + 1, $month, 1);
            $to = $from->copy()->endOfMonth();
        }
        if ($request->filled('as_at')) {
            [$period, $from, $to] = ['year', $year['start'], $request->date('as_at')];
            $year = FinancialYear::containing($to);
            $from = $year['start'];
        }
        $level = in_array($request->query('level'), ['categories', 'sections', 'accounts'], true) ? $request->query('level') : 'sections';
        $data = $tb->build($from->toDateString(), $to->toDateString());
        $zero = $request->boolean('zero');
        if ($request->boolean('export')) {
            return $this->trialBalanceCsv($data);
        }
        $months = collect(range(0, 11))->map(fn ($i) => $year['start']->copy()->addMonths($i))->mapWithKeys(fn ($d) => [(string) $d->month => $d->format('M Y')])->all();
        $fys = collect(range(0, 4))->map(fn ($i) => $current['start']->year - $i)->mapWithKeys(fn ($y) => [$y => Vouchers::yearLabel(Carbon::create($y, FinancialYear::startMonth(), 1))])->all();

        return view('ledger::reports.trial-balance', $data + [
            'fy' => $fy, 'fys' => $fys, 'period' => $period, 'months' => $months, 'level' => $level, 'zero' => $zero,
            'balanced' => round($data['totals']['debit'] - $data['totals']['credit'], 2) == 0.0 && round($data['totals']['dr'] - $data['totals']['cr'], 2) == 0.0,
        ]);
    }

    /** @param array{rows: list<array<string, mixed>>, totals: array<string, float>, from: string, to: string} $data */
    private function trialBalanceCsv(array $data): StreamedResponse
    {
        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Account', 'Level', 'Opening', 'Debit', 'Credit', 'Closing Dr', 'Closing Cr']);
            $row = fn (array $r, string $level) => fputcsv($out, [$r['account']?->code, $r['account']?->name ?? $r['name'] ?? '', $level, number_format($r['opening'], 2, '.', ''), number_format($r['debit'], 2, '.', ''), number_format($r['credit'], 2, '.', ''), $r['closing'] > 0 ? number_format($r['closing'], 2, '.', '') : '', $r['closing'] < 0 ? number_format(-$r['closing'], 2, '.', '') : '']);
            foreach ($data['rows'] as $c) {
                $row($c, 'Category');
                foreach ($c['sections'] as $s) {
                    $row($s, 'Section');
                    foreach ($s['accounts'] as $a) {
                        $row($a, 'Account');
                    }
                }
            }
            fclose($out);
        }, 'trial-balance-'.$data['from'].'-'.$data['to'].'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Income less expenses over a period, optionally beside the period before or the same period a year earlier. */
    public function profitAndLoss(Request $request, Statements $statements): View
    {
        $from = ($request->date('from') ?? \App\Support\FinancialYear::containing()['start'])->toDateString();
        $to = max($from, ($request->date('to') ?? today())->toDateString());
        $compareWith = in_array($request->query('compare'), ['previous', 'last_year'], true) ? $request->query('compare') : null;
        $compare = match ($compareWith) {
            'previous' => [Carbon::parse($from)->subDays((int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1)->toDateString(), Carbon::parse($from)->subDay()->toDateString()],
            'last_year' => [Carbon::parse($from)->subYearNoOverflow()->toDateString(), Carbon::parse($to)->subYearNoOverflow()->toDateString()],
            default => null,
        };

        return view('ledger::reports.profit-and-loss', ['from' => $from, 'to' => $to, 'compareWith' => $compareWith, 'compare' => $compare]
            + $statements->profitAndLoss($from, $to, $compare, $request->boolean('all')));
    }

    /** What the business owns against what it owes and its equity, as at a date and optionally another date beside it. */
    public function balanceSheet(Request $request, Statements $statements): View
    {
        $asAt = ($request->date('as_at') ?? today())->toDateString();
        $compareAt = $request->date('compare_at')?->toDateString();

        return view('ledger::reports.balance-sheet', ['asAt' => $asAt, 'compareAt' => $compareAt] + $statements->balanceSheet($asAt, $compareAt, $request->boolean('all')));
    }

    /** Every account's opening balance, postings and closing balance over a period, one after the other. */
    public function generalLedger(Request $request, Statements $statements): View
    {
        $from = ($request->date('from') ?? today()->startOfMonth())->toDateString();
        $to = max($from, ($request->date('to') ?? today())->toDateString());
        $type = array_key_exists((string) $request->query('type'), LedgerAccount::TYPES) ? $request->query('type') : null;

        return view('ledger::reports.general-ledger', ['from' => $from, 'to' => $to, 'type' => $type, 'accounts' => $statements->generalLedger($from, $to, $type)]);
    }

    /**
     * Ledger statement for an account over a period (design 19d), optionally for one customer or supplier, with its opening, debits,
     * credits and running balance. "Show voucher lines" lists the other side of each voucher under it.
     */
    public function account(Request $request, LedgerAccount $account): View
    {
        $from = ($request->date('from') ?? today()->startOfMonth())->toDateString();
        $to = max($from, ($request->date('to') ?? today())->toDateString());
        $ids = $account->treeIds();
        $parties = JournalLine::whereIn('account_id', $ids)->whereNotNull('party_id')->select('party_type', 'party_id')->distinct()->get();
        $names = \App\Domains\Ledger\Services\DayBook::partyNames($parties);
        [$partyType, $partyId] = array_pad(explode(':', (string) $request->query('party'), 2), 2, null);
        $party = $partyType && $partyId && isset($names[$partyType.':'.$partyId]) ? ['type' => $partyType, 'id' => $partyId, 'name' => $names[$partyType.':'.$partyId]] : null;
        $scope = fn ($q) => $q->whereIn('account_id', $ids)->when($party, fn ($q) => $q->where('party_type', $party['type'])->where('party_id', $party['id']));
        $openingRow = $scope(JournalLine::join('ledger_journals', 'ledger_journals.id', '=', 'ledger_journal_lines.journal_id')->where('journal_date', '<', $from))
            ->selectRaw('coalesce(sum(debit), 0) d, coalesce(sum(credit), 0) c')->first();
        $opening = round((float) $openingRow->d - (float) $openingRow->c, 2);
        $lines = $scope(JournalLine::with(['journal.lines.account', 'account'])
            ->join('ledger_journals', 'ledger_journals.id', '=', 'ledger_journal_lines.journal_id')
            ->whereBetween('ledger_journals.journal_date', [$from, $to]))
            ->orderBy('ledger_journals.journal_date')->orderBy('ledger_journals.number')->select('ledger_journal_lines.*')->get();
        $customer = $party && $party['type'] === 'customer' ? \App\Models\Customer::find($party['id']) : null;

        return view('ledger::reports.account', [
            'account' => $account, 'from' => $from, 'to' => $to, 'opening' => $opening, 'lines' => $lines,
            'parties' => collect($names)->sort()->all(), 'party' => $party, 'customer' => $customer, 'showLines' => $request->boolean('lines'),
            'accounts' => LedgerAccount::whereRaw('length(code) > 5')->orderBy('code')->get(['id', 'code', 'name']),
            'lineParties' => \App\Domains\Ledger\Services\DayBook::partyNames($lines->flatMap(fn ($l) => $l->journal->lines)),
        ]);
    }
}
