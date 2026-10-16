<?php

namespace App\Services\Approvals;

use App\Models\ApprovalDecision;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Approve-then-execute. An action behind a policy first raises a request; once enough different people with
 * the right permission approve it, the next attempt goes through exactly once (the approval is then USED).
 */
class ApprovalGate
{
    /** True when the action may proceed now. False means a request is waiting (see $message). */
    public function passes(string $actionKey, string $subjectType, string $subjectId, ?string $label, ?string &$message = null, ?string $note = null): bool
    {
        $policy = ApprovalPolicy::where('action_key', $actionKey)->where('is_active', true)->first();
        if (! $policy) {
            return true;
        }

        return DB::transaction(function () use ($policy, $actionKey, $subjectType, $subjectId, $label, &$message, $note) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["approval:$actionKey:$subjectType:$subjectId"]);
            $q = fn () => ApprovalRequest::where('action_key', $actionKey)->where('subject_type', $subjectType)->where('subject_id', $subjectId);

            $approved = $q()->where('status', 'APPROVED')->orderBy('created_at')->first();
            if ($approved) {
                $approved->update(['status' => 'USED', 'consumed_at' => now()]);
                $this->audit('UPDATED', $approved, ['status' => ['from' => 'APPROVED', 'to' => 'USED']]);

                return true;
            }
            $pending = $q()->where('status', 'PENDING')->first();
            if ($pending) {
                $message = 'Waiting for approval: '.$pending->actionLabel().' ('.$pending->subject_label.'). '.$this->progress($pending);

                return false;
            }
            $user = Auth::user();
            $req = ApprovalRequest::create([
                'action_key' => $actionKey, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'subject_label' => $label,
                'status' => 'PENDING', 'required_approvals' => $policy->required_approvals, 'allow_self_approval' => $policy->allow_self_approval,
                'permission' => $policy->permission, 'requested_by_id' => $user?->id, 'requested_by_name' => $user?->name, 'note' => $note,
            ]);
            $message = 'This needs approval. A request has been raised: '.$req->actionLabel().' ('.$label.'). Do it again once it is approved.';

            return false;
        });
    }

    public function decide(ApprovalRequest $req, string $decision, ?string $comment, ?User $user): ApprovalRequest
    {
        return DB::transaction(function () use ($req, $decision, $comment, $user) {
            $req = ApprovalRequest::lockForUpdate()->findOrFail($req->id);
            if ($req->status !== 'PENDING') {
                throw new RuntimeException('This request is already '.strtolower($req->status).'.');
            }
            if (config('foundry.require_login') && ! $user) {
                throw new RuntimeException('Sign in to decide.');
            }
            if ($user && ! $user->hasPermission($req->permission)) {
                throw new RuntimeException('Your role cannot decide this kind of request.');
            }
            if ($user && ! $req->allow_self_approval && $req->requested_by_id && (int) $req->requested_by_id === (int) $user->id) {
                throw new RuntimeException('You raised this request, so someone else must decide it.');
            }
            if ($user && $req->decisions()->where('user_id', $user->id)->exists()) {
                throw new RuntimeException('You have already decided this request.');
            }
            if ($decision === 'REJECT' && blank($comment)) {
                throw new RuntimeException('Give a reason when rejecting.');
            }
            ApprovalDecision::create(['approval_request_id' => $req->id, 'decision' => $decision, 'user_id' => $user?->id, 'user_name' => $user?->name, 'comment' => $comment]);

            $new = null;
            if ($decision === 'REJECT') {
                $new = 'REJECTED';
            } elseif ($req->decisions()->where('decision', 'APPROVE')->count() >= $req->required_approvals) {
                $new = 'APPROVED';
            }
            if ($new) {
                $req->update(['status' => $new]);
            }
            $this->audit('UPDATED', $req, ['decision' => ['from' => null, 'to' => $decision.($comment ? ': '.$comment : '')]] + ($new ? ['status' => ['from' => 'PENDING', 'to' => $new]] : []));

            return $req->refresh();
        });
    }

    public function cancel(ApprovalRequest $req, ?User $user): void
    {
        if ($req->status !== 'PENDING' && $req->status !== 'APPROVED') {
            throw new RuntimeException('Only an open request can be cancelled.');
        }
        if ($user && ! $user->isAdmin() && (int) $req->requested_by_id !== (int) $user->id) {
            throw new RuntimeException('Only the person who raised it, or an administrator, can cancel it.');
        }
        $from = $req->status;
        $req->update(['status' => 'CANCELLED']);
        $this->audit('UPDATED', $req, ['status' => ['from' => $from, 'to' => 'CANCELLED']]);
    }

    public function progress(ApprovalRequest $r): string
    {
        return $r->decisions()->where('decision', 'APPROVE')->count().' of '.$r->required_approvals.' approvals.';
    }

    private function audit(string $action, ApprovalRequest $r, array $changes): void
    {
        app(\App\Services\Audit\Audit::class)->record($action, 'ApprovalRequest', (string) $r->id, $r->actionLabel().' — '.$r->subject_label, $changes);
    }
}
