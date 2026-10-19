<?php

namespace Tests\Feature;

use App\Models\MouldingRun;
use App\Models\MouldingWorkList;
use App\Models\MouldingWorkListItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkLists;
use Tests\TestCase;

class WorkListMaintenanceTest extends TestCase
{
    use BuildsWorkLists;
    use RefreshDatabase;

    /** @return array{0: MouldingWorkList, 1: MouldingWorkListItem, 2: array} a draft list holding one planned item */
    private function draftList(): array
    {
        $w = $this->world();
        $this->post(route('work-lists.store'), ['list_date' => today()->toDateString(), 'shift' => 'Morning']);
        $list = MouldingWorkList::firstOrFail();
        $this->post(route('work-lists.items.add', $list), $this->item($w))->assertSessionHasNoErrors();

        return [$list, $list->items()->firstOrFail(), $w];
    }

    public function test_the_list_of_work_lists_shows_each_list(): void
    {
        [$list] = $this->draftList();

        $this->get(route('work-lists.index'))->assertOk()->assertSee($list->list_number);
    }

    public function test_an_item_not_yet_started_can_be_removed(): void
    {
        [$list, $item] = $this->draftList();

        $this->delete(route('work-lists.items.remove', [$list, $item]))->assertSessionHasNoErrors();

        $this->assertSame(0, $list->items()->count());
    }

    public function test_a_started_item_cannot_be_removed_and_an_item_of_another_list_is_not_found(): void
    {
        [$list, $item] = $this->draftList();
        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Planner']);
        $this->post(route('work-lists.items.start', [$list, $item]));

        $this->delete(route('work-lists.items.remove', [$list, $item]))->assertSessionHasErrors('item');
        $this->assertSame(1, $list->items()->count());

        $other = MouldingWorkList::create(['list_number' => 'WL-OTHER', 'list_date' => today(), 'shift' => 'Evening', 'status' => 'DRAFT']);
        $this->delete(route('work-lists.items.remove', [$other, $item]))->assertNotFound();
    }

    public function test_a_list_can_be_cancelled_until_a_run_starts_from_it(): void
    {
        [$list, $item] = $this->draftList();

        $this->post(route('work-lists.cancel', $list))->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $list->fresh()->status);
        $this->post(route('work-lists.cancel', $list))->assertSessionHasErrors('item');      // already cancelled

        $second = MouldingWorkList::create(['list_number' => 'WL-2', 'list_date' => today(), 'shift' => 'Evening', 'status' => 'ISSUED']);
        $started = $second->items()->create($item->only(['moulding_team_id', 'moulding_station_id', 'production_order_id', 'pattern_revision_id', 'sand_system', 'planned_moulds']) + ['sequence' => 1, 'status' => 'IN_PROGRESS']);
        $run = MouldingRun::create(['run_number' => 'MR-WL', 'production_order_id' => $started->production_order_id, 'run_date' => today(), 'status' => 'IN_PROGRESS', 'moulds_made_qty' => 0, 'accepted_mould_qty' => 0]);
        $started->update(['moulding_run_id' => $run->id]);

        $this->post(route('work-lists.cancel', $second))->assertSessionHasErrors('item');
        $this->assertSame('ISSUED', $second->fresh()->status);
    }
}
