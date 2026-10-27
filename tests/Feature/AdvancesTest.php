<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Finance\Services\FinanceRules;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancesTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        $this->supplier = Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        $this->customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
    }

    private function order(string $number): PurchaseOrder
    {
        return PurchaseOrder::create(['po_number' => $number, 'status' => 'ISSUED', 'supplier_id' => $this->supplier->id, 'order_date' => '2026-10-01']);
    }

    private function pay(float $amount, ?PurchaseOrder $order = null, string $date = '2026-10-02'): void
    {
        $this->post(route('supplier-payments.store'), ['supplier_id' => $this->supplier->id, 'paid_on' => $date, 'amount' => $amount, 'purchase_order_id' => $order?->id])->assertSessionHasNoErrors();
    }

    /** A posted bill of 1,000 with no tax, for the order if one is given. */
    private function postedBill(?PurchaseOrder $order = null): SupplierBill
    {
        $before = SupplierBill::pluck('id');
        $this->post(route('bills.store'), ['supplier_id' => $this->supplier->id, 'bill_date' => '2026-10-05', 'supplier_bill_number' => 'S-'.uniqid(),
            'lines' => [['description' => 'Pig iron', 'quantity' => 1, 'unit_price' => 1000, 'tax_percent' => 0]]])->assertSessionHasNoErrors();
        $bill = SupplierBill::whereNotIn('id', $before)->firstOrFail();
        $bill->update(['purchase_order_id' => $order?->id]);
        $this->post(route('bills.post', $bill))->assertSessionHasNoErrors();

        return $bill->fresh();
    }

    public function test_using_advances_takes_the_bills_own_order_first_and_never_another_orders(): void
    {
        [$ours, $other] = [$this->order('PO-1'), $this->order('PO-2')];
        $this->pay(300, null, '2026-09-01');
        $this->pay(600, $ours);
        $this->pay(500, $other);
        $bill = $this->postedBill($ours);
        $this->assertSame(1000.0, $bill->balance());

        $this->post(route('bills.apply-advances', $bill))->assertSessionHasNoErrors();

        $this->assertSame(100.0, $bill->balance());
        $this->assertSame([0.0, 0.0, 500.0], SupplierPayment::orderBy('payment_number')->get()->map->unallocated()->all());
    }

    public function test_a_payment_cannot_name_another_suppliers_order(): void
    {
        $theirs = PurchaseOrder::create(['po_number' => 'PO-9', 'status' => 'ISSUED', 'order_date' => '2026-10-01',
            'supplier_id' => Supplier::create(['name' => 'Other', 'review_interval_days' => 180, 'approval_status' => 'APPROVED'])->id]);

        $this->post(route('supplier-payments.store'), ['supplier_id' => $this->supplier->id, 'paid_on' => '2026-10-02', 'amount' => 10, 'purchase_order_id' => $theirs->id])
            ->assertSessionHasErrors('purchase_order_id');
    }

    public function test_by_default_advances_wait_for_the_button(): void
    {
        $this->pay(400);

        $this->assertSame(1000.0, $this->postedBill()->balance());
    }

    public function test_set_to_automatic_a_posted_bill_takes_the_advances_and_an_issued_invoice_takes_money_on_account(): void
    {
        AppSetting::setValue(FinanceRules::ADVANCES, 'AUTO');
        $this->pay(400);
        $this->assertSame(600.0, $this->postedBill()->balance());

        $order = SalesOrder::create(['order_number' => 'SO-1', 'customer_id' => $this->customer->id, 'order_date' => '2026-10-01', 'status' => 'CONFIRMED']);
        $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => '2026-10-02', 'amount' => 250, 'sales_order_id' => $order->id])->assertSessionHasNoErrors();
        $service = app(InvoiceService::class);
        $unrelated = $service->issue($service->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => '2026-10-05'], [['description' => 'Casting', 'quantity' => 1, 'unit_price' => 1000]]));
        $fromOrder = $service->issue($service->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => '2026-10-05', 'source_type' => 'sales_order', 'source_id' => $order->id],
            [['description' => 'Casting', 'quantity' => 1, 'unit_price' => 1000]]));

        $this->assertSame([1000.0, 750.0], [$unrelated->balance(), $fromOrder->balance()]);
        $this->assertSame(0.0, Receipt::sole()->unallocated());
    }

    public function test_the_invoice_page_offers_money_on_account(): void
    {
        $this->post(route('receipts.store'), ['customer_id' => $this->customer->id, 'received_on' => '2026-10-02', 'amount' => 250])->assertSessionHasNoErrors();
        $service = app(InvoiceService::class);
        $invoice = $service->issue($service->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => '2026-10-05'], [['description' => 'Casting', 'quantity' => 1, 'unit_price' => 1000]]));

        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Use money on account');
        $this->post(route('invoices.apply-credit', $invoice))->assertSessionHasNoErrors();

        $this->assertSame(750.0, Invoice::find($invoice->id)->balance());
    }
}
