<?php

namespace Tests\Feature;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Enquiry\Models\Enquiry;
use App\Models\Customer;
use App\Models\Pattern;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternDevelopmentTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        return Customer::create(['code' => 'ORB', 'name' => 'Orbis Auto', 'active' => true]);
    }

    private function product(): Product
    {
        return Product::create(['code' => 'PRD-9', 'name' => 'Bearing housing', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'active' => true]);
    }

    private function pattern(Customer $owner): Pattern
    {
        return Pattern::create(['code' => 'PT-0287', 'name' => 'Bearing housing BH-210', 'type' => 'PRODUCTION', 'owner_customer_id' => $owner->id, 'status' => 'ACTIVE']);
    }

    public function test_the_dialog_opens_over_the_list_from_each_place_with_the_right_type(): void
    {
        $customer = $this->customer();
        $prospect = Customer::create(['code' => 'NEW1', 'name' => 'Newcomer Ltd', 'active' => true, 'is_prospect' => true]);
        $enquiry = Enquiry::create(['customer_id' => $prospect->id, 'title' => 'Water pump housing', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()]);
        $established = Enquiry::create(['customer_id' => $customer->id, 'title' => 'Second part', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()]);
        $product = $this->product();
        $pattern = $this->pattern($customer);

        $this->get(route('developments.create', ['from' => 'enquiry:'.$enquiry->id]))->assertOk()->assertSeeHtml('<dialog class="qd dv-modal"')
            ->assertSeeHtml('name="type" value="NEW_PART" checked')->assertSee('Opened from enquiry '.$enquiry->code)->assertSee('Stages for this type');
        $this->get(route('developments.create', ['from' => 'enquiry:'.$established->id]))->assertOk()
            ->assertSeeHtml('name="type" value="NEW_PRODUCT" checked');
        $this->get(route('developments.create', ['from' => 'pattern:'.$pattern->id]))->assertOk()
            ->assertSeeHtml('name="type" value="REPLACEMENT" checked')->assertSee('Opened from pattern register PT-0287');
        $this->get(route('developments.create', ['from' => 'product:'.$product->id]))->assertOk()
            ->assertSeeHtml('name="type" value="MODIFICATION" checked');
        $this->get(route('developments.create', ['from' => 'customer:'.$customer->id]))->assertOk()
            ->assertSeeHtml('name="type" value="NEW_PRODUCT" checked')->assertSee('Opened from customer Orbis Auto');
        $this->get(route('developments.create', ['from' => 'products']))->assertOk()->assertSee('Opened from products list');
    }

    public function test_a_development_from_an_enquiry_takes_its_customer_and_part_and_starts_at_the_first_stage(): void
    {
        $customer = $this->customer();
        $enquiry = Enquiry::create(['customer_id' => $customer->id, 'title' => 'Water pump housing', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()]);
        $enquiry->items()->create(['line_no' => 1, 'description' => 'Pump housing', 'feasibility_status' => 'PENDING']);

        $this->post(route('developments.store'), ['type' => 'NEW_PART', 'enquiry_id' => $enquiry->id, 'opened_from' => 'enquiry '.$enquiry->code])->assertSessionHasNoErrors();

        $d = PatternDevelopment::firstOrFail();
        $this->assertStringStartsWith('PD-', $d->code);
        $this->assertSame($customer->id, $d->customer_id);
        $this->assertSame('Pump housing', $d->title);
        $this->assertSame('Enquiry review', $d->stageName());
        $this->assertSame('OPEN', $d->status);
        $this->assertStringContainsString('Opened from enquiry', $d->events->first()->text);
    }

    public function test_each_type_asks_for_what_it_needs(): void
    {
        $customer = $this->customer();
        $this->post(route('developments.store'), ['type' => 'NEW_PART', 'title' => 'Valve'])->assertSessionHasErrors('customer_id');
        $this->post(route('developments.store'), ['type' => 'REPLACEMENT'])->assertSessionHasErrors('pattern_id');
        $this->post(route('developments.store'), ['type' => 'MODIFICATION', 'product_id' => $this->product()->id])->assertSessionHasErrors('drawing_change');

        $pattern = $this->pattern($customer);
        $this->post(route('developments.store'), ['type' => 'REPLACEMENT', 'pattern_id' => $pattern->id, 'reason' => 'LIFE_LIMIT', 'who_pays' => 'CUSTOMER'])->assertSessionHasNoErrors();
        $d = PatternDevelopment::firstOrFail();
        $this->assertSame($customer->id, $d->customer_id, 'the pattern owner is the customer');
        $this->assertSame('Replace PT-0287 Bearing housing BH-210', $d->title);
        $this->assertSame('Cost approval', $d->stageName());
    }

    public function test_it_moves_through_its_stages_takes_notes_and_is_done_at_the_last_one(): void
    {
        $this->customer();
        $this->post(route('developments.store'), ['type' => 'NEW_PRODUCT', 'title' => 'Manifold', 'customer_id' => Customer::firstOrFail()->id]);
        $d = PatternDevelopment::firstOrFail();
        $this->assertSame(['Design discussion', 'Pattern build', 'Trial & sample', 'Approved'], $d->stages());

        $this->post(route('developments.notes', $d), ['text' => 'Sent the CAD'])->assertSessionHasNoErrors();
        foreach (range(1, 3) as $_) {
            $this->post(route('developments.advance', $d))->assertRedirect();
        }
        $d->refresh();
        $this->assertSame('DONE', $d->status);
        $this->assertSame('Approved', $d->stageName());
        $this->post(route('developments.advance', $d))->assertStatus(422);
        $this->assertTrue($d->events()->where('text', 'Sent the CAD')->exists());

        $this->get(route('developments.show', $d))->assertOk()->assertSee('Sent the CAD');
        $this->get(route('developments.index'))->assertOk()->assertSee($d->code)->assertSeeHtml('data-table="developments"');
    }

    public function test_cancelling_closes_it(): void
    {
        $this->post(route('developments.store'), ['type' => 'NEW_PRODUCT', 'title' => 'Manifold', 'customer_id' => $this->customer()->id]);
        $d = PatternDevelopment::firstOrFail();

        $this->post(route('developments.cancel', $d), ['reason' => 'Customer withdrew'])->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $d->fresh()->status);
        $this->post(route('developments.advance', $d))->assertStatus(422);
    }

    public function test_start_development_is_offered_on_the_enquiry_pattern_product_and_customer_screens(): void
    {
        $customer = $this->customer();
        $enquiry = Enquiry::create(['customer_id' => $customer->id, 'title' => 'Water pump housing', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()]);
        $pattern = $this->pattern($customer);

        $this->get(route('enquiries.show', $enquiry))->assertOk()->assertSee('Start pattern development');
        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('Replace pattern');
        $this->get(route('products.index'))->assertOk()->assertSee('Start development');
    }
}
