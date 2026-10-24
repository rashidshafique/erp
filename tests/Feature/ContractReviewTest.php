<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Furnace;
use App\Models\IronGrade;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\ProductSpecification;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\ThermalWindow;
use App\Domains\Sales\Services\ContractReviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContractReviewTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $specGrade = 'EN-GJS-500-7', ?string $custGrade = 'ASTM A536 70-50-05', string $revStatus = 'APPROVED'): SalesOrder
    {
        Furnace::create(['code' => 'IF1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 3000]);
        $c = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Acme Pumps']);
        $p = Product::create(['code' => 'P'.uniqid(), 'name' => 'Pump body']);
        $r = ProductRevision::create(['product_id' => $p->id, 'revision' => 'A', 'status' => $revStatus]);
        ProductSpecification::create(['product_revision_id' => $r->id, 'specification_code' => 'SPEC-1', 'title' => 'Spec', 'status' => 'APPROVED', 'material_grade' => $specGrade]);
        if ($custGrade) {
            \App\Models\CustomerProductRequirement::create(['customer_id' => $c->id, 'product_revision_id' => $r->id, 'requirement_code' => 'CR-1', 'title' => 'Req', 'status' => 'APPROVED', 'customer_grade' => $custGrade]);
        }
        $o = SalesOrder::create(['order_number' => 'SO-'.uniqid(), 'customer_id' => $c->id, 'order_date' => today(), 'requested_delivery_date' => today()->addDays(30), 'status' => 'DRAFT']);
        $o->lines()->create(['product_id' => $p->id, 'product_revision_id' => $r->id, 'line_number' => 1, 'ordered_qty' => 100, 'uom' => 'piece']);

        return $o;
    }

    private function codes($review, string $sev): array
    {
        return $review->items->where('severity', $sev)->pluck('check_code')->all();
    }

    public function test_clean_order_passes_with_expected_warnings_only(): void
    {
        $review = app(ContractReviewer::class)->evaluate($this->order());

        $this->assertNotSame('BLOCK', $review->verdict, json_encode($this->codes($review, 'BLOCK')));
        $this->assertContains('PATTERN', $this->codes($review, 'WARN'));
        $this->assertContains('THERMAL_WINDOW', $this->codes($review, 'WARN'));
        $this->assertContains('FURNACE', $this->codes($review, 'PASS'));
        $this->assertContains('GRADE', $this->codes($review, 'WARN'));   // ASTM equivalent is only the nearest match
    }

    public function test_grade_conflict_between_spec_and_customer_blocks(): void
    {
        $review = app(ContractReviewer::class)->evaluate($this->order('EN-GJS-500-7', 'ASTM A48 Class 35B'));

        $this->assertSame('BLOCK', $review->verdict);
        $this->assertContains('GRADE', $this->codes($review, 'BLOCK'));
        $this->assertSame('REJECTED', $review->status);
    }

    public function test_unapproved_revision_and_missing_grade_block(): void
    {
        $review = app(ContractReviewer::class)->evaluate($this->order('', null, 'DRAFT'));

        $this->assertContains('REVISION_STATUS', $this->codes($review, 'BLOCK'));
        $this->assertContains('GRADE', $this->codes($review, 'BLOCK'));
    }

    public function test_no_furnace_able_to_melt_the_grade_blocks(): void
    {
        $o = $this->order('EN-GJS-500-7', null);
        Furnace::query()->update(['furnace_type' => 'CUPOLA']);    // cupola default is grey only

        $review = app(ContractReviewer::class)->evaluate($o);
        $this->assertContains('FURNACE', $this->codes($review, 'BLOCK'));
    }

    public function test_pattern_link_and_thermal_window_clear_their_warnings(): void
    {
        $o = $this->order();
        $line = $o->lines->first();
        $pat = Pattern::create(['code' => 'PT'.uniqid(), 'name' => 'Pat']);
        $pr = PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        $pr->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $line->product_revision_id]);
        ThermalWindow::create(['iron_grade_id' => IronGrade::where('code', 'EN-GJS-500-7')->value('id'), 'cup_type' => 'STABLE', 'te_min_floor_c' => 1140]);

        $review = app(ContractReviewer::class)->evaluate($o);
        $this->assertNotContains('PATTERN', $this->codes($review, 'WARN'));
        $this->assertNotContains('THERMAL_WINDOW', $this->codes($review, 'WARN'));
    }

    public function test_findings_about_the_product_or_pattern_link_to_where_they_are_fixed(): void
    {
        $o = $this->order();
        $line = $o->lines->first();
        ProductSpecification::query()->delete();
        $review = app(ContractReviewer::class)->evaluate($o);
        $spec = $review->items->firstWhere('check_code', 'PRODUCT_SPEC');
        $this->assertSame([route('products.show', $line->product_id)], array_column($spec->fixLinks(), 'url'));
        $this->get(route('sales-orders.show', $o))->assertOk()->assertSee(route('products.show', $line->product_id), false);

        $this->assertContains(route('products.show', $line->product_id), array_column($review->items->firstWhere('check_code', 'PATTERN')->fixLinks(), 'url'), 'no pattern is linked yet, so go to the product');

        $pat = Pattern::create(['code' => 'PT'.uniqid(), 'name' => 'Pat']);
        $pr = PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'status' => 'DRAFT', 'cavities_per_mould' => 2]);
        $pr->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $line->product_revision_id]);
        $review = app(ContractReviewer::class)->evaluate($o);
        $pattern = $review->items->firstWhere('check_code', 'PATTERN');
        $this->assertSame([route('patterns.show', $pat)], array_column($pattern->fixLinks(), 'url'), 'a draft pattern revision still gets linked so it can be approved');
        $this->assertSame([], $review->items->firstWhere('check_code', 'FURNACE')->fixLinks());
    }

    public function test_signing_needs_acknowledged_warnings_and_both_signatures(): void
    {
        $svc = app(ContractReviewer::class);
        $review = $svc->evaluate($this->order());

        try {
            $svc->sign($review, 'sales', 'Ayesha');
            $this->fail('Unacknowledged warnings must stop signing.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('review', $e->errors());
        }

        foreach ($review->items->where('severity', 'WARN') as $item) {
            $svc->acknowledge($review, $item->id, 'Accepted for the first lot.');
        }
        $review = $svc->sign($review->fresh(), 'sales', 'Ayesha');
        $this->assertSame('IN_REVIEW', $review->status);

        $review = $svc->sign($review, 'quality', 'Bilal');
        $this->assertSame('APPROVED', $review->status);
        $this->assertSame('CONFIRMED', $review->salesOrder->fresh()->status);
    }

    public function test_blocked_review_cannot_be_signed_and_new_evaluation_supersedes(): void
    {
        $svc = app(ContractReviewer::class);
        $o = $this->order('EN-GJS-500-7', 'ASTM A48 Class 35B');
        $first = $svc->evaluate($o);

        $this->expectException(ValidationException::class);
        try {
            $svc->sign($first, 'sales', 'Ayesha');
        } finally {
            $second = $svc->evaluate($o);
            $this->assertSame(2, $second->review_number);
            $this->assertSame('SUPERSEDED', $first->fresh()->status);
        }
    }

    public function test_blocking_messages_name_the_product_and_write_its_code_as_code_dash_rev_number(): void
    {
        $c = Customer::create(['code' => 'C1', 'name' => 'Acme Pumps']);
        $p = Product::create(['code' => 'PRD-0240', 'name' => 'Golden Bend']);
        $r = $p->linkableRevision();
        $o = SalesOrder::create(['order_number' => 'SO-LABEL', 'customer_id' => $c->id, 'order_date' => today(), 'status' => 'DRAFT']);
        $o->lines()->create(['product_id' => $p->id, 'product_revision_id' => $r->id, 'line_number' => 1, 'ordered_qty' => 10, 'uom' => 'piece']);

        $review = app(ContractReviewer::class)->evaluate($o);

        $texts = $review->items->where('severity', 'BLOCK')->pluck('message')->all();
        $this->assertContains('Golden Bend PRD-0240-Rev0 has no approved, effective product specification.', $texts);
        $this->assertContains('No material grade is named in the specification or the customer requirement for Golden Bend PRD-0240-Rev0.', $texts);
    }

    public function test_missing_customer_requirement_is_optional_by_default(): void
    {
        $review = app(ContractReviewer::class)->evaluate($this->order('EN-GJS-500-7', null));

        $this->assertNotContains('CUSTOMER_REQUIREMENT', $review->items->pluck('check_code')->all());
    }

    public function test_enforced_customer_requirement_blocks_until_a_profile_is_selected(): void
    {
        \App\Support\OrderRules::set('customer_requirement', 'ENFORCED');
        $order = $this->order('EN-GJS-500-7', null);

        $this->assertContains('CUSTOMER_REQUIREMENT', $this->codes(app(ContractReviewer::class)->evaluate($order), 'BLOCK'));

        $profile = \App\Domains\Compliance\Models\RequirementProfile::create(['code' => 'IATF', 'name' => 'IATF 16949', 'region' => 'EUROPE', 'active' => true]);
        $order->customer->requirementProfiles()->attach($profile->id);

        $this->assertContains('CUSTOMER_REQUIREMENT', $this->codes(app(ContractReviewer::class)->evaluate($order), 'PASS'));
    }
}
