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

    public function test_the_ready_to_pack_pile_starts_collapsed_and_shows_the_oldest_fifteen_first(): void
    {
        foreach (range(1, 16) as $n) {
            $this->castingLot()->update(['status' => 'READY']);
        }

        $this->get(route('lots.queue'))->assertOk()->assertSee('Show lots')->assertSee('Show all 16 lots')->assertSee('Oldest 15 shown.');
    }
}
