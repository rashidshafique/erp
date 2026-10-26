<?php

namespace App\Domains\Ledger\Http\Controllers;

use App\Domains\Ledger\Models\JournalLine;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\Statements;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LedgerReportController extends Controller
{
    /** Every account's balance as at a date, debits and credits side by side; they always agree. */
    public function trialBalance(Request $request): View
    {
        $asAt = ($request->date('as_at') ?? today())->toDateString();
        $accounts = LedgerAccount::tree($asAt);
        $posting = $accounts->where('is_group', false);

        return view('ledger::reports.trial-balance', [
            'asAt' => $asAt, 'accounts' => $accounts->filter(fn ($a) => $a->net != 0.0 || $request->boolean('all'))->values(),
            'debits' => round($posting->where('net', '>', 0)->sum('net'), 2), 'credits' => round(-$posting->where('net', '<', 0)->sum('net'), 2),
        ]);
    }

    /** Income less expenses over a period, optionally beside the period before or the same period a year earlier. */
    public function profitAndLoss(Request $request, Statements $statements): View
    {
        $from = ($request->date('from') ?? today()->startOfYear())->toDateString();
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

    /** An account's postings over a period with its opening and running balance; a group shows its sub-accounts together. */
    public function account(Request $request, LedgerAccount $account): View
    {
        $from = ($request->date('from') ?? today()->startOfYear())->toDateString();
        $to = ($request->date('to') ?? today())->toDateString();
        $opening = $account->balance(\Illuminate\Support\Carbon::parse($from)->subDay()->toDateString());
        $lines = JournalLine::with(['journal', 'account'])->whereIn('account_id', $account->treeIds())
            ->join('ledger_journals', 'ledger_journals.id', '=', 'ledger_journal_lines.journal_id')
            ->whereBetween('ledger_journals.journal_date', [$from, $to])
            ->orderBy('ledger_journals.journal_date')->orderBy('ledger_journals.number')->select('ledger_journal_lines.*')->get();

        return view('ledger::reports.account', ['account' => $account, 'from' => $from, 'to' => $to, 'opening' => $opening, 'lines' => $lines]);
    }
}
