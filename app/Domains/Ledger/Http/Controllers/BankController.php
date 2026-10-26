<?php

namespace App\Domains\Ledger\Http\Controllers;

use App\Domains\Ledger\Models\BankAccount;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Models\Reconciliation;
use App\Domains\Ledger\Services\Reconciler;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** Bank accounts and their statements, ticked off against the ledger. */
class BankController extends Controller
{
    public function index(): View
    {
        $banks = BankAccount::with(['account', 'lastReconciled', 'draft'])->get()->sortBy(fn ($b) => $b->account->code)->values();
        foreach ($banks as $bank) {
            $bank->book = $bank->account->balance();
            $bank->uncleared = $bank->unclearedLines()->count();
        }

        return view('ledger::banks.index', [
            'banks' => $banks,
            'accounts' => LedgerAccount::postable()->where('type', 'ASSET')->whereNotIn('id', BankAccount::select('account_id'))->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, Reconciler $reconciler): RedirectResponse
    {
        $d = $this->validated($request) + $request->validate(['account_id' => ['nullable', 'uuid'], 'new_code' => ['nullable', 'string', 'max:20']]);
        try {
            $bank = $reconciler->addBank(collect($d)->except(['account_id', 'new_code'])->all(), $d['account_id'] ?? null, $d['new_code'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bank' => $e->getMessage()])->withInput();
        }

        return redirect()->route('ledger.banks.show', $bank)->with('success', "{$bank->bank_name} added.");
    }

    public function show(BankAccount $bank): View
    {
        $bank->load(['account', 'reconciliations.user']);

        return view('ledger::banks.show', [
            'bank' => $bank, 'book' => $bank->account->balance(), 'uncleared' => $bank->unclearedLines()->count(),
            'last' => $bank->lastReconciled()->first(), 'draft' => $bank->draft()->first(),
        ]);
    }

    public function update(Request $request, BankAccount $bank): RedirectResponse
    {
        $bank->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Bank details saved.');
    }

    public function startStatement(Request $request, BankAccount $bank, Reconciler $reconciler): RedirectResponse
    {
        $d = $request->validate(['statement_date' => ['required', 'date'], 'statement_balance' => ['required', 'numeric']]);
        try {
            $rec = $reconciler->start($bank, $request->date('statement_date')->toDateString(), (float) $d['statement_balance']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['statement' => $e->getMessage()])->withInput();
        }

        return redirect()->route('ledger.reconciliations.show', $rec);
    }

    public function statement(Reconciliation $reconciliation, Reconciler $reconciler): View
    {
        $reconciliation->load(['bankAccount.account', 'user']);

        return view('ledger::banks.reconcile', [
            'rec' => $reconciliation, 'lines' => $reconciliation->isDone() ? $reconciliation->lines()->with('journal')->get()->sortBy(fn ($l) => $l->journal->journal_date)->values() : $reconciler->candidates($reconciliation),
            'ticked' => $reconciliation->lines()->pluck('ledger_journal_lines.id')->all(), 'summary' => $reconciler->summary($reconciliation),
            'accounts' => LedgerAccount::postable()->whereKeyNot($reconciliation->bankAccount->account_id)->orderBy('code')->get(),
        ]);
    }

    /** Saves the ticks, and finishes the statement when asked and the difference is nil. */
    public function tick(Request $request, Reconciliation $reconciliation, Reconciler $reconciler): RedirectResponse
    {
        $d = $request->validate(['lines' => ['array'], 'lines.*' => ['uuid']]);
        try {
            $reconciler->tick($reconciliation, $d['lines'] ?? []);
            if ($request->boolean('finish')) {
                $reconciler->finish($reconciliation);

                return redirect()->route('ledger.banks.show', $reconciliation->bank_account_id)->with('success', 'Statement to '.$reconciliation->statement_date->displayDate().' reconciled.');
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['statement' => $e->getMessage()]);
        }

        return back()->with('success', 'Ticks saved.');
    }

    public function statementLine(Request $request, Reconciliation $reconciliation, Reconciler $reconciler): RedirectResponse
    {
        $d = $request->validate([
            'date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'gt:0'], 'direction' => ['required', 'in:in,out'],
            'account_id' => ['required', 'uuid'], 'memo' => ['required', 'string', 'max:300'], 'lines' => ['array'], 'lines.*' => ['uuid'],
        ]);
        try {
            $reconciler->tick($reconciliation, $d['lines'] ?? []);
            $reconciler->postStatementLine($reconciliation, ['date' => $request->date('date')->toDateString(), 'amount' => (float) $d['amount']] + $d);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['statement_line' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Posted and ticked.');
    }

    public function reopen(Reconciliation $reconciliation, Reconciler $reconciler): RedirectResponse
    {
        try {
            $reconciler->reopen($reconciliation);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['statement' => $e->getMessage()]);
        }

        return redirect()->route('ledger.reconciliations.show', $reconciliation);
    }

    public function discard(Reconciliation $reconciliation, Reconciler $reconciler): RedirectResponse
    {
        try {
            $reconciler->discard($reconciliation);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['statement' => $e->getMessage()]);
        }

        return redirect()->route('ledger.banks.show', $reconciliation->bank_account_id)->with('success', 'Statement discarded; nothing in the books changed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'bank_name' => ['required', 'string', 'max:100'], 'branch' => ['nullable', 'string', 'max:100'], 'account_title' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'], 'iban' => ['nullable', 'string', 'max:40'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
