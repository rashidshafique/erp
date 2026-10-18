<?php

namespace Tests\Feature;

use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\CapaAction;
use App\Models\NonconformanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class CapaRegisterTest extends TestCase
{
    use BuildsCastingLots;
    use RefreshDatabase;

    private function capa(array $extra = []): CapaAction
    {
        return CapaAction::create($extra + ['source_type' => 'COMPLAINT', 'title' => 'Blowholes on delivery 41', 'severity' => 'MAJOR', 'status' => 'OPEN']);
    }

    private function planned(): CapaAction
    {
        $capa = $this->capa();
        $this->put(route('capa.update', $capa), $this->plan())->assertSessionHasNoErrors();

        return $capa->fresh();
    }

    private function plan(array $extra = []): array
    {
        return $extra + ['title' => 'Blowholes on delivery 41', 'severity' => 'MAJOR', 'root_cause' => 'Wet sand from the second mixer', 'corrective_action' => 'Moisture check every hour', 'owner' => 'Imran', 'due_on' => today()->addDays(14)->toDateString()];
    }

    public function test_an_action_raised_by_hand_gets_a_code_and_starts_open(): void
    {
        $this->post(route('capa.store'), ['source_type' => 'AUDIT', 'source_label' => 'Finding 3', 'title' => 'Gauge not calibrated', 'severity' => 'MINOR'])->assertSessionHasNoErrors();

        $capa = CapaAction::firstOrFail();
        $this->assertStringStartsWith('CPA', $capa->code);
        $this->assertSame('OPEN', $capa->status);
        $this->get(route('capa.show', $capa))->assertOk()->assertSee('Gauge not calibrated')->assertSee('Finding 3');
    }

    public function test_it_moves_to_in_progress_only_when_the_plan_is_complete(): void
    {
        $capa = $this->capa();

        $this->put(route('capa.update', $capa), $this->plan(['owner' => '']))->assertSessionHasNoErrors();
        $this->assertSame('OPEN', $capa->fresh()->status);

        $this->put(route('capa.update', $capa), $this->plan())->assertSessionHasNoErrors();
        $this->assertSame('IN_PROGRESS', $capa->fresh()->status);
    }

    public function test_it_cannot_be_marked_done_before_the_plan_is_complete(): void
    {
        $capa = $this->capa();

        $this->post(route('capa.complete', $capa), ['completed_on' => today()->toDateString(), 'verification_due_on' => today()->addDays(30)->toDateString()])->assertSessionHasErrors('capa');

        $this->assertSame('OPEN', $capa->fresh()->status);
    }

    public function test_marking_it_done_sets_the_effectiveness_check_date(): void
    {
        $capa = $this->planned();

        $this->post(route('capa.complete', $capa), ['completed_on' => today()->toDateString(), 'verification_due_on' => today()->addDays(45)->toDateString(), 'implementation_notes' => 'Moisture meter fitted'])->assertSessionHasNoErrors();

        $capa->refresh();
        $this->assertSame(['AWAITING_VERIFICATION', today()->addDays(45)->toDateString()], [$capa->status, $capa->verification_due_on->toDateString()]);
        $this->post(route('capa.complete', $capa), ['completed_on' => today()->toDateString()])->assertSessionHasErrors('capa');
    }

    public function test_the_check_date_cannot_be_before_the_action_was_done(): void
    {
        $capa = $this->planned();

        $this->post(route('capa.complete', $capa), ['completed_on' => today()->toDateString(), 'verification_due_on' => today()->subDay()->toDateString()])->assertSessionHasErrors('verification_due_on');
    }

    private function awaitingCheck(): CapaAction
    {
        $capa = $this->planned();
        $this->post(route('capa.complete', $capa), ['completed_on' => today()->toDateString(), 'verification_due_on' => today()->addDays(30)->toDateString()]);

        return $capa->fresh();
    }

    public function test_an_effective_check_closes_it(): void
    {
        $capa = $this->awaitingCheck();

        $this->post(route('capa.verify', $capa), ['verification_result' => 'EFFECTIVE', 'verified_by' => 'Bilal', 'verified_on' => today()->toDateString(), 'verification_notes' => 'No blowholes in the last 6 lots'])->assertSessionHasNoErrors();

        $capa->refresh();
        $this->assertSame(['CLOSED', 'EFFECTIVE', 'Bilal'], [$capa->status, $capa->verification_result, $capa->verified_by]);
        $this->assertNotNull($capa->closed_at);
        $this->put(route('capa.update', $capa), $this->plan())->assertSessionHasErrors('capa');
    }

    public function test_a_check_that_finds_it_did_not_work_sends_it_back_and_keeps_the_record(): void
    {
        $capa = $this->awaitingCheck();

        $this->post(route('capa.verify', $capa), ['verification_result' => 'NOT_EFFECTIVE', 'verified_by' => 'Bilal', 'verified_on' => today()->toDateString()])->assertSessionHasErrors('verification_notes');

        $this->post(route('capa.verify', $capa), ['verification_result' => 'NOT_EFFECTIVE', 'verified_by' => 'Bilal', 'verified_on' => today()->toDateString(), 'verification_notes' => 'Two more blowholes in lot 52'])->assertSessionHas('warning');

        $capa->refresh();
        $this->assertSame(['IN_PROGRESS', null], [$capa->status, $capa->completed_on]);
        $this->assertStringContainsString('NOT EFFECTIVE', $capa->verification_log);
        $this->assertStringContainsString('Two more blowholes in lot 52', $capa->verification_log);
        $this->get(route('capa.show', $capa))->assertSee('Earlier effectiveness checks');
    }

    public function test_only_an_action_awaiting_its_check_can_be_verified(): void
    {
        $capa = $this->planned();

        $this->post(route('capa.verify', $capa), ['verification_result' => 'EFFECTIVE', 'verified_by' => 'Bilal', 'verified_on' => today()->toDateString()])->assertSessionHasErrors('capa');

        $this->assertSame('IN_PROGRESS', $capa->fresh()->status);
    }

    public function test_closing_goes_through_the_approval_policy_when_it_is_switched_on(): void
    {
        ApprovalPolicy::create(['action_key' => 'capa.close', 'is_active' => true, 'permission' => 'approvals.decide', 'required_approvals' => 1, 'allow_self_approval' => false]);
        $capa = $this->awaitingCheck();
        $check = ['verification_result' => 'EFFECTIVE', 'verified_by' => 'Bilal', 'verified_on' => today()->toDateString()];

        $this->post(route('capa.verify', $capa), $check)->assertSessionHasErrors('capa');
        $this->assertSame('AWAITING_VERIFICATION', $capa->fresh()->status);

        ApprovalRequest::where('action_key', 'capa.close')->firstOrFail()->update(['status' => 'APPROVED']);
        $this->post(route('capa.verify', $capa), $check)->assertSessionHasNoErrors();
        $this->assertSame('CLOSED', $capa->fresh()->status);
    }

    public function test_cancelling_needs_a_reason_and_closed_actions_cannot_be_cancelled(): void
    {
        $capa = $this->capa();

        $this->post(route('capa.cancel', $capa), [])->assertSessionHasErrors('cancel_reason');
        $this->post(route('capa.cancel', $capa), ['cancel_reason' => 'Raised twice'])->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $capa->fresh()->status);
        $this->post(route('capa.cancel', $capa), ['cancel_reason' => 'Again'])->assertSessionHasErrors('capa');
    }

    public function test_overdue_actions_are_counted_and_listed_first(): void
    {
        $this->capa(['title' => 'Late one', 'status' => 'IN_PROGRESS', 'due_on' => today()->subDays(3), 'owner' => 'A']);
        $this->capa(['title' => 'On time', 'status' => 'IN_PROGRESS', 'due_on' => today()->addDays(10), 'owner' => 'A']);
        $this->capa(['title' => 'Check late', 'status' => 'AWAITING_VERIFICATION', 'due_on' => today()->subDays(30), 'verification_due_on' => today()->subDay()]);

        $this->get(route('capa.index'))->assertOk()->assertViewHas('counts', fn ($c) => $c['overdue'] === 2 && $c['open'] === 3 && $c['verify'] === 1)->assertSeeInOrder(['Late one', 'On time']);
        $this->get(route('capa.index', ['state' => 'overdue']))->assertSee('Late one')->assertSee('Check late')->assertDontSee('On time');
    }

    public function test_an_ncr_raises_one_action_carrying_its_findings_and_is_not_duplicated(): void
    {
        $ncr = NonconformanceReport::create(['casting_lot_id' => $this->castingLot()->id, 'ncr_number' => 'NCR-1', 'title' => 'Cracks at the boss', 'description' => 'Found at fettling', 'severity' => 'MAJOR', 'status' => 'IN_PROGRESS', 'root_cause' => 'Cooling too fast', 'corrective_action' => 'Slower shakeout', 'assigned_to' => 'Imran', 'target_completion_at' => today()->addDays(10)]);

        $this->post(route('capa.from-ncr', $ncr))->assertSessionHas('success');
        $capa = CapaAction::firstOrFail();
        $this->assertSame(['NCR', 'NCR-1', 'Cooling too fast', 'Imran', 'IN_PROGRESS', 'MAJOR'], [$capa->source_type, $capa->source_label, $capa->root_cause, $capa->owner, $capa->status, $capa->severity]);

        $this->post(route('capa.from-ncr', $ncr))->assertRedirect(route('capa.show', $capa));
        $this->assertSame(1, CapaAction::count());
        $this->get(route('ncrs.show', $ncr))->assertSee($capa->code)->assertDontSee('Raise corrective action');
    }

    public function test_an_ncr_without_an_action_offers_to_raise_one(): void
    {
        $ncr = NonconformanceReport::create(['casting_lot_id' => $this->castingLot()->id, 'ncr_number' => 'NCR-2', 'title' => 'Short pour', 'description' => 'x', 'severity' => 'MINOR', 'status' => 'OPEN']);

        $this->get(route('ncrs.show', $ncr))->assertOk()->assertSee('Raise corrective action');
    }
}
