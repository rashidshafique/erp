<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\CharacteristicLimit;
use App\Models\Customer;
use App\Models\CustomerProductRequirement;
use App\Models\Dispatch;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\MouldAssembly;
use App\Models\MouldConfiguration;
use App\Models\MouldingRun;
use App\Models\NonconformanceReport;
use App\Models\PackingRecordItem;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\PlannedHeat;
use App\Models\Pour;
use App\Models\PourMouldAssembly;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionStageDefinition;
use App\Models\ProductRevision;
use App\Models\ProductSpecification;
use App\Models\QualityDefect;
use App\Models\QualityInspection;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\ThermalWindow;
use App\Support\PatternOwnershipRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopFloorFlowTest extends TestCase
{
    use RefreshDatabase;

    private array $w = [];

    /** Customer order -> released production order -> released heat with passing chemistry and thermal test. */
    private function releasedWorld(int $qty = 20): array
    {
        $furnace = Furnace::create(['code' => 'IF1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 3000, 'nominal_melting_rate_kg_per_hour' => 1500]);
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $other = Customer::create(['code' => 'OTHER', 'name' => 'Other Co']);
        $product = Product::create(['code' => 'PUMP-1', 'name' => 'Pump body']);
        $rev = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED', 'net_weight_kg' => 40]);
        ProductSpecification::create(['product_revision_id' => $rev->id, 'specification_code' => 'SP-1', 'title' => 'Spec', 'status' => 'APPROVED', 'material_grade' => 'EN-GJS-500-7']);
        CustomerProductRequirement::create(['customer_id' => $customer->id, 'product_revision_id' => $rev->id, 'requirement_code' => 'CR-1', 'title' => 'r', 'status' => 'APPROVED', 'customer_grade' => 'ISO 1083 JS/500-7']);
        $pat = Pattern::create(['code' => 'PAT-1', 'name' => 'Pump pattern']);
        // Two cavities on a cope-and-drag mould: two castings per mould (the drag and cope together hold the layout once).
        $pr = PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        $pr->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $rev->id, 'casting_weight_kg' => 40, 'poured_weight_kg' => 65]);
        $pr->cavityMap()->create(['cavity_number' => 2, 'product_revision_id' => $rev->id, 'casting_weight_kg' => 40, 'poured_weight_kg' => 65]);
        $grade = IronGrade::where('code', 'EN-GJS-500-7')->first();
        ThermalWindow::create(['iron_grade_id' => $grade->id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1130, 'recal_max_c' => 8]);

        $this->post(route('sales-orders.store'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'requested_delivery_date' => today()->addDays(40)->toDateString(),
            'lines' => [['product_revision_id' => $rev->id, 'ordered_qty' => $qty]]])->assertRedirect();
        $order = SalesOrder::firstOrFail();
        $line = $order->lines->first();
        $this->post(route('sales-orders.review', $order))->assertRedirect();
        foreach ($order->currentReview()->items->where('severity', 'WARN') as $i) {
            $this->post(route('sales-orders.review.acknowledge', $order), ['item_id' => $i->id, 'reason' => 'Accepted for the first lot'])->assertSessionHasNoErrors();
        }
        $this->post(route('sales-orders.review.sign', $order), ['role' => 'sales', 'name' => 'Ayesha'])->assertSessionHasNoErrors();
        $this->post(route('sales-orders.review.sign', $order), ['role' => 'quality', 'name' => 'Bilal'])->assertSessionHasNoErrors();
        $this->post(route('sales-orders.lines.production-order', [$order, $line]))->assertSessionHasNoErrors();
        $po = ProductionOrder::firstOrFail();
        $this->post(route('production-orders.release', $po))->assertSessionHasNoErrors();

        $this->post(route('heat-plan.rebuild'), ['from' => today()->toDateString()]);
        $this->post(route('heat-plan.release', PlannedHeat::firstOrFail()), ['operator_name' => 'Imran']);
        $heat = Heat::firstOrFail();
        $this->post(route('heats.chemistry.store', $heat), ['sample_stage' => 'FINAL', 'test_method' => 'SPECTROMETER', 'carbon_percent' => 3.6, 'silicon_percent' => 2.5,
            'manganese_percent' => 0.2, 'phosphorus_percent' => 0.03, 'sulphur_percent' => 0.01, 'copper_percent' => 0.4, 'magnesium_percent' => 0.045])->assertSessionHasNoErrors();
        $this->post(route('thermal.store'), ['heat_id' => $heat->id, 'stage' => 'POST_TREATMENT', 'cup_type' => 'STABLE', 'sample_reference' => 'T-1',
            'curve_file' => UploadedFile::fake()->createWithContent('c.csv', $this->get(route('thermal.sample'))->getContent())])->assertSessionHasNoErrors();

        $run = MouldingRun::create(['run_number' => 'MR-1', 'production_order_id' => $po->id, 'pattern_revision_id' => $pr->id, 'run_date' => today(), 'status' => 'COMPLETED',
            'planned_mould_qty' => 5, 'moulds_made_qty' => 5, 'accepted_mould_qty' => 5]);
        $cfg = MouldConfiguration::create(['code' => 'CF-2', 'name' => 'Two layer', 'mould_type' => 'GREEN_SAND', 'nominal_layer_count' => 2, 'is_active' => true]);

        return compact('customer', 'other', 'order', 'line', 'po', 'heat', 'run', 'cfg', 'rev');
    }

    public function test_run_to_dispatch_with_ncr_blocking_the_way(): void
    {
        $w = $this->releasedWorld();

        // Assemble 5 moulds; a sixth is refused
        $this->post(route('moulding-runs.assemble', $w['run']), ['count' => 5, 'mould_configuration_id' => $w['cfg']->id, 'layers' => 2])->assertSessionHasNoErrors();
        $this->assertSame(5, MouldAssembly::count());
        $this->get(route('mould-readiness.index'))->assertOk()->assertSee('EN-GJS-500-7')->assertSee('650');   // 5 moulds x 2 cavities x 65 kg poured weight
        $this->post(route('moulding-runs.assemble', $w['run']), ['count' => 1, 'mould_configuration_id' => $w['cfg']->id, 'layers' => 2])->assertSessionHasErrors();
        $this->get(route('moulding-runs.show', $w['run']))->assertOk();

        // Pour: attach the moulds, pour, complete
        $pour = Pour::create(['pour_number' => 'P-9', 'heat_id' => $w['heat']->id, 'status' => 'PLANNED']);
        $ids = MouldAssembly::pluck('id')->all();
        $this->get(route('pours.show', $pour))->assertOk();
        $this->post(route('pours.assemblies.attach', $pour), ['assembly_ids' => $ids])->assertSessionHasNoErrors();
        $this->post(route('pours.assemblies.attach', $pour), ['assembly_ids' => [$ids[0]]])->assertSessionHasErrors('assemblies');   // already assigned
        $this->post(route('pours.start', $pour))->assertSessionHasNoErrors();
        $this->post(route('pours.complete', $pour), ['pour_temperature_c' => 1380, 'total_poured_weight_kg' => 650])->assertSessionHasNoErrors();
        $this->get(route('pours.show', $pour))->assertOk();

        $this->assertSame(5, CastingLot::count());
        $lot = CastingLot::orderBy('lot_number')->first();
        $this->assertMatchesRegularExpression('/^\d{6}-\d{2}$/', $lot->code);
        $this->assertSame(5, CastingLot::distinct('code')->count('code'));
        $this->assertSame(2, $lot->quantity_poured);
        $this->assertSame('IN_PRODUCTION', $lot->status);
        $this->assertSame('IN_PROGRESS', $w['po']->fresh()->status);
        $this->get(route('lots.index'))->assertOk()->assertSee($lot->label);
        $this->get(route('lots.show', $lot))->assertOk()->assertSee('Dispatch readiness')->assertSee('Final inspection');
        $this->get(route('lots.queue'))->assertOk()->assertSee('Waiting for shakeout')->assertSee($lot->label);

        // Shop-floor stage with a rejection needs a reason, and cannot exceed pieces
        $shakeout = ProductionStageDefinition::where('code', 'SHAKEOUT')->firstOrFail();
        DB::table('pattern_revisions')->where('id', $w['run']->pattern_revision_id)->update(['min_cooling_minutes' => 120]);
        $this->get(route('lots.show', $lot))->assertOk()->assertSee('Cooling before shakeout')->assertSee('wait until');
        $this->post(route('lots.stages.store', $lot), ['stage_definition_id' => $shakeout->id, 'quantity_in' => 2, 'quantity_accepted' => 2, 'quantity_rejected' => 0])->assertSessionHasErrors('stage');   // poured minutes ago
        $this->post(route('lots.stages.store', $lot), ['stage_definition_id' => $shakeout->id, 'quantity_in' => 2, 'quantity_accepted' => 2, 'quantity_rejected' => 0, 'cooling_override_reason' => 'Order urgent, light section'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('EARLY SHAKEOUT', $lot->stageEvents()->firstOrFail()->notes);
        $this->post(route('lots.stages.store', $lot), ['stage_definition_id' => $shakeout->id, 'quantity_in' => 5, 'quantity_accepted' => 5, 'quantity_rejected' => 0])->assertSessionHasErrors('stage');
        $this->post(route('lots.stages.store', $lot), ['stage_definition_id' => $shakeout->id, 'quantity_in' => 2, 'quantity_accepted' => 1, 'quantity_rejected' => 1])->assertSessionHasErrors('stage');

        // Packing before inspection is refused
        $this->post(route('packing.store'), ['qty' => [$lot->id => 1]])->assertSessionHasErrors('packing');

        // Final inspection: one piece rejected -> NCR, lot stays in production
        $this->post(route('lots.inspections.store', $lot), ['inspection_type' => 'FINAL', 'sample_size' => 2, 'inspector_name' => 'Qasim'])->assertSessionHasNoErrors();
        $insp = QualityInspection::firstOrFail();
        $this->get(route('inspections.show', $insp))->assertOk();
        $this->post(route('inspections.results.add', $insp), ['test_name' => 'Visual', 'result_type' => 'PASS_FAIL'])->assertSessionHasNoErrors();
        $r = $insp->results()->firstOrFail();
        $this->post(route('inspections.results.save', $insp), ['results' => [$r->id => ['passed' => '0', 'quality_defect_id' => QualityDefect::where('code', 'SAND-INCL')->value('id')]]])->assertSessionHasNoErrors();
        $this->assertFalse($r->fresh()->passed);
        $bad = ['quantity_accepted' => 2, 'quantity_rejected' => 0, 'quantity_rework' => 0, 'quantity_pending' => 0, 'overall_disposition' => 'ACCEPT'];
        $this->post(route('inspections.complete', $insp), $bad)->assertSessionHasErrors('complete');                         // a failed result cannot pass every piece
        $this->post(route('inspections.complete', $insp), ['quantity_accepted' => 1, 'quantity_rejected' => 0, 'quantity_rework' => 0, 'quantity_pending' => 0, 'overall_disposition' => 'PARTIAL'])->assertSessionHasErrors('complete');   // does not add up
        $this->post(route('inspections.complete', $insp), ['quantity_accepted' => 1, 'quantity_rejected' => 1, 'quantity_rework' => 0, 'quantity_pending' => 0, 'overall_disposition' => 'PARTIAL'])->assertSessionHasNoErrors();

        $lot->refresh();
        $this->assertSame(1, $lot->quantity_rejected);
        $this->assertSame(1, $lot->quantity_good);
        $this->assertSame('IN_PRODUCTION', $lot->status);
        $ncr = NonconformanceReport::firstOrFail();
        $this->assertSame('OPEN', $ncr->status);
        $this->get(route('ncrs.index'))->assertOk()->assertSee($ncr->ncr_number);
        $this->get(route('ncrs.show', $ncr))->assertOk();
        $this->get(route('lots.certificate', $lot))->assertOk()->assertSee('DRAFT');
        $this->post(route('packing.store'), ['qty' => [$lot->id => 1]])->assertSessionHasErrors('packing');                 // open NCR / lot not ready

        // NCR cannot close without root cause, corrective action and disposition
        $this->post(route('ncrs.close', $ncr), ['closed_by' => 'Qasim'])->assertSessionHasErrors('ncr');
        $this->put(route('ncrs.update', $ncr), ['root_cause' => 'Sand inclusion from loose core', 'corrective_action' => 'Blow out cavities', 'disposition' => 'SCRAP'])->assertSessionHasNoErrors();
        $this->post(route('ncrs.close', $ncr), ['closed_by' => 'Qasim'])->assertSessionHasNoErrors();
        $this->assertSame('READY', $lot->fresh()->status);

        // A grade that requires a tensile result stops packing until a passing one exists
        CharacteristicLimit::where('subject_id', $w['heat']->iron_grade_id)->where('characteristic_key', 'tensile_mpa')->update(['is_required_for_release' => true]);
        $this->post(route('packing.store'), ['qty' => [$lot->id => 1]])->assertSessionHasErrors('packing');
        $this->post(route('heats.tests.store', [$w['heat'], 'MECHANICAL']), ['sample_reference' => 'Y1', 'values' => ['tensile_mpa' => '520']])->assertSessionHasNoErrors();

        // Pack: only the one good piece
        $this->get(route('packing.create'))->assertOk()->assertSee($lot->label);
        $this->post(route('packing.store'), ['qty' => [$lot->id => 2]])->assertSessionHasErrors('packing');
        $this->post(route('packing.store'), ['qty' => [$lot->id => 1], 'packed_by' => 'Zahid', 'storage_location' => 'Rack B2'])->assertSessionHasNoErrors();
        $this->get(route('finished-goods.index'))->assertOk()->assertSee('Rack B2')->assertSee('Acme Pumps');
        $this->assertSame('PACKED', $lot->fresh()->status);
        $this->get(route('packing.index'))->assertOk();

        // The pattern is being paid off by one casting; under the automatic rule the dispatch makes it the customer's.
        PatternOwnershipRules::set('pay_off', 'AUTO');
        Pattern::where('code', 'PAT-1')->update(['owner_type' => 'PAYING_OFF', 'owner_customer_id' => $w['customer']->id, 'amortise_qty' => 1]);

        // Dispatch: wrong customer refused, right customer accepted
        $this->get(route('dispatches.create'))->assertOk()->assertSee($lot->label);
        $item = PackingRecordItem::firstOrFail();
        $this->post(route('dispatches.store'), ['customer_id' => $w['other']->id, 'qty' => [$item->id => 1]])->assertSessionHasErrors('dispatch');
        $this->assertSame(0, Dispatch::count());
        $this->post(route('dispatches.store'), ['customer_id' => $w['customer']->id, 'qty' => [$item->id => 1], 'delivery_challan_number' => 'DC-1'])->assertSessionHasNoErrors();
        $disp = Dispatch::firstOrFail();
        $this->assertSame('DISPATCHED', $lot->fresh()->status);
        $this->assertSame('CUSTOMER', Pattern::where('code', 'PAT-1')->value('owner_type'));
        $this->get(route('dispatches.show', $disp))->assertOk()->assertSee('DC-1');
        $this->get(route('sales-orders.show', $w['order']))->assertOk()->assertSee('Shipped');
        $this->assertSame(1.0, (float) DB::table('dispatch_items')->where('sales_order_line_id', $w['line']->id)->sum('quantity_dispatched'));
        $this->get(route('finished-goods.index'))->assertOk()->assertDontSee('Rack B2');
        $this->get(route('dispatches.index'))->assertOk();
        $this->post(route('dispatches.store'), ['customer_id' => $w['customer']->id, 'qty' => [$item->id => 1]])->assertSessionHasErrors('dispatch');   // nothing left

        // Order progress counts only lots with a completed FINAL inspection
        $this->assertSame(1, (int) $w['po']->fresh()->completed_qty);
        $this->assertSame(1, (int) $w['po']->fresh()->rejected_qty);
        $this->get(route('lots.certificate', $lot))->assertOk()->assertSee('Material and inspection certificate')->assertSee('Visual');
        $this->get(route('reports.yield'))->assertOk()->assertSee('Metal yield')->assertSee('55.4')->assertSee('Sand inclusion')->assertSee('Inclusions or structural anomalies')->assertSee('No team recorded');   // 9 good x 40 kg of 650 kg poured
        $this->get(route('defects.index'))->assertOk()->assertSee('SAND-INCL');
        $d = QualityDefect::where('code', 'SAND-INCL')->firstOrFail();
        $this->assertSame('G', $d->icf_group);
        $this->put(route('defects.update', $d), ['code' => 'SAND-INCL', 'name' => 'Sand inclusion', 'category' => 'SAND', 'icf_group' => 'G', 'default_severity' => 'MAJOR', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->post(route('defects.store'), ['code' => 'SAND-INCL', 'name' => 'dup', 'category' => 'SAND', 'default_severity' => 'MINOR'])->assertSessionHasErrors('code');
        $this->get(route('inspections.index'))->assertOk();
        $this->get(route('lots.show', $lot))->assertOk();
    }

    public function test_pour_refuses_a_mould_for_a_different_grade(): void
    {
        $w = $this->releasedWorld();
        $this->post(route('moulding-runs.assemble', $w['run']), ['count' => 1, 'mould_configuration_id' => $w['cfg']->id, 'layers' => 2])->assertSessionHasNoErrors();
        $other = IronGrade::where('code', '!=', 'EN-GJS-500-7')->first();
        $w['heat']->update(['iron_grade_id' => $other->id]);
        $pour = Pour::create(['pour_number' => 'P-X', 'heat_id' => $w['heat']->id, 'status' => 'PLANNED']);
        $this->post(route('pours.assemblies.attach', $pour), ['assembly_ids' => [MouldAssembly::firstOrFail()->id]])->assertSessionHasErrors('assemblies');
        $this->assertSame(0, PourMouldAssembly::count());
    }
}
