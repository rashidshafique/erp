<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\MouldingRun;
use App\Models\Pour;
use App\Models\Product;
use App\Models\ProductionOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_week_month_and_year_show_past_and_future_items_and_reject_a_bad_view(): void
    {
        $furnace = Furnace::create(['code' => 'IF1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 1000, 'nominal_melting_rate_kg_per_hour' => 500]);
        $heat = Heat::create(['heat_number' => 'H-PAST', 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'CLOSED', 'actual_start_at' => today()->subDay()->setHour(8)]);
        Pour::create(['pour_number' => 'P-PAST-001', 'heat_id' => $heat->id, 'status' => 'COMPLETED', 'started_at' => today()->subDay()->setHour(10)]);
        $product = Product::create(['code' => 'P1', 'name' => 'Part']);
        $order = ProductionOrder::create(['production_order_number' => 'PO-1', 'product_id' => $product->id, 'product_revision_id' => $product->linkableRevision()->id, 'status' => 'RELEASED', 'planned_qty' => 10, 'required_date' => today()->addDays(2)]);
        MouldingRun::create(['production_order_id' => $order->id, 'run_number' => 'MR-FUTURE', 'run_date' => today()->addDays(2), 'planned_mould_qty' => 10, 'status' => 'PLANNED']);

        $this->get(route('calendar.index', ['view' => 'month', 'date' => today()->subDay()->toDateString()]))->assertOk()->assertSee('H-PAST')->assertSee('P-PAST-001');
        $this->get(route('calendar.index', ['view' => 'week', 'date' => today()->addDays(2)->toDateString()]))->assertOk()->assertSee('MR-FUTURE')->assertSee('PO-1');
        $this->get(route('calendar.index', ['view' => 'year']))->assertOk()->assertSee(today()->format('Y'));
        $this->get(route('calendar.index', ['view' => 'decade']))->assertSessionHasErrors('view');
    }

    public function test_the_gantt_view_draws_a_bar_per_open_order_and_marks_a_late_one(): void
    {
        $product = Product::create(['code' => 'P2', 'name' => 'Part']);
        $make = fn (string $number, $due) => ProductionOrder::create(['production_order_number' => $number, 'product_id' => $product->id, 'product_revision_id' => $product->linkableRevision()->id,
            'status' => 'IN_PROGRESS', 'planned_qty' => 10, 'required_date' => $due, 'actual_started_at' => today()->subDays(5)]);
        $make('PO-LATE', today()->subDays(2));
        $make('PO-OK', today()->addDays(10));
        ProductionOrder::where('production_order_number', 'PO-OK')->first()->update(['status' => 'CLOSED']);

        $this->get(route('calendar.index', ['view' => 'gantt']))->assertOk()->assertSee('PO-LATE')->assertSee('gt-bar late', false)->assertDontSee('PO-OK');
    }
}
