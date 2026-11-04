<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Services\Receivables;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivablesSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_summary_counts_unapplied_receipts_overdue_invoices_and_this_months_collections(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'active' => true]);
        Invoice::create(['invoice_number' => 'INV-LATE', 'kind' => 'INVOICE', 'status' => 'ISSUED', 'customer_id' => $customer->id, 'invoice_date' => today(), 'due_date' => today()->subDays(5), 'currency' => 'PKR', 'subtotal' => 500, 'tax_total' => 0, 'total' => 500]);
        Receipt::create(['receipt_number' => 'REC-1', 'customer_id' => $customer->id, 'received_on' => today(), 'amount' => 600, 'method' => 'BANK']);

        $this->assertSame(['unapplied' => 600.0, 'unapplied_count' => 1, 'overdue' => 500.0, 'overdue_count' => 1, 'collected' => 600.0], app(Receivables::class)->summary());
        $this->get(route('receipts.index'))->assertOk()->assertSee('Unapplied receipts');
        $this->get(route('receivables.index'))->assertOk()->assertSee('Collected');
    }
}
