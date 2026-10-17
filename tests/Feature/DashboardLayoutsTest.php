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
        $this->get(route('dashboard'))->assertOk()->assertSee('Foundry overview');
        $this->get(route('dashboard', ['layout' => 'command']))->assertOk()->assertSee('Needs attention')->assertSee('Priority production queue')->assertSee('Yield');
        $this->get(route('dashboard', ['layout' => 'columns']))->assertOk()->assertSee('Live floor status')->assertSee('Open enquiries');
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

    public function test_telemetry_can_be_the_site_default(): void
    {
        $this->put(route('settings.display.update'), ['density' => 'COMPACT', 'dashboard' => 'telemetry'])->assertSessionHasNoErrors();
        UiSettings::flush();

        $this->get(route('dashboard'))->assertOk()->assertSee('Floor board');
    }

    public function test_the_choice_is_remembered_in_a_cookie_and_beats_the_site_default(): void
    {
        $r = $this->get(route('dashboard', ['layout' => 'columns']))->assertOk();
        $r->assertCookie('dash_layout');
        $this->withCookie('dash_layout', 'command')->get(route('dashboard'))->assertSee('Priority production queue');
    }

    public function test_site_default_comes_from_display_settings(): void
    {
        AppSetting::setValue('ui.dashboard', 'columns');
        UiSettings::flush();
        $this->get(route('dashboard'))->assertSee('g-columns', false);
        $this->put(route('settings.display.update'), ['density' => 'COMPACT', 'dashboard' => 'command'])->assertRedirect();
        $this->get(route('dashboard'))->assertSee('g-command', false);
    }

    public function test_an_unknown_layout_falls_back_safely(): void
    {
        $this->get(route('dashboard', ['layout' => 'nonsense']))->assertOk()->assertSee('Foundry overview');
    }

    public function test_it_uses_the_accent_variable_not_a_fixed_colour(): void
    {
        $html = $this->get(route('dashboard', ['layout' => 'command']))->getContent();
        $this->assertStringContainsString('var(--accent)', $html);
        $this->assertStringNotContainsString('--accent: #', substr($html, strpos($html, 'class="dx')));
    }

    public function test_attention_and_queue_show_real_records(): void
    {
        // The NCR normally hangs off a casting lot; this test only needs the row, so relax the link inside the test's transaction.
        DB::statement('ALTER TABLE nonconformance_reports ALTER COLUMN casting_lot_id DROP NOT NULL');
        DB::table('nonconformance_reports')->insert(['id' => (string) Str::uuid(), 'ncr_number' => 'NCR-T1', 'title' => 'Gas porosity on flange', 'description' => 'x', 'severity' => 'MAJOR', 'status' => 'OPEN', 'quantity_affected' => 14, 'reported_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->get(route('dashboard', ['layout' => 'command']))->assertSee('NCR-T1')->assertSee('Gas porosity on flange')->assertSee('1 major');
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
}
