<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\CastingLot;
use App\Models\Furnace;
use App\Models\Gauge;
use App\Models\Heat;
use App\Models\HeatChemistryReading;
use App\Models\MouldAssembly;
use App\Models\MouldConfiguration;
use App\Models\MouldingRun;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Pour;
use App\Models\PourMouldAssembly;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\QualityInspection;
use App\Models\QualityInspectionResult;
use App\Models\ThermalAnalysisTest;
use App\Services\Dashboard\DashboardData;
use App\Services\Quality\CalibrationStatus;
use App\Support\CalibrationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GaugeCalibrationTest extends TestCase
{
    use RefreshDatabase;

    private function gauge(array $extra = []): Gauge
    {
        return Gauge::create($extra + ['name' => 'Caliper', 'instrument_type' => 'CALIPER', 'status' => 'IN_SERVICE', 'requires_calibration' => true, 'calibration_interval_days' => 365]);
    }

    private function calibrate(Gauge $gauge, array $fields = [])
    {
        return $this->post(route('gauges.calibrate', $gauge), $fields + ['calibrated_on' => today()->toDateString(), 'result' => 'PASS']);
    }

    public function test_an_instrument_is_added_with_a_generated_code_and_needs_an_interval(): void
    {
        $this->post(route('gauges.store'), ['name' => 'Vernier', 'instrument_type' => 'CALIPER', 'status' => 'IN_SERVICE', 'requires_calibration' => 1, 'calibration_interval_days' => 180])->assertSessionHasNoErrors();
        $gauge = Gauge::firstOrFail();
        $this->assertStringStartsWith('GAU', $gauge->code);
        $this->get(route('gauges.show', $gauge))->assertOk()->assertSee('Never calibrated');

        $this->post(route('gauges.store'), ['name' => 'No interval', 'instrument_type' => 'CALIPER', 'status' => 'IN_SERVICE', 'requires_calibration' => 1])->assertSessionHasErrors('calibration_interval_days');
        $this->post(route('gauges.store'), ['name' => 'Tape', 'instrument_type' => 'OTHER', 'status' => 'IN_SERVICE', 'requires_calibration' => 0])->assertSessionHasNoErrors();
    }

    public function test_the_statuses_follow_the_due_date(): void
    {
        $status = app(CalibrationStatus::class);

        $this->assertSame('NOT_CALIBRATED', $status->of($this->gauge()));
        $this->assertSame('IN_DATE', $status->of($this->gauge(['next_due_on' => today()->addDays(90)])));
        $this->assertSame('DUE_SOON', $status->of($this->gauge(['next_due_on' => today()->addDays(10)])));
        $this->assertSame('OVERDUE', $status->of($this->gauge(['next_due_on' => today()->subDay()])));
        $this->assertSame('NOT_REQUIRED', $status->of($this->gauge(['requires_calibration' => false])));
        $this->assertSame('OUT_OF_SERVICE', $status->of($this->gauge(['status' => 'OUT_OF_SERVICE', 'next_due_on' => today()->addYear()])));
        $this->assertSame('RETIRED', $status->of($this->gauge(['status' => 'RETIRED'])));
    }

    public function test_a_passing_calibration_sets_the_next_due_date_from_the_interval(): void
    {
        $gauge = $this->gauge(['calibration_interval_days' => 180]);

        $this->calibrate($gauge, ['certificate_number' => 'C-77', 'performed_by' => 'Metrology Lab', 'external_lab' => 1])->assertSessionHasNoErrors()->assertSessionHas('success');

        $gauge->refresh();
        $this->assertSame([today()->toDateString(), today()->addDays(180)->toDateString(), 'IN_SERVICE'], [$gauge->last_calibrated_on->toDateString(), $gauge->next_due_on->toDateString(), $gauge->status]);
        $this->get(route('gauges.show', $gauge))->assertSee('C-77')->assertSee('outside laboratory');
    }

    public function test_an_explicit_due_date_wins_and_a_future_calibration_date_is_refused(): void
    {
        $gauge = $this->gauge();

        $this->calibrate($gauge, ['due_on' => today()->addDays(30)->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(today()->addDays(30)->toDateString(), $gauge->fresh()->next_due_on->toDateString());

        $this->calibrate($gauge, ['calibrated_on' => today()->addDay()->toDateString()])->assertSessionHasErrors('calibrated_on');
    }

    public function test_a_failed_calibration_takes_the_instrument_out_of_service_and_a_later_pass_returns_it(): void
    {
        $gauge = $this->gauge(['next_due_on' => today()->addDays(100)]);

        $this->calibrate($gauge, ['result' => 'FAIL', 'as_found' => 'Reads 0.1 mm high'])->assertSessionHas('warning');
        $gauge->refresh();
        $this->assertSame(['OUT_OF_SERVICE', null], [$gauge->status, $gauge->next_due_on]);

        $this->calibrate($gauge, ['result' => 'ADJUSTED'])->assertSessionHasNoErrors();
        $gauge->refresh();
        $this->assertSame('IN_SERVICE', $gauge->status);
        $this->assertNotNull($gauge->next_due_on);
        $this->assertCount(2, $gauge->calibrations);
    }

    public function test_a_certificate_file_is_attached_to_the_instrument(): void
    {
        $gauge = $this->gauge();

        $this->calibrate($gauge, ['certificate' => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();

        $this->assertSame(1, Attachment::where('attachable_type', 'gauge')->where('attachable_id', $gauge->id)->where('category', 'CERTIFICATE')->count());
    }

    public function test_the_register_lists_the_overdue_first_and_filters_by_state(): void
    {
        $this->gauge(['code' => 'OK-1', 'next_due_on' => today()->addDays(200)]);
        $this->gauge(['code' => 'LATE-1', 'next_due_on' => today()->subDays(5)]);

        $this->get(route('gauges.index'))->assertOk()->assertSeeInOrder(['LATE-1', 'OK-1']);
        $this->get(route('gauges.index', ['state' => 'overdue']))->assertSee('LATE-1')->assertDontSee('OK-1');
    }

    private function inspectionResult(): QualityInspectionResult
    {
        $product = ProductRevision::create(['product_id' => Product::create(['code' => 'P-'.uniqid(), 'name' => 'Part'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'product_id' => $product->product_id, 'product_revision_id' => $product->id, 'order_type' => 'STOCK', 'status' => 'IN_PROGRESS', 'planned_qty' => 5, 'uom' => 'piece']);
        $heat = $this->heat();
        $pour = Pour::create(['pour_number' => 'P-'.uniqid(), 'heat_id' => $heat->id, 'status' => 'COMPLETED']);
        $pattern = Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE']);
        $patternRevision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);
        $run = MouldingRun::create(['run_number' => 'MR-'.uniqid(), 'production_order_id' => $order->id, 'pattern_revision_id' => $patternRevision->id, 'run_date' => today(), 'status' => 'COMPLETED', 'moulds_made_qty' => 1, 'accepted_mould_qty' => 1]);
        $configuration = MouldConfiguration::create(['code' => 'MC-'.uniqid(), 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);
        $assembly = MouldAssembly::create(['assembly_number' => 'MA-'.uniqid(), 'mould_configuration_id' => $configuration->id, 'moulding_run_id' => $run->id, 'status' => 'POURED']);
        $row = PourMouldAssembly::create(['pour_id' => $pour->id, 'mould_assembly_id' => $assembly->id, 'production_order_id' => $order->id, 'castings_poured_qty' => 5]);
        $lot = CastingLot::create(['lot_number' => 'LOT-'.uniqid(), 'pour_mould_assembly_id' => $row->id, 'production_order_id' => $order->id, 'product_id' => $product->product_id, 'product_revision_id' => $product->id, 'status' => 'IN_PRODUCTION', 'quantity_poured' => 5, 'quantity_good' => 5, 'quantity_rejected' => 0]);
        $inspection = QualityInspection::create(['inspection_number' => 'INS-'.uniqid(), 'casting_lot_id' => $lot->id, 'inspection_type' => 'DIMENSIONAL', 'status' => 'IN_PROGRESS', 'sample_size' => 1, 'quantity_inspected' => 1, 'inspector_name' => 'Imran']);

        return QualityInspectionResult::create(['quality_inspection_id' => $inspection->id, 'sequence' => 1, 'test_code' => 'LEN', 'test_name' => 'Length', 'result_type' => 'NUMERIC', 'minimum_value' => 9, 'maximum_value' => 11]);
    }

    private function saveResult(QualityInspectionResult $result, ?Gauge $gauge)
    {
        return $this->post(route('inspections.results.save', $result->inspection), ['results' => [$result->id => ['measured_value' => '10', 'gauge_id' => $gauge?->id]]]);
    }

    public function test_a_result_measured_with_an_in_date_instrument_records_it_without_a_warning(): void
    {
        $result = $this->inspectionResult();
        $gauge = $this->gauge(['code' => 'CAL-1', 'next_due_on' => today()->addDays(200)]);

        $this->saveResult($result, $gauge)->assertSessionHasNoErrors()->assertSessionMissing('warning');

        $result->refresh();
        $this->assertSame([$gauge->id, 'CAL-1'], [$result->gauge_id, $result->equipment_reference]);
    }

    public function test_an_overdue_instrument_warns_by_default_blocks_when_set_and_is_silent_when_off(): void
    {
        $result = $this->inspectionResult();
        $overdue = $this->gauge(['code' => 'OLD-1', 'next_due_on' => today()->subDays(3)]);

        $this->saveResult($result, $overdue)->assertSessionHas('warning');
        $this->assertSame($overdue->id, $result->fresh()->gauge_id);

        CalibrationRules::set('overdue_use', 'BLOCK');
        $other = $this->inspectionResult();
        $this->saveResult($other, $overdue)->assertSessionHasErrors("results.{$other->id}.gauge_id");
        $this->assertNull($other->fresh()->measured_value);
        $this->assertNull($other->fresh()->gauge_id);

        CalibrationRules::set('overdue_use', 'OFF');
        $quiet = $this->inspectionResult();
        $this->saveResult($quiet, $overdue)->assertSessionHasNoErrors()->assertSessionMissing('warning');
    }

    public function test_an_inspection_without_an_instrument_still_saves(): void
    {
        $result = $this->inspectionResult();

        $this->saveResult($result, null)->assertSessionHasNoErrors();

        $this->assertEquals(10, $result->fresh()->measured_value);
    }

    private function heat(): Heat
    {
        $furnace = Furnace::create(['code' => 'CU-'.uniqid(), 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'rated_capacity_kg' => 5000, 'nominal_melting_rate_kg_per_hour' => 3000]);

        return Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'MELTING']);
    }

    public function test_chemistry_readings_take_the_instrument_and_follow_the_same_rule(): void
    {
        $heat = $this->heat();
        $spectro = $this->gauge(['code' => 'SPEC-1', 'instrument_type' => 'SPECTROMETER', 'next_due_on' => today()->subDays(2)]);
        $form = ['sample_stage' => 'BASE', 'test_method' => 'SPECTROMETER', 'carbon_percent' => 3.3, 'silicon_percent' => 2.0, 'gauge_id' => $spectro->id];

        $this->post(route('heats.chemistry.store', $heat), $form)->assertSessionHas('warning');
        $reading = HeatChemistryReading::where('heat_id', $heat->id)->firstOrFail();
        $this->assertSame([$spectro->id, 'SPEC-1'], [$reading->gauge_id, $reading->instrument_reference]);

        CalibrationRules::set('overdue_use', 'BLOCK');
        $this->post(route('heats.chemistry.store', $heat), $form)->assertSessionHasErrors('gauge_id');
        $this->assertSame(1, HeatChemistryReading::where('heat_id', $heat->id)->count());
    }

    public function test_a_thermal_test_records_its_analyser_and_follows_the_same_rule(): void
    {
        $heat = $this->heat();
        $analyser = $this->gauge(['code' => 'TA-1', 'instrument_type' => 'THERMAL_ANALYSER', 'next_due_on' => today()->subDays(2)]);
        $form = ['heat_id' => $heat->id, 'stage' => 'BASE', 'cup_type' => 'STABLE', 'tl_c' => 1190, 'gauge_id' => $analyser->id];

        $this->post(route('thermal.store'), $form)->assertSessionHas('warning');
        $this->assertSame($analyser->id, ThermalAnalysisTest::where('heat_id', $heat->id)->firstOrFail()->gauge_id);

        CalibrationRules::set('overdue_use', 'BLOCK');
        $this->post(route('thermal.store'), $form)->assertSessionHasErrors('gauge_id');
        $this->assertSame(1, ThermalAnalysisTest::where('heat_id', $heat->id)->count());
    }

    public function test_the_dashboard_flags_overdue_instruments_and_stays_quiet_when_all_are_in_date(): void
    {
        $this->gauge(['code' => 'OK-1', 'next_due_on' => today()->addDays(200)]);
        $this->assertSame([], collect(app(DashboardData::class)->attention())->where('tag', 'Calibration')->all());

        $this->gauge(['code' => 'LATE-1', 'next_due_on' => today()->subDay()]);
        $item = collect(app(DashboardData::class)->attention())->firstWhere('tag', 'Calibration');
        $this->assertSame('red', $item['tone']);
        $this->assertStringContainsString('1 instrument is overdue', $item['text']);
    }

    public function test_imported_results_use_the_chosen_instrument(): void
    {
        $heat = $this->heat();
        $spectro = $this->gauge(['code' => 'SPEC-2', 'instrument_type' => 'SPECTROMETER', 'next_due_on' => today()->addDays(100)]);

        $this->post(route('heats.chemistry.import', $heat), ['test_method' => 'SPECTROMETER', 'sample_stage' => 'BASE', 'gauge_id' => $spectro->id, 'results' => "Sample,C,Si\nA,3.3,2.0"])->assertSessionHasNoErrors();

        $this->assertSame($spectro->id, HeatChemistryReading::where('heat_id', $heat->id)->firstOrFail()->gauge_id);
    }

    public function test_the_calibration_rules_can_be_changed_on_their_screen(): void
    {
        $this->get(route('settings.calibration.edit'))->assertOk()->assertSee('Calibration rules');

        $this->put(route('settings.calibration'), ['overdue_use' => 'BLOCK', 'due_soon_days' => 14])->assertSessionHasNoErrors();

        $this->assertSame(['BLOCK', '14'], [CalibrationRules::get('overdue_use'), CalibrationRules::get('due_soon_days')]);
        $this->put(route('settings.calibration'), ['overdue_use' => 'NEVER', 'due_soon_days' => 14])->assertSessionHasErrors('overdue_use');
    }
}
