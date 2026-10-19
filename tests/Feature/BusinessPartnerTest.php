<?php

namespace Tests\Feature;

use App\Domains\Parties\Models\Party;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPartnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_keeps_its_name_and_contacts_on_its_party(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'phone' => '021-1']);

        $this->assertSame('Acme Pumps', $customer->fresh()->name);
        $this->assertSame('Acme Pumps', Party::firstOrFail()->name);
        $this->assertStringStartsWith('BP', Party::first()->code);

        $customer->update(['name' => 'Acme Pumps Ltd', 'payment_terms' => '30 days']);

        $this->assertSame(1, Party::count());
        $this->assertSame(['Acme Pumps Ltd', '30 days'], [$customer->fresh()->name, $customer->fresh()->payment_terms]);
    }

    public function test_one_party_can_be_both_customer_and_supplier(): void
    {
        $this->post(route('parties.store'), ['name' => 'Beta Metals', 'role_customer' => 1])->assertSessionHasNoErrors();
        $party = Party::firstOrFail();
        $this->assertSame(['customer' => 'Customer'], $party->roles());

        $this->post(route('parties.roles.add', [$party, 'supplier']))->assertSessionHasNoErrors();
        $this->post(route('parties.roles.add', [$party, 'supplier']));

        $party->refresh();
        $this->assertSame(['customer', 'supplier'], array_keys($party->roles()));
        $this->assertSame(1, Party::count());
        $this->assertSame('PENDING', $party->supplier->approval_status);
        $this->assertSame('Beta Metals', $party->supplier->name);
    }

    public function test_customers_and_suppliers_sort_and_filter_by_the_party_name(): void
    {
        Customer::create(['code' => 'B', 'name' => 'Zed', 'active' => true]);
        Customer::create(['code' => 'A', 'name' => 'Alpha', 'active' => true]);
        Customer::create(['code' => 'C', 'name' => 'Gone', 'active' => false]);

        $this->assertSame(['Alpha', 'Zed'], Customer::active()->orderedByName()->get()->pluck('name')->all());
    }
}
