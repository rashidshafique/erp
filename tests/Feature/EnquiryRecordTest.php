<?php

namespace Tests\Feature;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Services\QuotationService;
use App\Models\Customer;
use App\Models\Pattern;
use App\Models\User;
use App\Notifications\AppNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The enquiries list (design 14a), the record once sent (14b) and closing it as won, lost or declined (14c). */
class EnquiryRecordTest extends TestCase
{
    use RefreshDatabase;

    private function enquiry(array $over = [], array $item = []): Enquiry
    {
        $customer = Customer::create(['code' => 'C'.uniqid(), 'name' => $over['customer'] ?? 'Meridian Auto', 'payment_terms' => '30 days']);
        unset($over['customer']);
        $e = Enquiry::create($over + ['customer_id' => $customer->id, 'title' => 'Brake bracket', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()->subDays(5)]);
        $e->items()->create($item + ['line_no' => 1, 'description' => 'Brake caliper bracket', 'material_text' => 'EN-GJS-450-10', 'weight_kg' => 3.6, 'annual_qty' => 1000, 'first_batch_qty' => 100, 'target_price' => 12, 'family' => 'DUCTILE', 'feasibility_status' => 'OK']);

        return $e->fresh(['items', 'customer']);
    }

    public function test_the_list_shows_stage_pattern_value_reply_by_and_the_tabs_filter_it(): void
    {
        $open = $this->enquiry(['due_on' => today()->addDays(2), 'pattern_route' => 'CUSTOMER']);
        $lost = $this->enquiry(['customer' => 'Kovar Pumps', 'status' => 'LOST', 'close_reason' => 'PRICE', 'closed_at' => now()]);
        $declined = $this->enquiry(['customer' => 'Nordline', 'status' => 'DECLINED', 'close_reason' => 'CAPABILITY', 'closed_at' => now()]);

        $this->get(route('enquiries.index'))->assertOk()
            ->assertSee($open->code)->assertSee('Brake caliper bracket')->assertSee('EN-GJS-450-10 · 1,000 / yr')->assertSee('Feasibility')->assertSee('Customer builds or supplies')
            ->assertSee('2 d')->assertSee('Open 1')->assertSee('Lost 1')->assertSee('Declined 1')->assertSee('Last 90 days')
            ->assertSee('Price')->assertSee('1 declined');
        $this->get(route('enquiries.index', ['tab' => 'lost']))->assertOk()->assertSee($lost->code)->assertDontSee($declined->code)->assertDontSee($open->code);
        $this->get(route('enquiries.index', ['tab' => 'due']))->assertOk()->assertSee($open->code);
        $this->get(route('enquiries.index', ['search' => 'kovar', 'tab' => 'lost']))->assertOk()->assertSee('Kovar Pumps');
        $this->get(route('enquiries.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_a_customer_on_credit_hold_shows_on_hold(): void
    {
        $e = $this->enquiry();
        $e->customer->forceFill(['credit_hold' => true])->save();
        $this->assertSame('ON_HOLD', $e->fresh(['customer', 'quotations'])->stage());
        $this->get(route('enquiries.index'))->assertSee('On hold')->assertSee('Paused');
    }

    public function test_the_record_shows_its_stage_feasibility_quotes_and_links(): void
    {
        $e = $this->enquiry(['customer_reference' => 'MAS-RFQ-88213']);
        app(QuotationService::class)->create($e);
        $this->get(route('enquiries.show', $e))->assertOk()
            ->assertSee('Tooling and pattern')->assertSee('Questions to customer · 0 open')->assertSee('Quotes')->assertSee('Log')
            ->assertSee('Quoting')->assertSee('MAS-RFQ-88213')->assertSee('Edit details')->assertSee('Close enquiry')->assertSee('Start pattern development')
            ->assertSee('0 of 7 signed')->assertSee('On win');
    }

    public function test_the_pattern_decision_questions_and_log_are_kept(): void
    {
        $e = $this->enquiry();
        $pattern = Pattern::create(['code' => 'PT-0302', 'name' => 'Valve bonnet', 'type' => 'PRODUCTION', 'status' => 'ACTIVE']);

        $this->post(route('enquiries.pattern', $e), ['pattern_route' => 'EXISTING'])->assertSessionHasErrors('pattern_id');
        $this->post(route('enquiries.pattern', $e), ['pattern_route' => 'EXISTING', 'pattern_id' => $pattern->id])->assertSessionHasNoErrors();
        $this->assertSame($pattern->id, $e->fresh()->pattern_id);
        $this->post(route('enquiries.pattern', $e), ['pattern_route' => 'NEW', 'pattern_id' => $pattern->id])->assertSessionHasNoErrors();
        $this->assertNull($e->fresh()->pattern_id, 'a new pattern drops the existing one');

        $this->post(route('enquiries.questions.store', $e), ['question' => 'Painted to which spec?'])->assertSessionHasNoErrors();
        $q = $e->questions()->firstOrFail();
        $this->assertTrue($q->isOpen());
        $this->put(route('enquiries.questions.answer', [$e, $q]), ['answer' => 'MAS-PS-07 black'])->assertSessionHasNoErrors();
        $this->assertFalse($q->fresh()->isOpen());

        $this->post(route('enquiries.log', $e), ['kind' => 'EMAIL', 'direction' => 'IN', 'text' => 'RFQ received with drawing rev D', 'contact' => 'K. Osei'])->assertSessionHasNoErrors();
        $this->post(route('enquiries.log', $e), ['kind' => 'NOTE', 'direction' => 'IN', 'text' => 'Similar to the RH bracket'])->assertSessionHasNoErrors();
        $this->assertNull($e->events()->where('kind', 'NOTE')->value('direction'), 'a note has no direction');
        $this->get(route('enquiries.show', $e))->assertSee('RFQ received with drawing rev D')->assertSee('In · K. Osei')->assertSee('MAS-PS-07 black');
    }

    public function test_won_for_an_established_customer_opens_a_new_product_development(): void
    {
        $e = $this->enquiry(['pattern_route' => 'NEW']);
        $svc = app(QuotationService::class);
        $this->assertNull($svc->issue($svc->create($e)->fresh()));
        $this->post(route('enquiries.close', $e), ['outcome' => 'WON'])->assertRedirect();

        $this->assertSame('NEW_PRODUCT', PatternDevelopment::where('enquiry_id', $e->id)->firstOrFail()->type);
    }

    public function test_won_accepts_the_issued_quote_makes_the_order_and_opens_the_development_for_a_new_pattern(): void
    {
        $e = $this->enquiry(['pattern_route' => 'NEW']);
        $this->post(route('enquiries.close', $e), ['outcome' => 'WON'])->assertSessionHasErrors('outcome', null, 'close');

        $e->customer->update(['is_prospect' => true]);
        $svc = app(QuotationService::class);
        $q = $svc->create($e);
        $this->assertNull($svc->issue($q->fresh()));
        $this->post(route('enquiries.close', $e), ['outcome' => 'WON', 'note' => 'PO 4471'])->assertRedirect();

        $e->refresh();
        $this->assertSame('WON', $e->status);
        $this->assertSame('ACCEPTED', $q->fresh()->status);
        $this->assertNotNull($q->fresh()->sales_order_id);
        $dev = PatternDevelopment::where('enquiry_id', $e->id)->firstOrFail();
        $this->assertSame('NEW_PART', $dev->type, 'A first-time customer starts with a new part.');
        $this->assertTrue($e->events()->where('text', 'like', 'Closed as won.%')->exists());
        $this->get(route('enquiries.show', $e))->assertOk()->assertSee($dev->code)->assertDontSee('Close enquiry');
    }

    public function test_lost_needs_a_reason_keeps_the_winning_price_and_reminds_on_the_follow_up_date(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['is_active' => true]);
        $e = $this->enquiry(['owner_id' => $owner->id]);

        $this->post(route('enquiries.close', $e), ['outcome' => 'LOST'])->assertSessionHasErrors('close_reason', null, 'close');
        $this->post(route('enquiries.close', $e), ['outcome' => 'LOST', 'close_reason' => 'PRICE', 'follow_up' => 1])->assertSessionHasErrors('follow_up_on', null, 'close');
        $this->post(route('enquiries.close', $e), ['outcome' => 'LOST', 'close_reason' => 'PRICE', 'winning_price' => 31.5, 'note' => 'Other supplier holds a pattern',
            'follow_up' => 1, 'follow_up_on' => today()->addDays(3)->toDateString()])->assertRedirect(route('enquiries.show', $e));
        $e->refresh();
        $this->assertSame('LOST', $e->status);
        $this->assertSame('PRICE', $e->close_reason);
        $this->assertEqualsWithDelta(31.5, $e->winning_price, 0.001);

        $this->artisan('enquiries:follow-ups')->assertSuccessful();
        Notification::assertNothingSent();
        $this->travel(3)->days();
        $this->artisan('enquiries:follow-ups')->assertSuccessful();
        Notification::assertSentTo($owner, AppNotice::class);
        $this->assertNotNull($e->fresh()->follow_up_sent_at);
    }

    public function test_declined_is_its_own_outcome_and_a_closed_enquiry_cannot_be_changed(): void
    {
        $e = $this->enquiry();
        $this->post(route('enquiries.close', $e), ['outcome' => 'DECLINED', 'close_reason' => 'CAPABILITY'])->assertRedirect();
        $this->assertSame('DECLINED', $e->fresh()->status);
        $this->post(route('enquiries.close', $e), ['outcome' => 'LOST', 'close_reason' => 'PRICE'])->assertStatus(422);
        $this->post(route('enquiries.pattern', $e), ['pattern_route' => 'NEW'])->assertStatus(422);
        $this->get(route('enquiries.edit', $e))->assertRedirect(route('enquiries.show', $e));
        $this->get(route('enquiries.index', ['tab' => 'declined']))->assertSee($e->code);
    }
}
