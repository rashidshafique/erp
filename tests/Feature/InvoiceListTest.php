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
        $list->assertSee('INV-LATE')->assertSee('Part paid')->assertSee('Overdue')->assertSeeText('Drafts 1')->assertSeeText('Unpaid 2')->assertSeeText('Overdue 1')->assertSeeText('Paid 1');

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

    public function test_the_date_menu_limits_the_list_to_a_preset_a_day_or_a_range(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'active' => true]);
        $due = today()->addDays(30)->toDateString();
        $this->invoice($customer, 'INV-NOW', 'ISSUED', 100, $due);
        $this->invoice($customer, 'INV-2MO', 'ISSUED', 100, $due, ['invoice_date' => today()->subMonths(2)]);
        $this->invoice($customer, 'INV-OLD', 'ISSUED', 100, $due, ['invoice_date' => today()->subYears(2)]);

        $list = Livewire::test('invoice-list')->assertDontSee('INV-OLD');
        $list->call('setPeriod', '1m')->assertSee('INV-NOW')->assertDontSee('INV-2MO')->assertSee('Last month');
        $list->call('setPeriod', '3m')->assertSee('INV-2MO')->assertDontSee('INV-OLD');
        $list->call('setPeriod', 'all')->assertSee('INV-OLD')->assertSee('All dates');

        $day = today()->subMonths(2)->toDateString();
        $list->call('setRange', $day, $day)->assertSet('period', 'day')->assertSee('INV-2MO')->assertDontSee('INV-NOW');
        $list->call('setRange', today()->toDateString(), today()->subYears(3)->toDateString())   // picked back to front
            ->assertSet('period', 'range')->assertSet('from', today()->subYears(3)->toDateString())->assertSee('INV-OLD')->assertSee('INV-NOW');
        $list->call('setRange', 'not-a-date', $day)->assertSet('period', 'range');
        $list->call('setPeriod', 'nonsense')->assertSet('period', 'fy');
    }

    public function test_the_list_sorts_by_a_column_and_a_second_click_turns_it_round(): void
    {
        $acme = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'active' => true]);
        $zeta = Customer::create(['code' => 'ZETA', 'name' => 'Zeta Castings', 'active' => true]);
        $this->invoice($zeta, 'INV-SMALL', 'ISSUED', 100, today()->addDays(9)->toDateString());
        $this->invoice($acme, 'INV-BIG', 'ISSUED', 900, today()->addDays(9)->toDateString());

        $list = Livewire::test('invoice-list')->call('sortBy', 'total')->assertSet('dir', 'desc')->assertSeeInOrder(['INV-BIG', 'INV-SMALL'])->assertSee('Total, largest first');
        $list->call('sortBy', 'total')->assertSet('dir', 'asc')->assertSeeInOrder(['INV-SMALL', 'INV-BIG']);
        $list->call('sortBy', 'customer')->assertSet('dir', 'asc')->assertSeeInOrder(['INV-BIG', 'INV-SMALL']);
        $list->call('sortBy', 'nonsense')->assertSet('sort', 'customer');
    }
}
