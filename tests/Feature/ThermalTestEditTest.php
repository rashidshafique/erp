<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\ThermalAnalysisTest;
use App\Models\ThermalWindow;
use App\Services\Thermal\ThermalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CurveSimulator;
use Tests\TestCase;

class ThermalTestEditTest extends TestCase
{
    use RefreshDatabase;

    private function heat(string $status = 'MELTING'): Heat
    {
        $furnace = Furnace::create(['code' => 'IF-'.uniqid(), 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $heat = Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => $status,
            'iron_grade_id' => IronGrade::where('code', 'EN-GJS-500-7')->value('id')]);
        ThermalWindow::create(['iron_grade_id' => $heat->iron_grade_id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1140, 'recal_max_c' => 6]);

        return $heat;
    }

    /** @return array{0: ThermalAnalysisTest, 1: ThermalAnalysisTest} the white and grey cup of one sample */
    private function sample(Heat $heat): array
    {
        $this->post(route('thermal.store'), ['heat_id' => $heat->id, 'stage' => 'POST_TREATMENT', 'sample_reference' => 'S-1',
            'white' => ['silicon' => 2.4, 'tl_c' => 1182, 'solidus_c' => 1121],
            'grey' => ['tl_c' => 1184, 'solidus_c' => 1122, 'te_min_c' => 1146, 'te_max_c' => 1149],
        ])->assertSessionHasNoErrors();

        return [ThermalAnalysisTest::where('cup_type', 'TELLURIUM')->sole(), ThermalAnalysisTest::where('cup_type', 'STABLE')->sole()];
    }

    /** The sample's form as the dialog sends it back: every figure the test worked out, as shown. */
    private function shown(ThermalAnalysisTest $white, ThermalAnalysisTest $grey, array $changes = []): array
    {
        $cup = fn (ThermalAnalysisTest $t) => ['ce' => (string) $t->ce_from_tl, 'carbon' => (string) $t->carbon_from_tl, 'silicon' => (string) $t->silicon_used,
            'tl_c' => (string) $t->tl_c, 'solidus_c' => (string) $t->solidus_c, 'te_min_c' => (string) $t->te_min_c, 'te_max_c' => (string) $t->te_max_c];

        return array_replace_recursive(['heat_id' => $white->heat_id, 'stage' => 'POST_TREATMENT', 'sample_reference' => 'S-1', 'white' => $cup($white), 'grey' => $cup($grey)], $changes);
    }

    public function test_an_edit_works_the_cups_out_again_in_place_and_shown_ce_stays_worked_out(): void
    {
        $heat = $this->heat();
        [$white, $grey] = $this->sample($heat);
        $this->assertSame('PASS', $grey->result);
        $white->update(['entered_by_name' => 'First Tech']);

        $this->put(route('thermal.update', $grey), $this->shown($white, $grey, ['sample_reference' => 'S-1B', 'grey' => ['te_min_c' => '1132', 'te_max_c' => '1150']]))
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Test updated: white cup '.str_replace('_', ' ', $white->result).', grey cup FAIL.');

        $this->assertSame(2, ThermalAnalysisTest::count(), 'the same two rows are updated, not replaced');
        $grey->refresh();
        $white->refresh();
        $this->assertSame(['FAIL', 1132.0, 'S-1B'], [$grey->result, (float) $grey->te_min_c, $grey->sample_reference]);
        $this->assertSame(['CALCULATED', null, 'First Tech'], [$white->ce_source, $white->carbon_reported_pct, $white->entered_by_name]);

        // A CE typed over the shown one is a meter reading.
        $this->put(route('thermal.update', $white), $this->shown($white, $grey, ['white' => ['ce' => '4.30']]))->assertSessionHasNoErrors();
        $this->assertSame(['METER', 4.3], [$white->fresh()->ce_source, (float) $white->fresh()->ce_from_tl]);
    }

    public function test_a_cup_keeps_its_curve_when_no_new_one_is_given_and_its_own_readings_are_not_keyed(): void
    {
        $heat = $this->heat();
        $curve = CurveSimulator::make(1195, 1150, 4.0)['curve'];
        $test = app(ThermalService::class)->recordCup($heat, 'STABLE', ['stage' => 'POST_TREATMENT'], implode("\n", array_map(fn ($r) => $r[0].','.$r[1], $curve)));
        $before = $test->only(['tl_c', 'te_min_c', 'te_max_c', 'result', 'findings']);

        $this->put(route('thermal.update', $test), ['stage' => 'BASE', 'grey' => ['tl_c' => (string) $test->tl_c, 'te_min_c' => (string) $test->te_min_c, 'te_max_c' => (string) $test->te_max_c]])
            ->assertSessionHasNoErrors();

        $test->refresh();
        $this->assertSame(['CURVE', count($curve), 'BASE'], [$test->entry_mode, count($test->curve), $test->stage]);
        $this->assertEquals($before, $test->only(['tl_c', 'te_min_c', 'te_max_c', 'result', 'findings']));
    }

    public function test_tests_of_a_closed_heat_or_voided_tests_cannot_be_edited_and_the_dialog_offers_edit_only_when_allowed(): void
    {
        $heat = $this->heat();
        [$white, $grey] = $this->sample($heat);
        $group = $white->sample_group_id;

        $this->get(route('heats.show', $heat))->assertOk()
            ->assertSeeInOrder(['<dialog id="sample-view-'.$group.'"', 'action="'.url('thermal').'/', 'name="_method" value="PUT"', 'data-sv-edit', '</dialog>'], false);

        $heat->update(['status' => 'CLOSED']);
        $this->put(route('thermal.update', $grey), $this->shown($white, $grey, ['grey' => ['te_min_c' => '1132']]))->assertSessionHasErrors('curve_text');
        $this->assertSame('PASS', $grey->fresh()->result);
        $this->assertStringNotContainsString('data-sv-edit>', $this->get(route('heats.show', $heat))->getContent());

        $heat->update(['status' => 'MELTING']);
        $grey->update(['voided_at' => now(), 'void_reason' => 'Wrong cup']);
        $this->put(route('thermal.update', $white), $this->shown($white, $grey))->assertSessionHasErrors('curve_text');   // nor the other cup of a sample with a voided one
        $this->assertSame('PASS', $grey->fresh()->result);
    }
}
