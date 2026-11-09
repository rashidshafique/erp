<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Cheque;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Finance\Services\FinanceRules;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Finance\Services\Receivables;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Purchasing\Models\Supplier;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PostDatedChequeTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 10:00');
        $this->admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($this->admin);
        $this->customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
    }

    /** @return array<string, float> account code => debits less credits, for accounts that moved */
    private function balances(): array
    {
        return LedgerAccount::tree()->where('is_group', false)->filter(fn ($a) => $a->net != 0.0)->mapWithKeys(fn ($a) => [$a->code => $a->net])->sortKeys()->all();
    }

    private function account(string $code): LedgerAccount
    {
        return LedgerAccount::where('code', $code)->firstOrFail();
    }

    private function invoice(float $amount): Invoice
    {
        return app(InvoiceService::class)->issue(app(InvoiceService::class)->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => '2026-11-01'],
            [['description' => 'Pump casing', 'quantity' => 1, 'unit_price' => $amount]]));
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
    }

    private function bill(Supplier $supplier, float $amount): SupplierBill
    {
        $this->post(route('bills.store'), ['supplier_id' => $supplier->id, 'bill_date' => '2026-11-01', 'supplier_bill_number' => 'S-'.$amount,
            'lines' => [['description' => 'Pig iron', 'quantity' => 1, 'unit_price' => $amount, 'tax_percent' => 0]]])->assertSessionHasNoErrors();
        $bill = SupplierBill::latest()->firstOrFail();
        $this->post(route('bills.post', $bill))->assertSessionHasNoErrors();

        return $bill->fresh();
    }

    /** @param array<string, float> $alloc */
    private function receiveCheque(float $amount, array $alloc, string $date = '2026-11-20')
    {
        return $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => '2026-11-09', 'amount' => $amount,
            'account_id' => $this->account('22-01-04')->id, 'method' => 'Cheque', 'reference' => 'CHQ 004512', 'cheque_date' => $date, 'drawn_on' => 'Meezan Gulberg', 'alloc' => $alloc]);
    }

    public function test_held_cheque_leaves_the_invoice_unpaid_until_it_clears_into_the_bank_on_or_after_its_date(): void
    {
        $invoice = $this->invoice(500);

        $cheque = null;
        $this->receiveCheque(500, [$invoice->id => 500])->assertRedirect(route('cheques.show', $cheque = Cheque::sole()));

        $this->assertSame(['RECEIVED', 'HOLD', 'IN_HAND', 'CHQ 004512', 'Meezan Gulberg'], [$cheque->direction, $cheque->mode, $cheque->status, $cheque->cheque_number, $cheque->drawn_on]);
        $this->assertSame(0, Receipt::count());
        $this->assertSame(500.0, $invoice->fresh()->balance());
        $this->assertSame(['22-02-01' => 500.0, '41-01-01' => -500.0], $this->balances());
        $this->get(route('cheques.received', ['tab' => 'later']))->assertOk()->assertSee('CHQ 004512')->assertSee('Acme Pumps')->assertSee('in 11 days');
        $this->get(route('cheques.show', $cheque))->assertOk()->assertSee('Held; invoices stay unpaid');

        $this->post(route('cheques.clear', $cheque), ['date' => '2026-11-19'])->assertSessionHasErrors('cheque');

        Carbon::setTestNow('2026-11-21 09:00');
        $this->post(route('cheques.clear', $cheque), ['date' => '2026-11-21'])->assertSessionHasNoErrors();

        $receipt = Receipt::sole();
        $this->assertSame(['CLEARED', $receipt->id], [$cheque->fresh()->status, $cheque->fresh()->receipt_id]);
        $this->assertSame(['2026-11-21', $this->account('22-01-04')->id, 'CHQ 004512', $cheque->id], [$receipt->received_on->toDateString(), $receipt->account_id, $receipt->reference, $receipt->cheque_id]);
        $this->assertSame(0.0, $invoice->fresh()->balance());
        $this->assertSame(['22-01-04' => 500.0, '41-01-01' => -500.0], $this->balances());
        $this->assertSame('BRV', Journal::where('source_type', 'receipt')->sole()->voucher_type);
    }

    public function test_a_held_cheque_only_settles_what_the_invoices_still_owe_when_it_clears(): void
    {
        $invoice = $this->invoice(500);
        $this->receiveCheque(500, [$invoice->id => 500]);
        $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => '2026-11-10', 'amount' => 200, 'method' => 'Cash', 'alloc' => [$invoice->id => 200]]);

        Carbon::setTestNow('2026-11-20 09:00');
        $this->post(route('cheques.clear', Cheque::sole()), ['date' => '2026-11-20'])->assertSessionHasNoErrors();

        $receipt = Cheque::sole()->receipt;
        $this->assertSame([300.0, 200.0], [(float) $receipt->allocations()->sum('amount'), $receipt->unallocated()]);
    }

    public function test_a_held_cheque_that_bounces_before_it_clears_changes_nothing(): void
    {
        $invoice = $this->invoice(500);
        $this->receiveCheque(500, [$invoice->id => 500]);

        $this->post(route('cheques.bounce', Cheque::sole()), ['date' => '2026-11-21', 'reason' => 'Insufficient funds'])->assertSessionHasNoErrors();

        $this->assertSame(['BOUNCED', 'Insufficient funds'], [Cheque::sole()->status, Cheque::sole()->bounce_reason]);
        $this->assertSame([0, 500.0], [Receipt::count(), $invoice->fresh()->balance()]);
        $this->get(route('cheques.received', ['tab' => 'bounced']))->assertSee('CHQ 004512')->assertSee('Insufficient funds');
    }

    public function test_a_settled_cheque_pays_the_invoice_at_once_through_cheques_in_hand_and_a_bounce_after_clearing_undoes_it_all(): void
    {
        AppSetting::setValue(FinanceRules::PDC_RECEIVED, 'SETTLE');
        $invoice = $this->invoice(500);

        $this->receiveCheque(500, [$invoice->id => 500])->assertSessionHasNoErrors();

        $cheque = Cheque::sole();
        $receipt = Receipt::sole();
        $this->assertSame(['SETTLE', $receipt->id, '2026-11-09'], [$cheque->mode, $cheque->receipt_id, $receipt->received_on->toDateString()]);
        $this->assertSame(0.0, $invoice->fresh()->balance());
        $this->assertSame(['22-02-04' => 500.0, '41-01-01' => -500.0], $this->balances());
        $this->get(route('receipts.show', $receipt))->assertSee('Post-dated cheques in hand')->assertSee('Paid by post-dated cheque');

        Carbon::setTestNow('2026-11-20 09:00');
        $this->post(route('cheques.clear', $cheque), ['date' => '2026-11-20'])->assertSessionHasNoErrors();
        $this->assertSame(['22-01-04' => 500.0, '41-01-01' => -500.0], $this->balances());
        $clearing = Journal::where('source_type', 'cheque')->sole();
        $this->assertSame(['2026-11-20', 'BRV', 'CHQ 004512'], [$clearing->journal_date->toDateString(), $clearing->voucher_type, $clearing->reference]);

        $this->post(route('cheques.bounce', $cheque), ['date' => '2026-11-22', 'reason' => 'Signature differs'])->assertSessionHasNoErrors();

        $this->assertSame(['22-02-01' => 500.0, '41-01-01' => -500.0], $this->balances());
        $this->assertSame(500.0, $invoice->fresh()->balance());
        $this->assertTrue($receipt->fresh()->isVoided());
        $this->assertSame(0.0, app(Receivables::class)->onAccount($this->customer->id));
        $statement = app(Receivables::class)->statement($this->customer);
        $this->assertSame(500.0, $statement['balance']);
        $this->get(route('receipts.index'))->assertSee('Bounced');
    }

    public function test_a_settled_cheque_whose_money_on_account_went_on_a_later_invoice_bounces_back_to_both_invoices_owed(): void
    {
        AppSetting::setValue(FinanceRules::PDC_RECEIVED, 'SETTLE');
        $first = $this->invoice(300);
        $this->receiveCheque(500, [$first->id => 300]);
        $second = $this->invoice(150);
        $this->post(route('receipts.allocate', Receipt::sole()), ['alloc' => [$second->id => 150]])->assertSessionHasNoErrors();
        $this->assertSame(['12-06-02' => -50.0, '22-02-04' => 500.0, '41-01-01' => -450.0], $this->balances());

        $this->post(route('cheques.bounce', Cheque::sole()), ['date' => '2026-11-12'])->assertSessionHasNoErrors();

        $this->assertSame(['22-02-01' => 450.0, '41-01-01' => -450.0], $this->balances());
        $this->assertSame([300.0, 150.0], [$first->fresh()->balance(), $second->fresh()->balance()]);
    }

    public function test_a_cheque_given_to_a_supplier_settles_the_bill_through_cheques_issued_and_clears_out_of_the_bank(): void
    {
        AppSetting::setValue(FinanceRules::PDC_ISSUED, 'SETTLE');
        $supplier = $this->supplier();
        $bill = $this->bill($supplier, 400);

        $this->post(route('supplier-payments.store'), ['supplier_id' => $supplier->id, 'paid_on' => '2026-11-09', 'amount' => 400, 'account_id' => $this->account('22-01-03')->id,
            'method' => 'Cheque', 'reference' => '771203', 'cheque_date' => '2026-11-30', 'alloc' => [$bill->id => 400]])->assertRedirect(route('cheques.show', Cheque::sole()));

        $this->assertSame(0.0, $bill->fresh()->balance());
        $this->assertSame(['12-01-03' => -400.0, '61-02-01' => 400.0], $this->balances());

        Carbon::setTestNow('2026-11-30 09:00');
        $this->post(route('cheques.clear', Cheque::sole()), ['date' => '2026-11-30'])->assertSessionHasNoErrors();

        $this->assertSame(['22-01-03' => -400.0, '61-02-01' => 400.0], $this->balances());
        $this->assertSame('BPV', Journal::where('source_type', 'cheque')->sole()->voucher_type);
        $this->get(route('cheques.issued', ['tab' => 'cleared']))->assertOk()->assertSee('771203')->assertSee('Acme Pig Iron');
    }

    public function test_a_held_cheque_to_a_supplier_makes_the_payment_when_it_clears(): void
    {
        $supplier = $this->supplier();
        $bill = $this->bill($supplier, 400);

        $this->post(route('supplier-payments.store'), ['supplier_id' => $supplier->id, 'paid_on' => '2026-11-09', 'amount' => 400, 'account_id' => $this->account('22-01-03')->id,
            'method' => 'Cheque', 'reference' => '771203', 'cheque_date' => '2026-11-30', 'alloc' => [$bill->id => 400]]);
        $this->assertSame([0, 400.0], [SupplierPayment::count(), $bill->fresh()->balance()]);

        Carbon::setTestNow('2026-11-30 09:00');
        $this->post(route('cheques.clear', Cheque::sole()), ['date' => '2026-11-30'])->assertSessionHasNoErrors();

        $this->assertSame(['2026-11-30', 0.0], [SupplierPayment::sole()->paid_on->toDateString(), $bill->fresh()->balance()]);
        $this->assertSame(['22-01-03' => -400.0, '61-02-01' => 400.0], $this->balances());
    }

    public function test_a_cheque_dated_today_is_an_ordinary_receipt(): void
    {
        $this->receiveCheque(500, [], '2026-11-09')->assertSessionHasNoErrors();

        $this->assertSame([0, 1], [Cheque::count(), Receipt::count()]);
    }

    public function test_with_a_deposit_step_a_cheque_is_deposited_before_it_can_clear(): void
    {
        AppSetting::setValue(FinanceRules::CHEQUE_STEPS, 'DEPOSIT');
        $this->receiveCheque(500, []);
        $cheque = Cheque::sole();
        Carbon::setTestNow('2026-11-20 09:00');

        $this->post(route('cheques.clear', $cheque), ['date' => '2026-11-20'])->assertSessionHasErrors('cheque');
        $this->post(route('cheques.deposit', $cheque), ['date' => '2026-11-20', 'account_id' => $this->account('22-01-03')->id])->assertSessionHasNoErrors();
        $this->assertSame(['DEPOSITED', $this->account('22-01-03')->id], [$cheque->fresh()->status, $cheque->fresh()->account_id]);
        $this->post(route('cheques.clear', $cheque), ['date' => '2026-11-22'])->assertSessionHasNoErrors();

        $this->assertSame(['12-06-02' => -500.0, '22-01-03' => 500.0], $this->balances());
    }

    public function test_the_cheque_accounts_are_never_offered_as_a_bank(): void
    {
        $this->get(route('receipts.create', ['customer' => $this->customer->id]))->assertOk()
            ->assertSee('Cheque date')->assertDontSee('Post-dated cheques in hand')->assertDontSee('Post-dated cheques issued');
    }

    public function test_finance_is_told_once_a_morning_which_cheques_are_due(): void
    {
        $accounts = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $accounts->roles()->attach(Role::where('code', 'ACCOUNTS')->value('id'));
        $this->receiveCheque(500, []);
        $this->receiveCheque(250, [], '2026-12-15');

        $this->artisan('finance:due-cheques')->assertSuccessful();
        $this->assertCount(0, $accounts->notifications);

        Carbon::setTestNow('2026-11-20 08:00');
        $this->artisan('finance:due-cheques')->assertSuccessful();
        $this->artisan('finance:due-cheques')->assertSuccessful();

        $notice = $accounts->fresh()->notifications()->sole();
        $this->assertSame(['cheques-due', 'A post-dated cheque is due', route('cheques.received')], [$notice->data['kind'], $notice->data['title'], $notice->data['url']]);
        $this->assertStringContainsString('1 cheque to deposit', $notice->data['body']);
    }

    public function test_only_finance_can_clear_or_bounce_a_cheque(): void
    {
        config(['foundry.require_login' => true]);
        $this->receiveCheque(500, []);
        $sales = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $sales->roles()->attach(Role::where('code', 'SALES')->value('id'));
        Carbon::setTestNow('2026-11-20 09:00');

        $this->actingAs($sales)->post(route('cheques.clear', Cheque::sole()), ['date' => '2026-11-20']);
        $this->actingAs($sales)->post(route('cheques.bounce', Cheque::sole()), ['date' => '2026-11-20']);
        $this->assertSame('IN_HAND', Cheque::sole()->status);
    }

    public function test_the_cheque_rules_are_set_under_invoicing(): void
    {
        $this->get(route('finance.settings'))->assertOk()->assertSee('Post-dated cheques from customers')->assertSee('Cheque steps');

        $this->put(route('finance.settings.update'), ['due_days' => 30, 'pdc_received' => 'SETTLE', 'pdc_issued' => 'HOLD', 'cheque_steps' => 'DEPOSIT'])->assertSessionHasNoErrors();

        $this->assertSame(['SETTLE', 'HOLD', 'DEPOSIT'], [FinanceRules::pdcMode(), FinanceRules::pdcMode(true), FinanceRules::chequeSteps()]);
    }

    public function test_clearing_a_held_cheque_twice_makes_one_receipt(): void
    {
        $invoice = $this->invoice(500);
        $this->receiveCheque(500, [$invoice->id => 500]);
        Carbon::setTestNow('2026-11-20 09:00');

        $this->post(route('cheques.clear', Cheque::sole()), ['date' => '2026-11-20'])->assertSessionHasNoErrors();
        $this->post(route('cheques.clear', Cheque::sole()), ['date' => '2026-11-20'])->assertSessionHasErrors('cheque');

        $this->assertSame(1, Receipt::count());
        $this->assertSame(['22-01-04' => 500.0, '41-01-01' => -500.0], $this->balances());
    }

    public function test_a_bounce_entered_late_reverses_on_the_day_it_bounced(): void
    {
        AppSetting::setValue(FinanceRules::PDC_RECEIVED, 'SETTLE');
        $this->receiveCheque(500, [$this->invoice(500)->id => 500]);
        Carbon::setTestNow('2026-11-20 09:00');
        $this->post(route('cheques.clear', Cheque::sole()), ['date' => '2026-11-20']);
        Carbon::setTestNow('2026-11-25 09:00');

        $this->post(route('cheques.bounce', Cheque::sole()), ['date' => '2026-11-22'])->assertSessionHasNoErrors();

        $this->assertSame(['2026-11-22'], Journal::whereNotNull('reverses_id')->pluck('journal_date')->map->toDateString()->unique()->values()->all());
        $rows = app(Receivables::class)->statement($this->customer)['rows'];
        $this->assertSame(['Cheque bounced', '2026-11-22'], [end($rows)['type'], end($rows)['date']->toDateString()]);
    }

    public function test_receive_a_cheque_starts_on_a_cheque_and_keeps_it_in_the_register_even_when_dated_today(): void
    {
        $this->get(route('cheques.received'))->assertSee(route('receipts.create', ['cheque' => 1]), false);
        $this->get(route('receipts.create', ['cheque' => 1, 'customer' => $this->customer->id]))->assertOk()
            ->assertSee('Cheques received')->assertSee('Save cheque')->assertSee('name="as_cheque"', false)->assertSee("Cheque", false);

        $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => '2026-11-09', 'amount' => 500, 'as_cheque' => 1,
            'account_id' => $this->account('22-01-04')->id, 'method' => 'Cheque', 'reference' => 'CHQ 1', 'cheque_date' => '2026-11-09'])
            ->assertRedirect(route('cheques.show', Cheque::sole()));
        $this->assertSame([0, 'IN_HAND'], [Receipt::count(), Cheque::sole()->status]);

        $this->post(route('cheques.clear', Cheque::sole()), ['date' => '2026-11-09'])->assertSessionHasNoErrors();
        $this->assertSame(1, Receipt::count());
    }

    public function test_a_cheque_from_the_register_needs_its_date(): void
    {
        $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => '2026-11-09', 'amount' => 500, 'as_cheque' => 1, 'method' => 'Cheque'])
            ->assertSessionHasErrors('cheque_date');
    }

    public function test_the_register_opens_on_all_cheques_with_open_ones_first(): void
    {
        $this->receiveCheque(500, [], '2026-11-25');
        $this->receiveCheque(300, [], '2026-11-12');
        $this->post(route('cheques.bounce', Cheque::where('amount', 300)->sole()), ['date' => '2026-11-12', 'reason' => 'Stopped']);
        $this->receiveCheque(200, [], '2026-11-20');

        $this->get(route('cheques.received'))->assertOk()->assertSee('All 3')->assertSeeInOrder(['20 Nov', '25 Nov', 'Bounced']);
    }
}
