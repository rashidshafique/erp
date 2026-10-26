<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Ledger\Models\BankAccount;
use App\Domains\Ledger\Models\JournalLine;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Models\Reconciliation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Matches bank statements to the books. A statement opens on the last finished one's closing balance; ticking the lines that cleared
 * must bring it to the statement's closing balance. Nothing in the journals changes; only which lines are ticked is kept.
 */
class Reconciler
{
    public function __construct(private Ledger $ledger) {}

    /**
     * Bank details on an existing asset account with no sub-accounts, or on a new account added under the group of the bank account set
     * under Ledger accounts.
     *
     * @param  array{bank_name: string, branch?: ?string, account_title?: ?string, account_number?: ?string, iban?: ?string, notes?: ?string}  $details
     */
    public function addBank(array $details, ?string $accountId, ?string $newCode = null): BankAccount
    {
        return DB::transaction(function () use ($details, $accountId, $newCode) {
            if ($accountId) {
                $account = LedgerAccount::postable()->where('type', 'ASSET')->find($accountId)
                    ?? throw new InvalidArgumentException('Choose an asset account with no sub-accounts.');
            } else {
                $group = LedgerSettings::account('bank')->parent ?? throw new InvalidArgumentException('The bank account set under Ledger accounts has no group to add a new account under; choose an existing account.');
                if (! $newCode || LedgerAccount::where('code', $newCode)->exists()) {
                    throw new InvalidArgumentException('Give the new ledger account a code that is not taken.');
                }
                $account = LedgerAccount::create(['code' => $newCode, 'name' => trim($details['bank_name'].' '.($details['account_number'] ?? '')), 'type' => $group->type, 'parent_id' => $group->id]);
            }
            if (BankAccount::where('account_id', $account->id)->exists()) {
                throw new InvalidArgumentException("{$account->label()} already has bank details.");
            }

            return BankAccount::create($details + ['account_id' => $account->id]);
        });
    }

    /** Opens a statement after the last finished one. Only one statement per bank is open at a time. */
    public function start(BankAccount $bank, string $statementDate, float $statementBalance): Reconciliation
    {
        if ($bank->draft()->exists()) {
            throw new InvalidArgumentException('A statement for this bank is already open; finish or discard it first.');
        }
        $last = $bank->lastReconciled()->first();
        if ($last && $statementDate <= $last->statement_date->toDateString()) {
            throw new InvalidArgumentException('The statement must be dated after the last one reconciled, '.$last->statement_date->displayDate().'.');
        }

        return $bank->reconciliations()->create([
            'statement_date' => $statementDate, 'statement_balance' => round($statementBalance, 2),
            'opening_balance' => $last?->statement_balance ?? 0, 'user_id' => auth()->id(),
        ]);
    }

    /**
     * Lines that can be ticked on this statement: those on the bank's account up to the statement date not cleared on another
     * statement, oldest first.
     *
     * @return Collection<int, JournalLine>
     */
    public function candidates(Reconciliation $rec): Collection
    {
        return JournalLine::with('journal')->where('account_id', $rec->bankAccount->account_id)
            ->join('ledger_journals', 'ledger_journals.id', '=', 'ledger_journal_lines.journal_id')
            ->where('ledger_journals.journal_date', '<=', $rec->statement_date->toDateString())
            ->whereNotIn('ledger_journal_lines.id', fn ($q) => $q->select('journal_line_id')->from('ledger_reconciliation_lines')->where('reconciliation_id', '!=', $rec->id))
            ->orderBy('ledger_journals.journal_date')->orderBy('ledger_journals.number')->select('ledger_journal_lines.*')->get();
    }

    /**
     * Keeps the ticked lines, ignoring any that cannot be ticked on this statement.
     *
     * @param  list<string>  $lineIds
     */
    public function tick(Reconciliation $rec, array $lineIds): void
    {
        $this->open($rec);
        $rec->lines()->sync($this->candidates($rec)->pluck('id')->intersect($lineIds)->values()->all());
    }

    /**
     * @return array{opening: float, in: float, out: float, cleared: float, statement: float, difference: float, book: float}
     */
    public function summary(Reconciliation $rec): array
    {
        $ticked = JournalLine::whereIn('id', fn ($q) => $q->select('journal_line_id')->from('ledger_reconciliation_lines')->where('reconciliation_id', $rec->id))->selectRaw('coalesce(sum(debit), 0) d, coalesce(sum(credit), 0) c')->first();
        $cleared = round($rec->opening_balance + (float) $ticked->d - (float) $ticked->c, 2);

        return [
            'opening' => $rec->opening_balance, 'in' => round((float) $ticked->d, 2), 'out' => round((float) $ticked->c, 2), 'cleared' => $cleared,
            'statement' => $rec->statement_balance, 'difference' => round($rec->statement_balance - $cleared, 2),
            'book' => $rec->bankAccount->account->balance($rec->statement_date->toDateString()),
        ];
    }

    public function finish(Reconciliation $rec): void
    {
        $this->open($rec);
        $difference = $this->summary($rec)['difference'];
        if ($difference != 0.0) {
            throw new InvalidArgumentException('The ticked lines are '.number_format(abs($difference), 2).' away from the statement balance.');
        }
        $rec->update(['status' => 'DONE', 'completed_at' => now()]);
    }

    /** Only the bank's latest finished statement can be opened again, and only while no other is open. */
    public function reopen(Reconciliation $rec): void
    {
        $bank = $rec->bankAccount;
        if (! $rec->isDone() || $bank->lastReconciled()->value('id') !== $rec->id) {
            throw new InvalidArgumentException('Only the latest finished statement can be opened again.');
        }
        if ($bank->draft()->exists()) {
            throw new InvalidArgumentException('Another statement for this bank is open; finish or discard it first.');
        }
        $rec->update(['status' => 'DRAFT', 'completed_at' => null]);
    }

    public function discard(Reconciliation $rec): void
    {
        $this->open($rec);
        $rec->delete();
    }

    /**
     * Posts a bank charge, interest or other line seen on the statement but not in the books, and ticks it.
     *
     * @param  array{date: string, amount: float, direction: string, account_id: string, memo: string}  $entry
     */
    public function postStatementLine(Reconciliation $rec, array $entry): void
    {
        $this->open($rec);
        if ($entry['date'] > $rec->statement_date->toDateString()) {
            throw new InvalidArgumentException('The line must be dated on or before the statement date.');
        }
        $bank = $rec->bankAccount->account_id;
        $in = $entry['direction'] === 'in';
        $amount = round($entry['amount'], 2);
        DB::transaction(function () use ($rec, $entry, $bank, $in, $amount) {
            $journal = $this->ledger->post($entry['date'], [
                ['account_id' => $bank, 'debit' => $in ? $amount : 0, 'credit' => $in ? 0 : $amount],
                ['account_id' => $entry['account_id'], 'debit' => $in ? 0 : $amount, 'credit' => $in ? $amount : 0],
            ], null, $entry['memo']);
            $rec->lines()->attach($journal->lines->firstWhere('account_id', $bank)->id);
        });
    }

    private function open(Reconciliation $rec): void
    {
        if ($rec->isDone()) {
            throw new InvalidArgumentException('This statement is finished; open it again to change it.');
        }
    }
}
