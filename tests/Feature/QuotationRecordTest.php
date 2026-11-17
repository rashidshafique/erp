<?php

namespace Tests\Feature;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Enquiry\Services\QuotationService;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The quotation record (design 30b): lines, how the piece price is built, revisions, the log and what it is linked to. */
class QuotationRecordTest extends TestCase
{
    use RefreshDatabase;

    private function quote(string $status = 'ISSUED'): Quotation
    {
        $c = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Kovar Pumps', 'payment_terms' => 'Net 45']);
        $e = Enquiry::create(['customer_id' => $c->id, 'title' => 'Impeller', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()->subDays(8)]);
        $e->items()->create(['line_no' => 1, 'description' => 'Impeller housing 65-160', 'material_text' => 'EN-GJL-250', 'weight_kg' => 11.4, 'annual_qty' => 4000, 'first_batch_qty' => 500, 'family' => 'GREY', 'feasibility_status' => 'OK', 'cores_count' => 1]);
        $q = app(QuotationService::class)->create($e->fresh('items'));
        $q->update(['status' => $status, 'delivery_weeks' => 8, 'payment_terms' => 'Net 45'] + ($status === 'DRAFT' ? [] : ['issued_at' => now()->subDays(3), 'valid_until' => today()->addDays(17)]));

        return $q->fresh('lines');
    }

    public function test_a_sent_quotation_opens_its_record_with_lines_price_build_up_and_links(): void
    {
        $q = $this->quote();

        $this->get(route('quotations.show', $q))->assertOk()
            ->assertSee($q->code)->assertSee('Impeller housing 65-160')->assertSee('EN-GJL-250')->assertSeeText('How the piece price is built')->assertSeeText('Metal')->assertSeeText('Scrap allowance')
            ->assertSeeText('Piece price')->assertSeeText('Revisions')->assertSeeText('First quote')->assertSee('8 weeks')->assertSee('Preview PDF')
            ->assertSee('Enquiry '.$q->enquiry->code)->assertSee('Feasibility')->assertDontSee('Save and recalculate');
    }

    public function test_a_draft_opens_the_costing_editor_and_the_record_when_asked(): void
    {
        $q = $this->quote('DRAFT');

        $this->get(route('quotations.show', $q))->assertOk()->assertSee('Save and recalculate');
        $this->get(route('quotations.show', [$q, 'record' => 1]))->assertOk()->assertSee('Open costing editor')->assertDontSee('Save and recalculate');
    }

    public function test_revisions_share_the_number_and_the_record_lists_each_with_what_changed(): void
    {
        $first = $this->quote();
        $first->update(['status' => 'SUPERSEDED']);
        $second = app(QuotationService::class)->create($first->enquiry->fresh(['items', 'quotations.lines']));
        $second->update(['change_note' => 'Price cut: yield 62% to 68% after the riser moved', 'created_by_name' => 'J. Varga']);

        $this->get(route('quotations.show', [$second, 'record' => 1]))->assertOk()
            ->assertSeeText('v2')->assertSeeText('v1')->assertSeeText('Price cut: yield 62% to 68%')->assertSeeText('J. Varga')->assertSeeText('Superseded');
        $this->get(route('quotations.show', $first))->assertOk()->assertSee('superseded by a later revision');
    }

    public function test_the_team_adds_emails_and_notes_to_the_log_and_sending_writes_a_line(): void
    {
        $q = $this->quote();

        $this->post(route('quotations.log', $q), ['kind' => 'EMAIL_IN', 'text' => 'Customer asked whether the price could come down.'])->assertRedirect();
        $this->post(route('quotations.log', $q), ['kind' => 'NOTE', 'text' => 'Riser moved to the flange boss.'])->assertRedirect();
        $this->post(route('quotations.log', $q), ['kind' => 'BOGUS', 'text' => 'x'])->assertSessionHasErrors('kind');

        $this->get(route('quotations.show', $q))->assertSee('Customer asked whether the price could come down.')->assertSee('In · ')->assertSee('Riser moved to the flange boss.');
        $this->assertSame(2, $q->events()->count());
    }

    public function test_an_expired_quotation_says_so_and_an_accepted_one_links_its_order(): void
    {
        $gone = $this->quote();
        $gone->update(['valid_until' => today()->subDays(2)]);
        $this->get(route('quotations.show', $gone))->assertOk()->assertSee('has expired');
    }

    public function test_a_sent_quotation_offers_the_close_dialog_and_can_still_be_declined_directly(): void
    {
        $q = $this->quote();

        $this->get(route('quotations.show', $q))->assertSee('Close quotation')->assertSee(route('quotations.close', $q));
        $this->post(route('quotations.reject', $q), ['decision_note' => 'Went with an import'])->assertRedirect();
        $this->assertSame('REJECTED', $q->fresh()->status);
        $this->assertStringContainsString('marked lost: Went with an import', $q->events()->first()->text);
    }
}
