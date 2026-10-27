<?php

namespace Tests\Feature;

use App\Domains\Finance\Services\CreditLimits;
use App\Domains\Finance\Services\FinanceRules;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Domains\Sales\Services\ContractReviewer;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\User;
use App\Support\Formats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditLimitTest extends TestCase
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
        // 800 owed on an invoice due a month ago
        $invoices = app(InvoiceService::class);
        $invoices->issue($invoices->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => today()->subDays(60)->toDateString(), 'due_date' => today()->subDays(30)->toDateString()],
            [['description' => 'Casting', 'quantity' => 1, 'unit_price' => 800]]));
    }

    /** An order of 10 pieces at 30 (300 before tax). */
    private function order(): SalesOrder
    {
        $product = Product::create(['code' => 'P-'.uniqid(), 'name' => 'Pump casing']);
        $rev = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = SalesOrder::create(['order_number' => 'SO-'.uniqid(), 'customer_id' => $this->customer->id, 'order_date' => today(), 'status' => 'DRAFT']);
        SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $product->id, 'product_revision_id' => $rev->id, 'line_number' => 1, 'ordered_qty' => 10, 'uom' => 'piece', 'unit_price' => 30]);

        return $order->load('lines');
    }

    private function terms(array $values): void
    {
        $this->put(route('finance.credit.update', $this->customer), $values)->assertSessionHasNoErrors();
    }

    public function test_an_order_that_takes_the_customer_over_its_limit_warns_by_default_and_blocks_when_the_rule_says_so(): void
    {
        $this->terms(['credit_limit' => 1000]);

        [[$code, $severity, $message]] = app(ContractReviewer::class)->checkOrder($this->order());
        $this->assertSame(['CREDIT', 'WARN'], [$code, $severity]);
        $this->assertStringContainsString('together '.Formats::number(1100, 2), $message);

        AppSetting::setValue(FinanceRules::CREDIT_CHECK, 'BLOCK');
        $this->assertSame('BLOCK', app(ContractReviewer::class)->checkOrder($this->order())[0][1]);
    }

    public function test_open_orders_count_towards_what_is_owed_unless_the_rule_leaves_them_out(): void
    {
        $this->order();

        $this->assertSame(['invoices' => 800.0, 'on_account' => 0.0, 'orders' => 300.0, 'total' => 1100.0], app(CreditLimits::class)->exposure($this->customer->id));
        AppSetting::setValue(FinanceRules::CREDIT_ORDERS, '0');
        $this->assertSame(800.0, app(CreditLimits::class)->exposure($this->customer->id)['total']);
    }

    public function test_a_hold_stops_a_dispatch_until_released_and_long_overdue_invoices_warn(): void
    {
        $this->terms(['credit_hold' => 1, 'credit_hold_reason' => 'Cheque bounced']);

        $this->post(route('dispatches.store'), ['customer_id' => $this->customer->id, 'qty' => ['x' => 1]])->assertSessionHasErrors('dispatch');
        $this->assertSame('BLOCK', app(CreditLimits::class)->check($this->customer->id)['severity']);

        $this->terms(['credit_hold' => 1, 'credit_hold_reason' => 'Cheque bounced', 'credit_release_until' => today()->addDay()->toDateString()]);
        $this->assertSame('PASS', app(CreditLimits::class)->check($this->customer->id)['severity']);

        $this->terms(['credit_hold' => 0]);
        AppSetting::setValue(FinanceRules::CREDIT_OVERDUE_DAYS, '14');
        $check = app(CreditLimits::class)->check($this->customer->id);
        $this->assertSame('WARN', $check['severity']);
        $this->assertStringContainsString('overdue by more than 14 days', $check['messages'][0]);
    }

    public function test_the_partner_page_shows_the_credit_position(): void
    {
        $this->terms(['credit_limit' => 1000]);

        $this->get(route('parties.show', $this->customer->party_id))->assertOk()->assertSee('Credit limit')->assertSee('Still available')->assertSee(Formats::number(200, 2));
    }
}
