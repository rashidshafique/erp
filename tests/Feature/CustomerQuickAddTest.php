<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerQuickAddTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_added_from_the_order_form_comes_back_as_json_for_the_dropdown(): void
    {
        $response = $this->postJson(route('customers.store'), ['code' => 'acme', 'name' => 'Acme Pumps'])->assertCreated();

        $customer = Customer::where('code', 'ACME')->firstOrFail();
        $response->assertJson(['id' => $customer->id, 'label' => 'ACME — Acme Pumps']);
    }

    public function test_duplicate_code_is_refused(): void
    {
        Customer::create(['code' => 'ACME', 'name' => 'Acme', 'active' => true]);

        $this->postJson(route('customers.store'), ['code' => 'ACME', 'name' => 'Other'])->assertJsonValidationErrors('code');
    }

    public function test_order_form_offers_the_quick_add_even_with_no_customers(): void
    {
        $this->get(route('sales-orders.create'))->assertOk()->assertSee('qd-customer', false);
    }
}
