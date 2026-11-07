<?php

namespace Tests\Feature;

use App\Models\MouldCount;
use App\Models\MouldingRun;
use App\Models\MouldingWorkList;
use App\Models\MouldingWorkListItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkLists;
use Tests\TestCase;

/** The moulding supervisor's tablet (design F3): today's list, moulds made, the count before a pour and its shortfall reasons. */
class MouldingTabletTest extends TestCase
{
    use BuildsWorkLists;
    use RefreshDatabase;

    /** @return array{0: array<string, mixed>, 1: MouldingWorkList, 2: MouldingWorkListItem} */
    private function issued(string $date = 'today'): array
    {
        $w = $this->world();
        $this->post(route('work-lists.store'), ['list_date' => $date === 'today' ? today()->toDateString() : $date, 'shift' => 'Morning'])->assertSessionHasNoErrors();
        $list = MouldingWorkList::latest('created_at')->firstOrFail();
        $this->post(route('work-lists.items.add', $list), $this->item($w))->assertSessionHasNoErrors();
        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Rashid'])->assertSessionHasNoErrors();

        return [$w, $list->fresh(), $list->items()->firstOrFail()];
    }

    public function test_the_list_shows_the_station_rows_and_folds_finished_ones(): void
    {
        [$w, $list, $item] = $this->issued();

        $this->get(route('floor.moulding.list'))->assertOk()->assertSee($list->list_number)->assertSee('MP-7 Flange plate')
            ->assertSee('Enter moulds')->assertSee('0 / 30')->assertDontSee('✓ Done');

        $item->update(['status' => 'DONE']);
        $this->get(route('floor.moulding.list', ['station' => $w['st']->id]))->assertOk()->assertSee('✓ Done · 1 pattern')->assertSee('Nothing left to mould on this list.');
    }

    public function test_the_first_save_starts_the_run_and_later_saves_add_on(): void
    {
        [, , $item] = $this->issued();

        $this->post(route('floor.moulding.save', $item), ['made' => 20, 'damaged' => 2, 'downtime' => 5])->assertRedirect()->assertSessionHasNoErrors();
        $run = MouldingRun::where('moulding_work_list_item_id', $item->id)->sole();
        $this->assertSame([20, 18, 2, 5, 'IN_PROGRESS'], [(int) $run->moulds_made_qty, (int) $run->accepted_mould_qty, (int) $run->damaged_mould_qty, (int) $run->downtime_minutes, $run->status]);

        $this->get(route('floor.moulding.item', $item))->assertOk()->assertSee('Already made')->assertSee('18 of 30')->assertSee($run->run_number);

        $this->post(route('floor.moulding.save', $item), ['made' => 12, 'complete' => 1])->assertSessionHasNoErrors();
        $run->refresh();
        $this->assertSame([32, 30, 2, 'COMPLETED'], [(int) $run->moulds_made_qty, (int) $run->accepted_mould_qty, (int) $run->damaged_mould_qty, $run->status]);
        $this->assertSame('DONE', $item->fresh()->status);
    }

    public function test_more_damaged_than_made_is_refused(): void
    {
        [, , $item] = $this->issued();

        $this->post(route('floor.moulding.save', $item), ['made' => 3, 'damaged' => 5])->assertSessionHasErrors('made');
        $this->assertSame(0, (int) MouldingRun::sum('moulds_made_qty'));
    }

    public function test_a_short_count_asks_why_for_each_pattern_then_writes_off(): void
    {
        [, , $item] = $this->issued();
        $this->post(route('floor.moulding.save', $item), ['made' => 20])->assertSessionHasNoErrors();
        $revision = $item->pattern_revision_id;

        $this->get(route('floor.moulding.count'))->assertOk()->assertSee('MP-7 Flange plate')->assertSee('Records 20');

        $this->post(route('floor.moulding.count.review'), ['counted' => [$revision => 17]])->assertOk()->assertSee('3 moulds short')->assertSee('Write off 3 and continue');
        $this->assertSame(0, MouldCount::count(), 'nothing is saved before the reason');

        $this->post(route('floor.moulding.count.store'), ['counted' => [$revision => 17]])->assertOk()->assertSee('Pick why for each short pattern.');

        $this->post(route('floor.moulding.count.store'), ['counted' => [$revision => 17], 'reason' => [$revision => 'BROKEN'], 'note' => 'crane'])
            ->assertRedirect(route('floor.moulding.count.ready'));
        $count = MouldCount::sole();
        $this->assertSame([20, 17, 3, 'BROKEN'], [(int) $count->expected, (int) $count->counted, (int) $count->written_off, $count->reason]);
        $this->assertSame(3, (int) MouldingRun::sole()->damaged_mould_qty);

        $this->get(route('floor.moulding.count.ready'))->assertOk()->assertSee('17 moulds ready')->assertSee('34')->assertSee('3 written off');
    }

    public function test_a_full_count_goes_straight_to_ready(): void
    {
        [, , $item] = $this->issued();
        $this->post(route('floor.moulding.save', $item), ['made' => 10])->assertSessionHasNoErrors();

        $this->post(route('floor.moulding.count.review'), ['counted' => [$item->pattern_revision_id => 10]])->assertRedirect(route('floor.moulding.count.ready'));
        $this->assertSame(0, (int) MouldCount::sole()->written_off);
    }

    public function test_rows_carry_over_when_today_has_no_issued_list(): void
    {
        [, $list] = $this->issued(today()->subDay()->toDateString());

        $this->get(route('floor.moulding.list'))->assertOk()->assertSee('no list for today')
            ->assertSee('Carried over · MP-7 Flange plate')->assertSee('from '.$list->list_number);
    }

    public function test_the_count_needs_shop_floor_recording(): void
    {
        config(['foundry.require_login' => true]);
        $sales = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $sales->roles()->attach(Role::where('code', 'SALES')->value('id'));
        $moulder = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $moulder->roles()->attach(Role::where('code', 'MOULDING_SUPERVISOR')->value('id'));

        $this->actingAs($sales)->get(route('floor.moulding.count'))->assertForbidden();
        $this->actingAs($sales)->get(route('floor.moulding.list'))->assertForbidden();
        $this->actingAs($moulder)->get(route('floor.moulding.count'))->assertOk();
        $this->actingAs($moulder)->get(route('floor.moulding.list'))->assertOk();
    }
}
