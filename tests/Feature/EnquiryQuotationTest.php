<?php

namespace Tests\Feature;

use App\Models\ApprovalPolicy;
use App\Models\CastingAnalysis;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\Orders\ContractReviewer;
use App\Services\Quotation\QuoteCalculator;
use App\Services\Quotation\QuotationService;
use App\Services\Quotation\Rates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\StlFactory as F;
use Tests\TestCase;

class EnquiryQuotationTest extends TestCase
{
    use RefreshDatabase;

    private function enquiry(string $source = 'DRAWING', array $item = []): Enquiry
    {
        $c = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Acme Pumps', 'payment_terms' => '30 days']);
        $e = Enquiry::create(['customer_id' => $c->id, 'title' => 'Pump housing', 'source_type' => $source, 'status' => 'FEASIBILITY', 'received_on' => today()]);
        $e->items()->create($item + ['line_no' => 1, 'description' => 'Housing', 'weight_kg' => 10, 'first_batch_qty' => 50, 'family' => 'GREY', 'feasibility_status' => 'OK']);

        return $e->fresh('items');
    }

    private function analysis(int $score, bool $severe = false): CastingAnalysis
    {
        return CastingAnalysis::create([
            'name' => 'Test', 'status' => 'COMPLETE', 'source_type' => 'STL', 'original_name' => 't.stl', 'file_disk' => 'local', 'file_path' => 'x', 'file_sha256' => 'x', 'file_size' => 1,
            'units' => 'mm', 'quality' => 'DRAFT', 'family' => 'DUCTILE', 'score' => $score, 'score_letter' => 'B',
            'result' => ['hotspots' => $severe ? [['role' => 'ISOLATED_SEVERE']] : [['role' => 'PRIMARY']]],
            'recommendation' => ['weight' => ['casting_kg' => 8.0, 'poured_kg' => 13.0, 'yield_pct' => 61.5], 'tooling' => ['core_count' => 2]],
        ]);
    }

    public function test_calculator_known_numbers(): void
    {
        $rates = config('quotation.rates');
        $in = ['weight_kg' => 10, 'poured_kg' => 20, 'family' => 'GREY', 'cores_count' => 2, 'heat_treatment' => false, 'ndt' => false, 'machining_cost' => 0, 'other_cost' => 0, 'scrap_pct' => 0, 'overhead_pct' => 10, 'margin_pct' => 20, 'tooling_cost' => 0, 'price_override' => null];
        $r = (new QuoteCalculator())->line($in, $rates);
        // metal 3200 + conversion (500 + 240 + 180 + 15 + 20 = 955) + overhead 95.5 = 4250.5; price = cost / 0.8
        $this->assertEqualsWithDelta(4250.5, $r['unit_cost'], 0.01);
        $this->assertEqualsWithDelta(5313.13, $r['unit_price'], 0.01);
        $this->assertEqualsWithDelta(20.0, $r['effective_margin_pct'], 0.01);

        $r = (new QuoteCalculator())->line(['scrap_pct' => 10] + $in, $rates);
        $this->assertEqualsWithDelta(4250.5 / 0.9, $r['unit_cost'], 0.01);   // good castings pay for scrapped ones
    }

    public function test_calculator_tooling_spread_and_price_override(): void
    {
        $rates = config('quotation.rates');
        $in = ['weight_kg' => 10, 'poured_kg' => 20, 'family' => 'DUCTILE', 'cores_count' => 0, 'heat_treatment' => true, 'ndt' => true, 'machining_cost' => 100, 'other_cost' => 50, 'scrap_pct' => 0, 'overhead_pct' => 0, 'margin_pct' => 0, 'tooling_cost' => 10000, 'price_override' => null];
        $spread = (new QuoteCalculator())->line($in, $rates, ['tooling_mode' => 'AMORTISED', 'tooling_amortise_qty' => 100]);
        $none = (new QuoteCalculator())->line($in, $rates, ['tooling_mode' => 'UPFRONT']);
        $this->assertEqualsWithDelta(100.0, $spread['breakdown']['tooling'], 0.01);           // 10000 / 100
        $this->assertEqualsWithDelta(120.0, $spread['breakdown']['tooling_price'], 0.01);     // plus 20 % mark-up
        $this->assertEqualsWithDelta(0.0, $none['breakdown']['tooling'], 0.01);
        $this->assertEqualsWithDelta(100.0, $spread['unit_cost'] - $none['unit_cost'], 0.01);   // the spread adds 100 to the cost of each casting
        $over = (new QuoteCalculator())->line(['price_override' => 9999] + $in, $rates);
        $this->assertEqualsWithDelta(9999.0, $over['unit_price'], 0.001);
        $this->assertGreaterThan(0, $over['effective_margin_pct']);
        $this->assertEqualsWithDelta(0.0, $none['effective_margin_pct'], 0.01);
    }

