<?php

namespace App\Domains\Ledger\Http\Controllers;

use App\Domains\Ledger\Models\JournalLine;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
