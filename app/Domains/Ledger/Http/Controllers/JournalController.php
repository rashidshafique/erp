<?php

namespace App\Domains\Ledger\Http\Controllers;

use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Models\Voucher;
use App\Domains\Ledger\Services\DayBook;
use App\Domains\Ledger\Services\Ledger;
use App\Domains\Ledger\Services\Vouchers;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The day book (design 19c), voucher entry (19b), a posted voucher and its print (19h). */
class JournalController extends Controller
{
    /** @return array{q: ?string, from: ?string, to: ?string, type: ?string, source: ?string, status: ?string, account: ?string} */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->query('q'), 'from' => $request->date('from')?->toDateString(), 'to' => $request->date('to')?->toDateString(),
            'type' => array_key_exists((string) $request->query('type'), DayBook::types()) ? $request->query('type') : null,
            'source' => array_key_exists((string) $request->query('source'), Journal::SOURCES + ['manual' => 1]) ? $request->query('source') : null,
            'status' => array_key_exists((string) $request->query('status'), DayBook::STATUSES) ? $request->query('status') : null,
            'account' => $request->filled('account') && Str::isUuid($request->query('account')) ? $request->query('account') : null,
        ];
    }

    public function index(Request $request, DayBook $book): View
    {
        $f = $this->filters($request);
        $rows = $book->rows($f);
        $page = max(1, (int) $request->query('page', 1));
        $paged = new LengthAwarePaginator($rows->forPage($page, 50)->values(), $rows->count(), 50, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('ledger::journals.index', ['rows' => $paged, 'f' => $f, 'count' => $rows->count(), 'drafts' => $rows->where('draft', true)->count(), 'open' => $request->query('open')]);
    }

    public function export(Request $request, DayBook $book): StreamedResponse
    {
        $rows = $book->rows($this->filters($request));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Voucher', 'Type', 'Source', 'Narration', 'Amount', 'Status', 'By']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['date']->toDateString(), $r['number'], $r['type'], $r['source'], $r['narration'], number_format($r['amount'], 2, '.', ''), DayBook::STATUSES[$r['status']], $r['by']]);
            }
            fclose($out);
        }, 'day-book-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    public function create(Request $request): View
    {
        return view('ledger::vouchers.form', ['voucher' => null, 'type' => (string) $request->query('type', 'JV')]);
    }

    public function edit(Voucher $voucher): View
    {
        return view('ledger::vouchers.form', ['voucher' => $voucher, 'type' => $voucher->voucher_type]);
    }

    /** Posts a journal straight from a form (kept for scripts and imports; people use the voucher screen). */
    public function store(Request $request, Ledger $ledger): RedirectResponse
    {
        $d = $request->validate([
            'journal_date' => ['required', 'date'], 'memo' => ['required', 'string', 'max:300'], 'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['nullable', 'uuid'], 'lines.*.debit' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:300'],
        ]);
        $lines = array_values(array_filter($d['lines'], fn ($l) => ! empty($l['account_id'])));
        try {
            $journal = $ledger->post($d['journal_date'], $lines, null, $d['memo'], voucher: ['type' => 'JV']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['journal' => $e->getMessage()])->withInput();
        }

        return redirect()->route('ledger.journals.show', $journal)->with('success', "Journal {$journal->number} posted.");
    }

    public function show(Journal $journal): View
    {
        $journal->load(['lines.account', 'reverses', 'reversal', 'user', 'counterAccount']);

        return view('ledger::journals.show', ['journal' => $journal, 'parties' => DayBook::partyNames($journal->lines)]);
    }

    /** Voucher print, A4 (design 19h). */
    public function print(Journal $journal): View
    {
        $journal->load(['lines.account', 'user', 'counterAccount']);
        $lines = $journal->lines->map(fn ($l) => ['account' => $l->account, 'party' => null, 'party_type' => $l->party_type, 'party_id' => $l->party_id, 'detail' => $l->description, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit]);

        return $this->printView($journal->number, $journal->typeLabel(), $journal->journal_date->toDateString(), $journal->reference, $journal->counterAccount, $lines->all(), $journal->user?->name, false, $journal->lines);
    }

    public function printDraft(Voucher $voucher): View
    {
        $accounts = LedgerAccount::whereIn('id', array_column($voucher->lines, 'account_id'))->get()->keyBy('id');
        $lines = array_map(fn ($l) => ['account' => $accounts[$l['account_id']] ?? null, 'detail' => $l['narration'] ?? null, 'debit' => (float) ($l['debit'] ?? 0), 'credit' => (float) ($l['credit'] ?? 0)], $voucher->lines);
        if ($voucher->counterAccount) {
            $net = array_sum(array_column($lines, 'debit')) - array_sum(array_column($lines, 'credit'));
            $lines[] = ['account' => $voucher->counterAccount, 'detail' => $voucher->reference, 'debit' => $net < 0 ? -$net : 0.0, 'credit' => $net > 0 ? $net : 0.0];
        }

        return $this->printView($voucher->number, $voucher->voucher_type, $voucher->voucher_date->toDateString(), $voucher->reference, $voucher->counterAccount, $lines, $voucher->user?->name, true, collect());
    }

    /**
     * @param  list<array{account: ?LedgerAccount, detail: ?string, debit: float, credit: float}>  $lines
     * @param  \Illuminate\Support\Collection<int, \App\Domains\Ledger\Models\JournalLine>  $journalLines
     */
    private function printView(string $number, string $type, string $date, ?string $reference, ?LedgerAccount $counter, array $lines, ?string $by, bool $draft, $journalLines): View
    {
        $parties = DayBook::partyNames($journalLines);
        foreach ($lines as $i => $l) {
            $key = ($l['party_type'] ?? '').':'.($l['party_id'] ?? '');
            $lines[$i]['party'] = $parties[$key] ?? null;
        }
        $paidTo = collect($lines)->pluck('party')->filter()->first();
        $bank = $counter ? \App\Domains\Ledger\Models\BankAccount::where('account_id', $counter->id)->first() : null;

        return view('ledger::vouchers.print', [
            'number' => $number, 'type' => $type, 'typeName' => Vouchers::TYPES[$type]['name'] ?? $type, 'date' => \Illuminate\Support\Carbon::parse($date),
            'reference' => $reference, 'counter' => $counter, 'bank' => $bank, 'lines' => $lines, 'by' => $by, 'draft' => $draft, 'paidTo' => $paidTo,
            'side' => Vouchers::TYPES[$type]['side'] ?? null,
        ]);
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
