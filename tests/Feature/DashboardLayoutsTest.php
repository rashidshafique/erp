<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Support\UiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE nonconformance_reports ALTER COLUMN casting_lot_id DROP NOT NULL');
        \Illuminate\Support\Facades\DB::table('nonconformance_reports')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'ncr_number' => 'NCR-T1', 'title' => 'Gas porosity on flange', 'description' => 'x', 'severity' => 'MAJOR', 'status' => 'OPEN', 'quantity_affected' => 14, 'reported_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
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