    public function test_rates_settings_override_placeholders(): void
    {
        $this->assertSame(160.0, Rates::get()['melt_grey_per_kg']);
        $this->put(route('settings.quotation.update'), collect(Rates::keys())->mapWithKeys(fn ($k) => [$k => $k === 'melt_grey_per_kg' ? 175 : Rates::get()[$k]])->all())->assertSessionHasNoErrors();
        $this->assertSame(175.0, Rates::get()['melt_grey_per_kg']);
        $this->get(route('settings.quotation'))->assertOk()->assertSee('placeholders');
    }

    public function test_quotation_uses_analysis_weight_yield_cores_and_scrap(): void
    {
        $e = $this->enquiry('DRAWING', ['casting_analysis_id' => $this->analysis(72)->id, 'weight_kg' => 99]);
        $q = app(QuotationService::class)->create($e);
        $l = $q->lines->first();
        $this->assertSame(1, $q->version);
        $this->assertEqualsWithDelta(8.0, $l->weight_kg, 0.001);      // from the analysis, not the typed 99
        $this->assertEqualsWithDelta(13.0, $l->poured_kg, 0.001);
        $this->assertSame(2, (int) $l->cores_count);
        $this->assertEqualsWithDelta(5.0, $l->scrap_pct, 0.001);      // score 72 falls in the 70 band
        $this->assertSame('DUCTILE', $l->family);
        $this->assertGreaterThan(0, $l->unit_price);
        $this->assertSame($q->id, app(QuotationService::class)->create($e->fresh())->id, 'an open draft is reused, not duplicated');
    }

    public function test_no_weight_means_no_quotation(): void
    {
        $e = $this->enquiry('DRAWING', ['weight_kg' => null]);
        $this->expectException(\RuntimeException::class);
        app(QuotationService::class)->create($e);
    }

    public function test_issue_is_blocked_until_feasibility_is_assessed(): void
    {
        $e = $this->enquiry('DRAWING', ['feasibility_status' => 'PENDING']);
        $svc = app(QuotationService::class);
        $q = $svc->create($e);
        $this->assertStringContainsString('not been assessed', (string) $svc->issue($q));
        $e->items->first()->update(['feasibility_status' => 'NOT_FEASIBLE', 'feasibility_notes' => 'wall too thin']);
        $this->assertStringContainsString('not feasible', (string) $svc->issue($q->fresh()));
        $this->assertSame('DRAFT', $q->fresh()->status);
    }

    public function test_severe_hotspot_needs_conditions_in_writing(): void
    {
        $e = $this->enquiry('DRAWING', ['casting_analysis_id' => $this->analysis(80, true)->id, 'feasibility_status' => 'OK']);
        $svc = app(QuotationService::class);
        $q = $svc->create($e);
        $this->assertStringContainsString('cut-off hotspot', (string) $svc->issue($q));
        $e->items->first()->update(['feasibility_status' => 'CONDITIONAL', 'feasibility_notes' => null]);
        $this->assertStringContainsString('conditions are not written down', (string) $svc->issue($q->fresh()));
        $e->items->first()->update(['feasibility_notes' => 'Chill the boss; customer agrees to a 7 mm wall']);
        $this->assertNull($svc->issue($q->fresh()));
        $this->assertSame('ISSUED', $q->fresh()->status);
        $this->assertSame('QUOTED', $e->fresh()->status);
        $this->assertNotNull($q->fresh()->valid_until);
    }

    public function test_low_margin_needs_approval_when_the_policy_is_on(): void
    {
        $e = $this->enquiry();
        $svc = app(QuotationService::class);
        $q = $svc->create($e);
        $q->lines->first()->update(['margin_pct' => 4]);
        $svc->recalc($q->fresh('lines'));

        $this->assertNull($svc->issue($q->fresh()), 'with the policy off a low margin is allowed');
        $q->fresh()->update(['status' => 'DRAFT']);

        ApprovalPolicy::create(['action_key' => 'quotation.issue_low_margin', 'is_active' => true, 'required_approvals' => 1, 'permission' => 'approvals.decide', 'allow_self_approval' => false]);
        $msg = $svc->issue($q->fresh());
        $this->assertStringContainsString('approval', (string) $msg);
        $this->assertSame('DRAFT', $q->fresh()->status);
    }

