<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class TodayBoardTest extends TestCase
{
    use BuildsCastingLots;
    use RefreshDatabase;

    public function test_the_board_lists_the_days_work_with_the_step_for_each(): void
    {
        $lot = $this->castingLot();

        $this->get(route('quick.today'))->assertOk()
            ->assertSeeInOrder(['Orders due', 'Moulding list', 'Count moulds', 'Heat', 'Moulds waiting to pour', 'After pour', 'Castings to check', 'Ready to go out'])
            ->assertSee($lot->productionOrder->production_order_number)
            ->assertSee('5 pieces in 1 lot')
            ->assertSee(route('quick-qc.index'), false)
            ->assertSee(route('quick.list.index'), false)
            ->assertSee(route('mould-count.index'), false)
            ->assertSee(route('after-pour.index'), false);
    }

    public function test_packing_and_dispatch_leave_the_board_with_their_module(): void
    {
        config(['modules.licensed' => ['quickflow', 'sales']]);

        $this->get(route('quick.today'))->assertOk()->assertDontSee('Ready to go out');
    }
}
