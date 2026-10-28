<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\MouldingStation;
use App\Support\UiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardLayoutsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        UiSettings::flush();
    }

    public function test_every_layout_renders_on_an_empty_database(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee('Pipeline')->assertSee('Feasibility')->assertSee('Needs attention')->assertSee("Today's melt", false)->assertSee('Open quotes');
        $this->get(route('dashboard', ['layout' => 'command']))->assertOk()->assertSee('Pipeline')->assertDontSee('Choose a dashboard layout');
    }

    public function test_telemetry_renders_on_an_empty_database(): void
    {
        $page = $this->get(route('dashboard', ['layout' => 'telemetry']));

        $page->assertOk()->assertSee('Floor board')->assertSee('Shift timeline')->assertSee('No heat or moulding run has started today.')
            ->assertSee('No sand test has been recorded yet.')->assertDontSee('location.reload', false);
    }

    public function test_telemetry_shows_every_machine_and_places_todays_work_on_the_timeline(): void
    {
        $this->travelTo('2026-10-17 10:00:00');
        $furnace = Furnace::create(['code' => 'IF-1', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        Furnace::create(['code' => 'IF-2', 'name' => 'Spare', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        Heat::create(['heat_number' => 'H-77', 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'IN_PROGRESS', 'actual_start_at' => '2026-10-17 08:00:00']);
        MouldingStation::create(['code' => 'AUTO-1', 'name' => 'Automatic line', 'station_type' => 'AUTOMATED_LINE', 'status' => 'MAINTENANCE', 'max_patterns' => 1]);

        $page = $this->get(route('dashboard', ['layout' => 'telemetry']));

        $page->assertOk()
            ->assertSeeInOrder(['IF-1', 'Melting', 'Heat H-77', 'IF-2', 'Idle', 'AUTO-1', 'Maintenance'])
            ->assertSee('left:16.67%;width:16.67%', false)
            ->assertSee('class="tl-now" style="left:33.33%"', false);
    }

    public function test_wall_screen_hides_the_menu_and_reloads(): void
    {
        $page = $this->get(route('dashboard', ['layout' => 'telemetry', 'tv' => 1]));

        $page->assertOk()->assertSee('.sidebar,.topbar', false)->assertSee('location.reload', false)->assertSee('Exit wall screen');
    }

    public function test_an_unknown_layout_falls_back_safely(): void
    {
        $this->get(route('dashboard', ['layout' => 'nonsense']))->assertOk()->assertSee('Pipeline');
    }

    public function test_it_uses_the_accent_variable_not_a_fixed_colour(): void
    {
        $html = $this->get(route('dashboard'))->getContent();
        $this->assertStringContainsString('var(--accent)', $html);
        $this->assertStringNotContainsString('#2f5fd0', substr($html, strpos($html, 'class="dash"')));
    }

    public function test_attention_and_queue_show_real_records(): void
    {
        // The NCR normally hangs off a casting lot; this test only needs the row, so relax the link inside the test's transaction.
        DB::statement('ALTER TABLE nonconformance_reports ALTER COLUMN casting_lot_id DROP NOT NULL');
        DB::table('nonconformance_reports')->insert(['id' => (string) Str::uuid(), 'ncr_number' => 'NCR-T1', 'title' => 'Gas porosity on flange', 'description' => 'x', 'severity' => 'MAJOR', 'status' => 'OPEN', 'quantity_affected' => 14, 'reported_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->get(route('dashboard'))->assertSee('NCR-T1')->assertSee('Gas porosity on flange');
    }

    public function test_the_chart_draws_a_line_and_bars_when_there_are_pours(): void
    {
        $rows = [];
        for ($i = 0; $i < 14; $i++) {
            $rows[] = ['date' => now()->subDays(13 - $i), 'poured' => 100, 'rejected' => $i % 4, 'good_kg' => 900.0, 'poured_kg' => 1000.0 + $i * 10, 'yield' => 72.0 + $i * 0.3, 'scrap' => ($i % 4) * 1.0];
        }
        $html = view('dashboard._chart', ['d' => ['daily' => $rows, 'yieldTotal' => 74.1]])->render();
        $this->assertStringContainsString('<polyline', $html);
        $this->assertStringContainsString('<rect', $html);
        $this->assertStringContainsString('74.1%', $html);
        $this->assertStringNotContainsString('nothing to plot', $html);
    }

    public function test_the_melt_panel_shows_the_next_planned_heat_and_charged_metal_for_a_cupola(): void
    {
        $this->travelTo('2026-10-17 10:00:00');
        $induction = Furnace::create(['code' => 'IF-1', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $cupola = Furnace::create(['code' => 'CU-1', 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE']);
        Heat::create(['heat_number' => 'H-1', 'furnace_id' => $induction->id, 'process_type' => 'INDUCTION', 'status' => 'IN_PROGRESS', 'actual_start_at' => '2026-10-17 08:00:00', 'actual_tapped_weight_kg' => 1500]);
        Heat::create(['heat_number' => 'H-2', 'furnace_id' => $cupola->id, 'process_type' => 'CUPOLA', 'status' => 'IN_PROGRESS', 'actual_start_at' => '2026-10-17 07:00:00']);
        Heat::create(['heat_number' => 'H-3', 'furnace_id' => $induction->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED', 'planned_start_at' => '2026-10-19 08:00:00']);

        $this->get(route('dashboard'))->assertOk()->assertSee('1.5 t poured')->assertSee('0.0 t charged')->assertSee('Next heat')->assertSee('H-3')->assertSee('19 Oct');
    }
}
