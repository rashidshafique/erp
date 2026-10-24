<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\CastingLotStageEvent;
use App\Models\MouldAssembly;
use App\Models\Pour;
use App\Models\PourMouldAssembly;
use App\Models\ProductionStageDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class ProductionQueueTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    private function pouredHoursAgo(CastingLot $lot, int $hours): void
    {
        Pour::whereKey($lot->pourMouldAssembly->pour_id)->update(['completed_at' => now()->subHours($hours)]);
    }

    private function passStage(CastingLot $lot, string $code): void
    {
        CastingLotStageEvent::create(['casting_lot_id' => $lot->id, 'stage_definition_id' => ProductionStageDefinition::where('code', $code)->value('id'), 'event_type' => 'COMPLETED', 'status' => 'COMPLETED', 'quantity_in' => 5, 'quantity_accepted' => 5, 'quantity_rejected' => 0, 'quantity_out' => 5, 'completed_at' => now()]);
    }

    /** @return array<string, array<string, mixed>> */
    private function steps(): array
    {
        return collect($this->get(route('lots.queue'))->assertOk()->viewData('steps'))->keyBy('key')->all();
    }

    public function test_every_step_is_listed_in_process_order_with_its_lots_pieces_and_oldest_age(): void
    {
        $waiting = $this->castingLot();
        $this->pouredHoursAgo($waiting, 3);
        $old = $this->castingLot();
        $this->pouredHoursAgo($old, 50);
        $this->passStage($old, 'SHAKEOUT');
        $recent = $this->castingLot();
        $this->pouredHoursAgo($recent, 30);
        $this->passStage($recent, 'SHAKEOUT');
        $ready = $this->castingLot();
        $ready->update(['status' => 'READY']);

        $steps = $this->steps();
        $keys = array_keys($steps);

        $this->assertSame('waiting', $keys[0]);
        $this->assertSame('ready', end($keys));
        $this->assertLessThan(array_search('after-SHOT_BLAST', $keys), array_search('after-SHAKEOUT', $keys));
        $this->assertSame([1, 5, 3, 'green'], [$steps['waiting']['lot_count'], $steps['waiting']['pieces'], $steps['waiting']['oldest_h'], $steps['waiting']['tone']]);
        $this->assertSame([2, 10, 50, 'red'], [$steps['after-SHAKEOUT']['lot_count'], $steps['after-SHAKEOUT']['pieces'], $steps['after-SHAKEOUT']['oldest_h'], $steps['after-SHAKEOUT']['tone']]);
        $this->assertSame($old->id, $steps['after-SHAKEOUT']['lots'][0]['lot']->id, 'oldest first');
        $this->assertSame([0, 'empty'], [$steps['after-SHOT_BLAST']['lot_count'], $steps['after-SHOT_BLAST']['tone']], 'a step with no work still shows, dimmed');
        $this->assertSame(1, $steps['ready']['lot_count']);
    }

    public function test_the_age_limits_and_step_limits_come_from_the_settings(): void
    {
        $lot = $this->castingLot();
        $this->pouredHoursAgo($lot, 10);
        $this->castingLot();
        $this->assertSame('green', $this->steps()['waiting']['tone'], '10 h is under the default 24 h');

        $this->put(route('settings.queue'), ['amber_hours' => 8, 'red_hours' => 12, 'wip_unit' => 'LOTS'])->assertSessionHasNoErrors();
        $this->assertSame('amber', $this->steps()['waiting']['tone']);

        $this->put(route('settings.queue'), ['amber_hours' => 8, 'red_hours' => 12, 'wip_unit' => 'LOTS', 'wip_waiting' => 1])->assertSessionHasNoErrors();
        $waiting = $this->steps()['waiting'];
        $this->assertSame([1, true, 'red'], [$waiting['limit'], $waiting['over_limit'], $waiting['tone']], 'two lots against a limit of one');
        $this->get(route('lots.queue'))->assertSee('over limit of 1 lots');

        $this->put(route('settings.queue'), ['amber_hours' => 8, 'red_hours' => 12, 'wip_unit' => 'PIECES', 'wip_waiting' => 10])->assertSessionHasNoErrors();
        $this->assertFalse($this->steps()['waiting']['over_limit'], 'ten pieces is within a limit of ten');
    }

    public function test_the_rules_page_lists_a_limit_for_each_step_and_red_must_come_after_amber(): void
    {
        $this->get(route('settings.queue.edit'))->assertOk()->assertSee('Limit: Waiting for shakeout')->assertSee('Limit: After Shakeout')->assertSee('Limit: Ready to pack');

        $this->put(route('settings.queue'), ['amber_hours' => 30, 'red_hours' => 20, 'wip_unit' => 'LOTS'])->assertSessionHasErrors('red_hours');
    }

    public function test_a_lot_from_several_moulds_is_one_row_that_expands_to_its_moulds(): void
    {
        $grouped = $this->castingLot();
        $first = $grouped->pourMouldAssembly;
        $second = PourMouldAssembly::create(['pour_id' => $first->pour_id, 'production_order_id' => $grouped->production_order_id, 'castings_poured_qty' => 3,
            'mould_assembly_id' => MouldAssembly::create(['assembly_number' => 'MA-SECOND', 'mould_configuration_id' => $first->mouldAssembly->mould_configuration_id, 'moulding_run_id' => $first->mouldAssembly->moulding_run_id, 'status' => 'POURED'])->id]);
        $grouped->pourMouldAssemblies()->attach([$first->id => ['quantity' => 2], $second->id => ['quantity' => 3]]);
        $single = $this->castingLot();
        $single->pourMouldAssemblies()->attach($single->pour_mould_assembly_id, ['quantity' => 5]);

        $page = $this->get(route('lots.queue'))->assertOk();
        $page->assertSee('2 moulds')->assertSee($first->mouldAssembly->assembly_number)->assertSee('MA-SECOND')->assertSee('3 pieces from this mould')->assertSee('Expand all');
        $this->assertSame(1, substr_count($page->getContent(), 'class="qmould-toggle"'), 'only the lot from several moulds can be expanded');
    }

    public function test_the_board_shows_a_column_per_step_and_a_card_per_lot_that_can_be_ticked(): void
    {
        $lot = $this->castingLot();
        $held = $this->castingLot();
        $held->update(['status' => 'ON_HOLD']);

        $page = $this->get(route('lots.queue', ['view' => 'board']))->assertOk()->assertViewHas('view', 'board');
        $html = $page->getContent();

        $this->assertSame(count($page->viewData('steps')), substr_count($html, '<section class="qcol'), 'one column per step, empty ones included');
        $this->assertSame(2, substr_count($html, '<article class="qcard"'));
        $page->assertSee('value="'.$lot->id.'"', false)->assertDontSee('value="'.$held->id.'"', false)->assertSee('on hold')->assertSee('Move ticked lots');
        $this->get(route('lots.queue'))->assertOk()->assertDontSee('<section class="qcol', false);
    }

    public function test_a_lot_tick_box_carries_what_the_move_bar_needs(): void
    {
        $lot = $this->castingLot();
        $this->passStage($lot, 'SHAKEOUT');

        $seq = ProductionStageDefinition::where('code', 'SHAKEOUT')->value('sequence');
        $this->get(route('lots.queue'))->assertOk()
            ->assertSee('data-seq="'.$seq.'"', false)->assertSee('data-code="', false)->assertSee('data-pieces="5"', false)->assertSee('Move all 1 →');
    }

    public function test_after_a_refused_move_the_same_lots_stay_ticked(): void
    {
        $lot = $this->castingLot();
        $stage = ProductionStageDefinition::where('is_active', true)->orderBy('sequence')->first();

        $this->from(route('lots.queue'))->post(route('lots.bulk-stage'), ['lots' => [$lot->id], 'stages' => [$stage->id], 'rejected' => 2])->assertSessionHasErrors('rejection_reason');

        $this->get(route('lots.queue'))->assertOk()->assertSee('value="'.$lot->id.'" class="queue-lot"', false)->assertSee('checked', false);
    }

    public function test_the_ready_to_pack_pile_starts_collapsed_and_shows_the_oldest_fifteen_first(): void
    {
        foreach (range(1, 16) as $n) {
            $this->castingLot()->update(['status' => 'READY']);
        }

        $this->get(route('lots.queue'))->assertOk()->assertSee('Show lots')->assertSee('Show all 16 lots')->assertSee('Oldest 15 shown.');
    }
}
