<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\User;
use App\Services\Production\WorkListService;
use App\Support\WorkListRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The planner's "Waiting for your decision" block on the home page (design A1). */
class PlannerWaitingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $u = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $u->roles()->attach(Role::where('code', $role)->value('id'));

        return $u;
    }

    private function openOrder(): void
    {
        $p = Product::create(['code' => 'P-1', 'name' => 'Part']);
        $r = ProductRevision::create(['product_id' => $p->id, 'revision' => 'A', 'status' => 'APPROVED']);
        ProductionOrder::create(['production_order_number' => 'PO-W1', 'product_id' => $p->id, 'product_revision_id' => $r->id, 'status' => 'RELEASED', 'planned_qty' => 5, 'order_type' => 'STOCK']);
    }

    public function test_the_planner_is_told_to_prepare_the_next_list_and_then_to_issue_it(): void
    {
        config(['foundry.require_login' => true]);
        WorkListRules::set('auto_prepare', 'NEXT_DAY');
        $planner = $this->user('PRODUCTION_MANAGER');

        $this->actingAs($planner)->get(route('home'))->assertOk()->assertSee('Waiting for your decision')->assertSee('Nothing is waiting for a decision.');

        $this->openOrder();
        $this->actingAs($planner)->get(route('home'))->assertSee('moulding list not prepared')->assertSee('Prepare list');

        $list = app(WorkListService::class)->create(today()->addDay()->toDateString(), 'General');
        $this->actingAs($planner)->get(route('home'))->assertDontSee('not prepared')->assertSee('moulding list not issued')->assertSee($list->list_number);

        $list->update(['status' => 'ISSUED']);
        $this->actingAs($planner)->get(route('home'))->assertDontSee('not issued')->assertSee('Nothing is waiting for a decision.');
    }

    public function test_a_role_without_planning_does_not_get_the_block(): void
    {
        config(['foundry.require_login' => true]);
        $this->openOrder();

        $this->actingAs($this->user('SALES'))->get(route('home'))->assertOk()->assertDontSee('Waiting for your decision');
    }
}
