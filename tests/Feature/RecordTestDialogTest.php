<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HeatChemistryReading;
use App\Models\IronGrade;
use App\Models\Pour;
use App\Models\ThermalAnalysisTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordTestDialogTest extends TestCase
{
    use RefreshDatabase;

    private function heat(string $status = 'IN_PROGRESS'): Heat
    {
        $f = Furnace::create(['code' => 'IF-'.uniqid(), 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => $status,
            'iron_grade_id' => IronGrade::where('code', 'EN-GJS-500-7')->value('id')]);
    }

    /** @return array<string, mixed> */
    private function thermal(Heat $heat, array $extra = []): array
    {
        return $extra + ['_quick' => 'qd-record-test', '_tab' => 'thermal', '_dialog' => 1, 'heat_id' => $heat->id, 'stage' => 'POST_TREATMENT',
            'white' => ['carbon' => 3.6, 'silicon' => 2.4, 'tl_c' => 1182, 'solidus_c' => 1121, 'peak_temp_c' => 1385]];
    }

    public function test_the_thermal_test_preselects_the_registered_thermal_analysis_unit(): void
    {
        $gauge = fn (string $name, string $type, array $extra = []) => \App\Domains\Maintenance\Models\Gauge::create($extra + ['name' => $name, 'instrument_type' => $type, 'status' => 'IN_SERVICE', 'requires_calibration' => false]);
        $gauge('Caliper', 'CALIPER');
        $gauge('Old analyser', 'THERMAL_ANALYSER', ['requires_calibration' => true, 'calibration_interval_days' => 365]);
        $analyser = $gauge('ATAS cup stand', 'THERMAL_ANALYSER');

        $this->get(route('heats.show', $this->heat()))->assertOk()
            ->assertSee('<option value="'.$analyser->id.'" selected', false);
    }

    public function test_the_thermal_form_shows_the_heats_grade_ranges_on_its_result_tiles_and_a_picker_carries_each_heats(): void
    {
        $grade = IronGrade::where('code', 'EN-GJS-500-7')->firstOrFail();
        $grade->update(['c_min' => 3.3, 'c_max' => 3.5, 'si_min' => 1.7, 'si_max' => 2.0, 'ce_min' => 3.95, 'ce_max' => 4.05]);
        $heat = $this->heat();

        $this->get(route('heats.show', $heat))->assertOk()
            ->assertSee('data-ranges=\'{"c":["3.300","3.500"],"si":["1.700","2.000"],"ce":["3.95","4.05"]}\'', false)
            ->assertSee('3.3–3.5%')->assertSee('3.95–4.05%')->assertSee('Grade: EN-GJS-500-7 · Target CE: 3.95–4.05%')->assertDontSee('Tick a cup to enter')->assertSee('All timestamps now')
            ->assertSee('<form id="qd-record-test-thermal-form"', false)->assertSee('form="qd-record-test-thermal-form"', false)
            ->assertSee('name="show_white" value="1" data-cup-toggle="white" checked', false)->assertSee('<div class="cup-body" data-cup="grey"', false)
            ->assertSee('Analyse &amp; save test', false)->assertSee('Line: '.$heat->furnace->code)->assertDontSee('Record a test on heat');
        $this->get(route('thermal.create'))->assertOk()->assertSee('name="gauge_id"', false)->assertSee('name="white[carbon]"', false)->assertSee('name="grey[te_min_c]"', false);
    }

    public function test_the_heat_page_offers_the_dialog_with_thermal_and_chemical_tabs_for_its_own_heat(): void
    {
        $heat = $this->heat();

        $this->get(route('heats.show', $heat))->assertOk()
            ->assertSee('data-quick="qd-record-test"', false)->assertSee('<dialog id="qd-record-test"', false)
            ->assertSee('Thermal test')->assertSee('Chemical analysis')
            ->assertSee('<input type="hidden" name="heat_id" value="'.$heat->id.'">', false)
            ->assertSee(route('heats.chemistry.store', $heat), false);

        $this->get(route('heats.show', $this->heat('CLOSED')))->assertOk()->assertDontSee('<dialog id="qd-record-test"', false);
    }

    public function test_a_thermal_test_saved_from_the_dialog_returns_to_the_page_it_was_opened_from(): void
    {
        $heat = $this->heat();

        $this->from(route('heats.show', $heat))->post(route('thermal.store'), $this->thermal($heat))
            ->assertSessionHasNoErrors()->assertRedirect(route('heats.show', $heat))->assertSessionHas('success');
        $this->assertSame(1, ThermalAnalysisTest::where('heat_id', $heat->id)->count());
    }

    public function test_a_refused_thermal_test_reopens_the_dialog_on_the_same_page_with_the_input_kept(): void
    {
        $heat = $this->heat();

        $this->from(route('heats.show', $heat))->post(route('thermal.store'), ['_quick' => 'qd-record-test', '_tab' => 'thermal', '_dialog' => 1, 'heat_id' => $heat->id, 'stage' => 'BASE', 'sample_reference' => 'S-9'])
            ->assertRedirect(route('heats.show', $heat))->assertSessionHasErrors('curve_text');
        $this->assertSame(0, ThermalAnalysisTest::count());

        $this->followingRedirects()->from(route('heats.show', $heat))->post(route('thermal.store'), ['_quick' => 'qd-record-test', '_tab' => 'thermal', '_dialog' => 1, 'heat_id' => $heat->id, 'stage' => 'BASE', 'sample_reference' => 'S-9'])
            ->assertSee('data-reopen=', false)->assertSee('value="S-9"', false);
    }

    public function test_a_chemical_analysis_saved_from_the_dialog_lands_back_on_the_heat_and_a_bad_one_reopens_the_chemistry_tab(): void
    {
        $heat = $this->heat();
        $chem = ['_quick' => 'qd-record-test', '_tab' => 'chemistry', 'sample_stage' => 'BASE', 'test_method' => 'SPECTROMETER', 'carbon_percent' => 3.6, 'silicon_percent' => 2.4];

        $this->from(route('heats.show', $heat))->post(route('heats.chemistry.store', $heat), $chem)->assertSessionHasNoErrors()->assertRedirect(route('heats.show', $heat));
        $this->assertSame(1, HeatChemistryReading::where('heat_id', $heat->id)->count());

        $empty = ['_quick' => 'qd-record-test', '_tab' => 'chemistry', 'sample_stage' => 'BASE', 'test_method' => 'SPECTROMETER'];
        $this->followingRedirects()->from(route('heats.show', $heat))->post(route('heats.chemistry.store', $heat), $empty)
            ->assertSee('data-reopen=', false)->assertSee('aria-selected="true">Chemical analysis', false);
        $this->assertSame(1, HeatChemistryReading::count());
    }

    public function test_the_quick_floor_page_uses_one_dialog_with_a_heat_picker_and_a_button_per_heat(): void
    {
        $a = $this->heat();
        $b = $this->heat();

        $this->get(route('quick.floor.index'))->assertOk()
            ->assertSee('data-quick="qd-record-test" data-heat="'.$a->id.'"', false)->assertSee('data-quick="qd-record-test" data-heat="'.$b->id.'"', false)
            ->assertSee('data-heat-select', false)->assertSee('<dialog id="qd-record-test"', false)->assertDontSee(route('thermal.create', ['heat_id' => $a->id]), false);
    }

    public function test_the_pour_gate_opens_the_dialog_for_the_pours_heat_instead_of_the_record_page(): void
    {
        $heat = $this->heat();
        $pour = Pour::create(['pour_number' => 'P-1', 'heat_id' => $heat->id, 'status' => 'PLANNED']);

        $this->get(route('pours.show', $pour))->assertOk()->assertSee('data-quick="qd-record-test"', false)
            ->assertSee('<input type="hidden" name="heat_id" value="'.$heat->id.'">', false)->assertDontSee(route('thermal.create', ['heat_id' => $heat->id]), false);
    }

    public function test_the_record_page_still_works_with_its_heat_picker(): void
    {
        $heat = $this->heat();

        $this->get(route('thermal.create', ['heat_id' => $heat->id]))->assertOk()->assertSee('data-heat-select', false)->assertSee('White cup', false)->assertSee('id="heat_id"', false);
        $this->post(route('thermal.store'), $this->thermal($heat, ['_dialog' => 0]))->assertSessionHasNoErrors();
        $this->assertSame(1, ThermalAnalysisTest::count());
        $this->post(route('thermal.store'), $this->thermal($heat, ['_dialog' => 0]))->assertRedirect(route('thermal.show', ThermalAnalysisTest::orderByDesc('id')->first()));
    }

    public function test_the_samples_panel_has_a_test_button_with_the_t_shortcut_that_opens_the_dialog_unless_the_heat_is_closed(): void
    {
        $heat = $this->heat();
        $heat->update(['actual_start_at' => now()->subHours(2)]);

        $this->get(route('heats.show', $heat))->assertOk()
            ->assertSeeInOrder(['Samples', 'class="btn btn-small sample-add" data-quick="qd-record-test"', '+ Test'], false)
            ->assertSee("(e.key || '').toLowerCase() !== 't'", false)->assertDontSee('sample-plus', false);

        $this->get(route('heats.show', $this->heat('CLOSED')))->assertOk()->assertDontSee('data-test-shortcut', false)->assertDontSee("toLowerCase() !== 't'", false);
    }
}
