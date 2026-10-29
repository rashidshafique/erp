<?php

namespace Tests\Feature;

use App\Models\MouldingRun;
use App\Models\MouldingWorkList;
use App\Support\FloorRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkLists;
use Tests\TestCase;

/** The day's moulding list: prepared from open orders after the moulds already made, earlier unstarted rows carried over, changed, then released. */
class DailyMouldingListTest extends TestCase
{
    use BuildsWorkLists;
    use RefreshDatabase;

    public function test_the_list_is_prepared_from_what_orders_still_need_and_carries_over_unstarted_rows(): void
    {
        $w = $this->world();                                                                   // 100 pieces, 2 per mould: 50 moulds
        $w['st']->update(['moulds_per_shift' => 60]);
        MouldingRun::create(['run_number' => 'MR-1', 'production_order_id' => $w['order']->id, 'pattern_revision_id' => $w['pr']->id, 'run_date' => today()->subDay(),
            'status' => 'COMPLETED', 'moulds_made_qty' => 10, 'accepted_mould_qty' => 10]);
        $this->post(route('work-lists.store'), ['list_date' => today()->subDay()->toDateString(), 'shift' => 'Morning']);
        $old = MouldingWorkList::sole();
        $this->post(route('work-lists.items.add', $old), $this->item($w, ['planned_moulds' => 30]));

        $this->post(route('work-lists.prepare'), ['day' => today()->addDay()->toDateString()])->assertSessionHasNoErrors();

        $list = MouldingWorkList::whereDate('list_date', today()->addDay())->sole();
        $row = $list->items()->sole();
        $this->assertSame(40, $row->planned_moulds, 'the 10 moulds already made come off the order');
        $this->assertSame($old->items()->sole()->id, $row->carried_from_item_id);
        $this->assertSame('SKIPPED', $old->items()->sole()->status, 'the earlier row is not left planned twice');
        $this->assertSame('DRAFT', $list->status);

        $this->post(route('work-lists.prepare'), ['day' => today()->addDay()->toDateString()])->assertSessionHas('success', fn ($m) => str_contains($m, 'already'));
        $this->assertSame(2, MouldingWorkList::count());
    }

    public function test_a_row_is_changed_before_release_and_the_released_list_fills_the_quick_grid(): void
    {
        FloorRules::set('work_lists', 'OFF');
        FloorRules::set('floor_screens', 'QUICK');
        $w = $this->world();
        $w['st']->update(['moulds_per_shift' => 60]);
        $this->get(route('quick.list.index'))->assertOk()->assertSee('Prepare list');
        $this->post(route('work-lists.prepare'), ['day' => today()->toDateString(), 'stay' => 1])->assertRedirect();
        $list = MouldingWorkList::sole();
        $row = $list->items()->sole();

        $this->put(route('work-lists.items.update', [$list, $row]), ['planned_moulds' => 35])->assertSessionHasNoErrors();
        $this->assertSame(35, $row->fresh()->planned_moulds);
        $this->get(route('quick.list.index'))->assertOk()->assertSee('MP-7')->assertSee('Release to the floor');

        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Planner'])->assertSessionHasNoErrors();
        $this->get(route('quick.list.index'))->assertSee('Released')->assertSee('Enter moulds made');
        $this->get(route('quick.floor.index', ['plan' => today()->toDateString()]))->assertOk()->assertSee('35');
    }

    public function test_the_evening_job_prepares_tomorrow_unless_the_rule_is_off(): void
    {
        $w = $this->world();
        $w['st']->update(['moulds_per_shift' => 60]);
        \App\Support\WorkListRules::set('auto_prepare', 'OFF');
        $this->artisan('moulding:prepare-list')->assertSuccessful();
        $this->assertSame(0, MouldingWorkList::count());

        \App\Support\WorkListRules::set('auto_prepare', 'NEXT_DAY');
        $this->artisan('moulding:prepare-list')->assertSuccessful();
        $this->assertTrue(MouldingWorkList::sole()->list_date->isSameDay(today()->addDay()));
    }
}
