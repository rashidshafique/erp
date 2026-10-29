<?php

namespace Tests\Feature;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Development\Support\DevelopmentRules;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Enquiry\Services\QuotationService;
use App\Domains\Licensing\SettingsModuleAccess;
use App\Models\Customer;
use App\Models\Pattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** How an enquiry and its pattern developments work together: which developments a won enquiry opens, one per part, on either way of winning. */
class EnquiryDevelopmentTest extends TestCase
{
    use RefreshDatabase;

    private function enquiry(array $over = [], int $parts = 1): Enquiry
    {
        $customer = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Meridian Auto', 'payment_terms' => '30 days']);
        $e = Enquiry::create($over + ['customer_id' => $customer->id, 'title' => 'Brake bracket', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()]);
        foreach (range(1, $parts) as $n) {
            $e->items()->create(['line_no' => $n, 'description' => 'Bracket '.$n, 'material_text' => 'EN-GJS-450-10', 'weight_kg' => 3.6, 'annual_qty' => 1000,
                'first_batch_qty' => 100, 'target_price' => 12, 'family' => 'DUCTILE', 'feasibility_status' => 'OK']);
        }

        return $e->fresh(['items', 'customer']);
    }

    private function issuedQuote(Enquiry $e): Quotation
    {
        $svc = app(QuotationService::class);
        $q = $svc->create($e);
        $this->assertNull($svc->issue($q->fresh()));

        return $q->fresh();
    }

    public function test_accepting_the_quote_from_the_quote_page_opens_a_development_for_each_part(): void
    {
        $e = $this->enquiry(['pattern_route' => 'NEW'], 2);
        $e->customer->update(['is_prospect' => true]);
        $this->post(route('quotations.accept', $this->issuedQuote($e)))->assertRedirect();

        $devs = PatternDevelopment::where('enquiry_id', $e->id)->orderBy('title')->get();
        $this->assertSame(['Bracket 1', 'Bracket 2'], $devs->pluck('title')->all());
        $this->assertSame($e->items->pluck('id')->all(), $devs->pluck('enquiry_item_id')->all());
        $this->assertTrue($devs->every(fn ($d) => $d->product_id !== null), 'each development names the part made from its line');
        $this->assertSame('NEW_PART', $devs->first()->type);
        $this->assertSame('Design discussion', $devs->first()->stageName(), 'the enquiry review is done once the enquiry is won');
        $this->assertSame(2, $e->events()->where('text', 'like', 'Pattern development%opened%')->count());
    }

    public function test_the_pattern_decision_sets_the_kind_of_development(): void
    {
        $pattern = Pattern::create(['code' => 'PT-0302', 'name' => 'Valve bonnet', 'type' => 'PRODUCTION', 'status' => 'ACTIVE']);
        $cases = [
            ['CUSTOMER', 'DRAWING', 'CUSTOMER_PATTERN'],
            ['EXISTING', 'CHANGE', 'MODIFICATION'],
            ['EXISTING', 'TRANSFER', 'CUSTOMER_PATTERN'],
            ['EXISTING', 'REPEAT', null],
        ];
        foreach ($cases as [$route, $source, $type]) {
            $e = $this->enquiry(['pattern_route' => $route, 'source_type' => $source, 'pattern_id' => $route === 'EXISTING' ? $pattern->id : null]);
            $this->issuedQuote($e);
            $this->post(route('enquiries.close', $e), ['outcome' => 'WON'])->assertRedirect();
            $dev = PatternDevelopment::where('enquiry_id', $e->id)->first();
            $this->assertSame($type, $dev?->type, "$route / $source");
            if ($dev && $route === 'EXISTING') {
                $this->assertSame($pattern->id, $dev->pattern_id);
            }
        }
        $this->assertSame('CUSTOMER_SUPPLIED', PatternDevelopment::where('type', 'CUSTOMER_PATTERN')->value('pattern_by'));
    }

    public function test_a_development_started_before_the_win_is_kept_and_given_its_part(): void
    {
        $e = $this->enquiry(['pattern_route' => 'NEW']);
        $this->post(route('developments.store'), ['type' => 'NEW_PRODUCT', 'enquiry_id' => $e->id, 'customer_id' => $e->customer_id])->assertSessionHasNoErrors();
        $this->issuedQuote($e);
        $this->post(route('enquiries.close', $e), ['outcome' => 'WON'])->assertRedirect();

        $dev = PatternDevelopment::where('enquiry_id', $e->id)->sole();
        $this->assertSame($e->items->first()->id, $dev->enquiry_item_id);
        $this->assertNotNull($dev->product_id);
    }

    public function test_with_the_module_off_the_won_enquiry_says_no_development_was_opened(): void
    {
        app(SettingsModuleAccess::class)->saveDisabled(['development']);
        $e = $this->enquiry(['pattern_route' => 'NEW']);
        app(QuotationService::class)->accept($this->issuedQuote($e));

        $this->assertSame(0, PatternDevelopment::count());
        $this->assertTrue($e->events()->where('text', 'like', 'No pattern development opened%')->exists());
    }

