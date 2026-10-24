<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Finance\Services\FinanceRules;
use App\Domains\Finance\Services\Payables;
use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\Supplier;
use App\Models\AppSetting;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierBillTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): Supplier
    {
        return Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
    }

    /** @param list<array<string, mixed>> $lines */
    private function bill(Supplier $supplier, array $lines, array $extra = []): SupplierBill
    {
        $this->flushSession();
        $before = SupplierBill::pluck('id');
        $this->post(route('bills.store'), $extra + ['supplier_id' => $supplier->id, 'bill_date' => '2026-10-01', 'supplier_bill_number' => 'S-'.uniqid(), 'lines' => $lines])->assertSessionHasNoErrors();

        return SupplierBill::whereNotIn('id', $before)->firstOrFail();
    }

    private function posted(Supplier $supplier, float $price = 100, float $qty = 2, float $tax = 10, string $date = '2026-10-01'): SupplierBill
    {
        $bill = $this->bill($supplier, [['description' => 'Pig iron', 'quantity' => $qty, 'unit_price' => $price, 'tax_percent' => $tax]], ['bill_date' => $date]);
        $this->post(route('bills.post', $bill))->assertSessionHasNoErrors();

        return $bill->fresh();
    }

    /** An issued, part-received purchase order with one material line. */
    private function receivedOrder(Supplier $supplier, float $ordered = 100, float $received = 100, float $price = 50): PurchaseOrder
    {
        $material = Material::create(['code' => 'M-'.uniqid(), 'name' => 'Pig iron', 'material_type' => 'METAL', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => true, 'requires_lot_tracking' => true]);
        $this->post(route('purchase-orders.store'), ['supplier_id' => $supplier->id, 'order_date' => '2026-10-01',
            'lines' => [['material_id' => $material->id, 'description' => 'Pig iron', 'ordered_qty' => $ordered, 'unit_price' => $price, 'tax_percent' => 10, 'uom' => 'kg']]]);
        $order = PurchaseOrder::firstOrFail();
        $this->post(route('purchase-orders.issue', $order));
        $this->post(route('purchase-orders.receive', $order), ['qty' => [$order->lines()->first()->id => $received]])->assertSessionHasNoErrors();

        return $order->fresh('lines');
    }

    public function test_a_bill_is_priced_taxed_numbered_and_due_from_its_terms_when_posted(): void
    {
        $bill = $this->posted($this->supplier(), 100, 2, 10);

        $this->assertSame('POSTED', $bill->status);
        $this->assertMatchesRegularExpression('/^BILL-\d{4}-0001$/', $bill->bill_number);
        $this->assertEquals(200.0, $bill->subtotal);
        $this->assertEquals(20.0, $bill->tax_total);
        $this->assertEquals(220.0, $bill->total);
        $this->assertSame('2026-10-31', $bill->due_date->toDateString());
        $this->assertEquals(220.0, $bill->balance());
    }

    public function test_the_suppliers_own_number_is_needed_and_cannot_be_posted_twice(): void
    {
        $supplier = $this->supplier();
        $noNumber = $this->bill($supplier, [['description' => 'Freight', 'quantity' => 1, 'unit_price' => 10]], ['supplier_bill_number' => '']);
        $this->post(route('bills.post', $noNumber))->assertSessionHasErrors('bill');

        $first = $this->bill($supplier, [['description' => 'Freight', 'quantity' => 1, 'unit_price' => 10]], ['supplier_bill_number' => 'INV-77']);
        $this->post(route('bills.post', $first))->assertSessionHasNoErrors();
        $again = $this->bill($supplier, [['description' => 'Freight', 'quantity' => 1, 'unit_price' => 10]], ['supplier_bill_number' => 'inv-77']);
        $this->post(route('bills.post', $again))->assertSessionHasErrors('bill');
    }

    public function test_a_bill_from_a_purchase_order_carries_what_was_received_and_not_yet_billed(): void
    {
        $order = $this->receivedOrder($this->supplier(), 100, 60, 50);

        $lines = $this->get(route('bills.create', ['order' => $order->id]))->assertOk()->viewData('lines');

        $this->assertCount(1, $lines);
        $this->assertEquals(60, $lines[0]['quantity']);
        $this->assertEquals(50, $lines[0]['unit_price']);
        $this->assertEquals(10, $lines[0]['tax_percent']);
    }

    public function test_matching_warns_by_default_and_blocks_when_the_rule_says_so(): void
    {
        $supplier = $this->supplier();
        $order = $this->receivedOrder($supplier, 100, 60, 50);
        $lineId = $order->lines->first()->id;
        $over = $this->bill($supplier, [['description' => 'Pig iron', 'quantity' => 80, 'unit_price' => 50, 'tax_percent' => 10, 'purchase_order_line_id' => $lineId]], ['purchase_order_id' => $order->id]);

        $this->get(route('bills.show', $over))->assertOk()->assertSee('Does not match the order');
        $this->post(route('bills.post', $over))->assertSessionHasNoErrors();   // WARN: still posts
        $this->post(route('bills.void', $over), ['reason' => 'Test'])->assertSessionHasNoErrors();

        AppSetting::setValue(FinanceRules::BILL_MATCH, 'BLOCK', 'x');
        $pricey = $this->bill($supplier, [['description' => 'Pig iron', 'quantity' => 10, 'unit_price' => 60, 'tax_percent' => 10, 'purchase_order_line_id' => $lineId]]);
        $this->post(route('bills.post', $pricey))->assertSessionHasErrors('bill');
        $this->assertSame('DRAFT', $pricey->fresh()->status);

        $this->put(route('bills.update', $pricey), ['supplier_id' => $supplier->id, 'bill_date' => '2026-10-01', 'supplier_bill_number' => 'S-FIX',
            'lines' => [['description' => 'Pig iron', 'quantity' => 10, 'unit_price' => 50.5, 'tax_percent' => 10, 'purchase_order_line_id' => $lineId]]]);
        $this->assertSame([], app(\App\Domains\Finance\Services\BillService::class)->matchIssues($pricey->fresh()), 'within the 2% tolerance');
    }

    public function test_a_payment_settles_bills_and_the_rest_stays_as_an_advance(): void
    {
        $supplier = $this->supplier();
        $bill = $this->posted($supplier, 100, 2, 0);

        $this->post(route('supplier-payments.store'), ['supplier_id' => $supplier->id, 'paid_on' => '2026-10-10', 'amount' => 250, 'alloc' => [$bill->id => 200]])->assertSessionHasNoErrors();

        $payment = SupplierPayment::firstOrFail();
        $this->assertMatchesRegularExpression('/^SP-\d{4}-0001$/', $payment->payment_number);
        $this->assertEquals(50.0, $payment->unallocated());
        $this->assertEquals(0.0, $bill->fresh()->balance());
        $this->assertEquals(50.0, app(Payables::class)->advance($supplier->id));
    }

    public function test_a_payment_cannot_exceed_what_a_bill_has_left(): void
    {
        $supplier = $this->supplier();
        $bill = $this->posted($supplier, 100, 1, 0);

        $this->post(route('supplier-payments.store'), ['supplier_id' => $supplier->id, 'paid_on' => '2026-10-10', 'amount' => 500, 'alloc' => [$bill->id => 150]])->assertSessionHasErrors('payment');

        $this->assertSame(0, SupplierPayment::count());
    }

    public function test_a_credit_note_reduces_what_is_owed_and_cannot_exceed_the_bill(): void
    {
        $supplier = $this->supplier();
        $bill = $this->posted($supplier, 100, 2, 0);

        $this->post(route('bills.credit.store', $bill), ['supplier_bill_number' => 'CN-1', 'reason' => 'Rejected load', 'lines' => [['description' => 'Pig iron', 'quantity' => 1, 'unit_price' => 100]]])->assertSessionHasNoErrors();
        $this->assertEquals(100.0, $bill->fresh()->balance());
        $this->assertSame('SCN', substr(SupplierBill::where('kind', 'CREDIT_NOTE')->value('bill_number'), 0, 3));

        $this->post(route('bills.credit.store', $bill), ['supplier_bill_number' => 'CN-2', 'reason' => 'Too much', 'lines' => [['description' => 'Pig iron', 'quantity' => 2, 'unit_price' => 100]]])->assertSessionHasErrors('bill');
        $this->assertEquals(100.0, $bill->fresh()->balance());
    }

    public function test_a_paid_bill_cannot_be_voided_but_an_unpaid_one_can(): void
    {
        $supplier = $this->supplier();
        $paid = $this->posted($supplier, 100, 1, 0);
        $this->post(route('supplier-payments.store'), ['supplier_id' => $supplier->id, 'paid_on' => '2026-10-10', 'amount' => 100, 'alloc' => [$paid->id => 100]]);
        $this->post(route('bills.void', $paid), ['reason' => 'Mistake'])->assertSessionHasErrors('bill');

        $open = $this->posted($supplier, 100, 1, 0);
        $this->post(route('bills.void', $open), ['reason' => 'Entered twice'])->assertSessionHasNoErrors();
        $this->assertSame('VOID', $open->fresh()->status);
    }

    public function test_ageing_and_the_statement_show_what_is_owed(): void
    {
        $supplier = $this->supplier();
        $this->posted($supplier, 100, 1, 0, now()->subDays(80)->toDateString());   // due 50 days ago
        $this->posted($supplier, 40, 1, 0, now()->toDateString());                  // not yet due

        $rows = app(Payables::class)->ageing();

        $this->assertCount(1, $rows);
        $this->assertEquals(140.0, $rows[0]['total']);
        $this->assertEquals(100.0, $rows[0]['buckets']['d60']);
        $this->assertEquals(40.0, $rows[0]['buckets']['current']);
        $this->assertEquals(140.0, app(Payables::class)->statement($supplier)['balance']);
    }

    public function test_the_screens_open(): void
    {
        $supplier = $this->supplier();
        $bill = $this->posted($supplier);
        $this->post(route('supplier-payments.store'), ['supplier_id' => $supplier->id, 'paid_on' => '2026-10-10', 'amount' => 10, 'alloc' => [$bill->id => 5]]);
        $payment = SupplierPayment::firstOrFail();

        $this->get(route('bills.index'))->assertOk()->assertSee($bill->bill_number);
        $this->get(route('bills.create'))->assertOk();
        $this->get(route('bills.show', $bill))->assertOk();
        $this->get(route('bills.credit', $bill))->assertOk();
        $this->get(route('supplier-payments.index'))->assertOk()->assertSee($payment->payment_number);
        $this->get(route('supplier-payments.create', ['supplier' => $supplier->id]))->assertOk()->assertSee($bill->bill_number);
        $this->get(route('supplier-payments.show', $payment))->assertOk();
        $this->get(route('payables.index'))->assertOk()->assertSee('Acme Pig Iron');
        $this->get(route('payables.statement', $supplier))->assertOk()->assertSee($payment->payment_number);
        $this->get(route('finance.settings'))->assertOk()->assertSee('Supplier bill matching');
    }
}
