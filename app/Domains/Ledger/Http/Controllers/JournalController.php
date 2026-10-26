<?php

namespace App\Domains\Ledger\Http\Controllers;

use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\Ledger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class JournalController extends Controller
{
    public function index(Request $request): View
    {
        $source = array_key_exists((string) $request->query('source'), Journal::SOURCES + ['manual' => 1]) ? $request->query('source') : null;
        $accountId = $request->filled('account') && Str::isUuid($request->query('account')) ? $request->query('account') : null;
        $journals = Journal::query()
            ->when($request->date('from'), fn ($q, $d) => $q->where('journal_date', '>=', $d->toDateString()))
            ->when($request->date('to'), fn ($q, $d) => $q->where('journal_date', '<=', $d->toDateString()))
            ->when($source === 'manual', fn ($q) => $q->whereNull('source_type'))
            ->when($source && $source !== 'manual', fn ($q) => $q->where('source_type', $source))
            ->when($accountId, fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('account_id', $accountId)))
            ->orderByDesc('journal_date')->orderByDesc('number')->paginate(50)->withQueryString();

        return view('ledger::journals.index', ['journals' => $journals, 'source' => $source, 'accountId' => $accountId, 'accounts' => LedgerAccount::postable()->orderBy('code')->get()]);
    }

    public function create(): View
    {
        return view('ledger::journals.create', ['accounts' => LedgerAccount::postable()->orderBy('code')->get()]);
    }

    public function store(Request $request, Ledger $ledger): RedirectResponse
    {
        $d = $request->validate([
            'journal_date' => ['required', 'date'], 'memo' => ['required', 'string', 'max:300'], 'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['nullable', 'uuid'], 'lines.*.debit' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:300'],
        ]);
        $lines = array_values(array_filter($d['lines'], fn ($l) => ! empty($l['account_id'])));
        try {
            $journal = $ledger->post($d['journal_date'], $lines, null, $d['memo']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['journal' => $e->getMessage()])->withInput();
        }

        return redirect()->route('ledger.journals.show', $journal)->with('success', "Journal {$journal->number} posted.");
    }

    public function show(Journal $journal): View
    {
        $journal->load(['lines.account', 'reverses', 'reversal', 'user']);

        return view('ledger::journals.show', ['journal' => $journal]);
    }

    /** Only a manual journal is reversed here; a document's journal is reversed by voiding the document. */
    public function reverse(Request $request, Journal $journal, Ledger $ledger): RedirectResponse
    {
        $d = $request->validate(['reverse_on' => ['required', 'date']]);
        if ($journal->source_type !== null) {
            return back()->withErrors(['journal' => 'This journal was posted by a document; void the document to reverse it.']);
        }
        try {
            $reversal = $ledger->reverse($journal->load('lines'), $d['reverse_on']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['journal' => $e->getMessage()]);
        }

        return redirect()->route('ledger.journals.show', $reversal)->with('success', "{$journal->number} reversed by {$reversal->number}.");
    }
}