    public function test_new_version_supersedes_the_issued_one_and_keeps_margins(): void
    {
        $e = $this->enquiry();
        $svc = app(QuotationService::class);
        $q1 = $svc->create($e);
        $q1->lines->first()->update(['margin_pct' => 25]);
        $this->assertNull($svc->issue($q1->fresh()));
        $q2 = $svc->create($e->fresh());
        $this->assertSame(2, $q2->version);
        $this->assertEqualsWithDelta(25.0, $q2->lines->first()->margin_pct, 0.001);
        $this->assertNull($svc->issue($q2->fresh()));
        $this->assertSame('SUPERSEDED', $q1->fresh()->status);
    }

    public function test_accept_creates_product_draft_revision_and_draft_order_that_contract_review_holds(): void
    {
        $e = $this->enquiry();
        $svc = app(QuotationService::class);
        $q = $svc->create($e);
        $this->assertNull($svc->issue($q->fresh()));
        $order = $svc->accept($q->fresh());

        $this->assertSame('DRAFT', $order->status);
        $this->assertSame('WON', $e->fresh()->status);
        $this->assertSame('ACCEPTED', $q->fresh()->status);
        $this->assertSame($order->id, $q->fresh()->sales_order_id);
        $item = $e->items->first()->fresh();
        $this->assertNotNull($item->product_id);
        $rev = ProductRevision::where('product_id', $item->product_id)->firstOrFail();
        $this->assertSame('A', $rev->revision);
        $this->assertSame('DRAFT', $rev->status);
        $line = $order->lines()->firstOrFail();
        $this->assertSame($rev->id, $line->product_revision_id);
        $this->assertEqualsWithDelta((float) $q->lines->first()->unit_price, (float) $line->unit_price, 0.01);
        $this->assertSame(50.0, (float) $line->ordered_qty);

        $review = app(ContractReviewer::class)->evaluate($order->fresh());
        $this->assertContains('REVISION_STATUS', $review->items->where('severity', 'BLOCK')->pluck('check_code')->all());
        $this->assertMatchesRegularExpression('/^SO-\d{4}-\d{4}$/', $order->order_number);

        $this->expectException(\RuntimeException::class);
        $svc->accept($q->fresh());   // cannot be accepted twice
    }

    public function test_repeat_work_uses_the_approved_revision(): void
    {
        $p = Product::create(['code' => 'PRD-R1', 'name' => 'Old part', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'active' => true]);
        $rev = ProductRevision::create(['product_id' => $p->id, 'revision' => 'C', 'status' => 'APPROVED']);
        $e = $this->enquiry('REPEAT', ['product_id' => $p->id]);
        $svc = app(QuotationService::class);
        $q = $svc->create($e);
        $svc->issue($q->fresh());
        $order = $svc->accept($q->fresh());
        $this->assertSame($rev->id, $order->lines()->first()->product_revision_id);
        $this->assertSame(1, ProductRevision::where('product_id', $p->id)->count());
    }

