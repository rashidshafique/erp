<?php

namespace Tests\Feature;

use App\Models\MouldConfiguration;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\MouldingTeam;
use App\Models\MouldingWorkList;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MouldingWorkListTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        $product = Product::create(['code' => 'FLG-1', 'name' => 'Flange']);
        $rev = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $pat = Pattern::create(['code' => 'MP-7', 'name' => 'Flange plate', 'pattern_type' => 'MATCHPLATE']);
        $pr = PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        $pr->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $rev->id, 'casting_weight_kg' => 5, 'poured_weight_kg' => 8]);
        $pr->cavityMap()->create(['cavity_number' => 2, 'product_revision_id' => $rev->id, 'casting_weight_kg' => 5, 'poured_weight_kg' => 8]);
        $order = ProductionOrder::create(['production_order_number' => 'PO-S1', 'product_id' => $product->id, 'product_revision_id' => $rev->id, 'order_type' => 'STOCK',
            'status' => 'RELEASED', 'planned_qty' => 100, 'uom' => 'piece']);
        $team = MouldingTeam::create(['code' => 'T1', 'name' => 'Team Alpha', 'lead_name' => 'Rashid', 'member_count' => 5, 'status' => 'ACTIVE']);
        $st = MouldingStation::create(['code' => 'MP-1', 'name' => 'Match plate machine 1', 'station_type' => 'MATCHPLATE_MACHINE', 'status' => 'ACTIVE']);

        return compact('product', 'rev', 'pr', 'order', 'team', 'st');
    }

    private function item(array $w, array $over = []): array
    {
        return $over + ['moulding_team_id' => $w['team']->id, 'moulding_station_id' => $w['st']->id, 'production_order_id' => $w['order']->id,
            'pattern_revision_id' => $w['pr']->id, 'sand_system' => 'Sheera', 'planned_moulds' => 30];
    }

    public function test_teams_and_stations_are_managed(): void
    {
        $this->get(route('moulding-teams.index'))->assertOk();
        $this->post(route('moulding-teams.teams.store'), ['code' => 't2', 'name' => 'Team Beta', 'lead_name' => 'Imtiaz', 'member_count' => 4, 'status' => 'ACTIVE'])->assertSessionHasNoErrors();
        $this->assertSame('T2', MouldingTeam::where('lead_name', 'Imtiaz')->value('code'));
        $this->post(route('moulding-teams.teams.store'), ['code' => 'T2', 'name' => 'x', 'lead_name' => 'y', 'member_count' => 1, 'status' => 'ACTIVE'])->assertSessionHasErrors('code');
        $this->post(route('moulding-teams.stations.store'), ['code' => 'FL-1', 'name' => 'Floor 1', 'station_type' => 'FLOOR', 'status' => 'ACTIVE'])->assertSessionHasNoErrors();
        $this->get(route('moulding-teams.index'))->assertOk()->assertSee('Imtiaz')->assertSee('Floor 1');
    }

    public function test_a_run_can_be_held_resumed_and_cancelled(): void
    {
        $w = $this->world();
        $this->post(route('work-lists.store'), ['list_date' => today()->toDateString(), 'shift' => 'Morning']);
        $list = MouldingWorkList::firstOrFail();
        $this->post(route('work-lists.items.add', $list), $this->item($w));
        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Planner']);
        $item = $list->items()->firstOrFail();
        $this->post(route('work-lists.items.start', [$list, $item]));
        $run = MouldingRun::firstOrFail();

        $this->get(route('moulding-runs.show', $run))->assertSee('Put on hold');
        $this->post(route('moulding-runs.status', $run), ['status' => 'ON_HOLD'])->assertSessionHasNoErrors();
        $this->assertSame('ON_HOLD', $run->fresh()->status);
        $this->get(route('moulding-runs.show', $run))->assertSee('Resume run');
        $this->post(route('moulding-runs.status', $run), ['status' => 'IN_PROGRESS'])->assertSessionHasNoErrors();
        $this->post(route('moulding-runs.status', $run), ['status' => 'CANCELLED'])->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $run->fresh()->status);
        $this->assertSame('IN_PROGRESS', $item->fresh()->status);                                                         // work list untouched without permission
        $this->assertSame('ISSUED', $list->fresh()->status);
        $this->post(route('moulding-runs.status', $run), ['status' => 'IN_PROGRESS'])->assertSessionHasErrors('output');
    }

    public function test_cancelling_a_run_can_also_skip_its_work_list_item_when_asked(): void
    {
        $w = $this->world();
        $this->post(route('work-lists.store'), ['list_date' => today()->toDateString(), 'shift' => 'Morning']);
        $list = MouldingWorkList::firstOrFail();
        $this->post(route('work-lists.items.add', $list), $this->item($w));
        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Planner']);
        $item = $list->items()->firstOrFail();
        $this->post(route('work-lists.items.start', [$list, $item]));
        $run = MouldingRun::firstOrFail();

        $this->post(route('moulding-runs.status', $run), ['status' => 'CANCELLED', 'skip_work_list_item' => 1])->assertSessionHasNoErrors();
        $this->assertSame('SKIPPED', $item->fresh()->status);
        $this->assertSame('COMPLETED', $list->fresh()->status);
    }

    public function test_list_to_run_to_completion(): void
    {
        $w = $this->world();
        $this->post(route('work-lists.store'), ['list_date' => today()->toDateString(), 'shift' => 'Morning'])->assertSessionHasNoErrors();
        $list = MouldingWorkList::firstOrFail();
        $this->assertStringStartsWith('WL-', $list->list_number);

        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Planner'])->assertSessionHasErrors('item');       // empty list
        $this->post(route('work-lists.items.add', $list), $this->item($w))->assertSessionHasNoErrors();
        $this->post(route('work-lists.items.add', $list), $this->item($w))->assertSessionHasErrors('item');              // duplicate
        $item = $list->items()->firstOrFail();
        $this->post(route('work-lists.items.start', [$list, $item]))->assertSessionHasErrors('item');                     // not issued
        $this->get(route('work-lists.show', $list))->assertOk()->assertSee('Rashid')->assertSee('Sheera');

        $this->post(route('work-lists.issue', $list), ['issued_by' => 'Planner'])->assertSessionHasNoErrors();
        $this->get(route('work-lists.print', $list))->assertOk()->assertSee('Rashid')->assertSee('MP-7');

        $this->post(route('work-lists.items.start', [$list, $item]))->assertRedirect();
        $run = MouldingRun::firstOrFail();
        $this->assertSame('Rashid', $run->operator_name);
        $this->assertSame($w['st']->id, $run->moulding_station_id);
        $this->assertSame(30, (int) $run->planned_mould_qty);
        $this->assertSame('IN_PROGRESS', $item->fresh()->status);
        $this->assertSame('IN_PROGRESS', $w['order']->fresh()->status);                                                   // starting a run starts the order
        $this->post(route('work-lists.items.start', [$list, $item]))->assertSessionHasErrors('item');                     // twice
        $this->get(route('moulding-runs.show', $run))->assertOk()->assertSee('Record output')->assertSee('MP-1');

        $this->post(route('moulding-runs.output', $run), ['moulds_made_qty' => 10, 'accepted_mould_qty' => 9, 'damaged_mould_qty' => 2])->assertSessionHasErrors('output');
        $this->post(route('moulding-runs.output', $run), ['moulds_made_qty' => 32, 'accepted_mould_qty' => 30, 'damaged_mould_qty' => 2, 'complete' => 1])->assertSessionHasNoErrors();
        $this->assertSame('COMPLETED', $run->fresh()->status);
        $this->assertSame('DONE', $item->fresh()->status);
        $this->assertSame('COMPLETED', $list->fresh()->status);
        $this->assertSame('IN_PROGRESS', $w['order']->fresh()->status);                                                   // moulds are not castings
        $this->post(route('moulding-runs.output', $run), ['moulds_made_qty' => 1, 'accepted_mould_qty' => 1, 'damaged_mould_qty' => 0])->assertSessionHasErrors('output');

        $this->get(route('moulding-runs.show', $run))->assertSee('Reopen run');
        $this->post(route('moulding-runs.reopen', $run))->assertSessionHasNoErrors();
        $this->assertSame('IN_PROGRESS', $run->fresh()->status);
        $this->assertNull($run->fresh()->completed_at);
        $this->assertSame('IN_PROGRESS', $item->fresh()->status);
        $this->assertSame('ISSUED', $list->fresh()->status);
        $this->post(route('moulding-runs.reopen', $run))->assertSessionHasErrors('output');                               // not completed any more
        $this->post(route('moulding-runs.output', $run), ['moulds_made_qty' => 32, 'accepted_mould_qty' => 30, 'damaged_mould_qty' => 2, 'complete' => 1])->assertSessionHasNoErrors();

        $this->get(route('moulding-teams.index'))->assertOk()->assertSee('Team Alpha');
        $this->get(route('mould-readiness.index'))->assertOk()->assertSee('30');                                          // 30 accepted, none assembled yet
    }

    public function test_bad_assignments_are_refused(): void
    {
        $w = $this->world();
        $list = app(\App\Services\Production\WorkListService::class)->create(today()->toDateString(), 'Night');
        $w['st']->update(['status' => 'MAINTENANCE']);
        $this->post(route('work-lists.items.add', $list), $this->item($w))->assertSessionHasErrors('item');
        $w['st']->update(['status' => 'ACTIVE']);
        $w['order']->update(['status' => 'PLANNED']);
        $this->post(route('work-lists.items.add', $list), $this->item($w))->assertSessionHasErrors('item');
        $w['order']->update(['status' => 'RELEASED']);
        $other = Pattern::create(['code' => 'LP-1', 'name' => 'Other', 'pattern_type' => 'LOOSE']);
        $or = PatternRevision::create(['pattern_id' => $other->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 1]);
        $x = Product::create(['code' => 'OTHER-1', 'name' => 'Other product']);
        $xr = ProductRevision::create(['product_id' => $x->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $or->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $xr->id, 'casting_weight_kg' => 3, 'poured_weight_kg' => 5]);
        $this->post(route('work-lists.items.add', $list), $this->item($w, ['pattern_revision_id' => $or->id]))->assertSessionHasErrors('item');   // pattern does not make the order's product
        $this->assertSame(0, $list->items()->count());
        $this->post(route('work-lists.items.add', $list), $this->item($w))->assertSessionHasNoErrors();
        $this->assertSame(1, $list->items()->count());
    }
}
