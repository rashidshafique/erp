<?php

namespace Tests\Feature;

use App\Models\MouldAssembly;
use App\Models\MouldConfiguration;
use App\Models\MouldCount;
use App\Models\MouldingRun;
use App\Services\Production\MouldAssembler;
use App\Services\Production\WorkListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkLists;
use Tests\TestCase;

/** Counting moulds before a pour: what the records say waits per pattern, and the shortfall written off so the orders need it again. */
class MouldCountTest extends TestCase
{
    use BuildsWorkLists;
    use RefreshDatabase;

    public function test_a_short_count_is_written_off_unassembled_first_then_assembled_and_the_order_needs_the_moulds_again(): void
    {
        $w = $this->world();                                                                   // 50 moulds needed
        $run = MouldingRun::create(['run_number' => 'MR-1', 'production_order_id' => $w['order']->id, 'pattern_revision_id' => $w['pr']->id, 'run_date' => today(),
            'status' => 'COMPLETED', 'moulds_made_qty' => 10, 'accepted_mould_qty' => 10]);
        $cfg = MouldConfiguration::create(['code' => 'MC-1', 'name' => 'Cope and drag', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);
        app(MouldAssembler::class)->assemble($run, 6, $cfg->id, 2);
        $this->assertSame(40, app(WorkListService::class)->mouldsStillNeeded($w['order'], $w['pr']->id));

        $this->get(route('mould-count.index'))->assertOk()->assertSee('MP-7')->assertSee('4 not assembled');
        $this->post(route('mould-count.store'), ['counted' => [$w['pr']->id => 3]])->assertSessionHasErrors('reason');
        $this->post(route('mould-count.store'), ['counted' => [$w['pr']->id => 3], 'reason' => 'BROKEN', 'note' => 'crane'])->assertSessionHasNoErrors();

        $run->refresh();
        $this->assertSame(3, $run->accepted_mould_qty);
        $this->assertSame(7, $run->damaged_mould_qty);
        $this->assertSame(3, MouldAssembly::where('status', 'SCRAPPED')->count(), 'the 4 unassembled go first, then 3 assembled');
        $this->assertSame(3, MouldAssembly::where('status', 'PREPARED')->count());
        $this->assertSame(47, app(WorkListService::class)->mouldsStillNeeded($w['order'], $w['pr']->id), 'written-off moulds are needed again');
        $this->assertSame([10, 3, 7, 'BROKEN'], array_values(MouldCount::sole()->only(['expected', 'counted', 'written_off', 'reason'])));
        $this->get(route('mould-count.index'))->assertSee('Broken or collapsed: crane');
    }
}
