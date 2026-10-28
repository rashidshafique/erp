<?php

namespace Tests\Feature;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Services\QuotationService;
use App\Models\CastingAnalysis;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnquiryEditingTest extends TestCase
{
    use RefreshDatabase;

    private function enquiry(): Enquiry
    {
        $customer = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Acme Pumps', 'payment_terms' => '30 days']);
        $enquiry = Enquiry::create(['customer_id' => $customer->id, 'title' => 'Pump housing', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()]);
        $enquiry->items()->create(['line_no' => 1, 'description' => 'Housing', 'weight_kg' => 10, 'first_batch_qty' => 50, 'family' => 'GREY', 'feasibility_status' => 'OK']);

        return $enquiry->fresh('items');
    }

    private function analysis(): CastingAnalysis
    {
        return CastingAnalysis::create([
            'name' => 'Test', 'status' => 'COMPLETE', 'source_type' => 'STL', 'original_name' => 't.stl', 'file_disk' => 'local', 'file_path' => 'x', 'file_sha256' => 'x', 'file_size' => 1,
            'units' => 'mm', 'quality' => 'DRAFT', 'family' => 'DUCTILE', 'score' => 80, 'score_letter' => 'B',
            'result' => ['hotspots' => [['role' => 'PRIMARY']]],
            'recommendation' => ['weight' => ['casting_kg' => 8.0, 'poured_kg' => 13.0, 'yield_pct' => 61.5], 'tooling' => ['core_count' => 2]],
        ]);
    }

    public function test_the_enquiry_heading_is_edited_and_checked(): void
    {
        $enquiry = $this->enquiry();

        $item = $enquiry->items->first();
        $page = fn (array $over = []) => $over + ['customer_id' => $enquiry->customer_id, 'source_type' => 'DRAWING', 'items' => [['id' => $item->id, 'description' => 'Housing', 'drawing_ref' => 'D-1']]];

        $this->put(route('enquiries.update', $enquiry), $page(['title' => 'Pump housing, rev B', 'customer_reference' => 'RFQ-77']))->assertSessionHasNoErrors();
        $this->assertSame('RFQ-77', $enquiry->fresh()->customer_reference);
        $this->assertSame('Pump housing, rev B', $enquiry->fresh()->title);
        $this->assertSame('FEASIBILITY', $enquiry->fresh()->status, 'saving leaves the status alone');
        $this->put(route('enquiries.update', $enquiry), $page(['items' => [['id' => $item->id, 'description' => '']]]))->assertSessionHasErrors('items.0.description');
        $this->put(route('enquiries.update', $enquiry), $page(['source_type' => 'CARRIER_PIGEON']))->assertSessionHasErrors('source_type');
    }

    public function test_a_part_is_edited_and_an_analysis_linked_and_unlinked_while_the_enquiry_is_open(): void
    {
        $enquiry = $this->enquiry();
        $item = $enquiry->items->first();
        $analysis = $this->analysis();

        $this->put(route('enquiries.items.update', [$enquiry, $item]), ['description' => 'Housing, machined', 'weight_kg' => 12])->assertSessionHasNoErrors();
        $this->assertSame('Housing, machined', $item->fresh()->description);
        $this->put(route('enquiries.items.update', [$enquiry, $item]), ['description' => ''])->assertSessionHasErrors('description');

        $this->post(route('enquiries.items.analysis', [$enquiry, $item]), ['casting_analysis_id' => $analysis->id])->assertSessionHas('success', 'Analysis linked.');
        $this->assertSame($analysis->id, $item->fresh()->casting_analysis_id);
        $this->post(route('enquiries.items.analysis', [$enquiry, $item]), [])->assertSessionHas('success', 'Analysis unlinked.');
        $this->assertNull($item->fresh()->casting_analysis_id);
    }

    public function test_a_part_on_a_live_quotation_cannot_be_removed(): void
    {
        $enquiry = $this->enquiry();
        $item = $enquiry->items->first();
        $second = $enquiry->items()->create(['line_no' => 2, 'description' => 'Cover', 'weight_kg' => 2, 'first_batch_qty' => 20, 'family' => 'GREY', 'feasibility_status' => 'OK']);
        $quotation = app(QuotationService::class)->create($enquiry->fresh());

        $this->delete(route('enquiries.items.destroy', [$enquiry, $item]))->assertSessionHas('error');
        $this->assertSame(2, $enquiry->items()->count());

        $quotation->update(['status' => 'REJECTED']);
        $this->delete(route('enquiries.items.destroy', [$enquiry, $second]))->assertSessionHas('success');
        $this->assertSame(1, $enquiry->items()->count());
    }

    public function test_a_closed_enquiry_cannot_be_changed_through_its_parts(): void
    {
        $enquiry = $this->enquiry();
        $item = $enquiry->items->first();
        $enquiry->update(['status' => 'LOST']);

        $this->put(route('enquiries.items.update', [$enquiry, $item]), ['description' => 'Late change'])->assertNotFound();
        $this->delete(route('enquiries.items.destroy', [$enquiry, $item]))->assertNotFound();
        $this->post(route('enquiries.items.analysis', [$enquiry, $item]), [])->assertNotFound();
    }

    public function test_a_quotation_is_refreshed_rejected_and_accepted_through_its_screens(): void
    {
        $enquiry = $this->enquiry();
        $quotation = app(QuotationService::class)->create($enquiry);
        $item = $enquiry->items->first();
        $item->update(['weight_kg' => 14]);

        $this->post(route('quotations.refresh', $quotation))->assertSessionHas('success');
        $this->assertEquals(14, $quotation->fresh()->lines->first()->weight_kg);

        $this->post(route('quotations.issue', $quotation))->assertSessionHas('success');
        $this->post(route('quotations.refresh', $quotation))->assertStatus(422);                    // issued quotations are fixed

        $this->post(route('quotations.accept', $quotation))->assertRedirect();
        $this->assertSame('ACCEPTED', $quotation->fresh()->status);
        $this->post(route('quotations.accept', $quotation))->assertSessionHas('error');             // not twice

        $second = app(QuotationService::class)->create($this->enquiry());
        $this->post(route('quotations.reject', $second), ['decision_note' => 'Too dear'])->assertStatus(422);       // a draft has not gone to the customer
        $this->post(route('quotations.issue', $second))->assertSessionHas('success');
        $this->post(route('quotations.reject', $second), ['decision_note' => 'Too dear'])->assertSessionHas('success');
        $this->assertSame('REJECTED', $second->fresh()->status);
        $this->assertSame('Too dear', $second->fresh()->decision_note);
    }
}
