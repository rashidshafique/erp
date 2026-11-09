<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Purchasing\Models\Supplier;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentAccountTest extends TestCase
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

    private function account(string $code): LedgerAccount
    {
        return LedgerAccount::where('code', $code)->firstOrFail();
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
    }

    /** @return array{string, string, string} the journal's voucher type and the account of its line without a party, and its reference */
    private function posted(string $source, string $id): array
    {
        $journal = Journal::where('source_type', $source)->where('source_id', $id)->sole();

        return [$journal->voucher_type, LedgerAccount::find($journal->lines()->whereNull('party_type')->value('account_id'))->code, (string) $journal->reference];
    }

    public function test_the_forms_offer_cash_and_bank_accounts_and_the_methods_with_ones_already_used(): void
    {
        Receipt::create(['receipt_number' => 'RC-1', 'customer_id' => $this->customer->id, 'received_on' => '2026-10-01', 'amount' => 10, 'method' => 'Easypaisa']);

        $this->get(route('receipts.create', ['customer' => $this->customer->id]))->assertOk()
            ->assertSee('Into')->assertSee('22-01-04 Bank Alfalah current a/c')->assertSee('22-01-01 Cash in hand')
            ->assertDontSee('22-01-05 HBL collection a/c')->assertDontSee('22-02-01')
            ->assertSee('<option value="Pay order">', false)->assertSee('<option value="Easypaisa">', false);
        $this->get(route('supplier-payments.create', ['supplier' => $this->supplier()->id]))->assertOk()->assertSee('Paid from')->assertSee('22-01-03 Meezan Bank current a/c');
    }

    public function test_a_receipt_and_a_payment_post_to_the_account_picked_whatever_the_method(): void
    {
        $supplier = $this->supplier();

        $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => '2026-10-05', 'amount' => 500,
            'account_id' => $this->account('22-01-04')->id, 'method' => 'Cheque', 'reference' => 'CHQ 004512'])->assertSessionHasNoErrors();
        $this->post(route('supplier-payments.store'), ['supplier_id' => $supplier->id, 'paid_on' => '2026-10-06', 'amount' => 80,
            'account_id' => $this->account('22-01-02')->id, 'method' => 'Bank transfer'])->assertSessionHasNoErrors();

        $receipt = Receipt::sole();
        $payment = SupplierPayment::sole();
        $this->assertSame([$this->account('22-01-04')->id, 'Cheque', 'CHQ 004512'], [$receipt->account_id, $receipt->method, $receipt->reference]);
        $this->assertSame(['BRV', '22-01-04', 'CHQ 004512'], $this->posted('receipt', $receipt->id));
        // petty cash is cash whatever the method says
        $this->assertSame(['CPV', '22-01-02', ''], $this->posted('supplier_payment', $payment->id));

        $this->get(route('receipts.show', $receipt))->assertSee('22-01-04 Bank Alfalah current a/c')->assertSee('Cheque CHQ 004512');
        $this->get(route('receipts.index'))->assertSee('22-01-04 Bank Alfalah current a/c');
        $this->get(route('supplier-payments.show', $payment))->assertSee('Paid from')->assertSee('22-01-02 Petty cash: factory');
    }

    public function test_only_an_active_cash_or_bank_account_can_be_picked(): void
    {
        foreach (['22-02-01', '22-01-05'] as $code) {
            $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => '2026-10-05', 'amount' => 500, 'account_id' => $this->account($code)->id])
                ->assertSessionHasErrors('account_id');
        }
        $this->assertSame(0, Receipt::count());
    }

    public function test_a_receipt_saved_without_an_account_is_given_the_one_its_method_posts_to(): void
    {
        $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => '2026-10-05', 'amount' => 500, 'method' => 'Cash'])->assertSessionHasNoErrors();

        $this->assertSame($this->account('22-01-01')->id, Receipt::sole()->account_id);
    }

    public function test_receipts_and_payments_posted_before_the_account_field_take_the_account_their_journal_moved(): void
    {
        $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => '2026-10-05', 'amount' => 500, 'account_id' => $this->account('22-01-04')->id]);
        $this->post(route('supplier-payments.store'), ['supplier_id' => $this->supplier()->id, 'paid_on' => '2026-10-06', 'amount' => 80, 'account_id' => $this->account('22-01-01')->id]);
        DB::table('receipts')->update(['account_id' => null]);
        DB::table('supplier_payments')->update(['account_id' => null]);

        $migration = require base_path('app/Domains/Finance/database/migrations/2026_11_09_003150_add_account_to_receipts_and_supplier_payments.php');
        $migration->down();
        $migration->up();

        $this->assertSame($this->account('22-01-04')->id, Receipt::sole()->account_id);
        $this->assertSame($this->account('22-01-01')->id, SupplierPayment::sole()->account_id);
    }
}
