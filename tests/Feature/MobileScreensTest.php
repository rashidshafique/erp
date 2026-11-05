<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\Role;
use App\Models\ThermalAnalysisTest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class MobileScreensTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    private function tech(string $role): User
    {
        $u = User::factory()->create(['is_active' => true, 'must_change_password' => false, 'password' => 'Longpass123']);
        $u->roles()->attach(Role::where('code', $role)->value('id'));

        return $u;
    }

    public function test_the_phone_login_is_open_and_sends_a_signed_in_tech_to_the_phone_home(): void
    {
        config(['foundry.require_login' => true]);
        $this->get(route('m.login'))->assertOk()->assertSee('Sign in')->assertSee('/manifest.webmanifest', false)->assertSee('Install on this phone');
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('icons/icon-512.png'));
        $u = $this->tech('QA_TEST_TECH');
        $this->post(route('login.attempt'), ['email' => $u->email, 'password' => 'Longpass123', 'mobile' => 1])->assertRedirect(route('m.home'));
    }

    public function test_tests_and_defects_are_given_separately(): void
    {
        config(['foundry.require_login' => true]);
        $tests = $this->tech('QA_TEST_TECH');
        $this->actingAs($tests)->get(route('m.home'))->assertOk()->assertSee('Tests due')->assertDontSee('Log defect');
        $this->get(route('m.tests.pick', 'thermal'))->assertOk();
        $this->get(route('m.defects.pick'))->assertForbidden();

        $defects = $this->tech('QA_DEFECT_TECH');
        $this->actingAs($defects)->get(route('m.home'))->assertOk()->assertSee('Log defect')->assertDontSee('Tests due');
        $this->get(route('m.tests.pick', 'thermal'))->assertForbidden();
        $this->get(route('m.defects.pick'))->assertOk();
    }

    private function meltingHeat(string $started = '-3 hours'): Heat
    {
        $furnace = Furnace::create(['code' => 'IF-'.uniqid(), 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 5000, 'nominal_melting_rate_kg_per_hour' => 3000]);

        return Heat::create(['heat_number' => 'HT-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'IN_PROGRESS', 'actual_start_at' => now()->modify($started)]);
    }

    public function test_the_home_shows_the_active_heat_and_when_its_next_reading_is_due(): void
    {
        $heat = $this->meltingHeat();
        $this->get(route('m.home'))->assertOk()->assertSee('Active heat')->assertSee($heat->heat_number)->assertSee('No thermal test yet')->assertSee('Start test');

        ThermalAnalysisTest::create(['heat_id' => $heat->id, 'sample_reference' => 'T-'.uniqid(), 'stage' => 'PRE_TREATMENT', 'cup_type' => 'TE', 'taken_at' => now()->subMinutes(53), 'curve' => [], 'result' => 'PASS']);
        $this->get(route('m.home'))->assertOk()->assertSee('>Reading overdue<', false)->assertSee('>+8:', false)->assertSee('re-test 2, TE cup');

        ThermalAnalysisTest::create(['heat_id' => $heat->id, 'sample_reference' => 'T-'.uniqid(), 'stage' => 'PRE_TREATMENT', 'cup_type' => 'TE', 'taken_at' => now()->subMinutes(5), 'curve' => [], 'result' => 'PASS']);
        $this->get(route('m.home'))->assertOk()->assertSee('>Next reading in<', false)->assertDontSee('>Reading overdue<', false);
    }

    public function test_tests_due_lists_what_is_waiting_and_marks_the_late_ones(): void
    {
        $this->get(route('m.home'))->assertOk()->assertSee('Nothing is waiting for a test.');

        $heat = $this->meltingHeat('-2 hours');
        $this->get(route('m.home'))->assertOk()->assertSee('Thermal analysis · '.$heat->heat_number, false)->assertSee('Spectro · '.$heat->heat_number, false)->assertSee('Overdue 105 min')->assertSee('Late');
    }

    public function test_a_role_with_no_phone_permission_has_no_home(): void
    {
        config(['foundry.require_login' => true]);
        $this->actingAs($this->tech('VIEWER'))->get(route('m.home'))->assertForbidden();
    }

    public function test_the_lot_list_is_newest_first_and_adjustable(): void
    {
        $old = $this->castingLot();
        $old->forceFill(['created_at' => now()->subDays(5)])->save();
        $new = $this->castingLot();
        $new->forceFill(['created_at' => now()])->save();

        $this->get(route('m.defects.pick', ['within' => 'all']))->assertSeeInOrder([$new->label, $old->label]);
        $this->get(route('m.defects.pick', ['within' => '1']))->assertSee($new->label)->assertDontSee($old->label);
        $this->get(route('m.defects.pick', ['size' => 10]))->assertOk();
    }

    public function test_a_thermal_test_is_always_linked_to_a_heat(): void
    {
        $this->get(route('m.tests.pick', 'thermal'))->assertOk()->assertDontSee('>Lots<', false);
        $this->get(route('m.tests.pick', 'inspection'))->assertOk()->assertSee('Heats')->assertSee('Lots');
        $this->get(route('m.tests.pick', 'nope'))->assertNotFound();
    }

    public function test_a_record_sent_twice_from_the_phone_is_saved_once(): void
    {
        $lot = $this->castingLot();
        $url = route('lots.inspections.store', $lot);
        $data = ['inspection_type' => 'FINAL', 'sample_size' => 1];

        $this->withHeaders(['X-Queue-Id' => 'abc-1', 'Accept' => 'application/json'])->post($url, $data)->assertRedirect();
        $this->withHeaders(['X-Queue-Id' => 'abc-1', 'Accept' => 'application/json'])->post($url, $data)->assertOk()->assertJson(['duplicate' => true]);

        $this->assertSame(1, $lot->inspections()->count());
    }

    public function test_a_refused_record_can_be_sent_again_once_fixed(): void
    {
        $lot = $this->castingLot();
        $url = route('lots.inspections.store', $lot);

        $this->withHeaders(['X-Queue-Id' => 'abc-2', 'Accept' => 'application/json'])->post($url, [])->assertStatus(422);
        $this->withHeaders(['X-Queue-Id' => 'abc-2', 'Accept' => 'application/json'])->post($url, ['inspection_type' => 'FINAL', 'sample_size' => 1])->assertRedirect();
        $this->assertSame(1, $lot->inspections()->count());
    }

    public function test_a_signed_out_phone_gets_401_not_a_redirect_and_a_token_when_signed_in(): void
    {
        config(['foundry.require_login' => true]);
        $this->getJson(route('m.token'))->assertUnauthorized();
        $this->actingAs($this->tech('QA_TEST_TECH'))->getJson(route('m.token'))->assertOk()->assertJsonStructure(['token']);
    }

    public function test_a_test_tech_can_save_an_inspection_and_the_right_people_hear_the_result(): void
    {
        config(['foundry.require_login' => true]);
        $tech = $this->tech('QA_TEST_TECH');
        $manager = $this->tech('QUALITY_MANAGER');
        $planner = $this->tech('PRODUCTION_MANAGER');
        $lot = $this->castingLot();

        $this->actingAs($tech)->post(route('lots.inspections.store', $lot), ['inspection_type' => 'FINAL', 'sample_size' => '5'])->assertSessionHasNoErrors();
        $inspection = \App\Models\QualityInspection::firstOrFail();
        $this->post(route('inspections.complete', $inspection), ['quantity_accepted' => '3', 'quantity_rejected' => '2', 'quantity_rework' => '0', 'quantity_pending' => '0', 'overall_disposition' => 'PARTIAL'])->assertSessionHasNoErrors();

        $this->assertStringContainsString('FAIL', $manager->notifications()->firstOrFail()->data['title']);
        $this->assertSame('danger', $planner->notifications()->firstOrFail()->data['tone']);
        $this->assertSame(0, $tech->notifications()->count());
    }

    public function test_a_passed_inspection_tells_planners_only(): void
    {
        config(['foundry.require_login' => true]);
        $tech = $this->tech('QA_TEST_TECH');
        $manager = $this->tech('QUALITY_MANAGER');
        $planner = $this->tech('PRODUCTION_MANAGER');
        $lot = $this->castingLot();

        $this->actingAs($tech)->post(route('lots.inspections.store', $lot), ['inspection_type' => 'FINAL', 'sample_size' => '5']);
        $this->post(route('inspections.complete', \App\Models\QualityInspection::firstOrFail()), ['quantity_accepted' => '5', 'quantity_rejected' => '0', 'quantity_rework' => '0', 'quantity_pending' => '0', 'overall_disposition' => 'ACCEPT']);

        $this->assertStringContainsString('PASS', $planner->notifications()->firstOrFail()->data['title']);
        $this->assertSame(0, $manager->notifications()->count());
    }

    public function test_a_defect_tech_cannot_save_test_results(): void
    {
        config(['foundry.require_login' => true]);
        $lot = $this->castingLot();
        $this->actingAs($this->tech('QA_DEFECT_TECH'))->post(route('lots.inspections.store', $lot), ['inspection_type' => 'FINAL', 'sample_size' => '5'])->assertForbidden();
    }

    public function test_a_failed_thermal_test_from_a_tech_tells_quality_and_the_melt_shop(): void
    {
        config(['foundry.require_login' => true]);
        $furnace = \App\Models\Furnace::create(['code' => 'IF-'.uniqid(), 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $heat = \App\Models\Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED',
            'iron_grade_id' => \App\Models\IronGrade::where('code', 'EN-GJS-500-7')->value('id')]);
        \App\Models\ThermalWindow::create(['iron_grade_id' => $heat->iron_grade_id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1140, 'recal_max_c' => 6]);
        $tech = $this->tech('QA_TEST_TECH');
        $manager = $this->tech('QUALITY_MANAGER');
        $melt = $this->tech('MELT_SUPERVISOR');

        $this->actingAs($tech)->post(route('thermal.store'), ['heat_id' => $heat->id, 'stage' => 'POST_TREATMENT', 'cup_type' => 'STABLE', 'tl_c' => 1190, 'te_min_c' => 1132, 'te_max_c' => 1150])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('FAIL', $manager->notifications()->firstOrFail()->data['title']);
        $this->assertStringContainsString('FAIL', $melt->notifications()->firstOrFail()->data['title']);
        $this->assertSame(0, $tech->notifications()->count());
    }

    public function test_the_tech_home_lists_tests_due_and_scan_opens_a_lot(): void
    {
        config(['foundry.require_login' => true]);
        $furnace = \App\Models\Furnace::create(['code' => 'IF-2', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $heat = \App\Models\Heat::create(['heat_number' => 'H-DUE-1', 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'IN_PROGRESS']);
        $lot = $this->castingLot();

        $this->actingAs($this->tech('QA_TEST_TECH'))->get(route('m.home'))->assertOk()->assertSee('Tests due')->assertSee('H-DUE-1')->assertSee('Thermal analysis')->assertSee('Scan label');
        $this->get(route('m.scan'))->assertOk()->assertSee('Scan label');
        $this->get(route('m.scan', ['code' => 'NOPE-0000']))->assertOk()->assertSee('Nothing found');
        $this->assertNotNull($heat->fresh());
    }

    public function test_people_land_on_their_own_home_after_signing_in(): void
    {
        config(['foundry.require_login' => true]);
        $this->assertSame('m.home', \App\Support\StartPage::routeFor($this->tech('QA_TEST_TECH')));
        $this->assertSame('m.home', \App\Support\StartPage::routeFor($this->tech('QA_DEFECT_TECH')));
        $this->assertSame('quality.home', \App\Support\StartPage::routeFor($this->tech('QUALITY_INSPECTOR')));
        $this->assertSame('quality.home', \App\Support\StartPage::routeFor($this->tech('QUALITY_MANAGER')));

        config(['foundry.require_login' => true]);
        $this->actingAs($this->tech('QUALITY_MANAGER'))->get(route('quality.home'))->assertOk()->assertSee('Waiting for inspection')->assertSee('Open nonconformances');
    }

    public function test_phone_entry_screens_are_their_own_and_save_without_the_desktop_pages(): void
    {
        config(['foundry.require_login' => true]);
        $furnace = \App\Models\Furnace::create(['code' => 'IF-'.uniqid(), 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $heat = \App\Models\Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED',
            'iron_grade_id' => \App\Models\IronGrade::where('code', 'EN-GJS-500-7')->value('id')]);
        \App\Models\ThermalWindow::create(['iron_grade_id' => $heat->iron_grade_id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1140, 'recal_max_c' => 6]);
        $lot = $this->castingLot();
        $manager = $this->tech('QUALITY_MANAGER');

        $this->actingAs($this->tech('QA_TEST_TECH'));
        $this->get(route('m.tests.thermal', $heat))->assertOk()->assertSee('Thermal analysis')->assertSee('Save test');
        $this->get(route('m.tests.spectro', $heat))->assertOk()->assertSee('Save analysis');
        $this->get(route('m.tests.hardness', $lot))->assertOk()->assertSee('Reading 1');
        $this->post(route('m.tests.thermal.store', $heat), ['stage' => 'POST_TREATMENT', 'cup_type' => 'STABLE', 'tl_c' => 1190, 'te_min_c' => 1132, 'te_max_c' => 1150])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('FAIL', \App\Models\ThermalAnalysisTest::firstOrFail()->result);
        $this->assertStringContainsString('FAIL', $manager->notifications()->firstOrFail()->data['title']);
        $this->post(route('m.tests.thermal.store', $heat), ['stage' => 'BASE', 'cup_type' => 'STABLE'])->assertSessionHasErrors('tl_c');
        $this->post(route('m.tests.hardness.store', $lot), ['readings' => [1 => '200', 2 => '210']])->assertSessionHasNoErrors();
        $this->assertSame('HARDNESS', \App\Models\QualityInspection::firstOrFail()->inspection_type);
        $this->post(route('m.tests.spectro.store', $heat), ['sample_stage' => 'AFTER_TREATMENT', 'carbon_percent' => '3.6', 'silicon_percent' => '2.4'])->assertSessionHasNoErrors();
        $this->assertSame(1, $heat->chemistryReadings()->count());
    }

    public function test_a_defect_tech_logs_a_defect_on_a_lot_with_its_own_screens(): void
    {
        config(['foundry.require_login' => true]);
        $lot = $this->castingLot();
        $defect = \App\Models\QualityDefect::create(['code' => 'SHR', 'name' => 'Shrinkage', 'category' => 'CASTING', 'is_active' => true]);
        $tech = $this->tech('QA_DEFECT_TECH');
        $good = $lot->quantity_good;

        $this->actingAs($tech)->get(route('m.defects.log', $lot))->assertOk()->assertSee('Shrinkage')->assertSee('Log defect');
        $this->post(route('m.defects.store', $lot), ['quality_defect_id' => $defect->id, 'quantity' => 2, 'disposition' => 'REJECT', 'location' => 'discharge flange'])->assertSessionHasNoErrors()->assertRedirect(route('m.defects.lot', $lot));
        $this->assertSame($good - 2, $lot->fresh()->quantity_good);
        $this->get(route('m.defects.lot', $lot))->assertOk()->assertSee('Shrinkage')->assertSee('discharge flange');
        $this->post(route('m.defects.store', $lot), ['quality_defect_id' => $defect->id, 'quantity' => 9999, 'disposition' => 'REJECT'])->assertSessionHasErrors('quantity');
        $this->actingAs($this->tech('QA_TEST_TECH'))->get(route('m.defects.log', $lot))->assertForbidden();
    }
}
