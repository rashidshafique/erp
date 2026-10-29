<?php

namespace Tests\Feature;

use App\Domains\Development\Models\PatternDevelopment;
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
}
