<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\ReceiptAllocation;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceListTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(Customer $customer, string $number, string $status, float $total, string $due, array $over = []): Invoice
    {
        return Invoice::create($over + [
            'invoice_number' => $number, 'kind' => 'INVOICE', 'status' => $status, 'customer_id' => $customer->id,
            'invoice_date' => today(), 'due_date' => $due, 'currency' => 'PKR', 'subtotal' => $total, 'tax_total' => 0, 'total' => $total,
        ]);
    }

    public function test_tabs_count_and_filter_invoices_by_where_they_stand_and_the_panel_follows_the_row(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'active' => true]);
        $draft = $this->invoice($customer, 'INV-D', 'DRAFT', 100, today()->addDays(10)->toDateString());
        $late = $this->invoice($customer, 'INV-LATE', 'ISSUED', 500, today()->subDays(5)->toDateString());
        $part = $this->invoice($customer, 'INV-PART', 'ISSUED', 1000, today()->addDays(20)->toDateString());
        $paid = $this->invoice($customer, 'INV-PAID', 'ISSUED', 200, today()->addDays(20)->toDateString());
        $receipt = Receipt::create(['receipt_number' => 'REC-1', 'customer_id' => $customer->id, 'received_on' => today(), 'amount' => 600, 'method' => 'BANK']);
        ReceiptAllocation::create(['receipt_id' => $receipt->id, 'invoice_id' => $part->id, 'amount' => 400]);
        ReceiptAllocation::create(['receipt_id' => $receipt->id, 'invoice_id' => $paid->id, 'amount' => 200]);

        $list = Livewire::test('invoice-list');
        $list->assertSee('INV-LATE')->assertSee('Part paid')->assertSee('Overdue')->assertSee('Drafts 1')->assertSee('Unpaid 2')->assertSee('Overdue 1')->assertSee('Paid 1');

        $list->call('showTab', 'OVERDUE')->assertSee('INV-LATE')->assertDontSee('INV-PART');
        $list->call('showTab', 'DRAFT')->assertSee('Review and post');
        $list->call('showTab', 'OPEN')->call('select', $part->id)->assertSee('Receive payment')->assertSee('REC-1')->assertSee('600');
        $list->call('showTab', 'PAID')->assertSee('INV-PAID')->assertDontSee('INV-LATE')->assertSee('Credit note');
    }

    public function test_the_old_status_and_customer_links_still_work(): void
    {
        $acme = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'active' => true]);
        $beta = Customer::create(['code' => 'BETA', 'name' => 'Beta Valves', 'active' => true]);
        $this->invoice($acme, 'INV-A', 'ISSUED', 100, today()->subDay()->toDateString());
        $this->invoice($beta, 'INV-B', 'ISSUED', 100, today()->addDays(9)->toDateString());

        $this->get(route('invoices.index', ['status' => 'OVERDUE']))->assertOk()->assertSee('INV-A')->assertDontSee('INV-B');
        $this->get(route('invoices.index', ['customer' => $beta->id]))->assertOk()->assertSee('INV-B')->assertDontSee('INV-A');
    }

    public function test_search_finds_an_invoice_by_number_customer_or_po(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'active' => true]);
        $this->invoice($customer, 'INV-ONE', 'ISSUED', 100, today()->addDays(9)->toDateString(), ['customer_reference' => 'PO-777']);
        $this->invoice($customer, 'INV-TWO', 'ISSUED', 100, today()->addDays(9)->toDateString());

        Livewire::test('invoice-list')->set('search', 'PO-777')->assertSee('INV-ONE')->assertDontSee('INV-TWO');
    }
}
