<?php

namespace Tests\Feature;

use App\Domains\Sales\Models\SalesOrder;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_prospect_added_from_an_enquiry_asks_for_little_and_comes_back_marked_as_a_prospect(): void
    {
        $response = $this->postJson(route('customers.store'), ['prospect' => 1, 'name' => 'Newcomer Ltd', 'contact_name' => 'Sara Khan'])->assertCreated();

        $customer = Customer::findOrFail($response->json('id'));
        $this->assertTrue($customer->is_prospect);
        $this->assertTrue($customer->isFirstTimeCustomer());
        $response->assertJson(['label' => 'Newcomer Ltd · prospect']);
    }

    public function test_a_name_already_on_file_is_refused_with_the_existing_record_named(): void
    {
        $existing = Customer::create(['code' => 'ORB', 'name' => 'Orbis Auto', 'active' => true, 'is_prospect' => true]);

        $this->postJson(route('customers.store'), ['prospect' => 1, 'name' => '  orbis AUTO '])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->assertStringContainsString($existing->code, $this->postJson(route('customers.store'), ['prospect' => 1, 'name' => 'Orbis Auto'])->json('errors.name.0'));
        $this->assertSame(1, Customer::count());
    }

    public function test_the_order_form_quick_add_still_allows_a_repeated_name_and_labels_with_the_code(): void
    {
        Customer::create(['code' => 'ORB', 'name' => 'Orbis Auto', 'active' => true]);

        $this->postJson(route('customers.store'), ['name' => 'Orbis Auto'])->assertCreated();
    }

    public function test_the_first_sales_order_makes_a_prospect_a_customer(): void
    {
        $customer = Customer::create(['code' => 'NEW1', 'name' => 'Newcomer Ltd', 'active' => true, 'is_prospect' => true]);
        $other = Customer::create(['code' => 'NEW2', 'name' => 'Other Ltd', 'active' => true, 'is_prospect' => true]);

        SalesOrder::create(['order_number' => 'SO-2026-0001', 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'DRAFT']);

        $this->assertFalse($customer->fresh()->is_prospect);
        $this->assertSame('active', $customer->fresh()->stage());
        $this->assertTrue($other->fresh()->is_prospect);
    }

    public function test_the_intake_page_offers_add_prospect_and_marks_prospects_in_the_picker(): void
    {
        Customer::create(['code' => 'ORB', 'name' => 'Orbis Auto', 'active' => true]);
        Customer::create(['code' => 'NEW1', 'name' => 'Newcomer Ltd', 'active' => true, 'is_prospect' => true]);

        $this->get(route('enquiries.create'))->assertOk()
            ->assertSee('Add prospect')->assertSee('Add a prospect')->assertSeeHtml('name="prospect" value="1"')
            ->assertSee('Newcomer Ltd · prospect', false)->assertDontSee('Orbis Auto · prospect', false);
    }
}
