<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\TaxRate;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Tax\Models\PartyTaxProfile;
use App\Domains\Tax\Services\TaxSetup;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\Formats;
use App\Support\TaxJurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        $this->customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
    }

    /** @return array<string, float> account code => debits less credits, for accounts that moved */
    private function balances(): array
    {
        return LedgerAccount::tree()->where('is_group', false)->filter(fn ($a) => $a->net != 0.0)->mapWithKeys(fn ($a) => [$a->code => $a->net])->all();
    }

    private function account(string $code): LedgerAccount
    {
        return LedgerAccount::where('code', $code)->firstOrFail();
    }

    private function draftInvoice(float $price, string $date = '2026-10-01', ?string $taxRateId = null): Invoice
    {
        return app(InvoiceService::class)->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => $date],
            [['description' => 'Pump casing', 'quantity' => 2, 'unit_price' => $price, 'tax_rate_id' => $taxRateId]]);
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
    }

    public function test_issuing_an_invoice_posts_receivables_sales_and_tax_and_voiding_it_posts_the_reversal(): void
    {
        $rate = TaxRate::create(['name' => 'Sales tax', 'rate_percent' => 18]);
        $invoice = $this->draftInvoice(500, '2026-10-01', $rate->id);

        $this->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();

        $this->assertSame(['1130' => 1180.0, '2140' => -180.0, '4100' => -1000.0], $this->balances());
        $journal = Journal::where('source_type', 'invoice')->where('source_id', $invoice->id)->sole();
        $this->assertSame(['2026-10-01', 1180.0], [$journal->journal_date->toDateString(), $journal->total]);
        $this->assertSame($this->customer->id, $journal->lines()->where('account_id', $this->account('1130')->id)->value('party_id'));

        $this->post(route('invoices.void', $invoice), ['reason' => 'Raised twice'])->assertSessionHasNoErrors();

        $this->assertSame([], $this->balances());
        $this->assertSame(today()->toDateString(), Journal::where('reverses_id', $journal->id)->sole()->journal_date->toDateString());
    }

    public function test_a_credit_note_debits_sales_returns_and_tax_goes_to_the_account_set_for_its_authority(): void
    {
        app(TaxSetup::class)->loadDefaults(TaxJurisdiction::pack());
        PartyTaxProfile::updateOrCreate(['party_id' => $this->customer->party_id], ['tax_status' => 'REGISTERED', 'region' => 'PB', 'registrations' => ['NTN' => '1234567-8', 'STRN' => '32-77-8761-123-45']]);
        AppSetting::setValue(LedgerSettings::TAX_ACCOUNTS, json_encode(['PRA' => $this->account('2141')->id]));
        $invoice = app(InvoiceService::class)->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => '2025-03-10'],
            [['description' => 'Pattern repair', 'quantity' => 1, 'unit_price' => 1000, 'supply_type' => 'SERVICE']]);
        app(InvoiceService::class)->issue($invoice);

        app(InvoiceService::class)->creditNote($invoice->fresh(), [['description' => 'Pattern repair', 'quantity' => 1, 'unit_price' => 500, 'supply_type' => 'SERVICE']], 'Half the work');

        $this->assertSame(['1130' => 580.0, '2141' => -80.0, '4100' => -1000.0, '4900' => 500.0], $this->balances());
    }

    public function test_a_bill_debits_each_line_account_and_input_tax_and_its_credit_note_goes_back_to_the_same_accounts(): void
    {
        $supplier = $this->supplier();
        $this->post(route('bills.store'), ['supplier_id' => $supplier->id, 'bill_date' => '2026-10-01', 'supplier_bill_number' => 'S-1', 'lines' => [
            ['description' => 'Electricity', 'quantity' => 1, 'unit_price' => 1000, 'tax_percent' => 10, 'account_id' => $this->account('5310')->id],
            ['description' => 'Stationery', 'quantity' => 1, 'unit_price' => 200, 'tax_percent' => 0],
        ]])->assertSessionHasNoErrors();
        $bill = SupplierBill::sole();

        $this->post(route('bills.post', $bill))->assertSessionHasNoErrors();

        $this->assertSame(['1161' => 100.0, '2110' => -1300.0, '5310' => 1000.0, '6600' => 200.0], $this->balances());

        $this->post(route('bills.credit.store', $bill), ['supplier_bill_number' => 'SC-1', 'reason' => 'Meter misread',
            'lines' => [['description' => 'Electricity', 'quantity' => 1, 'unit_price' => 400, 'tax_percent' => 10]]])->assertSessionHasNoErrors();

        $this->assertSame(['1161' => 60.0, '2110' => -860.0, '5310' => 600.0, '6600' => 200.0], $this->balances());
    }

    public function test_receipts_and_payments_move_cash_or_bank_by_method_and_a_receipt_credits_its_whole_amount(): void
    {
        $invoice = app(InvoiceService::class)->issue($this->draftInvoice(300));
        $supplier = $this->supplier();
        AppSetting::setValue(LedgerSettings::METHODS, json_encode(['cheque' => $this->account('1110')->id]));

        $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => '2026-10-05', 'amount' => 1000, 'method' => 'Bank transfer', 'alloc' => [$invoice->id => 600]])->assertSessionHasNoErrors();
        $this->post(route('supplier-payments.store'), ['supplier_id' => $supplier->id, 'paid_on' => '2026-10-06', 'amount' => 150, 'method' => 'Cash'])->assertSessionHasNoErrors();
        $this->post(route('supplier-payments.store'), ['supplier_id' => $supplier->id, 'paid_on' => '2026-10-06', 'amount' => 50, 'method' => 'CHEQUE'])->assertSessionHasNoErrors();

        $this->assertSame(['1110' => -200.0, '1121' => 1000.0, '1130' => -400.0, '2110' => 200.0, '4100' => -600.0], $this->balances());
    }

    public function test_a_manual_journal_must_balance_post_to_an_account_with_no_sub_accounts_and_fall_after_the_closed_date(): void
    {
        AppSetting::setValue(LedgerSettings::CLOSED_TO, '2026-09-30');
        $line = fn (string $code, float $debit, float $credit) => ['account_id' => $this->account($code)->id, 'debit' => $debit, 'credit' => $credit];
        $journal = fn (string $date, array $lines) => $this->post(route('ledger.journals.store'), ['journal_date' => $date, 'memo' => 'Opening balances', 'lines' => $lines]);

        $journal('2026-10-01', [$line('1121', 500, 0), $line('3900', 0, 400)])->assertSessionHasErrors('journal');
        $journal('2026-10-01', [$line('1120', 500, 0), $line('3900', 0, 500)])->assertSessionHasErrors('journal');
        $journal('2026-09-30', [$line('1121', 500, 0), $line('3900', 0, 500)])->assertSessionHasErrors('journal');
        $this->assertSame(0, Journal::count());

        $journal('2026-10-01', [$line('1121', 500, 0), $line('3900', 0, 500)])->assertSessionHasNoErrors();

        $this->assertSame(['1121' => 500.0, '3900' => -500.0], $this->balances());
    }

    public function test_an_invoice_dated_in_a_closed_period_stays_a_draft(): void
    {
        AppSetting::setValue(LedgerSettings::CLOSED_TO, '2026-09-30');
        $invoice = $this->draftInvoice(100, '2026-09-15');

        $this->post(route('invoices.issue', $invoice))->assertSessionHasErrors('invoice');

        $this->assertSame(['DRAFT', null], [$invoice->fresh()->status, $invoice->fresh()->invoice_number]);
        $this->assertSame(0, Journal::count());
    }

    public function test_documents_from_before_the_ledger_are_posted_once(): void
    {
        $invoice = app(InvoiceService::class)->issue($this->draftInvoice(250));
        Journal::query()->delete();

        $this->artisan('ledger:post-missing')->assertSuccessful();
        $this->artisan('ledger:post-missing')->assertSuccessful();

        $this->assertSame(1, Journal::where('source_id', $invoice->id)->count());
        $this->assertSame(['1130' => 500.0, '4100' => -500.0], $this->balances());
    }

    public function test_a_sub_account_cannot_go_under_an_account_with_postings_and_such_an_account_cannot_be_deleted(): void
    {
        app(InvoiceService::class)->issue($this->draftInvoice(100));
        $sales = $this->account('4100');

        $this->post(route('ledger.accounts.store'), ['code' => '4101', 'name' => 'Export sales', 'parent_id' => $sales->id])->assertSessionHasErrors('account');
        $this->delete(route('ledger.accounts.destroy', $sales))->assertSessionHasErrors('account');
        $this->post(route('ledger.accounts.store'), ['code' => '6610', 'name' => 'Printing', 'parent_id' => $this->account('6600')->id])->assertSessionHasNoErrors();

        $this->assertModelExists($sales);
        $this->assertSame([null, 'EXPENSE'], [LedgerAccount::where('code', '4101')->first(), LedgerAccount::where('code', '6610')->value('type')]);
    }

    public function test_the_trial_balance_agrees_and_the_account_ledger_runs_from_its_opening_balance(): void
    {
        app(InvoiceService::class)->issue($this->draftInvoice(100, '2026-09-01'));
        app(InvoiceService::class)->issue($this->draftInvoice(50, '2026-10-02'));
        $n = fn (float $v) => Formats::number($v, 2);

        $this->get(route('ledger.trial-balance', ['as_at' => '2026-10-31']))->assertOk()->assertSeeInOrder(['Total', $n(300), $n(300)]);
        $this->get(route('ledger.accounts.show', [$this->account('1130'), 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()->assertSeeInOrder(['Opening balance', $n(200).' Dr', $n(100), $n(300).' Dr', 'Closing balance']);
    }

    public function test_the_ledger_pages_and_the_bill_form_with_its_account_choice_open(): void
    {
        app(InvoiceService::class)->issue($this->draftInvoice(100));
        $journal = Journal::sole();

        foreach ([route('ledger.accounts.index'), route('ledger.journals.index'), route('ledger.journals.create'), route('ledger.journals.show', $journal), route('ledger.settings')] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('invoices.show', $journal->source_id))->assertOk()->assertSee($journal->number);
        $this->get(route('bills.create'))->assertOk()->assertSee('5310 Electricity');
    }
}
