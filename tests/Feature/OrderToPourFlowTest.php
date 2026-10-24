<?php

namespace Tests\Feature;

use App\Models\ChargeBatch;
use App\Models\Customer;
use App\Models\CustomerProductRequirement;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Pour;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\ProductSpecification;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\ThermalWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OrderToPourFlowTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        $furnace = Furnace::create(['code' => 'IF1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 3000, 'nominal_melting_rate_kg_per_hour' => 1500]);
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $product = Product::create(['code' => 'PUMP-1', 'name' => 'Pump body']);
        $rev = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED', 'net_weight_kg' => 40]);
        ProductSpecification::create(['product_revision_id' => $rev->id, 'specification_code' => 'SP-1', 'title' => 'Spec', 'status' => 'APPROVED', 'material_grade' => 'EN-GJS-500-7']);
        CustomerProductRequirement::create(['customer_id' => $customer->id, 'product_revision_id' => $rev->id, 'requirement_code' => 'CR-1', 'title' => 'r', 'status' => 'APPROVED', 'customer_grade' => 'ISO 1083 JS/500-7']);
        $pat = Pattern::create(['code' => 'PAT-1', 'name' => 'Pump pattern']);
        $pr = PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        $pr->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $rev->id, 'casting_weight_kg' => 40, 'poured_weight_kg' => 65]);
        $grade = IronGrade::where('code', 'EN-GJS-500-7')->first();
        ThermalWindow::create(['iron_grade_id' => $grade->id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1130, 'recal_max_c' => 8]);

        return compact('furnace', 'customer', 'product', 'rev', 'grade');
    }

    public function test_full_path_from_customer_order_to_a_gated_pour(): void
    {
        $w = $this->world();

        // Order entry
        $this->post(route('sales-orders.store'), [
            'customer_id' => $w['customer']->id, 'order_date' => today()->toDateString(), 'requested_delivery_date' => today()->addDays(40)->toDateString(),
            'lines' => [['product_revision_id' => $w['rev']->id, 'ordered_qty' => 20], ['product_revision_id' => '', 'ordered_qty' => '']],
        ])->assertRedirect();
        $order = SalesOrder::firstOrFail();
        $this->assertCount(1, $order->lines);

        // Production order is refused before the review is approved
        $line = $order->lines->first();
        $this->post(route('sales-orders.lines.production-order', [$order, $line]))->assertSessionHasErrors('review');

        // Review, acknowledge warnings, both sign-offs
        $this->post(route('sales-orders.review', $order))->assertRedirect();
        $this->get(route('sales-orders.show', $order))->assertOk()->assertSee('Contract review')->assertSee('GRADE');
        $review = $order->currentReview();
        $this->assertNotSame('BLOCK', $review->verdict, $review->items->where('severity', 'BLOCK')->pluck('message')->implode('|'));
        foreach ($review->items->where('severity', 'WARN') as $item) {
            $this->post(route('sales-orders.review.acknowledge', $order), ['item_id' => $item->id, 'reason' => 'Accepted for the first lot'])->assertSessionHasNoErrors();
        }
        $this->post(route('sales-orders.review.sign', $order), ['role' => 'sales', 'name' => 'Ayesha'])->assertSessionHasNoErrors();
        $this->post(route('sales-orders.review.sign', $order), ['role' => 'quality', 'name' => 'Bilal'])->assertSessionHasNoErrors();
        $this->assertSame('CONFIRMED', $order->fresh()->status);

        // Production order from the line, then release
        $this->post(route('sales-orders.lines.production-order', [$order, $line]))->assertSessionHasNoErrors();
        $po = ProductionOrder::firstOrFail();
        $this->assertSame($line->id, $po->sales_order_line_id);
        $this->get(route('sales-orders.show', $order))->assertOk()->assertSee(route('production-orders.show', $po), false);
        $this->post(route('sales-orders.lines.production-order', [$order, $line]))->assertSessionHasErrors('qty');   // nothing left to schedule
        $this->post(route('production-orders.release', $po))->assertSessionHasNoErrors();
        $this->assertSame('RELEASED', $po->fresh()->status);

        // Plan -> release heat
        $this->post(route('heat-plan.rebuild'), ['from' => today()->toDateString()])->assertRedirect();
        $this->get(route('heat-plan.index'))->assertOk()->assertSee('EN-GJS-500-7');
        $planned = \App\Models\PlannedHeat::firstOrFail();
        $this->assertEqualsWithDelta(20 * 65.0, (float) $planned->planned_metal_kg, 0.01);   // 65 kg poured per piece from the cavity map
        $this->post(route('heat-plan.release', $planned), ['operator_name' => 'Imran'])->assertRedirect();
        $heat = Heat::firstOrFail();
        $this->assertSame($w['grade']->id, $heat->iron_grade_id);

        // Pour cannot start: no chemistry, no thermal test
        $pour = Pour::create(['pour_number' => 'P-1', 'heat_id' => $heat->id, 'status' => 'PLANNED']);
        $this->get(route('pours.show', $pour))->assertOk()->assertSee('Pouring gate')->assertSee('BLOCKED');
        $this->post(route('pours.start', $pour))->assertSessionHasErrors('gate');
        $this->assertSame('PLANNED', $pour->fresh()->status);

        // Chemistry in the window and a passing thermal curve
        \DB::table('heat_chemistry_readings')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'heat_id' => $heat->id, 'reading_number' => 1,
            'carbon_percent' => 3.6, 'silicon_percent' => 2.5, 'manganese_percent' => 0.2, 'phosphorus_percent' => 0.03, 'sulphur_percent' => 0.01, 'copper_percent' => 0.4, 'magnesium_percent' => 0.045,
            'created_at' => now(), 'updated_at' => now()]);
        $csv = $this->get(route('thermal.sample'))->assertOk()->getContent();
        $this->post(route('thermal.store'), [
            'heat_id' => $heat->id, 'stage' => 'POST_TREATMENT', 'cup_type' => 'STABLE', 'sample_reference' => 'T-1',
            'curve_file' => UploadedFile::fake()->createWithContent('curve.csv', $csv),
        ])->assertSessionHasNoErrors();
        $test = \App\Models\ThermalAnalysisTest::firstOrFail();
        $this->assertNotNull($test->tl_c);
        $this->get(route('thermal.show', $test))->assertOk()->assertSee('Cooling curve')->assertSee('<polyline', false);
        $this->assertSame('PASS', $test->result, json_encode($test->findings));

        $this->post(route('pours.start', $pour))->assertSessionHasNoErrors();
        $this->assertSame('IN_PROGRESS', $pour->fresh()->status);
        $this->post(route('pours.complete', $pour), ['pour_temperature_c' => 1380, 'total_poured_weight_kg' => 1300])->assertSessionHasNoErrors();
        $this->assertSame('COMPLETED', $pour->fresh()->status);

        // Trace page
        $this->get(route('sales-orders.trace', $order))->assertOk()->assertSee($po->production_order_number);
    }

    public function test_pour_temperature_outside_window_needs_a_reason(): void
    {
        $w = $this->world();
        $w['grade']->update(['pour_temp_min_c' => 1350, 'pour_temp_max_c' => 1420]);
        $heat = Heat::create(['heat_number' => 'H-X', 'furnace_id' => $w['furnace']->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED', 'iron_grade_id' => $w['grade']->id]);
        $pour = Pour::create(['pour_number' => 'P-2', 'heat_id' => $heat->id, 'status' => 'IN_PROGRESS', 'started_at' => now()]);

        $this->post(route('pours.complete', $pour), ['pour_temperature_c' => 1300, 'total_poured_weight_kg' => 500])->assertSessionHasErrors('complete');
        $this->post(route('pours.complete', $pour), ['pour_temperature_c' => 1300, 'total_poured_weight_kg' => 500, 'out_of_window_reason' => 'Ladle cooled during delay'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('outside window', $pour->fresh()->notes);
    }

    public function test_heat_creation_screen_works_and_stores_grade_and_recipe(): void
    {
        $w = $this->world();
        $recipe = ChargeBatch::create(['code' => 'CR-IND', 'name' => 'Induction base', 'process_type' => 'INDUCTION', 'status' => 'ACTIVE', 'target_metal_weight_kg' => 1000, 'target_total_charge_weight_kg' => 1000]);

        $this->get(route('heats.create'))->assertOk()->assertSee('Iron grade')->assertSee('CR-IND');
        $this->post(route('heats.store'), [
            'furnace_id' => $w['furnace']->id, 'iron_grade_id' => $w['grade']->id, 'charge_batch_id' => $recipe->id,
            'target_metal_weight_kg' => 1000, 'operator_name' => 'Imran',
        ])->assertSessionHasNoErrors();
        $heat = Heat::firstOrFail();
        $this->assertSame($w['grade']->id, $heat->iron_grade_id);
        $this->assertSame($recipe->id, $heat->charge_batch_id);

        // A cupola cannot take a ductile heat
        $cupola = Furnace::create(['code' => 'CU1', 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'rated_capacity_kg' => 5000]);
        $this->post(route('heats.store'), ['furnace_id' => $cupola->id, 'iron_grade_id' => $w['grade']->id, 'target_metal_weight_kg' => 1000, 'operator_name' => 'x'])
            ->assertSessionHasErrors('iron_grade_id');
    }

    public function test_grade_screens_and_window_save(): void
    {
        $w = $this->world();
        $this->get(route('iron-grades.index'))->assertOk()->assertSee('EN-GJL-250');
        $this->get(route('iron-grades.show', $w['grade']))->assertOk()->assertSee('Furnace capability')->assertSee('ASTM A536');
        $this->put(route('iron-grades.window', $w['grade']), ['te_min_floor_c' => 1135, 'recal_max_c' => 9])->assertSessionHasNoErrors();
        $this->assertEquals(1135, ThermalWindow::where('iron_grade_id', $w['grade']->id)->value('te_min_floor_c'));
        $this->put(route('iron-grades.window', $w['grade']), ['tl_min_c' => 1200, 'tl_max_c' => 1100])->assertSessionHasErrors('tl_max_c');
        $this->post(route('iron-grades.capability', $w['grade']), ['furnace_id' => $w['furnace']->id, 'mode' => 'DENY'])->assertSessionHasNoErrors();
        $this->assertFalse(app(\App\Services\FurnaceEligibility::class)->canMelt($w['furnace'], $w['grade']));
    }

    public function test_release_is_blocked_without_an_approved_review_but_stock_orders_are_free(): void
    {
        $w = $this->world();
        $so = SalesOrder::create(['order_number' => 'SO-T', 'customer_id' => $w['customer']->id, 'order_date' => today(), 'status' => 'DRAFT']);
        $line = $so->lines()->create(['product_id' => $w['product']->id, 'product_revision_id' => $w['rev']->id, 'line_number' => 1, 'ordered_qty' => 5, 'uom' => 'piece']);
        $cust = ProductionOrder::create(['production_order_number' => 'PO-C', 'sales_order_line_id' => $line->id, 'product_id' => $w['product']->id, 'product_revision_id' => $w['rev']->id, 'status' => 'DRAFT', 'planned_qty' => 5]);
        $stock = ProductionOrder::create(['production_order_number' => 'PO-S', 'product_id' => $w['product']->id, 'product_revision_id' => $w['rev']->id, 'status' => 'DRAFT', 'planned_qty' => 5, 'order_type' => 'STOCK']);

        $this->post(route('production-orders.release', $cust))->assertSessionHasErrors('release');
        $this->assertSame('DRAFT', $cust->fresh()->status);
        $this->post(route('production-orders.release', $stock))->assertSessionHasNoErrors();
        $this->assertSame('RELEASED', $stock->fresh()->status);
    }

    public function test_bad_curve_shows_a_form_error_not_a_crash(): void
    {
        $w = $this->world();
        $heat = Heat::create(['heat_number' => 'H-B', 'furnace_id' => $w['furnace']->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED']);
        $this->post(route('thermal.store'), ['heat_id' => $heat->id, 'stage' => 'BASE', 'cup_type' => 'STABLE', 'curve_text' => "0,1300\n1,1290"])->assertSessionHasErrors('curve_text');
        $this->get(route('thermal.index'))->assertOk();
        $this->get(route('thermal.create'))->assertOk();
    }

    public function test_every_new_screen_renders_with_data_and_when_empty(): void
    {
        foreach (['customers.index', 'customers.create', 'sales-orders.index', 'sales-orders.create', 'heat-plan.index', 'iron-grades.index', 'thermal.index', 'thermal.create', 'dashboard', 'pours.index', 'heats.index', 'patterns.index', 'products.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $w = $this->world();
        foreach (['customers.index', 'sales-orders.create', 'heat-plan.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $this->get(route('customers.index', ['search' => 'acm']))->assertOk()->assertSee('ACME');
    }
}
