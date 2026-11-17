<?php

namespace Tests\Feature;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Enquiry\Services\QuotationService;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A new revision (design 30c): the reason, what changes, old figures beside new, and a quotation that keeps its number. */
class QuotationRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function sent(string $status = 'ISSUED'): Quotation
    {
        $c = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Kovar Pumps', 'payment_terms' => 'Net 45']);
        $e = Enquiry::create(['customer_id' => $c->id, 'title' => 'Impeller', 'source_type' => 'DRAWING', 'status' => 'QUOTED', 'received_on' => today()->subDays(20)]);
        $e->items()->create(['line_no' => 1, 'description' => 'Impeller housing 65-160', 'weight_kg' => 11.4, 'annual_qty' => 4000, 'first_batch_qty' => 500, 'family' => 'GREY', 'feasibility_status' => 'OK', 'cores_count' => 1]);
        $q = app(QuotationService::class)->create($e->fresh('items'));
        $q->update(['status' => $status, 'issued_at' => now()->subDays(10), 'valid_until' => today()->addDays(20), 'delivery_weeks' => 8, 'payment_terms' => 'Net 45']);

        return $q->fresh('lines');
    }

    private function payload(Quotation $q, array $over = []): array
    {
        $line = $q->lines->first();

        return $over + [
            'reason' => 'PRICE_ASK', 'changes' => ['price', 'qty'], 'action' => 'draft', 'valid_until' => today()->addDays(30)->toDateString(), 'notes' => 'Revised against a firm 6,000 a year.',
            'lines' => [$line->id => ['apply' => 1, 'price' => (float) $line->unit_price - 2, 'annual_qty' => 6000]],
        ];
    }

    public function test_the_form_shows_reasons_changes_lines_and_the_effect_on_the_deal(): void
    {
        $q = $this->sent();

        $this->get(route('quotations.revise', $q))->assertOk()
            ->assertSeeText('Why is it changing')->assertSeeText('Customer asked for a lower price')->assertSeeText('Correct an error')->assertSeeText('What changes on rev 2')
            ->assertSeeText('Piece price')->assertSeeText('Annual quantity')->assertSeeText('What it does to the deal')->assertSeeText('Revision 2 will')
            ->assertSeeText('keep the same quotation number, '.$q->code)->assertSee('Create rev 2 and send')->assertSee('Save as draft');
    }

    public function test_saving_a_draft_keeps_the_number_supersedes_the_last_revision_and_changes_only_what_was_ticked(): void
    {
        $q = $this->sent();
        $oldPrice = (float) $q->lines->first()->unit_price;

        $this->post(route('quotations.revise.store', $q), $this->payload($q))->assertRedirect();

        $rev = Quotation::where('code', $q->code)->where('version', 2)->firstOrFail();
        $line = $rev->lines->first();
        $this->assertSame('DRAFT', $rev->status);
        $this->assertSame('SUPERSEDED', $q->fresh()->status);
        $this->assertEqualsWithDelta($oldPrice - 2, (float) $line->unit_price, 0.001);
        $this->assertSame(6000, $line->annual_qty);
        $this->assertSame(4000, $q->lines->first()->fresh()->annual_qty);               // the earlier revision is untouched
        $this->assertSame('PRICE_ASK', $rev->revision_reason);
        $this->assertSame('Revised against a firm 6,000 a year.', $rev->notes);
        $this->assertStringContainsString('Customer asked for a lower price: piece price', $rev->change_note);
        $this->assertStringContainsString('annual quantity 4,000 to 6,000', $rev->change_note);
        $this->assertSame(1, $rev->events()->where('kind', 'SYSTEM')->count());
    }

    public function test_an_unticked_figure_or_line_stays_exactly_as_it_was(): void
    {
        $q = $this->sent();
        $line = $q->lines->first();
        $oldPrice = (float) $line->unit_price;

        $this->post(route('quotations.revise.store', $q), $this->payload($q, ['changes' => ['qty'], 'reason' => 'VOLUME']))->assertRedirect();
        $rev = Quotation::where('code', $q->code)->where('version', 2)->firstOrFail();
        $this->assertEqualsWithDelta($oldPrice, (float) $rev->lines->first()->unit_price, 0.001);       // price not ticked
        $this->assertSame(6000, $rev->lines->first()->annual_qty);
    }

    public function test_sending_sends_the_revision_and_leaves_the_quotation_with_one_live_revision(): void
    {
        $q = $this->sent();

        $this->post(route('quotations.revise.store', $q), $this->payload($q, ['action' => 'send']))->assertRedirect();

        $rev = Quotation::where('code', $q->code)->where('version', 2)->firstOrFail();
        $this->assertSame('ISSUED', $rev->status);
        $this->assertSame('SUPERSEDED', $q->fresh()->status);
        $this->assertCount(2, Quotation::where('code', $q->code)->get());
    }

    public function test_a_draft_or_accepted_quotation_cannot_be_revised_and_the_reason_is_required(): void
    {
        $accepted = $this->sent('ACCEPTED');
        $this->get(route('quotations.revise', $accepted))->assertStatus(422);
        $this->post(route('quotations.revise.store', $accepted), $this->payload($accepted))->assertStatus(422);

        $q = $this->sent();
        $this->post(route('quotations.revise.store', $q), $this->payload($q, ['reason' => 'BOGUS']))->assertSessionHasErrors('reason');
        $this->assertSame(1, Quotation::where('code', $q->code)->count());
    }

    public function test_the_record_offers_a_new_revision_only_when_one_can_be_made(): void
    {
        $q = $this->sent();
        $this->get(route('quotations.show', $q))->assertSee(route('quotations.revise', $q));
        $this->get(route('quotations.index', ['q' => $q->id]))->assertSee(route('quotations.revise', $q));

        $q->update(['status' => 'ACCEPTED']);
        $this->get(route('quotations.show', $q))->assertDontSee(route('quotations.revise', $q));
    }

    public function test_an_enquiry_with_a_newer_draft_reads_quoting_not_quoted(): void
    {
        $q = $this->sent();
        $this->assertSame('QUOTED', $q->enquiry->fresh(['customer', 'quotations'])->stage());

        $this->post(route('quotations.revise.store', $q), $this->payload($q));
        $this->assertSame('QUOTING', $q->enquiry->fresh(['customer', 'quotations'])->stage());
    }
}
