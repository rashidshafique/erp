<?php

namespace Tests\Feature;

use App\Models\MouldingWorkList;
use App\Models\ProductionOrder;
use App\Services\Planning\OrderPlanner;
use App\Support\PlannerRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkLists;
use Tests\TestCase;

class PlannerTest extends TestCase
{
    use BuildsWorkLists;
    use RefreshDatabase;

    public function test_orders_are_ranked_by_due_date_and_loaded_onto_the_station_shift_by_shift(): void
    {
        $w = $this->world();                                                                   // 100 pieces, 2 per mould: 50 moulds
        $w['st']->update(['moulds_per_shift' => 20]);
        $w['order']->update(['required_date' => today()->addDays(4)]);
        $urgent = ProductionOrder::create(['production_order_number' => 'PO-S2', 'product_id' => $w['product']->id, 'product_revision_id' => $w['rev']->id,
            'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 20, 'uom' => 'piece', 'required_date' => today()->addDays(30)]);
        $urgent->update(['required_date' => today()->addDay()]);

        $plan = app(OrderPlanner::class)->plan(today());

        $this->assertSame(['PO-S2', 'PO-S1'], array_map(fn ($o) => $o['order']->production_order_number, $plan['orders']));
        [$first, $second] = $plan['orders'];
        $this->assertSame(10, $first['moulds_left']);
        $this->assertTrue($first['finish']->isSameDay(today()->addDays(3)));                 // moulded today, plus 3 days to finish
        $this->assertSame('LATE', $first['state']);
        $this->assertTrue($second['finish']->isSameDay(today()->addDays(2 + 3)));            // 10 + 50 moulds at 20 a shift: shifts 0 to 2
        $this->assertSame('LATE', $second['state']);
        $this->assertSame([10, 10], array_column($plan['suggestion'][$w['st']->id]['rows'], 'moulds'));
        $this->get(route('planner.index'))->assertOk()->assertSeeInOrder(['Next shift', 'PO-S2', 'PO-S1', 'Put on a work list', 'Late']);

        PlannerRules::set('shifts_per_day', 3);
        $this->assertSame('AT_RISK', app(OrderPlanner::class)->plan(today())['orders'][1]['state']);   // all moulded today, finished a day before due
    }

    public function test_moulds_already_on_an_open_list_are_not_suggested_again_and_fill_the_station_first(): void
    {
        $w = $this->world();
        $w['st']->update(['moulds_per_shift' => 40]);
        $this->post(route('work-lists.store'), ['list_date' => today()->toDateString(), 'shift' => 'Morning']);
        $this->post(route('work-lists.items.add', MouldingWorkList::firstOrFail()), $this->item($w, ['planned_moulds' => 30]));

        $order = app(OrderPlanner::class)->plan(today())['orders'][0];

        $this->assertSame(30, $order['on_lists']);
        $this->assertSame(20, $order['moulds_left']);
        $this->assertTrue($order['finish']->isSameDay(today()->addDays(1 + 3)));             // 10 left in shift 0, the other 10 in shift 1
    }

    public function test_old_lists_and_work_for_finished_orders_hold_no_station_time(): void
    {
        $w = $this->world();
        $w['st']->update(['moulds_per_shift' => 40]);
        $this->post(route('work-lists.store'), ['list_date' => today()->subDays(3)->toDateString(), 'shift' => 'Morning']);
        $this->post(route('work-lists.items.add', MouldingWorkList::firstOrFail()), $this->item($w, ['planned_moulds' => 40]));
        $done = ProductionOrder::create(['production_order_number' => 'PO-S9', 'product_id' => $w['product']->id, 'product_revision_id' => $w['rev']->id,
            'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 100, 'uom' => 'piece']);
        $this->post(route('work-lists.store'), ['list_date' => today()->toDateString(), 'shift' => 'Morning']);
        $this->post(route('work-lists.items.add', MouldingWorkList::latest('created_at')->firstOrFail()), $this->item($w, ['production_order_id' => $done->id, 'planned_moulds' => 40]));
        $done->update(['status' => 'COMPLETED']);

        $order = app(OrderPlanner::class)->plan(today())['orders'][0];

        $this->assertSame([0, 50], [$order['on_lists'], $order['moulds_left']]);
        $this->assertTrue($order['finish']->isSameDay(today()->addDays(1 + 3)));             // 40 in shift 0, 10 in shift 1: the line was free
    }

    public function test_a_station_shift_is_shared_by_the_sequencing_rules(): void
    {
        $w = $this->world();
        $w['order']->update(['status' => 'CANCELLED']);
        $w['st']->update(['moulds_per_shift' => 40]);
        $p1 = $this->plate('P1', $w['st']);
        $p2 = $this->plate('P2', $w['st']);
        $this->order('A', $p1, 1);
        $this->order('B', $p2, 2);
        $this->order('C', $p1, 3);
        $firstShift = fn (): array => collect(app(OrderPlanner::class)->plan(today())['suggestion'])->flatMap(fn ($s) => $s['rows'])
            ->mapWithKeys(fn ($r) => [$r['order']->production_order_number => $r['moulds']])->all();

        $this->assertSame(['A' => 30, 'B' => 10], $firstShift());                            // earliest due fills the shift, the next starts in what is left

        PlannerRules::set('group_same_pattern', 1);
        $this->assertSame(['A' => 30, 'C' => 10], $firstShift());                            // C runs behind A on the same plate
        PlannerRules::set('group_same_pattern', 0);

        PlannerRules::set('patterns_per_shift', 2);
        $this->assertSame(['A' => 20, 'B' => 20], $firstShift());
        PlannerRules::set('patterns_per_shift', 1);

        PlannerRules::set('min_run_moulds', 15);
        $this->assertSame(['A' => 30], $firstShift());                                       // 10 left is less than B's minimum run
    }

    private function plate(string $code, \App\Models\MouldingStation $station): array
    {
        $product = \App\Models\Product::create(['code' => 'PR-'.$code, 'name' => 'Product '.$code]);
        $rev = \App\Models\ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $pat = \App\Models\Pattern::create(['code' => $code, 'name' => 'Plate '.$code, 'pattern_type' => 'MATCHPLATE', 'preferred_moulding_station_id' => $station->id]);
        $pr = \App\Models\PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 1]);
        $pr->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $rev->id, 'casting_weight_kg' => 5, 'poured_weight_kg' => 8]);

        return compact('product', 'rev');
    }

