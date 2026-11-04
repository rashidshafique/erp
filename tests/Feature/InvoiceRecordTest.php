<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\ReceiptAllocation;
use App\Domains\Finance\Models\TaxRate;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceRecordTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): Invoice
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'active' => true, 'payment_terms' => 'Net 30']);
        $tax = TaxRate::create(['name' => 'Sales tax', 'rate_percent' => 10, 'is_default' => true]);
        $this->post(route('invoices.store'), ['customer_id' => $customer->id, 'invoice_date' => today()->toDateString(), 'customer_reference' => 'PO-55',
            'lines' => [['description' => 'Pump body', 'quantity' => 2, 'unit_price' => 100, 'tax_rate_id' => $tax->id]]])->assertSessionHasNoErrors();

        return Invoice::firstOrFail();
    }

    public function test_a_draft_shows_the_page_beside_its_checks_and_the_journal_posting_will_make(): void
    {
        $invoice = $this->draft();

        $this->get(route('invoices.show', $invoice))->assertOk()
            ->assertSee('Post only')->assertSee('Back to edit')->assertSee('Checks')->assertSee('Posting')->assertSee('Balanced')->assertSee(route('invoices.print', $invoice), false);
    }

    public function test_a_posted_invoice_shows_its_lines_payments_linked_documents_and_history(): void
    {
        $invoice = $this->draft();
        $this->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();
        $receipt = Receipt::create(['receipt_number' => 'REC-9', 'customer_id' => $invoice->customer_id, 'received_on' => today(), 'amount' => 100, 'method' => 'BANK']);
        ReceiptAllocation::create(['receipt_id' => $receipt->id, 'invoice_id' => $invoice->id, 'amount' => 100]);

        $this->get(route('invoices.show', $invoice))->assertOk()
            ->assertSee($invoice->invoice_number)->assertSee('Part paid')->assertSee('Receive payment')->assertSee('Pump body')
            ->assertSee('REC-9')->assertSee('PO-55')->assertSee('Credit notes')->assertSee('History');
    }

    public function test_the_receipt_form_asks_for_a_customer_then_lists_what_they_owe_to_settle(): void
    {
        $invoice = $this->draft();
        $this->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();

        $this->get(route('receipts.create'))->assertOk()->assertSee('Pick a customer')->assertDontSee('Save receipt');
        $this->get(route('receipts.create', ['customer' => $invoice->customer_id, 'invoice' => $invoice->id]))->assertOk()
            ->assertSee('Save receipt')->assertSee('Settle invoices')->assertSee('1 open invoice')->assertSee($invoice->fresh()->invoice_number)->assertSee('Unallocated');
    }
}
