<?php

namespace Tests\Feature;

use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $u = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $u->roles()->attach(Role::where('code', $role)->value('id'));

        return $u;
    }

    private function order(): ProductionOrder
    {
        $p = Product::create(['code' => 'P-1', 'name' => 'Part']);
        $r = ProductRevision::create(['product_id' => $p->id, 'revision' => 'A', 'status' => 'APPROVED']);

        return ProductionOrder::create(['production_order_number' => 'PO-A1', 'product_id' => $p->id, 'product_revision_id' => $r->id, 'status' => 'DRAFT', 'planned_qty' => 5, 'order_type' => 'STOCK']);
    }

    private function policy(int $n = 1, bool $self = false): void
    {
        ApprovalPolicy::create(['action_key' => 'production_order.release', 'is_active' => true, 'required_approvals' => $n, 'permission' => 'approvals.decide', 'allow_self_approval' => $self]);
    }

    public function test_no_policy_means_no_gate(): void
    {
        $o = $this->order();
        $this->post(route('production-orders.release', $o))->assertSessionHasNoErrors();
        $this->assertSame('RELEASED', $o->fresh()->status);
        $this->assertSame(0, ApprovalRequest::count());
    }

    public function test_request_approve_then_execute_once(): void
    {
        $this->policy();
        $o = $this->order();
        $maker = $this->user('PRODUCTION_MANAGER');
        $checker = $this->user('QUALITY_MANAGER');

        $this->actingAs($maker)->post(route('production-orders.release', $o))->assertSessionHasErrors('release');
        $this->assertSame('DRAFT', $o->fresh()->status);
        $req = ApprovalRequest::firstOrFail();
        $this->actingAs($maker)->post(route('production-orders.release', $o));      // no duplicate request
        $this->assertSame(1, ApprovalRequest::count());

        // requester cannot approve own request
        $this->actingAs($maker)->post(route('approvals.decide', $req), ['decision' => 'APPROVE'])->assertSessionHasErrors('approval');
        $this->assertSame('PENDING', $req->fresh()->status);

        $this->actingAs($checker)->post(route('approvals.decide', $req), ['decision' => 'APPROVE'])->assertSessionHasNoErrors();
        $this->assertSame('APPROVED', $req->fresh()->status);

        $this->actingAs($maker)->post(route('production-orders.release', $o))->assertSessionHasNoErrors();
        $this->assertSame('RELEASED', $o->fresh()->status);
        $this->assertSame('USED', $req->fresh()->status);
    }

    public function test_two_approvals_needed_and_no_double_vote(): void
    {
        $this->policy(2);
        $o = $this->order();
        $this->actingAs($this->user('SALES'))->post(route('production-orders.release', $o));
        $req = ApprovalRequest::firstOrFail();
        $a = $this->user('QUALITY_MANAGER');
        $b = $this->user('PRODUCTION_MANAGER');

        $this->actingAs($a)->post(route('approvals.decide', $req), ['decision' => 'APPROVE']);
        $this->actingAs($a)->post(route('approvals.decide', $req), ['decision' => 'APPROVE'])->assertSessionHasErrors('approval');
        $this->assertSame('PENDING', $req->fresh()->status);
        $this->actingAs($b)->post(route('approvals.decide', $req), ['decision' => 'APPROVE']);
        $this->assertSame('APPROVED', $req->fresh()->status);
    }

    public function test_rejection_needs_reason_and_blocks_then_new_attempt_raises_new_request(): void
    {
        $this->policy();
        $o = $this->order();
        $this->post(route('production-orders.release', $o));
        $req = ApprovalRequest::firstOrFail();
        $checker = $this->user('QUALITY_MANAGER');
        $this->actingAs($checker)->post(route('approvals.decide', $req), ['decision' => 'REJECT'])->assertSessionHasErrors('approval');
        $this->actingAs($checker)->post(route('approvals.decide', $req), ['decision' => 'REJECT', 'comment' => 'Contract not reviewed']);
        $this->assertSame('REJECTED', $req->fresh()->status);
        $this->post(route('production-orders.release', $o))->assertSessionHasErrors('release');
        $this->assertSame(2, ApprovalRequest::count());
    }

    public function test_role_without_permission_cannot_decide_and_policy_screen_saves(): void
    {
        config(['foundry.require_login' => true]);
        $this->policy();
        $o = $this->order();
        $maker = $this->user('PRODUCTION_MANAGER');
        $this->actingAs($maker)->post(route('production-orders.release', $o));
        $req = ApprovalRequest::firstOrFail();

        $this->actingAs($this->user('SALES'))->post(route('approvals.decide', $req), ['decision' => 'APPROVE'])->assertForbidden();

        $admin = $this->user('ADMIN');
        $this->actingAs($admin)->put(route('approval-policies.update'), ['p' => ['ncr.close' => ['is_active' => 1, 'required_approvals' => 2, 'permission' => 'approvals.decide']]])->assertSessionHasNoErrors();
        $this->assertTrue(ApprovalPolicy::where('action_key', 'ncr.close')->value('is_active'));
        $this->assertFalse(ApprovalPolicy::where('action_key', 'production_order.release')->value('is_active'));   // omitted = off
        $this->actingAs($admin)->get(route('approvals.index'))->assertOk();
        $this->actingAs($admin)->get(route('approval-policies.index'))->assertOk();
    }

    public function test_limit_changes_can_need_approval_and_the_dashboard_shows_waiting_requests(): void
    {
        ApprovalPolicy::create(['action_key' => 'limits.change', 'is_active' => true, 'required_approvals' => 1, 'permission' => 'approvals.decide', 'allow_self_approval' => false]);
        $grade = \App\Models\IronGrade::create(['code' => 'GG-20', 'name' => 'Grey 20', 'family' => 'GREY']);
        $limit = \App\Models\CharacteristicLimit::create(['subject_type' => 'IRON_GRADE', 'subject_id' => $grade->id, 'test_group' => 'MECHANICAL', 'characteristic_key' => 'uts', 'name' => 'UTS', 'min_value' => 200, 'max_value' => 300, 'unit' => 'MPa']);

        $maker = $this->user('QUALITY_INSPECTOR');
        $this->actingAs($maker)->put(route('limits.update', $limit), ['min_value' => 150, 'max_value' => 300])->assertSessionHasErrors('limit');
        $this->assertEquals(200, $limit->fresh()->min_value);                    // unchanged until approved
        $this->actingAs($maker)->get(route('dashboard'))->assertOk();

        $req = ApprovalRequest::firstOrFail();
        $this->actingAs($this->user('QUALITY_MANAGER'))->get(route('dashboard'))->assertSee('waiting for a decision');
        $this->actingAs($this->user('QUALITY_MANAGER'))->post(route('approvals.decide', $req), ['decision' => 'APPROVE']);
        $this->actingAs($maker)->get(route('dashboard'))->assertSee('approved');

        $this->actingAs($maker)->put(route('limits.update', $limit), ['min_value' => 150, 'max_value' => 300])->assertSessionHasNoErrors();
        $this->assertEquals(150, $limit->fresh()->min_value);
    }
}
