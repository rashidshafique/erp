<?php

namespace Tests\Feature;

use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\MouldingTeam;
use App\Models\MouldingWorkList;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Production\WorkListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkListStationsTest extends TestCase
{
    use RefreshDatabase;

    /** A released order (for its own customer) with a pattern that makes its product. */
    private function job(string $code, string $customerName, string $patternType = 'LOOSE_PATTERN'): array
    {
        $customer = Customer::create(['code' => 'C-'.$code, 'name' => $customerName, 'active' => true]);
        $product = Product::create(['code' => $code, 'name' => 'Part '.$code]);
        $rev = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $so = SalesOrder::create(['order_number' => 'SO-'.$code, 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'DRAFT']);
        $line = SalesOrderLine::create(['sales_order_id' => $so->id, 'product_id' => $product->id, 'product_revision_id' => $rev->id, 'line_number' => 1, 'ordered_qty' => 50, 'uom' => 'piece']);
        $order = ProductionOrder::create(['production_order_number' => 'PO-'.$code, 'sales_order_line_id' => $line->id, 'product_id' => $product->id, 'product_revision_id' => $rev->id,
            'order_type' => 'CUSTOMER', 'status' => 'RELEASED', 'planned_qty' => 50, 'uom' => 'piece']);
        $pat = Pattern::create(['code' => 'P-'.$code, 'name' => 'Pattern '.$code, 'pattern_type' => $patternType]);
        $pr = PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 1]);
        $pr->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $rev->id, 'casting_weight_kg' => 5, 'poured_weight_kg' => 8]);

        return compact('order', 'pr', 'product', 'customer');
    }

    private function row(MouldingStation $st, array $job, array $over = []): array
    {
        return $over + ['moulding_station_id' => $st->id, 'production_order_id' => $job['order']->id, 'pattern_revision_id' => $job['pr']->id, 'sand_system' => 'Sheera', 'planned_moulds' => 10];
    }

    private function team(string $code, string $lead): MouldingTeam
    {
        return MouldingTeam::create(['code' => $code, 'name' => 'Team '.$code, 'lead_name' => $lead, 'member_count' => 4, 'status' => 'ACTIVE']);
    }

    private function issued(): MouldingWorkList
    {
        $svc = app(WorkListService::class);

        return $svc->create(today()->toDateString(), 'Morning');
    }

    public function test_automated_line_runs_one_pattern_at_a_time(): void
    {
        $team = $this->team('T1', 'Rashid');
        $line = MouldingStation::create(['code' => 'AUTO-1', 'name' => 'Automated line', 'station_type' => 'AUTOMATED_LINE', 'status' => 'ACTIVE', 'max_patterns' => 9, 'default_moulding_team_id' => $team->id]);
        $this->assertSame(1, $line->capacity());                                                                   // a line is one, whatever is stored

        $list = $this->issued();
        $a = $this->job('A1', 'Alpha Pumps');
        $b = $this->job('B1', 'Beta Valves');
        $this->post(route('work-lists.items.add', $list), $this->row($line, $a))->assertSessionHasNoErrors();
        $this->post(route('work-lists.items.add', $list), $this->row($line, $b))->assertSessionHasNoErrors();       // both can be planned, in sequence
        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Planner']);

        [$first, $second] = $list->items()->get()->all();
        $this->post(route('work-lists.items.start', [$list, $first]))->assertSessionHasNoErrors();
        $this->post(route('work-lists.items.start', [$list, $second]))->assertSessionHasErrors('item');            // line is busy
        $this->assertSame('PLANNED', $second->fresh()->status);

        $this->post(route('moulding-runs.output', $first->fresh()->run), ['moulds_made_qty' => 10, 'accepted_mould_qty' => 10, 'damaged_mould_qty' => 0, 'complete' => 1])->assertSessionHasNoErrors();
        $this->post(route('work-lists.items.start', [$list, $second]))->assertSessionHasNoErrors();                // free again
    }

    public function test_manual_station_takes_several_patterns_for_different_customers(): void
    {
        $team = $this->team('T2', 'Imtiaz');
        $bench = MouldingStation::create(['code' => 'MAN-1', 'name' => 'Manual bench 1', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 2, 'default_moulding_team_id' => $team->id]);
        $list = $this->issued();
        $jobs = [$this->job('A2', 'Alpha Pumps'), $this->job('B2', 'Beta Valves'), $this->job('C2', 'Gamma Engineering', 'MATCHPLATE')];
        foreach ($jobs as $j) {
            $this->post(route('work-lists.items.add', $list), $this->row($bench, $j))->assertSessionHasNoErrors();
        }
        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Planner']);

        $page = $this->get(route('work-lists.show', $list))->assertOk();
        $page->assertSee('Alpha Pumps')->assertSee('Beta Valves')->assertSee('Gamma Engineering')->assertSee('Up to 2 patterns at once');
        $this->get(route('work-lists.print', $list))->assertOk()->assertSee('Beta Valves')->assertSee('MAN-1');

        $this->post(route('work-lists.stations.start', [$list, $bench]))->assertSessionHasNoErrors();               // starts two, not three
        $this->assertSame(2, $list->items()->where('status', 'IN_PROGRESS')->count());
        $this->assertSame(1, $list->items()->where('status', 'PLANNED')->count());
        $this->post(route('work-lists.stations.start', [$list, $bench]))->assertSessionHasErrors('item');           // full
        $third = $list->items()->where('status', 'PLANNED')->firstOrFail();
        $this->post(route('work-lists.items.start', [$list, $third]))->assertSessionHasErrors('item');

        $this->assertSame('Imtiaz', MouldingRun::first()->operator_name);
    }

    public function test_team_comes_from_the_moulding_line_and_is_not_chosen_on_the_list(): void
    {
        $usual = $this->team('T3', 'Usual Lead');
        $bench = MouldingStation::create(['code' => 'MAN-2', 'name' => 'Manual bench 2', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 3, 'default_moulding_team_id' => $usual->id]);
        $bare = MouldingStation::create(['code' => 'MAN-3', 'name' => 'Bench without a team', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 3]);
        $list = $this->issued();
        $a = $this->job('A3', 'Alpha Pumps');
        $b = $this->job('B3', 'Beta Valves');

        $this->post(route('work-lists.items.add', $list), $this->row($bench, $a))->assertSessionHasNoErrors();
        $this->assertSame($usual->id, $list->items()->firstOrFail()->moulding_team_id);                             // the line's team, nothing chosen

        $this->post(route('work-lists.items.add', $list), $this->row($bare, $b))->assertSessionHasNoErrors();      // a line with no team still takes work
        $this->assertNull($list->items()->where('moulding_station_id', $bare->id)->value('moulding_team_id'));

        // the add form has no team field; it shows the line's team instead
        $this->get(route('work-lists.show', $list))->assertOk()->assertDontSee('name="moulding_team_id"', false)->assertDontSee('id="aw-team"', false)
            ->assertSee('data-team="T3 — Usual Lead"', false)->assertSee('data-products="'.$a['product']->id.'"', false)
            ->assertSee('Pattern A3 — P-A3 rev A')->assertSee('class="aw-pattern" data-search-select', false);   // plates are named and the list is searchable

        // the run is made by the line's team; a line without one runs without a team name
        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Planner']);
        foreach ($list->items as $item) {
            $this->post(route('work-lists.items.start', [$list, $item]))->assertSessionHasNoErrors();
        }
        $this->assertSame('Usual Lead', MouldingRun::where('moulding_station_id', $bench->id)->value('operator_name'));
        $this->assertNull(MouldingRun::where('moulding_station_id', $bare->id)->value('operator_name'));
    }

    public function test_station_settings_are_saved_and_a_line_is_forced_to_one(): void
    {
        $team = $this->team('T5', 'Lead Five');
        $this->post(route('moulding-teams.stations.store'), ['code' => 'm-9', 'name' => 'Manual 9', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 5, 'default_moulding_team_id' => $team->id])->assertSessionHasNoErrors();
        $st = MouldingStation::where('code', 'M-9')->firstOrFail();
        $this->assertSame(5, (int) $st->max_patterns);
        $this->assertSame($team->id, $st->default_moulding_team_id);
        $this->put(route('moulding-teams.stations.update', $st), ['code' => 'M-9', 'name' => 'Manual 9', 'station_type' => 'AUTOMATED_LINE', 'status' => 'ACTIVE', 'max_patterns' => 5])->assertSessionHasNoErrors();
        $this->assertSame(1, (int) $st->fresh()->max_patterns);
        $this->get(route('moulding-teams.index'))->assertOk()->assertSee('Patterns at once')->assertSee('Team on this line');
    }

    public function test_order_is_optional_and_links_to_the_best_open_order(): void
    {
        $team = $this->team('T6', 'Lead Six');
        $bench = MouldingStation::create(['code' => 'MAN-6', 'name' => 'Bench 6', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 3, 'default_moulding_team_id' => $team->id]);
        $list = $this->issued();
        $a = $this->job('A6', 'Alpha Pumps');                                                    // one open customer order for this product

        // a stock order and an urgent customer order for the same product, plus an unrelated order
        $stock = ProductionOrder::create(['production_order_number' => 'PO-STK', 'product_id' => $a['product']->id, 'product_revision_id' => $a['order']->product_revision_id,
            'order_type' => 'STOCK', 'status' => 'RELEASED', 'priority' => 'URGENT', 'planned_qty' => 500, 'uom' => 'piece']);
        $b = $this->job('B6', 'Beta Valves');
        $this->assertSame('PO-A6', app(WorkListService::class)->openOrdersFor($a['pr']->id)->first()->production_order_number);   // customer before stock, even though stock is urgent

        $a['order']->update(['priority' => 'LOW']);
        $a['order']->replicate(['production_order_number'])->fill(['production_order_number' => 'PO-A6B', 'priority' => 'HIGH'])->save();
        $this->assertSame('PO-A6B', app(WorkListService::class)->openOrdersFor($a['pr']->id)->first()->production_order_number); // then priority

        $this->post(route('work-lists.items.add', $list), ['moulding_station_id' => $bench->id, 'pattern_revision_id' => $a['pr']->id, 'sand_system' => 'Sheera', 'planned_moulds' => 10])->assertSessionHasNoErrors();
        $item = $list->items()->firstOrFail();
        $this->assertSame('AUTO', $item->order_link);
        $this->assertSame('PO-A6B', $item->productionOrder->production_order_number);
        $this->get(route('work-lists.show', $list))->assertOk()->assertSee('auto-linked')->assertSee('Alpha Pumps')
            ->assertSee('Automatic: the open order for this plate');
        $this->assertSame($stock->id, $stock->fresh()->id);                                       // unrelated order untouched
        $this->assertNotNull($b['order']);
    }

    public function test_no_open_order_then_stock_order_is_raised_at_start(): void
    {
        $team = $this->team('T7', 'Lead Seven');
        $bench = MouldingStation::create(['code' => 'MAN-7', 'name' => 'Bench 7', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 3, 'default_moulding_team_id' => $team->id]);
        $list = $this->issued();
        $a = $this->job('A7', 'Alpha Pumps');
        $a['order']->update(['status' => 'COMPLETED']);                                           // nothing open for this product

        $this->post(route('work-lists.items.add', $list), ['moulding_station_id' => $bench->id, 'pattern_revision_id' => $a['pr']->id, 'sand_system' => 'Sheera', 'planned_moulds' => 12])->assertSessionHasNoErrors();
        $item = $list->items()->firstOrFail();
        $this->assertNull($item->production_order_id);
        $this->assertSame('NONE', $item->order_link);
        $this->get(route('work-lists.show', $list))->assertOk()->assertSee('No order yet')->assertSee('Find open order');
        $this->post(route('work-lists.items.link', [$list, $item]))->assertSessionHasErrors('item');   // still nothing open
        $this->get(route('work-lists.print', $list))->assertOk()->assertSee('not linked');
        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Planner']);

        // an open order appears before the run starts: it is picked up at start
        $late = ProductionOrder::create(['production_order_number' => 'PO-LATE', 'product_id' => $a['product']->id, 'product_revision_id' => $a['order']->product_revision_id,
            'order_type' => 'CUSTOMER', 'status' => 'RELEASED', 'planned_qty' => 40, 'uom' => 'piece']);
        $this->post(route('work-lists.items.start', [$list, $item]))->assertSessionHasNoErrors();
        $this->assertSame($late->id, $item->fresh()->production_order_id);
        $this->assertSame($late->id, MouldingRun::firstOrFail()->production_order_id);

        // and with nothing open at all, a stock order is raised
        $b = $this->job('B7', 'Beta Valves');
        $b['order']->update(['status' => 'COMPLETED']);
        $this->post(route('work-lists.items.add', $list), ['moulding_station_id' => $bench->id, 'pattern_revision_id' => $b['pr']->id, 'sand_system' => 'Sheera', 'planned_moulds' => 6])->assertSessionHasNoErrors();
        $second = $list->items()->where('pattern_revision_id', $b['pr']->id)->firstOrFail();
        $this->post(route('work-lists.items.start', [$list, $second]))->assertSessionHasNoErrors();
        $stock = ProductionOrder::where('order_type', 'STOCK')->firstOrFail();
        $this->assertSame('IN_PROGRESS', $stock->status);
        $this->assertSame($b['product']->id, $stock->product_id);
        $this->assertSame(6.0, (float) $stock->planned_qty);                                      // 6 moulds x 1 casting
        $this->assertSame('STOCK', $second->fresh()->order_link);
        $this->assertSame($stock->id, MouldingRun::where('moulding_work_list_item_id', $second->id)->value('production_order_id'));
    }

    public function test_stock_order_waits_for_release_approval_when_a_policy_requires_it(): void
    {
        ApprovalPolicy::create(['action_key' => 'production_order.release', 'is_active' => true, 'required_approvals' => 1, 'permission' => 'approvals.decide', 'allow_self_approval' => false]);
        $team = $this->team('T8', 'Lead Eight');
        $bench = MouldingStation::create(['code' => 'MAN-8', 'name' => 'Bench 8', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 2, 'default_moulding_team_id' => $team->id]);
        $list = $this->issued();
        $a = $this->job('A8', 'Alpha Pumps');
        $a['order']->update(['status' => 'COMPLETED']);
        $this->post(route('work-lists.items.add', $list), ['moulding_station_id' => $bench->id, 'pattern_revision_id' => $a['pr']->id, 'sand_system' => 'Sheera', 'planned_moulds' => 5])->assertSessionHasNoErrors();
        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Planner']);
        $item = $list->items()->firstOrFail();

        $this->post(route('work-lists.items.start', [$list, $item]))->assertSessionHasErrors('item');   // raised, waiting for approval
        $stock = ProductionOrder::where('order_type', 'STOCK')->firstOrFail();
        $this->assertSame('DRAFT', $stock->status);
        $this->assertSame($stock->id, $item->fresh()->production_order_id);
        $this->assertSame(0, MouldingRun::count());
        $this->assertSame(1, ApprovalRequest::where('subject_id', $stock->id)->count());
        $this->post(route('work-lists.items.start', [$list, $item]))->assertSessionHasErrors('item');   // still waiting, no second order
        $this->assertSame(1, ProductionOrder::where('order_type', 'STOCK')->count());
    }

    public function test_a_moulding_line_takes_rows_with_the_lines_team_and_a_sand_default_that_can_be_changed(): void
    {
        $usual = $this->team('T9', 'Usual Nine');
        $line = MouldingStation::create(['code' => 'ML-9', 'name' => 'Line 9', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 3, 'default_moulding_team_id' => $usual->id, 'default_sand_system' => 'Alphaset']);
        $list = $this->issued();
        $a = $this->job('A9', 'Alpha Pumps');
        $b = $this->job('B9', 'Beta Valves');
        $rows = [['pattern_revision_id' => $a['pr']->id, 'planned_moulds' => 20], ['pattern_revision_id' => $b['pr']->id, 'production_order_id' => $b['order']->id, 'planned_moulds' => 8]];

        // only the line is chosen: team and sand come from it
        $this->post(route('work-lists.rows.add', $list), ['moulding_station_id' => $line->id, 'rows' => $rows, 'instructions' => 'Check cores'])->assertSessionHasNoErrors();
        $items = $list->items()->get();
        $this->assertCount(2, $items);
        $this->assertSame([$usual->id], $items->pluck('moulding_team_id')->unique()->all());
        $this->assertSame(['Alphaset'], $items->pluck('sand_system')->unique()->all());
        $this->assertSame([20, 8], $items->pluck('planned_moulds')->all());
        $this->assertSame('Check cores', $items->first()->instructions);
        $this->assertSame('AUTO', $items[0]->order_link);
        $this->assertSame('MANUAL', $items[1]->order_link);

        // the page offers the line's sand, a row template and a delete on each row
        $this->get(route('work-lists.show', $list))->assertOk()->assertSee('data-sand="Alphaset"', false)
            ->assertSee('aw-del', false)->assertSee('+ Add row')->assertSee('Change sand');

        // sand can be changed when adding
        $c = $this->job('C9', 'Gamma Engineering');
        $this->post(route('work-lists.rows.add', $list), ['moulding_station_id' => $line->id, 'sand_system' => 'Greensand',
            'rows' => [['pattern_revision_id' => $c['pr']->id, 'planned_moulds' => 5]]])->assertSessionHasNoErrors();
        $this->assertSame('Greensand', $list->items()->where('production_order_id', $c['order']->id)->value('sand_system'));

        // and changed afterwards for the rows not yet started
        $this->post(route('work-lists.stations.change', [$list, $line]), ['sand_system' => 'Sheera'])->assertSessionHasNoErrors();
        $this->assertSame(['Sheera'], $list->items()->pluck('sand_system')->unique()->all());
    }

    public function test_rows_are_all_added_or_none_and_the_bad_row_is_named(): void
    {
        $team = $this->team('T11', 'Lead Eleven');
        $line = MouldingStation::create(['code' => 'ML-11', 'name' => 'Line 11', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 3, 'default_moulding_team_id' => $team->id]);
        $list = $this->issued();
        $a = $this->job('A11', 'Alpha Pumps');
        $b = $this->job('B11', 'Beta Valves');
        // row 2 names an order for another product, which this plate does not make
        $this->post(route('work-lists.rows.add', $list), ['moulding_station_id' => $line->id, 'rows' => [
            ['pattern_revision_id' => $a['pr']->id, 'planned_moulds' => 10],
            ['pattern_revision_id' => $a['pr']->id, 'production_order_id' => $b['order']->id, 'planned_moulds' => 10],
        ]])->assertSessionHasErrors('item');
        $this->assertStringContainsString('Row 2', session('errors')->first('item'));
        $this->assertSame(0, $list->items()->count());                                              // row 1 was not kept

        $this->post(route('work-lists.rows.add', $list), ['moulding_station_id' => $line->id, 'rows' => []])->assertSessionHasErrors('rows');
        $this->post(route('work-lists.rows.add', $list), ['moulding_station_id' => $line->id, 'rows' => [['pattern_revision_id' => $a['pr']->id, 'planned_moulds' => 0]]])->assertSessionHasErrors('rows.0.planned_moulds');
        $this->post(route('work-lists.rows.add', $list), ['moulding_station_id' => $line->id, 'rows' => [
            ['pattern_revision_id' => $a['pr']->id, 'planned_moulds' => 10], ['pattern_revision_id' => $a['pr']->id, 'planned_moulds' => 4],
        ]])->assertSessionHasErrors('item');                                                         // the same plate twice on one line
        $this->assertSame(0, $list->items()->count());
    }

    public function test_a_pattern_suits_its_own_sand_and_the_ones_listed(): void
    {
        $p = new Pattern(['code' => 'PX', 'sand_system' => 'Sheera', 'compatible_sands' => ['Alphaset']]);
        $this->assertTrue($p->suitsSand('Sheera'));
        $this->assertTrue($p->suitsSand('Alphaset'));
        $this->assertFalse($p->suitsSand('Greensand'));
        $this->assertStringContainsString('taper', $p->sandWarning('Greensand'));
        $this->assertNull($p->sandWarning('Sheera'));
        $this->assertTrue((new Pattern(['code' => 'PY']))->suitsSand('Greensand'));                                  // nothing recorded: suits any
    }

    public function test_a_sheera_pattern_on_a_greensand_line_is_refused_unless_accepted(): void
    {
        $team = $this->team('T12', 'Lead Twelve');
        $green = MouldingStation::create(['code' => 'GS-1', 'name' => 'Greensand line', 'station_type' => 'AUTOMATED_LINE', 'status' => 'ACTIVE', 'default_moulding_team_id' => $team->id, 'default_sand_system' => 'Greensand']);
        $list = $this->issued();
        $sheera = $this->job('S12', 'Alpha Pumps');
        $sheera['pr']->pattern->update(['sand_system' => 'Sheera', 'sand_note' => 'Taper 0.5 deg']);
        $both = $this->job('B12', 'Beta Valves');
        $both['pr']->pattern->update(['sand_system' => 'Sheera', 'compatible_sands' => ['Greensand']]);
        $none = $this->job('N12', 'Gamma Engineering');                                                             // no sand recorded

        $bad = ['pattern_revision_id' => $sheera['pr']->id, 'planned_moulds' => 10];
        $this->post(route('work-lists.rows.add', $list), ['moulding_station_id' => $green->id, 'rows' => [$bad]])->assertSessionHasErrors('item');
        $this->assertStringContainsString('made for Sheera', session('errors')->first('item'));
        $this->assertStringContainsString('Taper 0.5 deg', session('errors')->first('item'));
        $this->assertSame(0, $list->items()->count());

        // a plate listed as working on Greensand, and one with no sand recorded, go straight on
        $this->post(route('work-lists.rows.add', $list), ['moulding_station_id' => $green->id, 'rows' => [
            ['pattern_revision_id' => $both['pr']->id, 'planned_moulds' => 5], ['pattern_revision_id' => $none['pr']->id, 'planned_moulds' => 5],
        ]])->assertSessionHasNoErrors();
        $this->assertSame([false, false], $list->items()->get()->pluck('sand_override')->all());

        // accepting the mismatch knowingly goes through and is recorded
        $this->post(route('work-lists.rows.add', $list), ['moulding_station_id' => $green->id, 'rows' => [$bad + ['sand_override' => 1]]])->assertSessionHasNoErrors();
        $this->assertTrue((bool) $list->items()->where('pattern_revision_id', $sheera['pr']->id)->value('sand_override'));
        $this->get(route('work-lists.show', $list))->assertOk()->assertSee('sand mismatch accepted')->assertSee('Use anyway')->assertSee('"for":"Sheera"', false);
        $this->get(route('work-lists.print', $list))->assertOk()->assertSee('plate not made for this sand');
    }

    public function test_changing_a_lines_sand_checks_the_plates_already_on_it(): void
    {
        $team = $this->team('T13', 'Lead Thirteen');
        $line = MouldingStation::create(['code' => 'SH-1', 'name' => 'Sheera line', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 3, 'default_moulding_team_id' => $team->id, 'default_sand_system' => 'Sheera']);
        $list = $this->issued();
        $a = $this->job('A13', 'Alpha Pumps');
        $a['pr']->pattern->update(['sand_system' => 'Sheera']);
        $this->post(route('work-lists.rows.add', $list), ['moulding_station_id' => $line->id, 'rows' => [['pattern_revision_id' => $a['pr']->id, 'planned_moulds' => 10]]])->assertSessionHasNoErrors();

        $this->post(route('work-lists.stations.change', [$list, $line]), ['sand_system' => 'Greensand'])->assertSessionHasErrors('item');
        $this->assertSame('Sheera', $list->items()->value('sand_system'));                                         // refused, nothing changed
        $this->post(route('work-lists.stations.change', [$list, $line]), ['sand_system' => 'Greensand', 'accept_unsuited' => 1])->assertSessionHasNoErrors();
        $item = $list->items()->firstOrFail();
        $this->assertSame('Greensand', $item->sand_system);
        $this->assertTrue((bool) $item->sand_override);
        $this->post(route('work-lists.stations.change', [$list, $line]), ['sand_system' => 'Sheera'])->assertSessionHasNoErrors();   // back to its own sand
        $this->assertFalse((bool) $item->fresh()->sand_override);
    }

    public function test_the_sand_system_is_recorded_on_the_pattern(): void
    {
        $this->get(route('patterns.create'))->assertOk()->assertSee('Made for sand system')->assertSee('Also works on');
        $this->post(route('patterns.store'), ['name' => 'Flange plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE', 'revision' => 'A', 'revision_status' => 'DRAFT',
            'sand_system' => 'Sheera', 'compatible_sands' => ['Sheera', 'Alphaset'], 'sand_note' => 'Taper 0.5 deg'])->assertSessionHasNoErrors();
        $p = Pattern::firstOrFail();
        $this->assertSame('Sheera', $p->sand_system);
        $this->assertSame(['Alphaset'], $p->compatible_sands);                                                      // its own sand is not repeated

        $this->put(route('patterns.sand', $p), ['sand_system' => 'Greensand', 'compatible_sands' => ['Sheera'], 'sand_note' => 'Wider taper'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame('Greensand', $p->sand_system);
        $this->assertSame(['Sheera'], $p->compatible_sands);
        $this->get(route('patterns.show', $p))->assertOk()->assertSee('Made for sand')->assertSee('also works on Sheera')->assertSee('Wider taper');
        $this->put(route('patterns.sand', $p), ['sand_system' => 'Mud'])->assertSessionHasErrors('sand_system');
    }

    public function test_the_plate_column_shows_the_name_code_type_and_photo(): void
    {
        $team = $this->team('T9', 'Rashid');
        $bench = MouldingStation::create(['code' => 'MAN-9', 'name' => 'Bench', 'station_type' => 'MANUAL_BENCH', 'status' => 'ACTIVE', 'max_patterns' => 2, 'default_moulding_team_id' => $team->id]);
        $list = $this->issued();
        $job = $this->job('D9', 'Delta Castings');
        $this->post(route('work-lists.items.add', $list), $this->row($bench, $job))->assertSessionHasNoErrors();

        $photo = Attachment::create([
            'attachable_type' => 'pattern', 'attachable_id' => $job['pr']->pattern_id, 'category' => 'PHOTO',
            'original_name' => 'plate.png', 'disk' => 'local', 'path' => 'attachments/plate.png', 'mime_type' => 'image/png',
            'size_bytes' => 10, 'sha256' => str_repeat('a', 64),
        ]);

        $this->get(route('work-lists.show', $list))->assertOk()
            ->assertSee('Pattern D9')->assertSee('P-D9 rev A')->assertSee('loose pattern')
            ->assertSee(route('attachments.download', $photo), false);
    }
}
