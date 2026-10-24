<?php

namespace Tests\Feature;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Services\ContractReviewer;
use App\Models\Customer;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use App\Models\ProcessControlPlan;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Services\Production\ControlPlans;
use App\Support\ControlPlanRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlPlanTest extends TestCase
{
    use RefreshDatabase;

    private function revision(?Product $product = null, string $label = '0'): ProductRevision
    {
        $product ??= Product::create(['code' => 'P'.uniqid(), 'name' => 'Grill plate']);

        return ProductRevision::create(['product_id' => $product->id, 'revision' => $label, 'status' => 'APPROVED']);
    }

    private function patternMaking(ProductRevision $revision): PatternRevision
    {
        $pattern = Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE']);
        $patternRevision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'is_current' => true]);
        PatternRevisionCavity::create(['pattern_revision_id' => $patternRevision->id, 'cavity_number' => 1, 'product_revision_id' => $revision->id]);

        return $patternRevision;
    }

    private function review(ProductRevision $revision): array
    {
        $customer = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Acme']);
        $order = SalesOrder::create(['order_number' => 'SO-'.uniqid(), 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'DRAFT']);
        $order->lines()->create(['product_id' => $revision->product_id, 'product_revision_id' => $revision->id, 'line_number' => 1, 'ordered_qty' => 10, 'uom' => 'piece']);
        $item = app(ContractReviewer::class)->evaluate($order)->items->firstWhere('check_code', 'CONTROL_PLAN');

        return [$item?->severity, $item?->message];
    }

    public function test_a_new_revision_follows_the_default_plan_and_passes_the_review(): void
    {
        $revision = $this->revision();

        $plan = ProcessControlPlan::where('product_revision_id', $revision->id)->firstOrFail();
        $this->assertTrue($plan->follows_default);
        $this->assertSame('PASS', $this->review($revision)[0]);
    }

    public function test_the_next_revision_copies_a_customised_plan(): void
    {
        $service = app(ControlPlans::class);
        $first = $this->revision();
        $plan = $service->forRevision($first);
        $service->customise($plan);
        $service->saveItems($plan->fresh(), [['parameter_name' => 'Pouring temperature', 'minimum_value' => 1380, 'maximum_value' => 1420, 'unit' => 'C']]);
        $service->approve($plan->fresh(), 'Quality');

        $second = $this->revision(Product::find($first->product_id), '1');

        $copy = ProcessControlPlan::where('product_revision_id', $second->id)->firstOrFail();
        $this->assertFalse($copy->follows_default);
        $this->assertSame($plan->id, $copy->based_on_plan_id);
        $this->assertSame(['Pouring temperature'], $copy->items->pluck('parameter_name')->all());
        $this->assertTrue($copy->isApproved());
    }

    public function test_an_own_plan_that_is_not_approved_follows_the_check_setting(): void
    {
        $revision = $this->revision();
        $service = app(ControlPlans::class);
        $service->customise($service->forRevision($revision));

        $this->assertSame('WARN', $this->review($revision)[0]);

        ControlPlanRules::set('control_plan_check', 'BLOCK');
        $this->assertSame('BLOCK', $this->review($revision)[0]);

        ControlPlanRules::set('control_plan_check', 'OFF');
        $this->assertNull($this->review($revision)[0]);
    }

    public function test_approving_a_pattern_revision_marks_the_plans_of_its_products_until_confirmed(): void
    {
        $revision = $this->revision();
        $service = app(ControlPlans::class);
        $service->forRevision($revision);
        $patternRevision = $this->patternMaking($revision);

        $this->assertSame(1, $service->flagForPattern($patternRevision));
        $this->assertSame('WARN', $this->review($revision)[0]);

        $service->confirmReview($service->forRevision($revision), 'Quality');
        $this->assertSame('PASS', $this->review($revision)[0]);
    }

    public function test_full_ppap_blocks_a_flagged_plan_and_a_copied_one_waits_for_approval(): void
    {
        ControlPlanRules::set('pattern_change', 'PPAP');
        $service = app(ControlPlans::class);
        $first = $this->revision();
        $plan = $service->forRevision($first);
        $service->customise($plan);
        $service->approve($plan->fresh(), 'Quality');
        $service->flagForPattern($this->patternMaking($first));

        $this->assertSame('BLOCK', $this->review($first)[0]);

        $second = $this->revision(Product::find($first->product_id), '1');
        $this->assertSame('DRAFT', ProcessControlPlan::where('product_revision_id', $second->id)->value('status'));
    }

    public function test_a_pattern_change_does_nothing_when_switched_off(): void
    {
        ControlPlanRules::set('pattern_change', 'OFF');
        $revision = $this->revision();

        $this->assertSame(0, app(ControlPlans::class)->flagForPattern($this->patternMaking($revision)));
        $this->assertSame('PASS', $this->review($revision)[0]);
    }

    public function test_the_product_page_and_settings_page_show_the_plan(): void
    {
        $revision = $this->revision();

        $this->get(route('products.show', $revision->product_id))->assertOk()->assertSee('Follows Our process')->assertDontSee('$loop->first', false);
        $this->get(route('settings.process'))->assertOk()->assertSee('Default control plan');
        $this->get(route('settings.control-plans.edit'))->assertOk();
    }

    public function test_the_default_plan_rows_are_saved_and_shown_on_a_following_revision(): void
    {
        $revision = $this->revision();

        $this->put(route('settings.process.control-plan'), ['rows' => [['parameter_name' => 'Sand compactability', 'minimum_value' => '38', 'maximum_value' => '44', 'unit' => '%']]])
            ->assertSessionHasNoErrors();

        $this->get(route('products.show', $revision->product_id))->assertSee('Sand compactability');
    }
}
