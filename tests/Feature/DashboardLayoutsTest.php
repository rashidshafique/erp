<?php

namespace Tests\Feature;

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
        $this->get(route('dashboard'))->assertOk()->assertSee('Good castings')->assertSee('Cash to collect')->assertSee('Waiting for your decision')->assertSee("Where it's slipping", false);
        $this->get(route('dashboard', ['layout' => 'telemetry']))->assertOk()->assertSee('Floor board');
    }

    public function test_the_dashboard_is_also_reachable_at_dashboard(): void
    {
        $this->get('/dashboard')->assertOk()->assertSee('Good castings');
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

    public function test_a_removed_or_unknown_layout_shows_the_owner_home(): void
    {
        foreach (['nonsense', 'classic', 'icast', 'command', 'columns'] as $layout) {
            $this->get(route('dashboard', ['layout' => $layout]))->assertOk()->assertSee('Good castings')->assertDontSee('iCast view');
        }
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

    public function test_owner_home_shows_the_months_sales_and_what_customers_owe(): void
    {
        $this->travelTo('2026-10-17 10:00:00');
        $customer = \App\Models\Customer::create(['code' => 'MER', 'name' => 'Meridian Auto']);
        $base = ['customer_id' => $customer->id, 'kind' => 'INVOICE', 'status' => 'ISSUED', 'currency' => 'USD', 'tax_total' => 0];
        \App\Domains\Finance\Models\Invoice::create($base + ['invoice_number' => 'INV-1', 'invoice_date' => '2026-10-05', 'due_date' => '2026-12-05', 'subtotal' => 4000, 'total' => 4000]);
        \App\Domains\Finance\Models\Invoice::create($base + ['invoice_number' => 'INV-0', 'invoice_date' => '2026-07-01', 'due_date' => '2026-07-31', 'subtotal' => 1000, 'total' => 1000]);

        $this->get(route('dashboard'))->assertOk()->assertSee('Meridian Auto')->assertSee('4,000')->assertSee('5,000')->assertSee('1,000 over 60 days');
    }
}