    /** An order for 30 moulds of a plate's product, due in the given days. */
    private function order(string $number, array $plate, int $dueInDays): ProductionOrder
    {
        return ProductionOrder::create(['production_order_number' => $number, 'product_id' => $plate['product']->id, 'product_revision_id' => $plate['rev']->id,
            'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 30, 'uom' => 'piece', 'required_date' => today()->addDays($dueInDays)]);
    }

    public function test_the_suggestion_is_put_on_a_draft_work_list_linked_to_the_order(): void
    {
        $w = $this->world();
        $w['st']->update(['moulds_per_shift' => 20]);
        $this->post(route('work-lists.store'), ['list_date' => today()->toDateString(), 'shift' => 'Morning']);

        $this->post(route('planner.fill'), ['day' => today()->toDateString(), 'shift' => 'Morning'])->assertSessionHasNoErrors();

        $list = MouldingWorkList::with('items')->sole();                                      // the draft for that day and shift is reused
        $this->assertSame([[$w['order']->id, $w['pr']->id, 20]], $list->items->map(fn ($i) => [$i->production_order_id, $i->pattern_revision_id, $i->planned_moulds])->all());

        $this->post(route('planner.fill'), ['day' => today()->toDateString(), 'shift' => 'Morning'])->assertSessionHasErrors('plan');
        $this->assertSame(20, (int) $list->items()->sum('planned_moulds'));                  // the station is full this shift
    }

    public function test_orders_without_a_rated_station_are_flagged_and_nothing_is_filled(): void
    {
        $w = $this->world();

        $this->get(route('planner.index'))->assertOk()->assertSee('No station rate')->assertSee($w['st']->code);
        $this->post(route('planner.fill'), ['day' => today()->toDateString(), 'shift' => 'Morning'])->assertSessionHasErrors('plan');
        $this->assertSame(0, MouldingWorkList::count());
    }

    public function test_assumptions_are_saved_within_their_limits(): void
    {
        $rest = ['patterns_per_shift' => 1, 'min_run_moulds' => 0, 'group_same_pattern' => 0];
        $this->put(route('planner.rules'), ['shifts_per_day' => 4, 'days_after_moulding' => 2] + $rest)->assertSessionHasErrors('shifts_per_day');
        $this->put(route('planner.rules'), ['shifts_per_day' => 2, 'days_after_moulding' => 5] + $rest)->assertSessionHasNoErrors();

        $this->assertSame([2, 5], [PlannerRules::get('shifts_per_day'), PlannerRules::get('days_after_moulding')]);
    }

    public function test_a_station_keeps_its_moulds_per_shift(): void
    {
        $this->post(route('moulding-teams.stations.store'), ['code' => 'FL-1', 'name' => 'Floor 1', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'moulds_per_shift' => 60])->assertSessionHasNoErrors();

        $this->get(route('moulding-teams.index'))->assertSee('60');
        $this->assertSame(60, (int) \App\Models\MouldingStation::where('code', 'FL-1')->value('moulds_per_shift'));
    }
}
