<?php

namespace Tests\Feature;

use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Dispatch\Models\PackingRecordItem;
use App\Models\CastingLot;
use App\Models\Customer;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\MouldingStation;
use App\Models\MouldingWorkList;
use App\Models\IronGrade;
use App\Models\Pattern;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionStageDefinition;
use App\Models\QualityDefect;
use App\Models\QualityDefectMark;
use App\Services\Production\ProcessRoutes;
use App\Support\ProcessDepth;
use App\Support\QualityRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Drives an order from entry to dispatch and counts what a person has to submit, so the small-foundry path cannot quietly grow.
 * A step is one form sent; pages only looked at do not count.
 */
class StepCountTest extends TestCase
{
    use RefreshDatabase;

    private int $steps = 0;

    /** One form submitted by the user, which must go through cleanly. */
    private function step(string $route, array $data, mixed $parameters = []): TestResponse
    {
        $this->steps++;

        return $this->post(route($route, $parameters), $data)->assertSessionHasNoErrors();
    }

    public function test_basic_takes_an_order_to_dispatch_in_seven_steps(): void
    {
        $this->put(route('settings.process-depth'), ['preset' => 'BASIC']);
        Furnace::create(['code' => 'IF1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 1000, 'nominal_melting_rate_kg_per_hour' => 500]);
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $product = Product::create(['code' => 'PUMP-1', 'name' => 'Pump body']);
        $pattern = Pattern::create(['code' => 'PAT-1', 'name' => 'Pump plate', 'status' => 'ACTIVE']);
        $revision = $pattern->addRevision(['cavities_per_mould' => 2]);
        foreach ([1, 2] as $n) {
            $revision->cavityMap()->create(['cavity_number' => $n, 'product_revision_id' => $product->linkableRevision()->id, 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1]);
        }

        // 1. The order, straight to production.
        $this->step('quick.orders.store', ['customer_id' => $customer->id, 'due_date' => today()->addDays(10)->toDateString(), 'lines' => [
            ['product_id' => $product->id, 'qty' => 20, 'iron_grade_id' => IronGrade::where('code', 'EN-GJS-500-7')->value('id'), 'casting_weight_kg' => 6],
        ]]);
        $order = ProductionOrder::sole();
        // 2. Moulds made, assembled as they are saved.
        $this->step('quick.floor.made', ['rows' => [['pattern_id' => $pattern->id, 'moulds' => 10, 'production_order_id' => $order->id]]]);
        // 3. The heat started from Next heat.
        $this->step('quick.heat.start', ['kg' => 150, 'furnace_id' => Furnace::sole()->id, 'iron_grade_id' => IronGrade::where('code', 'EN-GJS-500-7')->value('id')]);
        // 4. Moulds poured: the pour, its completion and its lots in one.
        $this->step('quick.floor.poured', ['heat_id' => Heat::sole()->id, 'rows' => [['pattern_id' => $pattern->id, 'moulds' => 10, 'production_order_id' => $order->id]]]);
        $this->assertSame(20, (int) CastingLot::sum('quantity_poured'));
        $this->assertSame('COMPLETED', $order->fresh()->status);
        // 5. Quick QC passes the pieces.
        $this->step('quick-qc.store', ['rows' => [$order->product_revision_id => ['accepted' => 20]]]);
        // 6. Packing.
        $this->step('packing.store', ['qty' => CastingLot::pluck('quantity_good', 'id')->all()]);
        // 7. Dispatch.
        $this->step('dispatches.store', ['customer_id' => $customer->id, 'qty' => PackingRecordItem::pluck('quantity_packed', 'id')->all()]);

        $this->assertSame(1, Dispatch::count());
        $this->assertSame(7, $this->steps, 'Basic grew past seven steps from order to dispatch.');
    }

    public function test_a_real_day_with_the_list_count_heat_close_after_pour_steps_and_qc_by_cavity_takes_fourteen_steps(): void
    {
        $this->put(route('settings.process-depth'), ['preset' => 'BASIC']);
        QualityRules::set('qc_after_steps', 'STEPS');
        QualityRules::set('ncr_threshold', 'NEVER');   // the defect marks by cavity are the record
        app(ProcessRoutes::class)->saveDefault(collect(['SHAKEOUT', 'CUTTING', 'SHOT_BLAST', 'FINAL_INSPECTION'])->map(fn ($c) => ProductionStageDefinition::where('code', $c)->value('id'))->all());
        Furnace::create(['code' => 'IF1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 1000, 'nominal_melting_rate_kg_per_hour' => 500]);
        $station = MouldingStation::create(['code' => 'ST-1', 'name' => 'Floor 1', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'moulds_per_shift' => 40]);
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $product = Product::create(['code' => 'DW-3', 'name' => 'Drain waste 3"']);
        $pattern = Pattern::create(['code' => 'PAT-3', 'name' => 'Drain waste plate', 'status' => 'ACTIVE']);
        $revision = $pattern->addRevision(['cavities_per_mould' => 2]);
        $revision->update(['status' => 'APPROVED']);
        foreach ([1, 2] as $n) {
            $revision->cavityMap()->create(['cavity_number' => $n, 'product_revision_id' => $product->linkableRevision()->id, 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1]);
        }
        $grade = IronGrade::where('code', 'EN-GJS-500-7')->value('id');

        // 1. The order.
        $this->step('quick.orders.store', ['customer_id' => $customer->id, 'due_date' => today()->addDays(10)->toDateString(), 'lines' => [
            ['product_id' => $product->id, 'qty' => 20, 'iron_grade_id' => $grade, 'casting_weight_kg' => 6],
        ]]);
        $order = ProductionOrder::sole();
        // 2-3. The day's moulding list from the order, then released.
        $this->step('work-lists.prepare', ['day' => today()->toDateString(), 'stay' => 1]);
        $list = MouldingWorkList::sole();
        $this->assertSame([10, $station->id], [$list->items()->sole()->planned_moulds, $list->items()->sole()->moulding_station_id]);
        $this->step('work-lists.issue', ['issued_by' => 'Planner'], $list);
        // 4. Moulds made.
        $this->step('quick.floor.made', ['rows' => [['pattern_id' => $pattern->id, 'moulds' => 10, 'production_order_id' => $order->id, 'moulding_station_id' => $station->id]]]);
        // 5. Counted before pouring: one mould broke.
        $this->step('mould-count.store', ['counted' => [$revision->id => 9], 'reason' => 'BROKEN']);
        // 6-8. The heat started, the moulds poured, the heat closed.
        $this->step('quick.heat.start', ['kg' => 150, 'furnace_id' => Furnace::sole()->id, 'iron_grade_id' => $grade]);
        $this->step('quick.floor.poured', ['heat_id' => Heat::sole()->id, 'rows' => [['pattern_id' => $pattern->id, 'moulds' => 9, 'production_order_id' => $order->id]]]);
        $this->assertSame(18, (int) CastingLot::sum('quantity_poured'));
        $this->step('heats.close', ['leftover_metal_kg' => 20, 'leftover_use' => 'PIGGED'], Heat::sole());
        // 9-11. Next day: knock-out, fettling (two rejected), shot blast.
        $rev = $order->product_revision_id;
        $this->step('lots.batch-stage', ['stage_definition_id' => ProductionStageDefinition::where('code', 'SHAKEOUT')->value('id'), 'product_revision_id' => $rev, 'cooling_override_reason' => 'test']);
        $this->step('lots.batch-stage', ['stage_definition_id' => ProductionStageDefinition::where('code', 'CUTTING')->value('id'), 'product_revision_id' => $rev, 'rejected' => 2, 'rejection_reason' => 'Broken in fettling']);
        $this->step('lots.batch-stage', ['stage_definition_id' => ProductionStageDefinition::where('code', 'SHOT_BLAST')->value('id'), 'product_revision_id' => $rev]);
        // 12. QC with the defects marked by cavity.
        $this->step('quick-qc.store', ['rows' => [$rev => ['accepted' => 13, 'rejected' => 3, 'marks' => [
            ['defect_id' => QualityDefect::where('code', 'COLD-SHUT')->value('id'), 'quantity' => 3, 'disposition' => 'REJECT', 'cavity' => $revision->id.':2'],
        ]]]]);
        $this->assertSame(3, (int) QualityDefectMark::where('cavity_number', 2)->sum('quantity'));
        // 13-14. Packing and dispatch.
        $this->step('packing.store', ['qty' => CastingLot::where('quantity_good', '>', 0)->pluck('quantity_good', 'id')->all()]);
        $this->step('dispatches.store', ['customer_id' => $customer->id, 'qty' => PackingRecordItem::pluck('quantity_packed', 'id')->all()]);

        $this->assertSame(1, Dispatch::count());
        $this->assertSame(14, $this->steps, 'The real day grew past fourteen steps from order to dispatch.');
    }
}
