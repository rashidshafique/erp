<?php

namespace Tests\Feature;

use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_orders_filter_by_status_buttons_with_counts_and_search_by_number_or_supplier(): void
    {
        $acme = Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        $morbi = Supplier::create(['name' => 'Morbi Silica Sand', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        foreach ([['PO-1', $acme, 'DRAFT'], ['PO-2', $morbi, 'ISSUED'], ['PO-3', $acme, 'CLOSED']] as [$number, $supplier, $status]) {
            PurchaseOrder::create(['po_number' => $number, 'supplier_id' => $supplier->id, 'order_date' => today(), 'status' => $status, 'currency' => 'PKR']);
        }

        $this->get(route('purchase-orders.index'))->assertOk()->assertSee('Open <span>(2)</span>', false)->assertSee('All <span>(3)</span>', false)->assertSee('PO-3');
        $this->get(route('purchase-orders.index', ['status' => 'OPEN']))->assertSee('PO-1')->assertSee('PO-2')->assertDontSee('PO-3');
        $this->get(route('purchase-orders.index', ['status' => 'DRAFT']))->assertSee('PO-1')->assertDontSee('PO-2');
        $this->get(route('purchase-orders.index', ['status' => 'RECEIVING']))->assertSee('PO-2')->assertDontSee('PO-1');
        $this->get(route('purchase-orders.index', ['search' => 'morbi']))->assertSee('PO-2')->assertDontSee('PO-1')->assertSee('All <span>(1)</span>', false);
    }

    public function test_sales_orders_filter_by_status_buttons_with_counts_and_search_by_customer(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $other = Customer::create(['code' => 'VALV', 'name' => 'Valve Works']);
        foreach ([['SO-1', $customer, 'DRAFT'], ['SO-2', $other, 'IN_PRODUCTION'], ['SO-3', $customer, 'DISPATCHED']] as [$number, $c, $status]) {
            SalesOrder::create(['order_number' => $number, 'customer_id' => $c->id, 'order_date' => today(), 'status' => $status]);
        }

        $this->get(route('sales-orders.index'))->assertOk()->assertSee('Open <span>(2)</span>', false)->assertSee('Dispatched <span>(1)</span>', false);
        $this->get(route('sales-orders.index', ['status' => 'OPEN']))->assertSee('SO-1')->assertSee('SO-2')->assertDontSee('SO-3');
        $this->get(route('sales-orders.index', ['status' => 'DISPATCHED']))->assertSee('SO-3')->assertDontSee('SO-1');
        $this->get(route('sales-orders.index', ['search' => 'valve']))->assertSee('SO-2')->assertDontSee('SO-1');
    }
}
