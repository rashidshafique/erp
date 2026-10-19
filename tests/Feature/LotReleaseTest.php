<?php

namespace Tests\Feature;

use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class LotReleaseTest extends TestCase
{
    use BuildsCastingLots;
    use RefreshDatabase;

    public function test_a_lot_is_held_with_a_reason_and_released_from_hold(): void
    {
        $lot = $this->castingLot();

        $this->post(route('lots.hold', $lot), [])->assertSessionHasErrors('reason');
        $this->post(route('lots.hold', $lot), ['reason' => 'Suspect sand'])->assertSessionHasNoErrors();
        $this->assertSame('ON_HOLD', $lot->fresh()->status);
        $this->assertStringContainsString('hold: Suspect sand', $lot->fresh()->notes);

        $this->post(route('lots.release', $lot))->assertSessionHasNoErrors();
        $this->assertSame('IN_PRODUCTION', $lot->fresh()->status);
        $this->assertStringContainsString('released from hold', $lot->fresh()->notes);
    }

    public function test_only_a_lot_on_hold_can_be_released_and_finished_lots_cannot_be_held(): void
    {
        $lot = $this->castingLot();

        $this->post(route('lots.release', $lot))->assertSessionHasErrors('hold');

        foreach (['DISPATCHED', 'CLOSED', 'SCRAPPED'] as $status) {
            $lot->update(['status' => $status]);
            $this->post(route('lots.hold', $lot), ['reason' => 'Too late'])->assertSessionHasErrors('hold');
            $this->assertSame($status, $lot->fresh()->status);
        }
    }

    public function test_releasing_a_hold_waits_for_approval_when_a_policy_requires_it(): void
    {
        $lot = $this->castingLot();
        $lot->update(['status' => 'ON_HOLD']);
        ApprovalPolicy::updateOrCreate(['action_key' => 'lot.release_hold'], ['is_active' => true, 'required_approvals' => 1, 'permission' => 'approvals.decide', 'allow_self_approval' => false]);

        $this->post(route('lots.release', $lot))->assertSessionHasErrors('hold');

        $this->assertSame('ON_HOLD', $lot->fresh()->status);
        $this->assertSame(1, ApprovalRequest::where('action_key', 'lot.release_hold')->where('status', 'PENDING')->count());
    }

    public function test_the_new_inspection_form_opens_for_a_lot(): void
    {
        $lot = $this->castingLot();

        $this->get(route('lots.inspections.create', $lot))->assertOk()->assertSee($lot->lot_number);
    }
}