    public function test_customer_change_opens_a_new_revision_letter(): void
    {
        $p = Product::create(['code' => 'PRD-R2', 'name' => 'Old part', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'active' => true]);
        ProductRevision::create(['product_id' => $p->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $e = $this->enquiry('CHANGE', ['product_id' => $p->id]);
        $svc = app(QuotationService::class);
        $q = $svc->create($e);
        $svc->issue($q->fresh());
        $order = $svc->accept($q->fresh());
        $new = ProductRevision::findOrFail($order->lines()->first()->product_revision_id);
        $this->assertSame('B', $new->revision);
        $this->assertSame('DRAFT', $new->status);
        $this->assertSame('APPROVED', ProductRevision::where('product_id', $p->id)->where('revision', 'A')->value('status'));
    }

    public function test_expired_quotation_cannot_be_accepted(): void
    {
        $e = $this->enquiry();
        $svc = app(QuotationService::class);
        $q = $svc->create($e);
        $svc->issue($q->fresh());
        $q->fresh()->update(['valid_until' => today()->subDay()]);
        try {
            $svc->accept($q->fresh());
            $this->fail('expected the expired quotation to be refused');
        } catch (\RuntimeException $ex) {
            $this->assertStringContainsString('expired', $ex->getMessage());
        }
        $this->assertNotSame('WON', $e->fresh()->status);
        $this->get(route('quotations.show', $q))->assertOk()->assertSee('passed its valid-until');
        $this->assertSame('EXPIRED', $q->fresh()->status);
    }

    public function test_pages_work_and_the_customer_copy_hides_costs(): void
    {
        $this->get(route('enquiries.index'))->assertOk();
        $this->get(route('enquiries.create'))->assertOk();
        $c = Customer::create(['code' => 'CX1', 'name' => 'Beta Valves']);
        $this->post(route('enquiries.store'), ['customer_id' => $c->id, 'title' => 'Valve body', 'source_type' => 'CONCEPT'])->assertSessionHasNoErrors();
        $e = Enquiry::firstOrFail();
        $this->assertMatchesRegularExpression('/^ENQ-\d{5}$/', $e->code);
        $this->post(route('enquiries.items.store', $e), ['description' => 'Valve body', 'weight_kg' => 12, 'first_batch_qty' => 20, 'family' => 'DUCTILE'])->assertSessionHasNoErrors();
        $this->assertSame('FEASIBILITY', $e->fresh()->status);
        $item = $e->items()->first();
        $this->get(route('enquiries.show', $e))->assertOk()->assertSee('Valve body')->assertSee('Analyse a model');

        $this->post(route('enquiries.items.feasibility', [$e, $item]), ['feasibility_status' => 'CONDITIONAL', 'feasibility_notes' => ''])->assertSessionHas('error');
        $this->post(route('enquiries.items.feasibility', [$e, $item]), ['feasibility_status' => 'CONDITIONAL', 'feasibility_notes' => 'Customer to relax flatness'])->assertSessionHas('success');

        $resp = $this->post(route('enquiries.quotations.store', $e));
        $q = Quotation::firstOrFail();
        $resp->assertRedirect(route('quotations.show', $q));
        $this->get(route('quotations.show', $q))->assertOk()->assertSee('Where the cost comes from')->assertSee('Molten metal');
        $this->get(route('quotations.print', $q))->assertNotFound();   // not issued yet
        $this->put(route('quotations.update', $q), ['tooling_mode' => 'NONE', 'delivery_weeks' => 8, 'lines' => [$q->lines()->first()->id => ['margin_pct' => 22]]])->assertSessionHasNoErrors();
        $this->post(route('quotations.issue', $q))->assertSessionHas('success');
        $this->get(route('quotations.print', $q))->assertOk()->assertSee('Valve body')->assertSee('Customer to relax flatness')
            ->assertDontSee('Molten metal')->assertDontSee('Effective margin')->assertDontSee('Scrap');
        $this->get(route('enquiries.show', $e))->assertOk()->assertSee($q->code);
        $this->get(route('settings.quotation'))->assertOk();
    }

    public function test_closing_an_enquiry_needs_a_reason_and_rejects_open_quotes(): void
    {
        $e = $this->enquiry();
        $q = app(QuotationService::class)->create($e);
        $this->post(route('enquiries.lost', $e), ['lost_reason' => ''])->assertSessionHasErrors('lost_reason');
        $this->post(route('enquiries.lost', $e), ['lost_reason' => '12% above competitor', 'outcome' => 'LOST'])->assertSessionHas('success');
        $this->assertSame('LOST', $e->fresh()->status);
        $this->assertSame('REJECTED', $q->fresh()->status);
    }

    public function test_analysis_started_from_a_part_is_linked_to_it(): void
    {
        Storage::fake('local');
        $e = $this->enquiry('DRAWING', ['weight_kg' => null, 'feasibility_status' => 'PENDING']);
        $item = $e->items->first();
        $this->get(route('casting-analyses.create', ['enquiry_item' => $item->id]))->assertOk()->assertSee($e->code);
        $this->post(route('casting-analyses.store'), [
            'cad' => UploadedFile::fake()->createWithContent('cube.stl', F::cells(F::box(6, 6, 6), 10)), 'units' => 'mm', 'quality' => 'DRAFT', 'family' => 'GREY', 'feeder' => 'AUTO', 'enquiry_item_id' => $item->id,
        ])->assertRedirect(route('enquiries.show', $e));
        $item->refresh();
        $this->assertNotNull($item->casting_analysis_id);
        $q = app(QuotationService::class)->create($e->fresh());   // no typed weight: the analysis supplies it
        $this->assertGreaterThan(1.0, (float) $q->lines->first()->weight_kg);
    }

    public function test_permissions_follow_the_sales_role(): void
    {
        config(['foundry.require_login' => true]);
        $mk = function (string $role) {
            $u = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
            $u->roles()->attach(Role::where('code', $role)->value('id'));

            return $u;
        };
        $viewer = $mk('VIEWER');
        $this->actingAs($viewer)->get(route('enquiries.index'))->assertOk();
        $this->actingAs($viewer)->post(route('enquiries.store'), [])->assertForbidden();
        $this->actingAs($viewer)->get(route('settings.quotation'))->assertForbidden();
        $this->actingAs($mk('SALES'))->post(route('enquiries.store'), [])->assertSessionHasErrors();
    }
}
