<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** The one way into the books. Every journal balances, posts only to active accounts with no sub-accounts, and falls after the closed date. */
class Ledger
{
    public function __construct(private InvoiceService $numbers) {}

    /**
     * Posts a journal. Lines with nothing on them are dropped; lines on the same account, side and party are added together.
     *
     * @param  list<array{account_id: string, debit?: float, credit?: float, description?: ?string, party_type?: ?string, party_id?: ?string}>  $lines
     * @param  array{type: string, id: string}|null  $source
     */
    public function post(string $date, array $lines, ?array $source = null, ?string $memo = null, string $kind = 'POST', ?string $reversesId = null): Journal
    {
        $date = Carbon::parse($date)->toDateString();
        if (($closed = LedgerSettings::closedTo()) && $date <= $closed) {
            throw new InvalidArgumentException('The books are closed to '.Carbon::parse($closed)->displayDate().'; nothing can be posted on '.Carbon::parse($date)->displayDate().'.');
        }
        $merged = [];
        foreach ($lines as $l) {
            $debit = round((float) ($l['debit'] ?? 0), 2);
            $credit = round((float) ($l['credit'] ?? 0), 2);
            if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
                throw new InvalidArgumentException('A journal line is either a debit or a credit, and never below zero.');
            }
            if ($debit == 0 && $credit == 0) {
                continue;
            }
            $key = implode('|', [$l['account_id'], $debit > 0 ? 'D' : 'C', $l['party_type'] ?? '', $l['party_id'] ?? '', $l['description'] ?? '']);
            $merged[$key] ??= ['account_id' => $l['account_id'], 'debit' => 0.0, 'credit' => 0.0, 'party_type' => $l['party_type'] ?? null, 'party_id' => $l['party_id'] ?? null, 'description' => $l['description'] ?? null];
            $merged[$key]['debit'] = round($merged[$key]['debit'] + $debit, 2);
            $merged[$key]['credit'] = round($merged[$key]['credit'] + $credit, 2);
        }
        $debits = round(array_sum(array_column($merged, 'debit')), 2);
        $credits = round(array_sum(array_column($merged, 'credit')), 2);
        if (count($merged) < 2 || $debits <= 0) {
            throw new InvalidArgumentException('A journal needs at least one debit and one credit.');
        }
        if ($debits !== $credits) {
            throw new InvalidArgumentException("Debits ({$debits}) and credits ({$credits}) must be equal.");
        }
        $accounts = LedgerAccount::whereIn('id', array_unique(array_column($merged, 'account_id')))->get()->keyBy('id');
        foreach (array_unique(array_column($merged, 'account_id')) as $id) {
            $account = $accounts[$id] ?? throw new InvalidArgumentException('A journal line names an account that does not exist.');
            if (! $account->is_active) {
                throw new InvalidArgumentException("Account {$account->label()} is switched off.");
            }
            if ($account->isGroup()) {
                throw new InvalidArgumentException("Account {$account->label()} has sub-accounts; post to one of them.");
            }
        }

        return DB::transaction(function () use ($date, $merged, $source, $memo, $kind, $reversesId, $debits) {
            if ($source && Journal::where('source_type', $source['type'])->where('source_id', $source['id'])->where('kind', $kind)->exists()) {
                throw new InvalidArgumentException('This is already in the ledger.');
            }
            $journal = Journal::create([
                'number' => $this->numbers->nextNumber('JV', 'ledger_journals', 'number'), 'journal_date' => $date, 'kind' => $kind,
                'source_type' => $source['type'] ?? null, 'source_id' => $source['id'] ?? null, 'reverses_id' => $reversesId,
                'memo' => $memo ? mb_substr($memo, 0, 300) : null, 'total' => $debits, 'user_id' => auth()->id(),
            ]);
            foreach ($merged as $line) {
                $journal->lines()->create($line);
            }

            return $journal->load('lines');
        });
    }

    /** Posts the mirror of a journal on the given date. A journal is reversed once. */
    public function reverse(Journal $journal, string $date, ?string $memo = null): Journal
    {
        if ($journal->kind === 'REVERSAL') {
            throw new InvalidArgumentException('A reversal cannot itself be reversed; post a new journal instead.');
        }
        if ($journal->reversal()->exists()) {
            throw new InvalidArgumentException("{$journal->number} is already reversed.");
        }
        $lines = $journal->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'party_type' => $l->party_type, 'party_id' => $l->party_id, 'description' => $l->description])->all();
        $source = $journal->source_type ? ['type' => $journal->source_type, 'id' => $journal->source_id] : ['type' => 'journal', 'id' => $journal->id];

        return $this->post($date, $lines, $source, $memo ?? "Reverses {$journal->number}".($journal->memo ? ": {$journal->memo}" : ''), 'REVERSAL', $journal->id);
    }
}
