<?php

namespace App\Domains\Documents\Services;

use App\Domains\Documents\Models\ControlledDocument;
use App\Domains\Documents\Models\ControlledDocumentRevision;
use App\Domains\Documents\Models\DocumentApprovalRecord;
use App\Domains\Documents\Support\DocumentFiles;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A document's life: a revision is written, submitted to approvers who decide in order, and when the last one approves it
 * takes effect and the revision it replaces is superseded. Only one revision is open at a time.
 */
class DocumentWorkflow
{
    /**
     * @param  array<string, mixed>  $document  document_number, title, document_type, owning_department, owner_name, description, review_interval_months
     * @param  array<string, mixed>  $revision  revision_code, revision_summary, change_reason
     */
    public function create(array $document, array $revision, UploadedFile $file, ?string $preparedBy): ControlledDocument
    {
        return DB::transaction(function () use ($document, $revision, $file, $preparedBy) {
            $doc = ControlledDocument::create($document + ['status' => 'DRAFT']);
            $this->newRevision($doc, $revision, $file, $preparedBy);

            return $doc;
        });
    }

    /** @param array<string, mixed> $revision revision_code, revision_summary, change_reason */
    public function addRevision(ControlledDocument $document, array $revision, UploadedFile $file, ?string $preparedBy): ControlledDocumentRevision
    {
        if ($document->status === 'OBSOLETE') {
            throw ValidationException::withMessages(['document' => 'An obsolete document cannot be revised.']);
        }
        if ($open = $document->openRevision()) {
            throw ValidationException::withMessages(['document' => "Revision {$open->revision_code} is still open. Finish or reject it before starting another."]);
        }

        return DB::transaction(fn () => $this->newRevision($document, $revision, $file, $preparedBy));
    }

    /** Replaces the file or the notes of a revision that has not been submitted. */
    public function updateDraft(ControlledDocumentRevision $revision, array $notes, ?UploadedFile $file): void
    {
        if ($revision->status !== 'DRAFT') {
            throw ValidationException::withMessages(['revision' => 'Only a draft can be changed. A revision under review or in force is fixed.']);
        }
        DB::transaction(function () use ($revision, $notes, $file) {
            $revision->update($notes);
            if ($file) {
                DocumentFiles::delete($revision);
                $revision->update(DocumentFiles::store($file, $revision->document, $revision));
            }
        });
    }

    /**
     * Sends a draft to its approvers, in the order given.
     *
     * @param  list<array{id: ?string, name: string, role: ?string}>  $approvers
     */
    public function submit(ControlledDocumentRevision $revision, array $approvers): void
    {
        if ($revision->status !== 'DRAFT') {
            throw ValidationException::withMessages(['revision' => 'Only a draft can be submitted.']);
        }
        if (! $revision->hasFile()) {
            throw ValidationException::withMessages(['revision' => 'Attach the file before submitting.']);
        }
        if ($approvers === []) {
            throw ValidationException::withMessages(['approvers' => 'Choose at least one approver.']);
        }
        if (config('foundry.require_login')) {
            foreach ($approvers as $a) {
                if (mb_strtolower($a['name']) === mb_strtolower((string) $revision->prepared_by)) {
                    throw ValidationException::withMessages(['approvers' => 'The person who prepared a revision cannot approve it.']);
                }
            }
        }
        DB::transaction(function () use ($revision, $approvers) {
            foreach (array_values($approvers) as $i => $a) {
                $revision->approvals()->create([
                    'approval_step' => $i + 1, 'approval_type' => 'APPROVAL', 'decision' => 'PENDING',
                    'approver_name' => $a['name'], 'approver_identifier' => $a['id'], 'approver_role' => $a['role'], 'assigned_at' => now(),
                ]);
            }
            $revision->update(['status' => 'IN_REVIEW', 'submitted_at' => now()]);
            $revision->document->update(['created_for_review_at' => $revision->document->created_for_review_at ?? now()]);
        });
    }

