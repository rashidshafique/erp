<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\TaxRate;
use App\Domains\Finance\Services\Receivables;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoicingTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $terms = 'Net 45'): Customer
    {
        return Customer::create(['code' => 'C'.Str::random(4), 'name' => 'Acme Pumps', 'active' => true, 'payment_terms' => $terms]);
    }

    private function tax(float $percent = 10): TaxRate
    {
        return TaxRate::create(['name' => 'Sales tax', 'rate_percent' => $percent, 'is_default' => true]);
    }

    /** @return array<string, mixed> */
    private function form(Customer $customer, array $lines, array $extra = []): array
    {
        return $extra + ['customer_id' => $customer->id, 'invoice_date' => '2026-10-01', 'lines' => $lines];
    }

    private function issued(Customer $customer, float $price = 100, float $qty = 2, ?TaxRate $tax = null, string $date = '2026-10-01'): Invoice
    {
        $this->post(route('invoices.store'), $this->form($customer, [['description' => 'Pump body', 'quantity' => $qty, 'unit_price' => $price, 'tax_rate_id' => $tax?->id]], ['invoice_date' => $date]))->assertSessionHasNoErrors();
        $invoice = Invoice::where('status', 'DRAFT')->firstOrFail();
        $this->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();

        return $invoice->fresh();
    }

    public function test_a_manual_invoice_is_priced_taxed_numbered_and_due_from_the_customers_terms(): void
    {
        $customer = $this->customer('Net 45');
        $tax = $this->tax(10);

        $this->post(route('invoices.store'), $this->form($customer, [
            ['description' => 'Pump body', 'quantity' => 3, 'unit_price' => 100.5, 'tax_rate_id' => $tax->id],
            ['description' => 'Tooling charge', 'quantity' => 1, 'unit_price' => 50],
            ['description' => '', 'quantity' => 1, 'unit_price' => 999],
        ]))->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();

        $this->assertSame([351.5, 30.15, 381.65, 'DRAFT', null], [$invoice->subtotal, $invoice->tax_total, $invoice->total, $invoice->status, $invoice->invoice_number], 'the blank line is dropped; tax only on the taxed line');
        $this->get(route('invoices.edit', $invoice))->assertOk();

        $this->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertMatchesRegularExpression('/^INV-\d{4}-0001$/', $invoice->invoice_number);
        $this->assertSame('2026-11-15', $invoice->due_date->toDateString(), 'Net 45 from 1 October');
        $this->put(route('invoices.update', $invoice), $this->form($customer, [['description' => 'x', 'quantity' => 1, 'unit_price' => 1]]))->assertSessionHasErrors('invoice');
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee($invoice->invoice_number);
        $this->get(route('invoices.print', $invoice))->assertOk()->assertSee('Pump body');

        $second = $this->issued($customer);
        $this->assertStringEndsWith('0002', $second->invoice_number);
    }

    public function test_an_invoice_can_be_raised_from_a_sales_order_for_what_is_not_yet_invoiced(): void
    {
        $customer = $this->customer();
        $product = Product::create(['code' => 'PB-1', 'name' => 'Pump body']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = SalesOrder::create(['order_number' => 'SO-1', 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'CONFIRMED']);
        $line = SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $product->id, 'product_revision_id' => $revision->id, 'line_number' => 1, 'ordered_qty' => 10, 'unit_price' => 25, 'uom' => 'PCS']);

        $this->get(route('invoices.create', ['order' => $order->id]))->assertOk()->assertSee('PB-1 Pump body')->assertSee('Lines from order SO-1');

        $this->post(route('invoices.store'), $this->form($customer, [['description' => 'PB-1 Pump body', 'quantity' => 4, 'unit_price' => 25, 'sales_order_line_id' => $line->id]], ['source_type' => 'sales_order', 'source_id' => $order->id]));
        $this->assertSame(4.0, app(\App\Domains\Finance\Services\InvoiceService::class)->invoicedQuantity($line->id));
        $this->get(route('invoices.create', ['order' => $order->id]))->assertOk()->assertSee('quantity', false);

        $this->assertSame(6.0, (float) collect(app(\App\Domains\Finance\Services\InvoiceService::class)->linesFromOrder($order->fresh('lines.product')))->first()['quantity']);
        Invoice::firstOrFail()->update(['status' => 'VOID']);
        $this->assertSame(0.0, app(\App\Domains\Finance\Services\InvoiceService::class)->invoicedQuantity($line->id), 'a voided invoice frees the quantity');
    }

    public function test_receipts_pay_invoices_oldest_first_and_cannot_overpay_one(): void
    {
        $customer = $this->customer('Net 30');
        $old = $this->issued($customer, 100, 1, null, '2026-08-01');
        $new = $this->issued($customer, 200, 1, null, '2026-10-01');

        $this->post(route('receipts.store'), ['customer_id' => $customer->id, 'received_on' => '2026-10-10', 'amount' => 150, 'alloc' => [$old->id => 100, $new->id => 50]])->assertSessionHasNoErrors();
        $this->assertSame([0.0, 150.0], [$old->fresh()->balance(), $new->fresh()->balance()]);
        $this->post(route('receipts.store'), ['customer_id' => $customer->id, 'received_on' => '2026-10-10', 'amount' => 500, 'alloc' => [$new->id => 500]])->assertSessionHasErrors('receipt');
        $this->post(route('receipts.store'), ['customer_id' => $customer->id, 'received_on' => '2026-10-10', 'amount' => 10, 'alloc' => [$new->id => 60]])->assertSessionHasErrors('receipt');

        $this->post(route('receipts.store'), ['customer_id' => $customer->id, 'received_on' => '2026-10-11', 'amount' => 300, 'alloc' => [$new->id => 150]])->assertSessionHasNoErrors();
        $receipt = \App\Domains\Finance\Models\Receipt::where('amount', 300)->firstOrFail();
        $this->assertSame(150.0, $receipt->unallocated(), 'the rest stays on account');
        $this->assertSame(150.0, app(Receivables::class)->onAccount($customer->id));
        $this->get(route('receipts.show', $receipt))->assertOk()->assertSee($receipt->receipt_number);
        $this->get(route('receipts.create', ['customer' => $customer->id]))->assertOk();
        $this->post(route('invoices.void', $new->fresh()), ['reason' => 'mistake'])->assertSessionHasErrors('invoice');
    }

    public function test_a_credit_note_reduces_the_invoice_and_cannot_exceed_it(): void
    {
        $customer = $this->customer();
        $tax = $this->tax(10);
        $invoice = $this->issued($customer, 100, 2, $tax);   // 200 + 20 tax = 220
        $line = ['description' => 'Pump body (2 rejected)', 'quantity' => 1, 'unit_price' => 100, 'tax_rate_id' => $tax->id];

        $this->get(route('invoices.credit', $invoice))->assertOk();
        $this->post(route('invoices.credit.store', $invoice), ['reason' => 'Rejected on arrival', 'lines' => [$line]])->assertSessionHasNoErrors();
        $note = Invoice::where('kind', 'CREDIT_NOTE')->firstOrFail();
        $this->assertMatchesRegularExpression('/^CN-\d{4}-0001$/', $note->invoice_number);
        $this->assertSame([110.0, 110.0], [$note->total, $invoice->fresh()->balance()]);

        $big = ['description' => 'Too much', 'quantity' => 2, 'unit_price' => 100, 'tax_rate_id' => $tax->id];
        $this->post(route('invoices.credit.store', $invoice), ['reason' => 'Again', 'lines' => [$big]])->assertSessionHasErrors('invoice');
        $this->assertSame(1, Invoice::where('kind', 'CREDIT_NOTE')->count(), 'the refused credit note is not left behind');
        $this->post(route('invoices.void', $invoice), ['reason' => 'a mistake'])->assertSessionHasErrors('invoice');
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee($note->invoice_number);
    }

    public function test_ageing_buckets_and_the_statement_balance(): void
    {
        $customer = $this->customer('Net 30');
        $this->issued($customer, 100, 1, null, today()->subDays(100)->toDateString());   // 70 days late
        $this->issued($customer, 50, 1, null, today()->subDays(10)->toDateString());      // not yet due
        $this->post(route('receipts.store'), ['customer_id' => $customer->id, 'received_on' => today()->toDateString(), 'amount' => 20])->assertSessionHasNoErrors();

        $rows = app(Receivables::class)->ageing();
        $this->assertCount(1, $rows);
        $this->assertSame([100.0, 50.0, 150.0, 20.0], [$rows[0]['buckets']['d90'], $rows[0]['buckets']['current'], $rows[0]['total'], $rows[0]['on_account']]);

        $this->get(route('receivables.index'))->assertOk()->assertSee('Acme Pumps');
        $this->get(route('receivables.statement', $customer))->assertOk()->assertSee('130.00');
        $this->assertSame(130.0, app(Receivables::class)->statement($customer)['balance'], 'owed 150 less 20 on account');
        $this->get(route('invoices.index', ['status' => 'OVERDUE']))->assertOk();
        $this->get(route('invoices.index', ['status' => 'OPEN']))->assertOk();
    }

    public function test_a_dispatch_can_be_invoiced_and_its_items_are_not_invoiced_twice(): void
    {
        $customer = $this->customer();
        $dispatchId = (string) Str::uuid();
        DB::table('dispatches')->insert(['id' => $dispatchId, 'dispatch_number' => 'DSP-1', 'customer_id' => $customer->id, 'status' => 'DISPATCHED', 'dispatched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('invoices.create', ['dispatch' => $dispatchId]))->assertOk()->assertSee('Everything on dispatch DSP-1 is already invoiced.');
        $this->get(route('dispatches.show', $dispatchId))->assertOk()->assertSee('Raise invoice');
    }

    public function test_settings_save_and_only_one_tax_rate_is_the_default(): void
    {
        $this->put(route('finance.settings.update'), ['due_days' => 14, 'currency' => 'USD'])->assertSessionHasNoErrors();
        $this->assertSame(14, \App\Domains\Finance\Services\FinanceRules::dueDays());
        $this->assertSame(21, \App\Domains\Finance\Services\FinanceRules::daysFromTerms('21 days'));

        $this->post(route('finance.tax-rates.store'), ['name' => 'Standard', 'rate_percent' => 17, 'is_default' => 1])->assertSessionHasNoErrors();
        $this->post(route('finance.tax-rates.store'), ['name' => 'Reduced', 'rate_percent' => 5, 'is_default' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['Reduced'], TaxRate::where('is_default', true)->pluck('name')->all());
        $this->get(route('finance.tax-year'))->assertOk()->assertSee('Reduced');
        $this->get(route('finance.settings'))->assertOk()->assertDontSee('Reduced');
    }
}
