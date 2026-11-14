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
        $this->get(route('dashboard'))->assertOk()->assertSee('Good castings')->assertSee('Cash to collect')->assertSee('Waiting for your decision')->assertSee("Where it's slipping", false);
        $this->get(route('dashboard', ['layout' => 'classic']))->assertOk()->assertSee('Pipeline')->assertSee('Feasibility')->assertSee('Needs attention')->assertSee("Today's melt", false)->assertSee('Open quotes');
        $this->get(route('dashboard', ['layout' => 'command']))->assertOk()->assertSee('Good castings')->assertDontSee('Choose a dashboard layout');
    }

    public function test_the_dashboard_is_also_reachable_at_dashboard(): void
    {
        $this->get('/dashboard?layout=icast')->assertOk()->assertSee('Classic owner view');
        $this->get('/dashboard')->assertOk()->assertSee('Good castings');
    }

    public function test_the_icast_owner_view_renders_on_an_empty_database(): void
    {
        $this->get(route('dashboard', ['layout' => 'icast']))->assertOk()
            ->assertSee('Castings sold')->assertSee('Turnover')->assertSee('Casting returns')->assertSee('Orders pending')
            ->assertSee('Cash to collect')->assertSee('Waiting for your decision')
            ->assertSee("Where it's slipping", false)->assertSee('Classic owner view')
            ->assertSee('fonts.googleapis.com/css2?family=Inter', false)->assertSee('#F99B31');
    }

    public function test_the_four_owner_tiles_show_sold_weight_turnover_returns_and_pending_orders(): void
    {
        $this->travelTo('2026-10-17 10:00:00');
        $customer = \App\Models\Customer::create(['code' => 'MER', 'name' => 'Meridian Auto']);
        $product = \App\Models\Product::create(['code' => 'P-1', 'name' => 'Flange', 'nominal_weight_kg' => 25]);
        $revision = \App\Models\ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED', 'net_weight_kg' => 25]);

        $invoice = ['customer_id' => $customer->id, 'status' => 'ISSUED', 'currency' => 'USD', 'tax_total' => 0];
        $sold = \App\Domains\Finance\Models\Invoice::create($invoice + ['kind' => 'INVOICE', 'invoice_number' => 'INV-1', 'invoice_date' => '2026-10-05', 'subtotal' => 200000, 'total' => 200000]);
        $lastSold = \App\Domains\Finance\Models\Invoice::create($invoice + ['kind' => 'INVOICE', 'invoice_number' => 'INV-0', 'invoice_date' => '2026-09-10', 'subtotal' => 100000, 'total' => 100000]);
        $credit = \App\Domains\Finance\Models\Invoice::create($invoice + ['kind' => 'CREDIT_NOTE', 'invoice_number' => 'CN-1', 'invoice_date' => '2026-10-06', 'subtotal' => 50000, 'total' => 50000]);
        $lastCredit = \App\Domains\Finance\Models\Invoice::create($invoice + ['kind' => 'CREDIT_NOTE', 'invoice_number' => 'CN-0', 'invoice_date' => '2026-09-12', 'subtotal' => 20000, 'total' => 20000]);
        foreach ([[$sold, 2000], [$lastSold, 1000], [$credit, 500], [$lastCredit, 200]] as [$document, $kg]) {
            \App\Domains\Finance\Models\InvoiceLine::create(['invoice_id' => $document->id, 'line_number' => 1, 'description' => 'Flange', 'quantity' => $kg, 'uom' => 'kg', 'unit_price' => 100, 'line_net' => $kg * 100]);
        }

        $order = \App\Domains\Sales\Models\SalesOrder::create(['order_number' => 'S-1', 'customer_id' => $customer->id, 'order_date' => '2026-10-01', 'status' => 'CONFIRMED']);
        \App\Domains\Sales\Models\SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $product->id, 'product_revision_id' => $revision->id, 'line_number' => 1, 'ordered_qty' => 100, 'unit_price' => 1000, 'uom' => 'piece']);

        $this->get(route('dashboard', ['layout' => 'icast']))->assertOk()
            ->assertSeeInOrder(['Castings sold', '2.0', 'Last month to date 1.0 t', '▲ 1.0 t · 100.0%'])
            ->assertSeeInOrder(['Turnover', '1.5', 'Last month 0.8', '▲ 87.5% vs last month'])
            ->assertSeeInOrder(['Casting returns', '0.5', 'Projected 0.9 t · last month 0.2 t', '▲ 355.9% vs last month'])
            ->assertSeeInOrder(['Orders pending', '2.5', '1.0 L', '1 open order', 'Not yet dispatched']);
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
        $this->get(route('dashboard', ['layout' => 'nonsense']))->assertOk()->assertSee('Good castings');
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

    public function test_owner_home_shows_the_months_sales_and_what_customers_owe(): void
    {
        $this->travelTo('2026-10-17 10:00:00');
        $customer = \App\Models\Customer::create(['code' => 'MER', 'name' => 'Meridian Auto']);
        $base = ['customer_id' => $customer->id, 'kind' => 'INVOICE', 'status' => 'ISSUED', 'currency' => 'USD', 'tax_total' => 0];
        \App\Domains\Finance\Models\Invoice::create($base + ['invoice_number' => 'INV-1', 'invoice_date' => '2026-10-05', 'due_date' => '2026-12-05', 'subtotal' => 4000, 'total' => 4000]);
        \App\Domains\Finance\Models\Invoice::create($base + ['invoice_number' => 'INV-0', 'invoice_date' => '2026-07-01', 'due_date' => '2026-07-31', 'subtotal' => 1000, 'total' => 1000]);

        $this->get(route('dashboard'))->assertOk()->assertSee('Meridian Auto')->assertSee('4,000')->assertSee('5,000')->assertSee('1,000 over 60 days');
    }

    public function test_the_melt_panel_shows_the_next_planned_heat_and_charged_metal_for_a_cupola(): void
    {
        $this->travelTo('2026-10-17 10:00:00');
        $induction = Furnace::create(['code' => 'IF-1', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $cupola = Furnace::create(['code' => 'CU-1', 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE']);
        Heat::create(['heat_number' => 'H-1', 'furnace_id' => $induction->id, 'process_type' => 'INDUCTION', 'status' => 'IN_PROGRESS', 'actual_start_at' => '2026-10-17 08:00:00', 'actual_tapped_weight_kg' => 1500]);
        Heat::create(['heat_number' => 'H-2', 'furnace_id' => $cupola->id, 'process_type' => 'CUPOLA', 'status' => 'IN_PROGRESS', 'actual_start_at' => '2026-10-17 07:00:00']);
        Heat::create(['heat_number' => 'H-3', 'furnace_id' => $induction->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED', 'planned_start_at' => '2026-10-19 08:00:00']);

        $this->get(route('dashboard', ['layout' => 'classic']))->assertOk()->assertSee('1.5 t poured')->assertSee('0.0 t charged')->assertSee('Next heat')->assertSee('H-3')->assertSee('19 Oct');
    }
}
