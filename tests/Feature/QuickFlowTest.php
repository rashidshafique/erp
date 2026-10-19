<?php

namespace Tests\Feature;

use App\Domains\QuickFlow\Models\QuickFloorEntry;
use App\Domains\QuickFlow\Services\NextHeatPlan;
use App\Models\Customer;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\MouldingRun;
use App\Models\Pour;
use App\Models\Product;
use App\Models\ProductionOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickFlowTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): array
    {
        $furnace = Furnace::create(['code' => 'IF1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 1000, 'nominal_melting_rate_kg_per_hour' => 500]);

        return [
            'furnace' => $furnace, 'customer' => Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']),
            'product' => Product::create(['code' => 'PUMP-1', 'name' => 'Pump body']), 'grade' => IronGrade::where('code', 'EN-GJS-500-7')->firstOrFail(),
        ];
    }

    private function order(array $shop, int $qty = 100, array $line = [], array $extra = [])
    {
        return $this->post(route('quick.orders.store'), $extra + ['customer_id' => $shop['customer']->id, 'due_date' => today()->addDays(10)->toDateString(), 'lines' => [
            $line + ['product_id' => $shop['product']->id, 'qty' => $qty, 'iron_grade_id' => $shop['grade']->id, 'casting_weight_kg' => 6],
        ]]);
    }

    public function test_a_quick_order_is_released_for_planning_without_review_and_remembers_grade_and_weight(): void
    {
        $shop = $this->shop();

        $this->order($shop)->assertSessionHasNoErrors()->assertRedirect(route('quick.heat.index'));

        $order = ProductionOrder::firstOrFail();
        $this->assertSame(['RELEASED', '100.000'], [$order->status, (string) $order->released_qty]);
        $this->order($shop, 50, ['iron_grade_id' => '', 'casting_weight_kg' => ''])->assertSessionHasNoErrors();
        $this->assertCount(2, ProductionOrder::all());
    }

    public function test_a_line_is_refused_when_the_product_has_no_grade_or_weight_to_plan_with(): void
    {
        $shop = $this->shop();

        $this->order($shop, 10, ['iron_grade_id' => ''])->assertSessionHasErrors('lines.0.iron_grade_id');
        $this->order($shop, 10, ['casting_weight_kg' => ''])->assertSessionHasErrors('lines.0.casting_weight_kg');
        $this->assertSame(0, ProductionOrder::count());
    }

    public function test_a_new_customer_can_be_typed_on_the_order(): void
    {
        $shop = $this->shop();

        $this->order($shop, 10, [], ['customer_id' => '', 'new_customer' => 'Brand New Ltd'])->assertSessionHasNoErrors();

        $this->assertTrue(Customer::matching('Brand New')->exists());
    }

    public function test_the_next_heat_is_capped_at_the_furnace_working_capacity_and_runs_over_into_further_heats(): void
    {
        $shop = $this->shop();
        $this->order($shop, 300);

        $heats = app(NextHeatPlan::class)->build()['heats'];

        $this->assertCount(4, $heats);
        $this->assertSame(90, $heats[0]['lines'][0]['pieces']);
        $this->assertLessThanOrEqual(900.0, $heats[0]['kg']);
        $this->assertSame(300, collect($heats)->sum(fn ($h) => $h['lines'][0]['pieces']));
        $this->assertSame(1.8, $heats[0]['hours']);
    }

    public function test_metal_for_moulds_already_made_comes_before_moulds_still_to_make(): void
    {
        $shop = $this->shop();
        $this->order($shop, 50, [], ['due_date' => today()->addDays(30)->toDateString()]);
        $this->order($shop, 50, [], ['due_date' => today()->addDays(2)->toDateString()]);
        $late = ProductionOrder::orderBy('production_order_number')->first();

        $this->post(route('quick.floor.made'), ['production_order_id' => $late->id, 'moulds' => 10, 'pieces_per_mould' => 2, 'entry_date' => today()->toDateString()])->assertSessionHasNoErrors();

        $lines = app(NextHeatPlan::class)->build()['heats'][0]['lines'];
        $this->assertTrue($lines[0]['ready']);
        $this->assertSame([$late->id, 20], [$lines[0]['order']->id, $lines[0]['pieces']]);
    }

    public function test_moulds_made_write_a_moulding_run_and_start_the_order(): void
    {
        $shop = $this->shop();
        $this->order($shop);
        $order = ProductionOrder::firstOrFail();

        $this->post(route('quick.floor.made'), ['production_order_id' => $order->id, 'moulds' => 12, 'pieces_per_mould' => 4, 'entry_date' => today()->toDateString()])->assertSessionHasNoErrors();

        $run = MouldingRun::firstOrFail();
        $this->assertSame([12, 'COMPLETED', 'IN_PROGRESS'], [$run->moulds_made_qty, $run->status, $order->refresh()->status]);
        $this->assertSame(48, QuickFloorEntry::firstOrFail()->pieces());
    }

    public function test_pouring_records_a_pour_and_warns_about_thermal_tests_without_blocking(): void
    {
        $shop = $this->shop();
        $this->order($shop);
        $order = ProductionOrder::firstOrFail();
        $heat = Heat::create(['heat_number' => 'H-1', 'furnace_id' => $shop['furnace']->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED', 'iron_grade_id' => $shop['grade']->id]);
        $this->post(route('quick.floor.made'), ['production_order_id' => $order->id, 'moulds' => 20, 'pieces_per_mould' => 1, 'entry_date' => today()->toDateString()]);

        $this->post(route('quick.floor.poured'), ['heat_id' => $heat->id, 'production_order_id' => $order->id, 'moulds' => 15, 'pieces_per_mould' => 1, 'entry_date' => today()->toDateString()])
            ->assertSessionHasNoErrors()->assertSessionHas('warning', 'No thermal test has been recorded on H-1.');

        $this->assertSame(['COMPLETED', 'ADVISORY'], [Pour::firstOrFail()->status, Pour::firstOrFail()->gate_mode]);
        $row = app(NextHeatPlan::class)->orders()->first();
        $this->assertSame([20, 15, 5, 80], [$row['made'], $row['poured'], $row['awaiting'], $row['to_mould']]);
    }

    public function test_the_next_heat_starts_a_real_heat_on_an_eligible_furnace(): void
    {
        $shop = $this->shop();

        $this->post(route('quick.heat.start'), ['iron_grade_id' => $shop['grade']->id, 'furnace_id' => $shop['furnace']->id, 'kg' => 850, 'operator_name' => 'Imran'])->assertSessionHasNoErrors();

        $heat = Heat::firstOrFail();
        $this->assertSame(['PLANNED', '850.000', 'INDUCTION'], [$heat->status, (string) $heat->target_metal_weight_kg, $heat->process_type]);
    }

    public function test_the_pages_render(): void
    {
        $shop = $this->shop();
        $this->order($shop);

        $this->get(route('quick.orders.create'))->assertOk();
        $this->get(route('quick.floor.index'))->assertOk();
        $this->get(route('quick.heat.index'))->assertOk()->assertSee('Next heat')->assertSee('Start this heat');
    }

    public function test_the_grade_set_on_a_product_page_clears_the_review_spec_and_grade_blocks(): void
    {
        $shop = $this->shop();
        $revision = \App\Models\ProductRevision::create(['product_id' => $shop['product']->id, 'revision' => 'A', 'status' => 'APPROVED', 'net_weight_kg' => 5]);

        $this->post(route('products.grade', $shop['product']), ['product_revision_id' => $revision->id, 'iron_grade_id' => $shop['grade']->id])->assertSessionHasNoErrors();

        $this->assertSame($shop['grade']->id, app(\App\Services\Planning\HeatPlanner::class)->gradeFor($revision)?->id);
        $this->get(route('products.show', $shop['product']))->assertOk()->assertSee('Set grade')->assertSee('EN-GJS-500-7')->assertSee('Ductile iron');
        $this->get(route('iron-grades.index'))->assertOk()->assertSeeInOrder(['Grey iron', 'Ductile iron']);
    }
}
