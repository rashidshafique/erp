<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HeatChemistryReading;
use App\Models\IronGrade;
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
use App\Models\ThermalAnalysisTest;
use App\Services\Production\Containment;
use App\Services\Production\HeatSamples;
use App\Services\Quality\ChemistryImport;
use App\Support\PouringRules;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HeatSamplingTest extends TestCase
{
    use RefreshDatabase;

    private function cupolaHeat(array $extra = []): Heat
    {
        $furnace = Furnace::create(['code' => 'CU-'.uniqid(), 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'rated_capacity_kg' => 5000, 'nominal_melting_rate_kg_per_hour' => 3000]);

        return Heat::create($extra + ['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'MELTING', 'iron_grade_id' => IronGrade::where('family', 'GREY')->value('id')]);
    }

    private function reading(Heat $heat, \DateTimeInterface $at, ?bool $accepted = true, string $ref = 'S'): HeatChemistryReading
    {
        return HeatChemistryReading::create(['heat_id' => $heat->id, 'reading_number' => HeatChemistryReading::where('heat_id', $heat->id)->count() + 1, 'carbon_percent' => 3.3, 'silicon_percent' => 2.0,
            'is_accepted' => $accepted, 'tested_at' => $at, 'sample_reference' => $ref]);
    }

    public function test_samples_are_listed_in_the_order_they_were_taken(): void
    {
        $heat = $this->cupolaHeat();
        $this->reading($heat, now()->subMinutes(10), true, 'LATE');
        $this->reading($heat, now()->subMinutes(40), false, 'EARLY');

        $timeline = app(HeatSamples::class)->timeline($heat);

        $this->assertSame(['EARLY', 'LATE'], $timeline->pluck('reference')->all());
        $this->assertSame(['FAIL', 'PASS'], $timeline->pluck('result')->all());
    }

    public function test_the_samples_panel_shows_c_ce_and_si_with_the_time_in_12_hours_and_the_date_only_when_not_today(): void
    {
        $heat = $this->cupolaHeat();
        HeatChemistryReading::create(['heat_id' => $heat->id, 'reading_number' => 1, 'carbon_percent' => 3.42, 'silicon_percent' => 1.85, 'carbon_equivalent' => 4.01,
            'is_accepted' => true, 'tested_at' => now()->setTime(14, 5), 'sample_reference' => 'TODAY']);
        HeatChemistryReading::create(['heat_id' => $heat->id, 'reading_number' => 2, 'carbon_percent' => 3.1, 'is_accepted' => true,
            'tested_at' => now()->subDay()->setTime(9, 30), 'sample_reference' => 'OLDER']);
        ThermalAnalysisTest::create(['heat_id' => $heat->id, 'stage' => 'POST_TREATMENT', 'cup_type' => 'WHITE', 'taken_at' => now()->setTime(15, 20), 'sample_reference' => 'TH-1',
            'carbon_from_tl' => 3.55, 'ce_from_tl' => 3.98, 'silicon_used' => 2.1, 'result' => 'PASS', 'curve' => [], 'entry_mode' => 'CURVE']);

        $timeline = app(HeatSamples::class)->timeline($heat);
        $this->assertSame([3.1, 3.42, 3.55], $timeline->pluck('c')->all());
        $this->assertSame([null, 4.01, 3.98], $timeline->pluck('ce')->all());

        $page = $this->get(route('heats.show', $heat))->assertOk()->assertSee('<th class="num">C %</th><th class="num">CE</th><th class="num">Si %</th>', false)
            ->assertSee('2:05 PM')->assertSee('3:20 PM')->assertSee(now()->subDay()->displayShortDate().', 9:30 AM')
            ->assertSee('<td class="num">3.42</td><td class="num">4.01</td><td class="num">1.85</td>', false)
            ->assertSee('<td class="num">3.10</td><td class="num">—</td><td class="num">—</td>', false)
            ->assertSee('<th>Taken</th><th>Reference</th>', false)->assertDontSee('<th>Taken</th><th>Type</th>', false);
        $this->assertStringNotContainsString(now()->displayShortDate().', 2:05 PM', $page->getContent());
    }

    public function test_the_latest_test_comes_first_in_the_lists_and_on_the_status_board(): void
    {
        $heat = $this->cupolaHeat();
        HeatChemistryReading::create(['heat_id' => $heat->id, 'reading_number' => 1, 'carbon_percent' => 3.1, 'silicon_percent' => 1.5, 'carbon_equivalent' => 3.6, 'is_accepted' => true,
            'tested_at' => now()->subHours(2), 'sample_reference' => 'FIRST']);
        HeatChemistryReading::create(['heat_id' => $heat->id, 'reading_number' => 2, 'carbon_percent' => 3.42, 'silicon_percent' => 1.85, 'carbon_equivalent' => 4.01, 'is_accepted' => false,
            'tested_at' => now()->subMinutes(10), 'sample_reference' => 'LATEST']);

        $this->get(route('heats.show', $heat))->assertOk()->assertSeeInOrder(['class="sample-readings no-row-click"', 'LATEST', 'FIRST'], false);
        \Livewire\Livewire::test('heat-status-board', ['heatId' => $heat->id])->assertSeeInOrder(['Latest test', 'FAIL', 'C 3.42', 'CE 4.01', 'Si 1.85', 'LATEST'])->assertDontSee('FIRST');
        \Livewire\Livewire::test('heat-status-board', ['heatId' => $this->cupolaHeat()->id])->assertSee('No test yet');
    }

    public function test_a_sample_opens_read_only_in_the_dialog_it_was_entered_in_and_a_fail_carries_its_reason(): void
    {
        $heat = $this->cupolaHeat();
        $heat->ironGrade->update(['c_min' => 3.2, 'c_max' => 3.6]);
        $chemistry = HeatChemistryReading::create(['heat_id' => $heat->id, 'reading_number' => 1, 'carbon_percent' => 2.9, 'is_accepted' => false, 'tested_at' => now()->subMinutes(30),
            'sample_reference' => 'LOW-C', 'sample_stage' => 'FINAL', 'test_method' => 'SPECTROMETER']);
        $group = (string) \Illuminate\Support\Str::uuid();
        $cup = fn (string $type, array $extra) => ThermalAnalysisTest::create($extra + ['heat_id' => $heat->id, 'sample_group_id' => $group, 'stage' => 'POST_TREATMENT', 'cup_type' => $type,
            'taken_at' => now()->subMinutes(10), 'sample_reference' => 'T-BAD', 'curve' => [], 'entry_mode' => 'MANUAL', 'result' => 'PASS']);
        $cup('TELLURIUM', ['tl_c' => 1180.5, 'ce_from_tl' => 4.12]);
        $cup('STABLE', ['te_min_c' => 1141, 'result' => 'FAIL', 'findings' => ['Eutectic too low.']]);

        $timeline = app(HeatSamples::class)->timeline($heat);
        $this->assertSame(['sample-view-'.$group], $timeline->where('kind', 'THERMAL')->pluck('view')->unique()->values()->all());
        $this->assertSame('sample-view-'.$chemistry->id, $timeline->firstWhere('kind', 'CHEMISTRY')['view']);
        $this->assertSame('Eutectic too low.', $timeline->where('kind', 'THERMAL')->firstWhere('result', 'FAIL')['detail']);
        $this->assertStringContainsString('C 2.9 % is below the minimum 3.2 %', $timeline->firstWhere('kind', 'CHEMISTRY')['detail']);

        $page = $this->get(route('heats.show', $heat))->assertOk()
            ->assertSee('data-quick="sample-view-'.$group.'"', false)->assertSee('data-quick="sample-view-'.$chemistry->id.'"', false)
            ->assertSee('data-fail-reason="Eutectic too low."', false);
        $html = $page->getContent();
        $this->assertSame(1, substr_count($html, '<dialog id="sample-view-'.$group.'"'));   // both cups of a sample open one dialog

        // The dialogs show the saved values in the entry form's own fields, read-only.
        $page->assertSeeInOrder(['<dialog id="sample-view-'.$group.'"', 'Eutectic too low.', '<fieldset disabled>', 'value="4.12"', 'value="1180.5"', 'value="1141"', '</dialog>'], false)
            ->assertSeeInOrder(['<dialog id="sample-view-'.$chemistry->id.'"', 'Chemical analysis', '<fieldset disabled>', 'value="LOW-C"', 'value="2.9"', '</dialog>'], false);
    }

    public function test_the_next_sample_is_due_after_the_interval_and_overdue_after_it(): void
    {
        PouringRules::set('test_interval_minutes', '30');
        $heat = $this->cupolaHeat(['first_tap_at' => now()->subMinutes(50)]);
        $samples = app(HeatSamples::class);

        $this->assertTrue($samples->due($heat)['overdue']);

        $this->reading($heat, now()->subMinutes(10));
        $due = $samples->due($heat->fresh());
        $this->assertFalse($due['overdue']);
        $this->assertSame(20, $due['minutes_left']);
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Next sample due in 20 min')
            ->assertSee('data-sample-clock data-since="'.$heat->fresh()->chemistryReadings()->first()->tested_at->toIso8601String().'" data-interval="30" data-has-last="1"', false);
        $this->assertSame($heat->first_tap_at->toIso8601String(), app(HeatSamples::class)->due($this->cupolaHeat(['first_tap_at' => $heat->first_tap_at]))['since_at']->toIso8601String(), 'With no sample yet the count starts at the run.');
    }

    public function test_no_reminder_before_the_cupola_runs_after_the_bottom_drop_or_on_other_furnaces(): void
    {
        $samples = app(HeatSamples::class);

        $this->assertNull($samples->due($this->cupolaHeat()));
        $this->assertNull($samples->due($this->cupolaHeat(['first_tap_at' => now()->subHour(), 'dropped_at' => now()])));
    }

    public function test_a_pour_is_judged_by_the_sample_before_it_or_the_first_after_it(): void
    {
        $heat = $this->cupolaHeat();
        $pour = Pour::create(['pour_number' => 'P-1', 'heat_id' => $heat->id, 'status' => 'IN_PROGRESS', 'started_at' => now()->subMinutes(30)]);
        $samples = app(HeatSamples::class);

        $this->assertSame([], $samples->governing($pour));

        $this->reading($heat, now()->subMinutes(10), true, 'AFTER');
        $afterOnly = $samples->governing($pour->fresh('heat'));
        $this->assertSame(['AFTER', 20], [$afterOnly['CHEMISTRY']['timing'], $afterOnly['CHEMISTRY']['minutes']]);

        $this->reading($heat, now()->subMinutes(45), true, 'BEFORE');
        $both = $samples->governing($pour->fresh('heat'));
        $this->assertSame(['BEFORE', 'BEFORE'], [$both['CHEMISTRY']['timing'], $both['CHEMISTRY']['sample']['reference'] === 'BEFORE' ? 'BEFORE' : 'WRONG']);
        $this->get(route('pours.show', $pour))->assertOk()->assertSee('Samples for this pour')->assertSee('before the pour started');
    }

    public function test_spectrometer_results_are_parsed_whatever_the_separator_and_heading_style(): void
    {
        $import = app(ChemistryImport::class);

        $comma = $import->parse("Sample,Time,C,Si,Mn,S,P\nS-1,2026-10-18 10:15,3.35,2.10,0.60,0.08,0.12\nS-2,2026-10-18 10:45,3.30,2.00,0.55,0.09,0.11");
        $this->assertCount(2, $comma['rows']);
        $this->assertSame([3.35, 'S-1'], [$comma['rows'][0]['carbon_percent'], $comma['rows'][0]['sample_reference']]);

        $tabs = $import->parse("Sample ID\tC %\tSi %\tMn %\n7\t3,4\t2,2\t0,7");
        $this->assertSame(3.4, $tabs['rows'][0]['carbon_percent']);

        $semi = $import->parse("Carbon;Silicon\n3.2;1.9");
        $this->assertSame(1.9, $semi['rows'][0]['silicon_percent']);
    }

    public function test_bad_rows_and_files_without_element_columns_are_refused_with_a_reason(): void
    {
        $import = app(ChemistryImport::class);

        $this->assertStringContainsString('No element columns', $import->parse("Name,Colour\na,b")['errors'][0]);
        $bad = $import->parse("C,Si\nabc,2.0\n3.3,2.0");
        $this->assertCount(1, $bad['rows']);
        $this->assertStringContainsString("'abc' is not a percentage", $bad['errors'][0]);
        $this->assertStringContainsString('header row', $import->parse('C,Si')['errors'][0]);
    }

    public function test_pasted_results_become_readings_with_their_own_times(): void
    {
        $heat = $this->cupolaHeat();

        $this->post(route('heats.chemistry.import', $heat), [
            'test_method' => 'SPECTROMETER', 'sample_stage' => 'BASE', 'instrument_reference' => 'SPECTRO-1',
            'results' => "Sample,Time,C,Si,Mn,S,P\nS-1,2026-10-18 10:15,3.35,2.10,0.60,0.08,0.12\nS-2,2026-10-18 10:45,3.30,2.00,0.55,0.09,0.11",
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $readings = HeatChemistryReading::where('heat_id', $heat->id)->orderBy('reading_number')->get();
        $this->assertSame(['S-1', 'S-2'], $readings->pluck('sample_reference')->all());
        $this->assertSame('SPECTRO-1', $readings->first()->instrument_reference);
        $this->assertSame('2026-10-18 10:15', $readings->first()->tested_at->format('Y-m-d H:i'));
        $this->assertNotNull($readings->first()->carbon_equivalent);
    }

    public function test_an_uploaded_export_works_like_pasted_text(): void
    {
        $heat = $this->cupolaHeat();
        $file = UploadedFile::fake()->createWithContent('run.csv', "Sample,C,Si\nA1,3.3,2.0");

        $this->post(route('heats.chemistry.import', $heat), ['test_method' => 'SPECTROMETER', 'sample_stage' => 'BASE', 'results_file' => $file])->assertSessionHasNoErrors();

        $this->assertSame(1, HeatChemistryReading::where('heat_id', $heat->id)->count());
    }

    public function test_an_unreadable_paste_is_refused_without_saving_anything(): void
    {
        $heat = $this->cupolaHeat();

        $this->post(route('heats.chemistry.import', $heat), ['test_method' => 'SPECTROMETER', 'sample_stage' => 'BASE', 'results' => "Name,Colour\na,b"])->assertSessionHasErrors('chemistry');

        $this->assertSame(0, HeatChemistryReading::where('heat_id', $heat->id)->count());
    }

    public function test_the_hand_entry_form_still_records_a_reading(): void
    {
        $heat = $this->cupolaHeat();

        $this->post(route('heats.chemistry.store', $heat), ['sample_stage' => 'BASE', 'test_method' => 'SPECTROMETER', 'carbon_percent' => 3.3, 'silicon_percent' => 2.0])->assertSessionHasNoErrors();

        $this->assertSame(1, HeatChemistryReading::where('heat_id', $heat->id)->count());
    }

    private function completedLotFor(Heat $heat, CarbonInterface $startedAt): array
    {
        $pour = Pour::create(['pour_number' => 'P-'.uniqid(), 'heat_id' => $heat->id, 'status' => 'COMPLETED', 'started_at' => $startedAt, 'completed_at' => $startedAt->copy()->addMinutes(5)]);
        $product = ProductRevision::create(['product_id' => Product::create(['code' => 'RING-'.uniqid(), 'name' => 'Ring'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'product_id' => $product->product_id, 'product_revision_id' => $product->id, 'order_type' => 'STOCK', 'status' => 'IN_PROGRESS', 'planned_qty' => 10, 'uom' => 'piece']);
        $pattern = Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE']);
        $patternRevision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);
        $run = MouldingRun::create(['run_number' => 'MR-'.uniqid(), 'production_order_id' => $order->id, 'pattern_revision_id' => $patternRevision->id, 'run_date' => today(), 'status' => 'COMPLETED', 'moulds_made_qty' => 1, 'accepted_mould_qty' => 1]);
        $configuration = MouldConfiguration::create(['code' => 'MC-'.uniqid(), 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);
        $assembly = MouldAssembly::create(['assembly_number' => 'MA-'.uniqid(), 'mould_configuration_id' => $configuration->id, 'moulding_run_id' => $run->id, 'status' => 'POURED']);
        $row = PourMouldAssembly::create(['pour_id' => $pour->id, 'mould_assembly_id' => $assembly->id, 'production_order_id' => $order->id, 'castings_poured_qty' => 2]);
        $lot = CastingLot::create(['lot_number' => 'LOT-'.uniqid(), 'pour_mould_assembly_id' => $row->id, 'production_order_id' => $order->id, 'product_id' => $product->product_id, 'product_revision_id' => $product->id, 'status' => 'IN_PRODUCTION', 'quantity_poured' => 2, 'quantity_good' => 2, 'quantity_rejected' => 0]);

        return [$pour, $lot];
    }

    public function test_a_failing_sample_flags_the_pours_it_covers_and_holds_their_lots(): void
    {
        $heat = $this->cupolaHeat(['first_tap_at' => now()->subHours(3)]);
        $this->reading($heat, now()->subMinutes(150), true, 'GOOD-1');
        [$covered, $coveredLot] = $this->completedLotFor($heat, now()->subMinutes(100));
        [$before, $beforeLot] = $this->completedLotFor($heat, now()->subMinutes(160));

        $this->post(route('heats.chemistry.store', $heat), ['sample_stage' => 'BASE', 'test_method' => 'SPECTROMETER', 'carbon_percent' => 2.0, 'silicon_percent' => 2.0, 'tested_at' => now()->subMinutes(60)->format('Y-m-d\TH:i')])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertStringContainsString('out of limits', $covered->fresh()->containment_note);
        $this->assertSame('ON_HOLD', $coveredLot->fresh()->status);
        $this->assertStringContainsString('containment', $coveredLot->fresh()->notes);
        $this->assertNull($before->fresh()->containment_note);
        $this->assertSame('IN_PRODUCTION', $beforeLot->fresh()->status);
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Containment');
    }

    public function test_a_later_passing_sample_ends_the_period_and_pours_after_it_are_not_flagged(): void
    {
        $heat = $this->cupolaHeat(['first_tap_at' => now()->subHours(3)]);
        $this->reading($heat, now()->subMinutes(120), false, 'BAD');
        $this->reading($heat, now()->subMinutes(60), true, 'GOOD');
        [$inside] = $this->completedLotFor($heat, now()->subMinutes(90));
        [$after] = $this->completedLotFor($heat, now()->subMinutes(30));

        $result = app(Containment::class)->apply($heat);

        $this->assertSame(1, $result['pours']);
        $this->assertNotNull($inside->fresh()->containment_note);
        $this->assertNull($after->fresh()->containment_note);
    }

    public function test_lots_already_dispatched_or_on_hold_are_left_alone_and_running_it_twice_changes_nothing(): void
    {
        $heat = $this->cupolaHeat(['first_tap_at' => now()->subHours(3)]);
        $this->reading($heat, now()->subMinutes(30), false, 'BAD');
        [, $dispatched] = $this->completedLotFor($heat, now()->subMinutes(100));
        $dispatched->update(['status' => 'DISPATCHED']);
        [, $open] = $this->completedLotFor($heat, now()->subMinutes(90));
        $containment = app(Containment::class);

        $first = $containment->apply($heat);
        $second = $containment->apply($heat);

        $this->assertSame(['DISPATCHED', 'ON_HOLD'], [$dispatched->fresh()->status, $open->fresh()->status]);
        $this->assertSame([2, 1], [$first['pours'], $first['lots']]);
        $this->assertSame([0, 0], [$second['pours'], $second['lots']]);
    }

    public function test_flag_only_policy_marks_the_pours_but_holds_no_lots(): void
    {
        PouringRules::set('containment', 'NOTE');
        $heat = $this->cupolaHeat(['first_tap_at' => now()->subHours(3)]);
        $this->reading($heat, now()->subMinutes(30), false, 'BAD');
        [$pour, $lot] = $this->completedLotFor($heat, now()->subMinutes(90));

        app(Containment::class)->apply($heat);

        $this->assertStringContainsString('flagged only', $pour->fresh()->containment_note);
        $this->assertSame('IN_PRODUCTION', $lot->fresh()->status);
    }

    public function test_a_failing_thermal_test_is_treated_the_same_as_failing_chemistry(): void
    {
        $heat = $this->cupolaHeat(['first_tap_at' => now()->subHours(3)]);
        ThermalAnalysisTest::create(['heat_id' => $heat->id, 'iron_grade_id' => $heat->iron_grade_id, 'sample_reference' => 'T-BAD', 'stage' => 'POST_TREATMENT', 'cup_type' => 'STABLE', 'taken_at' => now()->subMinutes(20), 'curve' => [], 'entry_mode' => 'CURVE', 'result' => 'FAIL', 'findings' => ['Eutectic too low.']]);
        [$pour] = $this->completedLotFor($heat, now()->subMinutes(80));

        app(Containment::class)->apply($heat);

        $this->assertStringContainsString('Thermal analysis T-BAD', $pour->fresh()->containment_note);
    }
}
