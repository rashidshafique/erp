<?php

namespace Tests\Feature;

use App\Domains\QuickFlow\Models\QuickFloorEntry;
use App\Domains\QuickFlow\Services\NextHeatPlan;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\Pattern;
use App\Models\Pour;
use App\Models\Product;
use App\Models\ProductionOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickFlowTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): array
    {
        $furnace = Furnace::create(['code' => 'IF1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 1000, 'nominal_melting_rate_kg_per_hour' => 500]);

        return [
            'furnace' => $furnace, 'customer' => Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']),
            'product' => Product::create(['code' => 'PUMP-1', 'name' => 'Pump body']), 'grade' => IronGrade::where('code', 'EN-GJS-500-7')->firstOrFail(),
        ];
    }

    /** A pattern whose one cavity makes the shop's product, two per mould level, on a default station. */
    private function pattern(array $shop, ?string $stationId = null): Pattern
    {
        $pattern = Pattern::create(['code' => 'PAT-1', 'name' => 'Pump plate', 'status' => 'ACTIVE', 'preferred_moulding_station_id' => $stationId]);
        $revision = $pattern->addRevision(['cavities_per_mould' => 2]);
        $productRevision = $shop['product']->linkableRevision();
        foreach ([1, 2] as $n) {
            $revision->cavityMap()->create(['cavity_number' => $n, 'product_revision_id' => $productRevision->id, 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1]);
        }

        return $pattern;
    }

    /** One grid row. */
    private function row(Pattern $pattern, int $moulds, array $extra = []): array
    {
        return $extra + ['pattern_id' => $pattern->id, 'moulds' => $moulds];
    }

    private function order(array $shop, int $qty = 100, array $line = [], array $extra = [])
    {
        return $this->post(route('quick.orders.store'), $extra + ['customer_id' => $shop['customer']->id, 'due_date' => today()->addDays(10)->toDateString(), 'lines' => [
            $line + ['product_id' => $shop['product']->id, 'qty' => $qty, 'iron_grade_id' => $shop['grade']->id, 'casting_weight_kg' => 6],
        ]]);
    }

    public function test_a_quick_order_is_released_for_planning_without_review_and_remembers_grade_and_weight(): void
    {
        $shop = $this->shop();

        $this->order($shop)->assertSessionHasNoErrors()->assertRedirect(route('quick.heat.index'));

        $order = ProductionOrder::firstOrFail();
        $this->assertSame(['RELEASED', '100.000'], [$order->status, (string) $order->released_qty]);
        $this->order($shop, 50, ['iron_grade_id' => '', 'casting_weight_kg' => ''])->assertSessionHasNoErrors();
        $this->assertCount(2, ProductionOrder::all());
    }

    public function test_a_quick_order_leaves_production_in_draft_while_its_release_waits_for_approval(): void
    {
        $shop = $this->shop();
        ApprovalPolicy::create(['action_key' => 'production_order.release', 'is_active' => true, 'required_approvals' => 1, 'permission' => 'approvals.decide', 'allow_self_approval' => false]);

        $this->order($shop)->assertSessionHasNoErrors()->assertSessionHas('warning', fn ($w) => str_contains($w, 'waits for release approval'));

        $this->assertSame('DRAFT', ProductionOrder::firstOrFail()->status);
        $this->assertSame('PENDING', ApprovalRequest::firstOrFail()->status);
    }

    public function test_a_line_is_refused_when_the_product_has_no_grade_or_weight_to_plan_with(): void
    {
        $shop = $this->shop();

        $this->order($shop, 10, ['iron_grade_id' => ''])->assertSessionHasErrors('lines.0.iron_grade_id');
        $this->order($shop, 10, ['casting_weight_kg' => ''])->assertSessionHasErrors('lines.0.casting_weight_kg');
        $this->assertSame(0, ProductionOrder::count());
    }

    public function test_a_new_customer_can_be_typed_on_the_order(): void
    {
        $shop = $this->shop();

        $this->order($shop, 10, [], ['customer_id' => '', 'new_customer' => 'Brand New Ltd'])->assertSessionHasNoErrors();

        $this->assertTrue(Customer::matching('Brand New')->exists());
    }

    public function test_the_next_heat_is_capped_at_the_furnace_working_capacity_and_runs_over_into_further_heats(): void
    {
        $shop = $this->shop();
        $this->order($shop, 300);

        $heats = app(NextHeatPlan::class)->build()['heats'];

        $this->assertCount(4, $heats);
        $this->assertSame(90, $heats[0]['lines'][0]['pieces']);
        $this->assertLessThanOrEqual(900.0, $heats[0]['kg']);
        $this->assertSame(300, collect($heats)->sum(fn ($h) => $h['lines'][0]['pieces']));
        $this->assertSame(1.8, $heats[0]['hours']);
    }

    public function test_metal_for_moulds_already_made_comes_before_moulds_still_to_make(): void
    {
        $shop = $this->shop();
        $this->order($shop, 50, [], ['due_date' => today()->addDays(30)->toDateString()]);
        $this->order($shop, 50, [], ['due_date' => today()->addDays(2)->toDateString()]);
        $late = ProductionOrder::orderBy('production_order_number')->first();

        $this->post(route('quick.floor.made'), ['rows' => [$this->row($this->pattern($shop), 10, ['production_order_id' => $late->id])]])->assertSessionHasNoErrors();

        $lines = app(NextHeatPlan::class)->build()['heats'][0]['lines'];
        $this->assertTrue($lines[0]['ready']);
        $this->assertSame([$late->id, 20], [$lines[0]['order']->id, $lines[0]['pieces']]);
    }

    public function test_moulds_made_write_a_moulding_run_and_start_the_order(): void
    {
        $shop = $this->shop();
        $this->order($shop);
        $order = ProductionOrder::firstOrFail();

        $this->post(route('quick.floor.made'), ['rows' => [$this->row($this->pattern($shop), 12, ['production_order_id' => $order->id, 'cavities' => 9])]])->assertSessionHasNoErrors();

        $run = MouldingRun::firstOrFail();
        $this->assertSame([12, 'COMPLETED', 'IN_PROGRESS'], [$run->moulds_made_qty, $run->status, $order->refresh()->status]);
        $this->assertSame(24, QuickFloorEntry::firstOrFail()->pieces(), 'The cavity map gives 2 per mould; a typed count is ignored.');
    }

    public function test_pouring_records_a_pour_and_warns_about_thermal_tests_without_blocking(): void
    {
        $shop = $this->shop();
        $this->order($shop);
        $order = ProductionOrder::firstOrFail();
        $heat = Heat::create(['heat_number' => 'H-1', 'furnace_id' => $shop['furnace']->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED', 'iron_grade_id' => $shop['grade']->id]);
        $pattern = $this->pattern($shop);
        $this->post(route('quick.floor.made'), ['rows' => [$this->row($pattern, 20, ['production_order_id' => $order->id])]]);

        $this->post(route('quick.floor.poured'), ['heat_id' => $heat->id, 'rows' => [$this->row($pattern, 15, ['production_order_id' => $order->id])]])
            ->assertSessionHasNoErrors()->assertSessionHas('warning', 'No thermal test has been recorded on H-1.');

        $this->assertSame(['COMPLETED', 'ADVISORY'], [Pour::firstOrFail()->status, Pour::firstOrFail()->gate_mode]);
        $row = app(NextHeatPlan::class)->orders()->first();
        $this->assertSame([40, 30, 10, 60], [$row['made'], $row['poured'], $row['awaiting'], $row['to_mould']]);
    }

    public function test_yesterdays_moulds_and_pour_are_recorded_on_yesterday_even_from_a_heat_closed_since(): void
    {
        $shop = $this->shop();
        $this->order($shop);
        $order = ProductionOrder::firstOrFail();
        $yesterday = today()->subDay();
        $heat = Heat::create(['heat_number' => 'H-1', 'furnace_id' => $shop['furnace']->id, 'process_type' => 'INDUCTION', 'status' => 'CLOSED', 'closed_at' => now(), 'iron_grade_id' => $shop['grade']->id]);
        $pattern = $this->pattern($shop);
        $dated = ['production_order_id' => $order->id, 'entry_date' => $yesterday->toDateString()];

        $this->post(route('quick.floor.made'), ['rows' => [$this->row($pattern, 20, $dated)]])->assertSessionHasNoErrors();
        $this->post(route('quick.floor.poured'), ['heat_id' => $heat->id, 'rows' => [$this->row($pattern, 12, $dated)]])->assertSessionHasNoErrors();

        $pour = Pour::firstOrFail();
        $this->assertSame([$yesterday->toDateString(), 'P-'.$yesterday->format('Ymd').'-001'], [$pour->completed_at->toDateString(), $pour->pour_number]);
        $this->assertSame($yesterday->toDateString(), MouldingRun::firstOrFail()->completed_at->toDateString());
        $this->assertSame([20, 12], [(int) QuickFloorEntry::where('kind', 'MADE')->sum('moulds'), (int) QuickFloorEntry::where('kind', 'POURED')->sum('moulds')], 'Moulds made but not yet poured stay as awaiting.');
    }

    public function test_the_next_heat_starts_a_real_heat_on_an_eligible_furnace(): void
    {
        $shop = $this->shop();

        $this->post(route('quick.heat.start'), ['iron_grade_id' => $shop['grade']->id, 'furnace_id' => $shop['furnace']->id, 'kg' => 850, 'operator_name' => 'Imran'])->assertSessionHasNoErrors();

        $heat = Heat::firstOrFail();
        $this->assertSame(['PLANNED', '850.000', 'INDUCTION'], [$heat->status, (string) $heat->target_metal_weight_kg, $heat->process_type]);
    }

    public function test_the_pages_render(): void
    {
        $shop = $this->shop();
        $this->order($shop);

        $this->get(route('quick.orders.create'))->assertOk();
        $this->get(route('quick.floor.index'))->assertOk();
        $this->get(route('quick.heat.index'))->assertOk()->assertSee('Next heat')->assertSee('Start this heat');
    }

    public function test_the_grade_set_on_a_product_page_clears_the_review_spec_and_grade_blocks(): void
    {
        $shop = $this->shop();
        $revision = \App\Models\ProductRevision::create(['product_id' => $shop['product']->id, 'revision' => 'A', 'status' => 'APPROVED', 'net_weight_kg' => 5]);

        $this->post(route('products.grade', $shop['product']), ['product_revision_id' => $revision->id, 'iron_grade_id' => $shop['grade']->id])->assertSessionHasNoErrors();

        $this->assertSame($shop['grade']->id, app(\App\Services\Planning\HeatPlanner::class)->gradeFor($revision)?->id);
        $this->get(route('products.show', $shop['product']))->assertOk()->assertSee('Set grade')->assertSee('EN-GJS-500-7')->assertSee('Ductile iron');
        $this->get(route('iron-grades.index'))->assertOk()->assertSeeInOrder(['Grey iron', 'Ductile iron']);
    }

    public function test_a_grid_row_takes_the_order_station_and_cavities_from_the_pattern(): void
    {
        $shop = $this->shop();
        $this->order($shop);
        $order = ProductionOrder::firstOrFail();
        $station = MouldingStation::create(['code' => 'M2', 'name' => 'Manual 2', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 2]);
        $pattern = $this->pattern($shop, $station->id);

        $this->get(route('quick.floor.index'))->assertOk()->assertSee('data-station="'.$station->id.'"', false)->assertSee('data-cavities="2"', false)
            ->assertSee('Automatic: '.$order->production_order_number, false);

        $this->post(route('quick.floor.made'), ['rows' => [$this->row($pattern, 10), ['pattern_id' => '', 'moulds' => '']]])
            ->assertSessionHasNoErrors();

        $entry = QuickFloorEntry::sole();
        $this->assertSame([$order->id, $pattern->id, $station->id, 20, today()->toDateString()], [$entry->production_order_id, $entry->pattern_id, $entry->moulding_station_id, $entry->pieces(), $entry->entry_date->toDateString()]);
        $run = MouldingRun::sole();
        $this->assertSame([$pattern->currentRevision->id, $station->id], [$run->pattern_revision_id, $run->moulding_station_id]);
    }

    public function test_grid_rows_without_a_pattern_or_count_are_refused_and_nothing_is_saved(): void
    {
        $loose = Pattern::create(['code' => 'PAT-9', 'name' => 'Unmapped plate', 'status' => 'ACTIVE']);

        $this->post(route('quick.floor.made'), ['rows' => [['pattern_id' => '', 'moulds' => 4], ['pattern_id' => $loose->id, 'moulds' => '']]])
            ->assertSessionHasErrors(['rows.0.pattern_id', 'rows.1.moulds']);
        $this->post(route('quick.floor.made'), ['rows' => [['pattern_id' => '', 'moulds' => '']]])->assertSessionHasErrors('rows');

        $this->assertSame(0, QuickFloorEntry::count());
    }

    public function test_moulds_made_and_poured_without_an_open_order_are_stored_and_wait_to_be_linked(): void
    {
        $shop = $this->shop();
        $pattern = $this->pattern($shop);
        $heat = Heat::create(['heat_number' => 'H-1', 'furnace_id' => $shop['furnace']->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED', 'iron_grade_id' => $shop['grade']->id]);

        $this->post(route('quick.floor.made'), ['rows' => [$this->row($pattern, 10)]])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'waiting for an order'));
        $this->post(route('quick.floor.poured'), ['heat_id' => $heat->id, 'rows' => [$this->row($pattern, 6)]])->assertSessionHasNoErrors();

        $this->assertSame(2, QuickFloorEntry::unlinked()->count());
        $this->assertSame([0, 1], [MouldingRun::count(), Pour::count()]);
        $this->assertSame(2, QuickFloorEntry::where('kind', 'MADE')->sole()->pieces_per_mould);
        $this->get(route('quick.floor.index'))->assertOk()->assertSee('Waiting for an order')->assertSee('Not linked yet');
    }

    public function test_a_waiting_entry_is_linked_by_hand_and_writes_its_moulding_run(): void
    {
        $shop = $this->shop();
        $loose = Pattern::create(['code' => 'PAT-9', 'name' => 'Unmapped plate', 'status' => 'ACTIVE']);
        $this->post(route('quick.floor.made'), ['rows' => [$this->row($loose, 8, ['cavities' => 1])]])->assertSessionHasNoErrors();
        $this->order($shop);
        $order = ProductionOrder::firstOrFail();
        $entry = QuickFloorEntry::sole();
        $this->assertNull($entry->production_order_id);

        $this->post(route('quick.floor.link', $entry), ['production_order_id' => $order->id])->assertSessionHasNoErrors();

        $entry->refresh();
        $this->assertSame($order->id, $entry->production_order_id);
        $this->assertSame([8, 'IN_PROGRESS'], [MouldingRun::sole()->moulds_made_qty, $order->refresh()->status]);
        $this->post(route('quick.floor.link', $entry), ['production_order_id' => $order->id])->assertSessionHasErrors('production_order_id');
        $this->assertSame(1, MouldingRun::count());
    }

    public function test_waiting_entries_link_themselves_when_a_matching_order_opens_up_to_its_quantity(): void
    {
        $shop = $this->shop();
        $pattern = $this->pattern($shop);
        foreach ([30, 30] as $moulds) {
            $this->post(route('quick.floor.made'), ['rows' => [$this->row($pattern, $moulds)]])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, QuickFloorEntry::unlinked()->count());

        $this->order($shop, 50);

        $order = ProductionOrder::firstOrFail();
        $this->assertSame(1, QuickFloorEntry::unlinked()->count(), 'The first 60 pieces cover the order of 50, so the second entry keeps waiting.');
        $this->assertSame(60, (int) QuickFloorEntry::where('production_order_id', $order->id)->get()->sum(fn ($e) => $e->pieces()));
        $this->assertSame([1, 'IN_PROGRESS'], [MouldingRun::count(), $order->refresh()->status]);
    }

    public function test_a_pattern_without_a_cavity_map_needs_its_cavity_count_and_warns_it_makes_no_known_product(): void
    {
        $loose = Pattern::create(['code' => 'PAT-9', 'name' => 'Unmapped plate', 'status' => 'ACTIVE']);

        $this->post(route('quick.floor.made'), ['rows' => [$this->row($loose, 5)]])->assertSessionHasErrors('rows.0.cavities');
        $this->assertSame(0, QuickFloorEntry::count());

        $this->post(route('quick.floor.made'), ['rows' => [$this->row($loose, 5, ['cavities' => 3])]])
            ->assertSessionHasNoErrors()->assertSessionHas('warning', fn ($m) => str_contains($m, 'Unmapped plate is not linked to any product'));
        $this->assertSame(15, QuickFloorEntry::sole()->pieces());
        $this->get(route('quick.floor.index'))->assertOk()->assertSee('data-cavities=""', false)->assertSee('Not linked to any product');
    }
}
