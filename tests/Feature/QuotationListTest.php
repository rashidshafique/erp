<?php

namespace Tests\Feature;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Enquiry\Services\QuotationService;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The quotations list (design 30a): one row per quotation with its newest revision, tabs, filters, figures and the selected quotation's panel. */
class QuotationListTest extends TestCase
{
    use RefreshDatabase;

    /** An enquiry with a priced quotation, sent and valid until the date given (or still a draft when null). */
    private function quote(string $customer = 'Kovar Pumps', string $part = 'Impeller housing 65-160', ?int $validDays = 17, array $over = []): Quotation
    {
        $c = Customer::create(['code' => 'C'.uniqid(), 'name' => $customer, 'payment_terms' => 'Net 45']);
        $e = Enquiry::create(['customer_id' => $c->id, 'title' => $part, 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()->subDays(8), 'customer_reference' => 'RFQ-'.uniqid()]);
        $e->items()->create(['line_no' => 1, 'description' => $part, 'weight_kg' => 11.4, 'annual_qty' => 4000, 'first_batch_qty' => 500, 'family' => 'GREY', 'feasibility_status' => 'OK']);
        $q = app(QuotationService::class)->create($e->fresh('items'));
        if ($validDays !== null) {
            $q->update($over + ['status' => 'ISSUED', 'issued_at' => now()->subDays(3), 'valid_until' => today()->addDays($validDays)]);
        } elseif ($over) {
            $q->update($over);
        }

        return $q->fresh('lines');
    }

    public function test_a_new_revision_keeps_the_quotations_number(): void
    {
        $first = $this->quote();
        $second = app(QuotationService::class)->create($first->enquiry->fresh(['items', 'quotations.lines']));

        $this->assertSame($first->code, $second->code);
        $this->assertSame(2, $second->version);
        $this->assertSame([2, 1], $first->revisions()->pluck('version')->all());
    }

    public function test_the_list_shows_one_row_per_quotation_with_its_newest_revision(): void
    {
        $sent = $this->quote();
        app(QuotationService::class)->create($sent->enquiry->fresh(['items', 'quotations.lines']));
        $other = $this->quote('Stellar', 'Bearing cap', null);

        $this->get(route('quotations.index'))->assertOk()
            ->assertSee($sent->code)->assertSee($other->code)->assertSee('Impeller housing 65-160')->assertSee('Bearing cap')
            ->assertSeeText('All 2')->assertSeeText('Draft 2')->assertSee('Valid / outcome');
    }

    public function test_tabs_and_filters_narrow_the_list(): void
    {
        $sent = $this->quote('Kovar Pumps', 'Impeller housing', 3);
        $draft = $this->quote('Stellar', 'Bearing cap', null);
        $won = $this->quote('Nordline', 'Valve bonnet', 5, ['status' => 'ACCEPTED', 'decided_at' => now()]);
        $lost = $this->quote('Orbis', 'Gearbox end cover', 5, ['status' => 'REJECTED', 'lost_reason' => 'PRICE', 'decided_at' => now()]);

        $this->get(route('quotations.index', ['tab' => 'sent']))->assertSee($sent->code)->assertDontSee($draft->code)->assertDontSee($won->code);
        $this->get(route('quotations.index', ['tab' => 'draft']))->assertSee($draft->code)->assertDontSee($sent->code);
        $this->get(route('quotations.index', ['tab' => 'accepted']))->assertSee($won->code)->assertDontSee($lost->code);
        $this->get(route('quotations.index', ['tab' => 'lost']))->assertSee($lost->code)->assertSee('Price')->assertDontSee($won->code);
        $this->get(route('quotations.index', ['search' => 'stellar']))->assertSee($draft->code)->assertDontSee($sent->code);
        $this->get(route('quotations.index', ['valid' => '7']))->assertSee($sent->code)->assertDontSee($draft->code);
        $this->get(route('quotations.index', ['sort' => 'customer', 'dir' => 'asc']))->assertOk();
    }

    public function test_the_figures_count_what_is_waiting_and_what_is_about_to_expire(): void
    {
        $this->quote('Kovar Pumps', 'Impeller housing', 3);
        $this->quote('Stellar', 'Bearing cap', null, ['status' => 'AWAITING_APPROVAL', 'approval_asked_at' => now()->subDays(6)]);

        $this->get(route('quotations.index'))->assertOk()
            ->assertSeeText('Awaiting approval')->assertSeeText('oldest 6 days')->assertSeeText('Expiring in 7 days');
    }

    public function test_the_panel_shows_the_selected_quotation_with_its_revisions_and_links(): void
    {
        $q = $this->quote('Stellar', 'Bearing cap', null, ['status' => 'AWAITING_APPROVAL', 'approval_asked_at' => now()->subDay()]);

        $this->get(route('quotations.index', ['q' => $q->id]))->assertOk()
            ->assertSee('Open quotation')->assertSee('under the margin floor')->assertSeeText('Revisions')->assertSeeText('Linked')->assertSee($q->enquiry->code)->assertSee('Net 45');
    }

    public function test_an_expired_sent_quotation_reads_expired_and_the_list_exports(): void
    {
        $gone = $this->quote('Hartwell', 'Manifold block', -4);

        $this->assertSame('EXPIRED', $gone->statusKey());
        $this->get(route('quotations.index'))->assertSee('Expired')->assertSee('gone');
        $csv = $this->get(route('quotations.export'));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($gone->code, $csv->streamedContent());
    }

    public function test_the_new_quotation_menu_offers_open_enquiries_that_have_items(): void
    {
        $c = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Meridian', 'payment_terms' => '30 days']);
        $e = Enquiry::create(['customer_id' => $c->id, 'title' => 'Brake bracket', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()]);
        $e->items()->create(['line_no' => 1, 'description' => 'Bracket', 'weight_kg' => 3, 'feasibility_status' => 'OK']);

        $this->get(route('quotations.index'))->assertOk()->assertSee('+ New quotation')->assertSee($e->code)->assertSee(route('enquiries.quotations.store', $e));
    }
}
