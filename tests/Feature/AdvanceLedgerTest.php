<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\AdvanceReclass;
use App\Domains\Ledger\Services\Ledger;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Domains\Purchasing\Models\Supplier;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceLedgerTest extends TestCase
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

    private function net(string $code): float
    {
        return LedgerAccount::tree()->firstWhere('code', $code)->net;
    }

    private function invoice(float $amount): Invoice
    {
        $service = app(InvoiceService::class);

        return $service->issue($service->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => today()->toDateString()], [['description' => 'Casting', 'quantity' => 1, 'unit_price' => $amount]]));
    }

    public function test_money_received_ahead_is_a_customer_advance_until_it_is_put_on_an_invoice(): void
    {
        $paid = $this->invoice(300);
        $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => today()->toDateString(), 'amount' => 1000, 'alloc' => [$paid->id => 300]])->assertSessionHasNoErrors();

        $this->assertSame([0.0, -700.0, 1000.0], [$this->net('1130'), $this->net('2130'), $this->net('1121')]);

        $later = $this->invoice(600);
        $this->post(route('invoices.apply-credit', $later))->assertSessionHasNoErrors();

        $this->assertSame([0.0, -100.0], [$this->net('1130'), $this->net('2130')]);
        $this->post(route('receipts.allocate', Receipt::sole()), ['alloc' => [$this->invoice(50)->id => 50]])->assertSessionHasNoErrors();
        $this->assertSame([0.0, -50.0], [$this->net('1130'), $this->net('2130')]);
    }

    public function test_money_paid_to_a_supplier_ahead_is_an_advance_to_suppliers_until_it_is_put_on_a_bill(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        $this->post(route('supplier-payments.store'), ['supplier_id' => $supplier->id, 'paid_on' => today()->toDateString(), 'amount' => 500])->assertSessionHasNoErrors();
        $this->assertSame([500.0, 0.0], [$this->net('1140'), $this->net('2110')]);

        $this->post(route('bills.store'), ['supplier_id' => $supplier->id, 'bill_date' => today()->toDateString(), 'supplier_bill_number' => 'S-1',
            'lines' => [['description' => 'Pig iron', 'quantity' => 1, 'unit_price' => 400, 'tax_percent' => 0]]])->assertSessionHasNoErrors();
        $bill = SupplierBill::sole();
        $this->post(route('bills.post', $bill))->assertSessionHasNoErrors();
        $this->post(route('bills.apply-advances', $bill))->assertSessionHasNoErrors();

        $this->assertSame([100.0, 0.0], [$this->net('1140'), $this->net('2110')]);
    }

    public function test_money_on_account_posted_the_old_way_is_moved_once(): void
    {
        $receipt = Receipt::create(['receipt_number' => 'RC-OLD-1', 'customer_id' => $this->customer->id, 'received_on' => today()->subMonth()->toDateString(), 'amount' => 800]);
        app(Ledger::class)->post($receipt->received_on->toDateString(), [
            ['debit' => 800, 'account_id' => LedgerSettings::account('bank')->id],
            ['credit' => 800, 'account_id' => LedgerSettings::account('receivables')->id, 'party_type' => 'customer', 'party_id' => $this->customer->id],
        ], ['type' => 'receipt', 'id' => $receipt->id]);
        $this->assertSame(-800.0, $this->net('1130'));

        $this->assertSame(1, app(AdvanceReclass::class)->run());
        $this->assertSame(0, app(AdvanceReclass::class)->run());

        $this->assertSame([0.0, -800.0], [$this->net('1130'), $this->net('2130')]);
        $this->post(route('invoices.apply-credit', $this->invoice(800)))->assertSessionHasNoErrors();
        $this->assertSame([0.0, 0.0], [$this->net('1130'), $this->net('2130')]);
    }
}
