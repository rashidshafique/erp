<?php

namespace Tests\Feature;

use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Models\Voucher;
use App\Domains\Ledger\Services\AmountInWords;
use App\Domains\Ledger\Services\Vouchers;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\Formats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Design 19b, 19c and 19h: voucher types on one entry screen, drafts, numbers per type and year, the day book and the print. */
class VoucherEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false, 'name' => 'A. Rauf']);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        AppSetting::setValue('books.year_start_month', '7');
    }

    private function id(string $code): string
    {
        return LedgerAccount::where('code', $code)->value('id');
    }

    private function balance(string $code): float
    {
        return LedgerAccount::where('code', $code)->firstOrFail()->balance();
    }

    public function test_a_journal_voucher_posts_when_balanced_and_takes_the_next_jv_number_of_the_financial_year(): void
    {
        $c = Livewire::test('ledger::voucher-form')
            ->set('date', '2026-10-31')->set('reference', 'NCR-031')
            ->set('lines.0.account_id', $this->id('51-05-01'))->set('lines.0.narration', 'Rejected castings HT-4471')->set('lines.0.debit', '212500')
            ->set('lines.1.account_id', $this->id('22-08-02'))->set('lines.1.credit', '200000')
            ->assertSee('Debits and credits differ by')->call('post');
        $this->assertSame(0, Journal::count());

        $c->set('lines.1.credit', '212500')->assertSee('Balanced. Posting assigns JV-26-0001')->call('post')->assertRedirect();

        $j = Journal::sole();
        $this->assertSame(['JV-26-0001', 'JV', 'NCR-031', 'Rejected castings HT-4471'], [$j->number, $j->voucher_type, $j->reference, $j->memo]);
        $this->assertSame([212500.0, -212500.0], [$this->balance('51-05-01'), $this->balance('22-08-02')]);
    }

    public function test_a_bank_payment_adds_the_balancing_line_and_must_take_money_out(): void
    {
        $c = Livewire::test('ledger::voucher-form', ['type' => 'BPV'])
            ->set('date', '2026-10-31')->assertSet('counter', $this->id('22-01-03'))->set('reference', '00418824')
            ->set('lines.0.account_id', $this->id('63-01-01'))->set('lines.0.credit', '500')
            ->assertSee('A payment must take money out. Use a receipt voucher instead.')->call('post');
        $this->assertSame(0, Journal::count());

        $c->set('lines.0.debit', '412000')->call('addLine')->set('lines.1.account_id', $this->id('63-01-01'))->set('lines.1.narration', 'Bank charges')->set('lines.1.debit', '350')
            ->assertSee('Balancing line, filled in for you')->call('post');

        $j = Journal::sole();
        $this->assertSame(['BPV-26-0001', '00418824', $this->id('22-01-03')], [$j->number, $j->reference, $j->counter_account_id]);
        $this->assertSame([-412350.0, 412350.0], [$this->balance('22-01-03'), $this->balance('63-01-01')]);
    }

    public function test_a_cash_receipt_brings_money_in_through_cash(): void
    {
        Livewire::test('ledger::voucher-form', ['type' => 'CRV'])->set('date', '2026-10-31')->assertSet('counter', $this->id('22-01-01'))
            ->set('lines.0.account_id', $this->id('42-01-01'))->set('lines.0.narration', 'Swarf sale, 2.3 t')->set('lines.0.credit', '46000')->call('post');

        $this->assertSame(['CRV-26-0001', 46000.0], [Journal::sole()->number, $this->balance('22-01-01')]);
    }

    public function test_control_accounts_are_not_offered_on_a_voucher_but_opening_balance_equity_is(): void
    {
        $codes = Vouchers::lineAccounts()->pluck('code');

        $this->assertNotContains('22-02-01', $codes);
        $this->assertNotContains('12-01-01', $codes);
        $this->assertNotContains('12-01-02', $codes);
        $this->assertContains('31-03-02', $codes);
        $this->assertNotContains('22-06', $codes);
    }

    public function test_a_draft_keeps_its_number_never_counts_and_posting_it_keeps_the_number(): void
    {
        Livewire::test('ledger::voucher-form')->set('date', '2026-10-30')
            ->set('lines.0.account_id', $this->id('51-06-03'))->set('lines.0.narration', 'Pattern depreciation, October')->set('lines.0.debit', '400000')
            ->set('lines.1.account_id', $this->id('21-03-02'))->set('lines.1.credit', '400000')
            ->call('saveDraft')->assertRedirect();

        $draft = Voucher::sole();
        $this->assertSame(['JV-26-0001', 0.0, 0], [$draft->number, $this->balance('51-06-03'), Journal::count()]);
        $this->get(route('ledger.journals.index'))->assertOk()->assertSee('JV-26-0001')->assertSee('Draft')->assertSee('1 draft');

        Livewire::test('ledger::voucher-form', ['voucher' => $draft])->assertSee('JV-26-0001')->call('post');

        $j = Journal::sole();
        $this->assertSame([$draft->id, 'JV-26-0001', 400000.0, 0], [$j->id, $j->number, $this->balance('51-06-03'), Voucher::count()]);
    }

    public function test_documents_post_under_their_own_number_and_receipts_as_bank_receipt_vouchers(): void
    {
        $customer = Customer::create(['code' => 'KOV', 'name' => 'Kovar Pumps']);
        $invoice = app(InvoiceService::class)->issue(app(InvoiceService::class)->saveDraft(['customer_id' => $customer->id, 'invoice_date' => '2026-10-06'],
            [['description' => 'Pump casing', 'quantity' => 2, 'unit_price' => 1000]]));
        $this->post(route('receipts.store'), ['customer_id' => $customer->id, 'received_on' => '2026-10-15', 'amount' => 2000, 'method' => 'Bank transfer', 'reference' => 'IBFT 9921', 'alloc' => [$invoice->id => 2000]])->assertSessionHasNoErrors();

        $this->assertSame($invoice->invoice_number, Journal::where('source_type', 'invoice')->value('number'));
        $receipt = Journal::where('source_type', 'receipt')->sole();
        $this->assertSame(['BRV-26-0001', 'BRV', 'IBFT 9921'], [$receipt->number, $receipt->voucher_type, $receipt->reference]);

        $this->get(route('ledger.journals.index', ['type' => 'BRV']))->assertOk()->assertSee('BRV-26-0001')->assertDontSee($invoice->invoice_number);
        $this->get(route('ledger.journals.index', ['q' => 'pump']))->assertOk()->assertSee($invoice->invoice_number)->assertSee('Kovar Pumps');
        $this->get(route('ledger.journals.export'))->assertOk()->assertDownload();
    }

    public function test_a_voucher_prints_with_its_amount_in_words(): void
    {
        AppSetting::setValue('format.currency', 'Rs');
        Formats::flush();
        Livewire::test('ledger::voucher-form', ['type' => 'BPV'])->set('date', '2026-10-30')->set('reference', '00418823')
            ->set('lines.0.account_id', $this->id('51-01-01'))->set('lines.0.narration', 'Against PI-26-0388')->set('lines.0.debit', '1171800')->call('post');

        $this->get(route('ledger.journals.print', Journal::sole()))->assertOk()
            ->assertSee('Bank payment voucher')->assertSee('BPV-26-0001')->assertSee('Rupees eleven lakh seventy-one thousand eight hundred only')->assertSee('A. Rauf');
        $this->assertSame('Rupees two crore five lakh only', AmountInWords::say(20500000));
    }
}