    /** One approver's decision. A rejection ends the revision; the last approval puts it in force. */
    public function decide(DocumentApprovalRecord $step, ?User $by, string $decision, ?string $comments): void
    {
        $revision = $step->revision;
        if ($revision->status !== 'IN_REVIEW' || $step->decision !== 'PENDING') {
            throw ValidationException::withMessages(['decision' => 'This step has already been decided.']);
        }
        $next = $revision->approvals()->where('decision', 'PENDING')->orderBy('approval_step')->first();
        if ($next->id !== $step->id) {
            throw ValidationException::withMessages(['decision' => 'An earlier approver has to decide first.']);
        }
        if (config('foundry.require_login')) {
            if (! $by) {
                throw ValidationException::withMessages(['decision' => 'Sign in to decide.']);
            }
            if ($step->approver_identifier !== null && $step->approver_identifier !== (string) $by->id && ! $by->isAdmin()) {
                throw ValidationException::withMessages(['decision' => 'This step is assigned to '.$step->approver_name.'.']);
            }
            if (mb_strtolower($by->name) === mb_strtolower((string) $revision->prepared_by)) {
                throw ValidationException::withMessages(['decision' => 'You prepared this revision, so someone else has to approve it.']);
            }
        }
        if ($decision === 'REJECTED' && blank($comments)) {
            throw ValidationException::withMessages(['comments' => 'Say why it is rejected.']);
        }
        DB::transaction(function () use ($revision, $step, $decision, $comments, $by) {
            $step->update(['decision' => $decision, 'decided_at' => now(), 'comments' => $comments, 'approver_name' => $by?->name ?? $step->approver_name]);
            if ($decision === 'REJECTED') {
                $revision->approvals()->where('decision', 'PENDING')->update(['decision' => 'CANCELLED']);
                $revision->update(['status' => 'REJECTED']);

                return;
            }
            if ($revision->approvals()->where('decision', 'PENDING')->exists()) {
                return;
            }
            $revision->update([
                'status' => 'APPROVED', 'approved_at' => now(),
                'approved_by' => $revision->approvals()->orderBy('approval_step')->pluck('approver_name')->filter()->implode(', '),
            ]);
            $this->putInForce($revision->fresh());
        });
    }

    /** The document is no longer used. Its revision in force becomes obsolete, and its files stay for the record. */
    public function obsolete(ControlledDocument $document, string $reason): void
    {
        if ($document->status === 'OBSOLETE') {
            throw ValidationException::withMessages(['document' => 'This document is already obsolete.']);
        }
        if ($open = $document->openRevision()) {
            throw ValidationException::withMessages(['document' => "Revision {$open->revision_code} is still open. Finish or reject it first."]);
        }
        DB::transaction(function () use ($document, $reason) {
            $document->currentRevision?->update(['status' => 'OBSOLETE', 'superseded_at' => now()]);
            $document->update(['status' => 'OBSOLETE', 'obsolete_at' => now(), 'next_review_on' => null, 'description' => trim($document->description."\n\nObsolete: ".$reason)]);
        });
    }

    /** The owner has read the document and it stands; the next review is one interval away. */
    public function markReviewed(ControlledDocument $document, ?string $by): void
    {
        if ($document->status !== 'ACTIVE') {
            throw ValidationException::withMessages(['document' => 'Only a document in force is reviewed.']);
        }
        $document->update([
            'last_reviewed_on' => today(), 'last_reviewed_by' => $by,
            'next_review_on' => $document->review_interval_months ? today()->addMonthsNoOverflow($document->review_interval_months) : null,
        ]);
    }

    /** The next revision code after the latest one: A to B, 2 to 3. */
    public function nextRevisionCode(ControlledDocument $document): string
    {
        $last = (string) $document->revisions->first()?->revision_code;
        if ($last === '') {
            return 'A';
        }
        if (ctype_digit($last)) {
            return (string) ((int) $last + 1);
        }
        if (strlen($last) === 1 && ctype_alpha($last) && strtoupper($last) !== 'Z') {
            return chr(ord(strtoupper($last)) + 1);
        }

        return $last.'+1';
    }

    private function newRevision(ControlledDocument $document, array $revision, UploadedFile $file, ?string $preparedBy): ControlledDocumentRevision
    {
        $new = $document->revisions()->create($revision + ['status' => 'DRAFT', 'prepared_by' => $preparedBy]);
        $new->update(DocumentFiles::store($file, $document, $new));
        $document->unsetRelation('revisions');

        return $new;
    }

    private function putInForce(ControlledDocumentRevision $revision): void
    {
        $document = $revision->document;
        $document->currentRevision?->update(['status' => 'SUPERSEDED', 'superseded_at' => now()]);
        $revision->update(['status' => 'EFFECTIVE', 'effective_at' => now()]);
        $document->update([
            'current_revision_id' => $revision->id, 'status' => 'ACTIVE', 'released_at' => $document->released_at ?? now(),
            'next_review_on' => $document->review_interval_months ? today()->addMonthsNoOverflow($document->review_interval_months) : null,
        ]);
    }
}
