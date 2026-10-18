<?php

namespace Tests\Feature;

use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\Pattern;
use App\Models\PatternRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternRevisionStatusTest extends TestCase
{
    use RefreshDatabase;

    private function draftRevision(): PatternRevision
    {
        $pattern = Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Plate', 'pattern_type' => 'MATCH_PLATE']);

        return PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'DRAFT']);
    }

    private function requireApproval(): void
    {
        ApprovalPolicy::create(['action_key' => 'pattern.approve_revision', 'is_active' => true, 'permission' => 'approvals.decide', 'required_approvals' => 1, 'allow_self_approval' => true]);
    }

    private function change(PatternRevision $revision, string $status)
    {
        return $this->put(route('patterns.revisions.status', [$revision->pattern, $revision]), ['status' => $status]);
    }

    public function test_approval_is_immediate_when_no_policy_is_switched_on(): void
    {
        $revision = $this->draftRevision();

        $this->change($revision, 'APPROVED')->assertSessionHas('success');

        $this->assertSame('APPROVED', $revision->fresh()->status);
    }

    public function test_approval_raises_a_request_when_the_policy_is_on_and_applies_once_it_is_approved(): void
    {
        $this->requireApproval();
        $revision = $this->draftRevision();

        $this->change($revision, 'APPROVED')->assertSessionHas('warning');
        $this->assertSame('DRAFT', $revision->fresh()->status);

        ApprovalRequest::where('action_key', 'pattern.approve_revision')->firstOrFail()->update(['status' => 'APPROVED']);
        $this->change($revision, 'APPROVED')->assertSessionHas('success');
        $this->assertSame('APPROVED', $revision->fresh()->status);
    }

    public function test_marking_obsolete_never_needs_approval(): void
    {
        $this->requireApproval();
        $revision = $this->draftRevision();

        $this->change($revision, 'OBSOLETE')->assertSessionHas('success');

        $this->assertSame('OBSOLETE', $revision->fresh()->status);
    }
}