    public function test_under_the_decided_rule_the_pattern_decision_opens_the_development_before_the_quote(): void
    {
        $e = $this->enquiry();
        $e->customer->update(['is_prospect' => true]);
        $this->post(route('enquiries.pattern', $e), ['pattern_route' => 'NEW'])->assertSessionHasNoErrors();
        $this->assertSame(0, PatternDevelopment::count(), 'by default the development waits for the win');

        DevelopmentRules::set('development_open', 'DECIDED');
        $this->post(route('enquiries.pattern', $e), ['pattern_route' => 'CUSTOMER']);
        $this->post(route('enquiries.pattern', $e), ['pattern_route' => 'NEW'])->assertSessionHas('success', fn ($m) => str_contains($m, 'opened'));
        $dev = PatternDevelopment::where('enquiry_id', $e->id)->where('status', 'OPEN')->sole();
        $this->assertSame('NEW_PART', $dev->type);
        $this->assertSame('Enquiry review', $dev->stageName(), 'opened before the win, it starts with the enquiry review');
        $this->assertSame('CANCELLED', PatternDevelopment::where('type', 'CUSTOMER_PATTERN')->value('status'), 'the customer pattern one goes when the decision changes');

        $this->issuedQuote($e->fresh());
        $this->post(route('enquiries.close', $e), ['outcome' => 'WON'])->assertRedirect();
        $this->assertSame(1, PatternDevelopment::where('status', 'OPEN')->count(), 'the win keeps it rather than opening another');
        $this->get(route('settings.process-depth.edit'))->assertSee('When an enquiry opens its pattern development');
    }

    public function test_approving_the_development_approves_the_parts_draft_revision(): void
    {
        $e = $this->enquiry(['pattern_route' => 'NEW']);
        $this->issuedQuote($e);
        $this->post(route('enquiries.close', $e), ['outcome' => 'WON'])->assertRedirect();
        $dev = PatternDevelopment::where('enquiry_id', $e->id)->sole();
        $revision = $dev->draftRevision();
        $this->assertNotNull($revision, 'the accepted quote left a draft revision for the new part');

        $this->post(route('developments.advance', $dev));
        $this->post(route('developments.advance', $dev));
        $this->assertSame('DRAFT', $revision->fresh()->status, 'only the last stage approves it');
        $this->get(route('developments.show', $dev))->assertSee('also approves revision '.$revision->revision);
        $this->post(route('developments.advance', $dev));

        $this->assertSame('DONE', $dev->fresh()->status);
        $this->assertSame('APPROVED', $revision->fresh()->status);
    }

    public function test_a_lost_enquiry_cancels_its_open_development_and_a_cancelled_development_shows_on_the_enquiry(): void
    {
        $e = $this->enquiry(['pattern_route' => 'NEW']);
        $this->post(route('developments.store'), ['type' => 'NEW_PRODUCT', 'enquiry_id' => $e->id, 'customer_id' => $e->customer_id]);
        $this->post(route('enquiries.close', $e), ['outcome' => 'LOST', 'close_reason' => 'PRICE'])->assertSessionHasNoErrors('close');
        $this->assertSame('CANCELLED', PatternDevelopment::where('enquiry_id', $e->id)->value('status'));

        $other = $this->enquiry();
        $this->post(route('developments.store'), ['type' => 'NEW_PRODUCT', 'enquiry_id' => $other->id, 'customer_id' => $other->customer_id]);
        $this->post(route('developments.cancel', PatternDevelopment::where('enquiry_id', $other->id)->sole()), ['reason' => 'Customer paused the project']);
        $this->assertTrue($other->events()->where('text', 'like', '%cancelled: Customer paused the project')->exists());
    }

    public function test_the_quote_sets_the_tooling_allowed_and_who_pays_and_the_actual_cost_shows_against_it(): void
    {
        $e = $this->enquiry(['pattern_route' => 'NEW']);
        $q = $this->issuedQuote($e);
        $q->update(['tooling_mode' => 'UPFRONT']);
        $q->lines()->update(['tooling_cost' => 150000]);
        $this->post(route('enquiries.close', $e), ['outcome' => 'WON'])->assertRedirect();

        $dev = PatternDevelopment::where('enquiry_id', $e->id)->sole();
        $this->assertSame(150000.0, $dev->quoted_tooling_cost);
        $this->assertSame('CUSTOMER', $dev->who_pays);
        $this->assertSame($q->currency, $dev->cost_currency);

        $this->put(route('developments.cost', $dev), ['actual_cost' => 175000])->assertSessionHasNoErrors();
        $this->get(route('developments.show', $dev))->assertSee('Over the quote by '.$q->currency.' 25,000.00');
    }
}
