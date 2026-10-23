<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DispatchListTest extends TestCase
{
    use RefreshDatabase;

    private function dispatch(Customer $customer, string $number): void
    {
        DB::table('dispatches')->insert(['id' => (string) Str::uuid(), 'dispatch_number' => $number, 'customer_id' => $customer->id, 'status' => 'DISPATCHED', 'dispatched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_list_can_be_filtered_to_one_customer(): void
    {
        $acme = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'active' => true]);
        $beta = Customer::create(['code' => 'BETA', 'name' => 'Beta Valves', 'active' => true]);
        Customer::create(['code' => 'NONE', 'name' => 'Never Shipped', 'active' => true]);
        $this->dispatch($acme, 'DSP-ACME-1');
        $this->dispatch($beta, 'DSP-BETA-1');

        $this->get(route('dispatches.index'))->assertOk()->assertSee('DSP-ACME-1')->assertSee('DSP-BETA-1')
            ->assertSee('Acme Pumps (ACME)')->assertDontSee('Never Shipped', 'only customers with a dispatch are offered');
        $this->get(route('dispatches.index', ['customer' => $acme->id]))->assertOk()->assertSee('DSP-ACME-1')->assertDontSee('DSP-BETA-1');
        $this->get(route('dispatches.index', ['customer' => 'not-a-uuid']))->assertOk()->assertSee('DSP-BETA-1');
    }

    public function test_the_customer_list_links_the_last_dispatch_date_to_that_customers_dispatches(): void
    {
        $acme = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'active' => true]);
        $this->dispatch($acme, 'DSP-ACME-1');

        $this->get(route('customers.index'))->assertOk()->assertSee(route('dispatches.index', ['customer' => $acme->id]), false);
    }
}
