<?php

namespace Tests\Feature;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Enquiry\Services\QuotationService;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Closing a quotation (design 30e): accepted opens the order and wins the enquiry, lost asks why, no reply is not lost. */
class QuotationCloseTest extends TestCase
{
    use RefreshDatabase;

    private function sent(int $validDays = 17): Quotation
    {
        $c = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Kovar Pumps', 'payment_terms' => 'Net 45']);
        $e = Enquiry::create(['customer_id' => $c->id, 'title' => 'Impeller', 'source_type' => 'DRAWING', 'status' => 'QUOTED', 'received_on' => today()->subDays(20)]);
        $e->items()->create(['line_no' => 1, 'description' => 'Impeller housing 65-160', 'material_text' => 'EN-GJL-250', 'weight_kg' => 11.4, 'annual_qty' => 4000, 'first_batch_qty' => 500, 'family' => 'GREY', 'feasibility_status' => 'OK']);
        $q = app(QuotationService::class)->create($e->fresh('items'));
        $q->update(['status' => 'ISSUED', 'issued_at' => now()->subDays(10), 'valid_until' => today()->addDays($validDays), 'delivery_weeks' => 8]);

        return $q->fresh('lines');
    }

    public function test_the_dialog_offers_the_three_outcomes_and_what_each_will_do(): void
    {
        $q = $this->sent();

        $this->get(route('quotations.close', $q))->assertOk()
            ->assertSeeText('Accepted')->assertSeeText('Lost')->assertSeeText('No reply')->assertSeeText('What they sent')->assertSeeText('The order this makes')
            ->assertSeeText('Marking it accepted will')->assertSeeText('close enquiry '.$q->enquiry->code.' as won')->assertSeeText('lock rev 1')->assertSee('Mark accepted and open the order')
            ->assertSeeText('Nothing is sent to the customer from here');
    }

    public function test_accepting_makes_the_order_with_their_reference_wins_the_enquiry_and_locks_the_revision(): void
    {
        $q = $this->sent();

        $this->post(route('quotations.close.store', $q), ['outcome' => 'ACCEPTED', 'customer_po' => '4500-88213', 'customer_po_date' => '2026-11-17'])->assertRedirect();

        $q = $q->fresh();
        $this->assertSame('ACCEPTED', $q->status);
        $this->assertSame('ACCEPTED', $q->outcome);
        $this->assertSame('4500-88213', $q->customer_po);
        $order = SalesOrder::findOrFail($q->sales_order_id);
        $this->assertSame('4500-88213', $order->customer_reference);
        $this->assertSame('WON', $q->enquiry->fresh()->status);
        $this->assertStringContainsString('4500-88213', $q->events()->where('kind', 'NOTE')->first()->text);
        $this->get(route('quotations.revise', $q))->assertStatus(422);
        $this->get(route('quotations.close', $q))->assertStatus(422);
    }

    public function test_accepting_moves_a_development_opened_earlier_on_to_its_build_stage(): void
    {
        $q = $this->sent();
        $q->enquiry->update(['pattern_route' => 'NEW']);
        $dev = PatternDevelopment::create(['type' => 'NEW_PART', 'title' => 'Impeller', 'enquiry_id' => $q->enquiry_id, 'enquiry_item_id' => $q->enquiry->items->first()->id, 'customer_id' => $q->enquiry->customer_id, 'pattern_by' => 'DECIDE_LATER', 'stage_index' => 1]);

        $this->post(route('quotations.close.store', $q), ['outcome' => 'ACCEPTED'])->assertRedirect();

        $dev->refresh();
        $this->assertSame('Pattern build', $dev->stageName());
        $this->assertStringContainsString('Moved to Pattern build', $dev->events()->where('kind', 'STAGE')->first()->text);
    }

    public function test_losing_it_needs_a_reason_closes_the_enquiry_with_it_and_feeds_the_lost_figures(): void
    {
        $q = $this->sent();

        $this->post(route('quotations.close.store', $q), ['outcome' => 'LOST'])->assertSessionHas('error');
        $this->assertSame('ISSUED', $q->fresh()->status);

        $this->post(route('quotations.close.store', $q), ['outcome' => 'LOST', 'lost_reason' => 'PRICE', 'price_gap_pct' => 6, 'note' => 'Import quote was lower'])->assertRedirect();

        $q = $q->fresh();
        $this->assertSame('REJECTED', $q->status);
        $this->assertSame('LOST', $q->outcome);
        $this->assertSame('PRICE', $q->lost_reason);
        $this->assertEqualsWithDelta(6.0, $q->price_gap_pct, 0.001);
        $enquiry = $q->enquiry->fresh();
        $this->assertSame('LOST', $enquiry->status);
        $this->assertSame('PRICE', $enquiry->close_reason);
        $this->assertStringContainsString('about 6% off', $q->events()->first()->text);
        $this->get(route('quotations.index', ['tab' => 'lost']))->assertSee($q->code)->assertSee('Price');
        $this->get(route('enquiries.index', ['tab' => 'lost']))->assertSee($enquiry->code);
    }

    public function test_no_reply_expires_the_quotation_without_calling_it_lost_or_closing_the_enquiry(): void
    {
        $q = $this->sent();

        $this->post(route('quotations.close.store', $q), ['outcome' => 'NO_REPLY'])->assertRedirect();

        $q = $q->fresh();
        $this->assertSame('EXPIRED', $q->status);
        $this->assertSame('NO_REPLY', $q->outcome);
        $this->assertSame('QUOTED', $q->enquiry->fresh()->status);
        $this->get(route('quotations.index', ['tab' => 'lost']))->assertDontSee($q->code);
    }

    public function test_an_expired_quotation_can_be_closed_as_lost_but_not_accepted(): void
    {
        $q = $this->sent(-3);

        $this->get(route('quotations.close', $q))->assertOk()->assertSee('passed its valid-until date');
        $this->post(route('quotations.close.store', $q), ['outcome' => 'ACCEPTED'])->assertSessionHas('error');
        $this->assertNotSame('ACCEPTED', $q->fresh()->status);
        $this->post(route('quotations.close.store', $q), ['outcome' => 'LOST', 'lost_reason' => 'LEAD_TIME'])->assertRedirect();
        $this->assertSame('REJECTED', $q->fresh()->status);
        $this->assertSame('DELIVERY_LEAD', $q->enquiry->fresh()->close_reason);
    }

    public function test_a_draft_cannot_be_closed(): void
    {
        $q = $this->sent();
        $q->update(['status' => 'DRAFT']);

        $this->get(route('quotations.close', $q))->assertStatus(422);
    }
}
