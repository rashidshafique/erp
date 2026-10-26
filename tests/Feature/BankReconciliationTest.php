<?php

namespace Tests\Feature;

use App\Domains\Ledger\Models\BankAccount;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\JournalLine;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Models\Reconciliation;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        $this->post(route('ledger.banks.store'), ['bank_name' => 'Meezan Bank', 'account_number' => '0101-555', 'account_id' => $this->account('1121')->id])->assertSessionHasNoErrors();
        $this->bank = BankAccount::sole();
    }

    private function account(string $code): LedgerAccount
    {
        return LedgerAccount::where('code', $code)->firstOrFail();
    }

    /** Money received into the main bank account on a date. */
    private function receipt(string $date, float $amount): JournalLine
    {
        $customer = Customer::firstOrCreate(['code' => 'ACME'], ['name' => 'Acme Pumps']);
        $this->post(route('receipts.store'), ['customer_id' => $customer->id, 'received_on' => $date, 'amount' => $amount, 'method' => 'Bank transfer'])->assertSessionHasNoErrors();

        return JournalLine::where('account_id', $this->account('1121')->id)->latest('created_at')->get()->firstWhere('debit', $amount);
    }

    private function start(string $date, float $balance): Reconciliation
    {
        $this->post(route('ledger.banks.statements.store', $this->bank), ['statement_date' => $date, 'statement_balance' => $balance])->assertSessionHasNoErrors();

        return Reconciliation::where('status', 'DRAFT')->sole();
    }

    public function test_a_statement_finishes_only_when_the_ticked_lines_reach_its_balance_and_the_next_opens_on_it(): void
    {
        $a = $this->receipt('2026-10-02', 1000);
        $b = $this->receipt('2026-10-20', 250);
        $later = $this->receipt('2026-11-03', 75);
        $rec = $this->start('2026-10-31', 1000);

        $this->get(route('ledger.reconciliations.show', $rec))->assertOk()->assertSee($a->journal->number)->assertSee($b->journal->number)->assertDontSee($later->journal->number);
        $this->put(route('ledger.reconciliations.update', $rec), ['lines' => [$a->id, $b->id], 'finish' => 1])->assertSessionHasErrors('statement');
        $this->assertSame('DRAFT', $rec->fresh()->status);

        $this->put(route('ledger.reconciliations.update', $rec), ['lines' => [$a->id], 'finish' => 1])->assertSessionHasNoErrors();

        $this->assertSame('DONE', $rec->fresh()->status);
        $this->post(route('ledger.banks.statements.store', $this->bank), ['statement_date' => '2026-10-31', 'statement_balance' => 0])->assertSessionHasErrors('statement');
        $next = $this->start('2026-11-30', 1325);
        $this->assertSame(1000.0, $next->opening_balance);
        $this->get(route('ledger.reconciliations.show', $next))->assertOk()->assertDontSee($a->journal->number)->assertSee($b->journal->number)->assertSee($later->journal->number);
        $this->put(route('ledger.reconciliations.update', $next), ['lines' => [$a->id, $b->id, $later->id], 'finish' => 1])->assertSessionHasNoErrors();
        $this->assertSame([$b->id, $later->id], $next->lines()->orderBy('debit', 'desc')->pluck('ledger_journal_lines.id')->all());
    }

    public function test_a_bank_charge_on_the_statement_is_posted_against_the_bank_and_ticked(): void
    {
        $a = $this->receipt('2026-10-02', 1000);
        $rec = $this->start('2026-10-31', 985);

        $this->post(route('ledger.reconciliations.line', $rec), ['date' => '2026-10-31', 'direction' => 'out', 'amount' => 15, 'account_id' => $this->account('6800')->id, 'memo' => 'Bank charges', 'lines' => [$a->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([985.0, 15.0], [$this->account('1121')->balance(), $this->account('6800')->balance()]);
        $this->put(route('ledger.reconciliations.update', $rec), ['lines' => $rec->lines()->pluck('ledger_journal_lines.id')->all(), 'finish' => 1])->assertSessionHasNoErrors();
        $this->assertSame('DONE', $rec->fresh()->status);
    }

    public function test_only_the_latest_finished_statement_reopens_and_discarding_a_draft_leaves_the_books_alone(): void
    {
        $a = $this->receipt('2026-10-02', 1000);
        $first = $this->start('2026-10-31', 1000);
        $this->put(route('ledger.reconciliations.update', $first), ['lines' => [$a->id], 'finish' => 1])->assertSessionHasNoErrors();
        $second = $this->start('2026-11-30', 1000);
        $this->put(route('ledger.reconciliations.update', $second), ['finish' => 1])->assertSessionHasNoErrors();

        $this->post(route('ledger.reconciliations.reopen', $first))->assertSessionHasErrors('statement');
        $this->post(route('ledger.reconciliations.reopen', $second))->assertSessionHasNoErrors();
        $this->assertSame('DRAFT', $second->fresh()->status);

        $journals = Journal::count();
        $this->delete(route('ledger.reconciliations.destroy', $second))->assertSessionHasNoErrors();

        $this->assertModelMissing($second);
        $this->assertSame([$journals, 1000.0], [Journal::count(), $this->account('1121')->balance()]);
        $this->get(route('ledger.banks.index'))->assertOk()->assertSee('Meezan Bank');
        $this->get(route('ledger.banks.show', $this->bank))->assertOk()->assertSee('Reconciled');
    }

    public function test_a_bank_can_be_added_on_a_new_ledger_account_under_the_bank_group_but_not_twice_on_one_account(): void
    {
        $this->post(route('ledger.banks.store'), ['bank_name' => 'HBL', 'account_number' => '7788', 'new_code' => '1122'])->assertSessionHasNoErrors();
        $this->post(route('ledger.banks.store'), ['bank_name' => 'HBL again', 'account_id' => $this->account('1121')->id])->assertSessionHasErrors('bank');

        $new = $this->account('1122');
        $this->assertSame(['HBL 7788', '1120', 'ASSET'], [$new->name, $new->parent->code, $new->type]);
        $this->assertSame(2, BankAccount::count());
    }
}
