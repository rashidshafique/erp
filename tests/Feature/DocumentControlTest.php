<?php

namespace Tests\Feature;

use App\Domains\Documents\Models\ControlledDocument;
use App\Domains\Documents\Models\ControlledDocumentRevision;
use App\Models\Role;
use App\Models\User;
use App\Support\GlobalSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('foundry.attachments.disk'));
    }

    private function pdf(string $name = 'qp-07.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 40, 'application/pdf');
    }

    private function createDocument(array $extra = []): ControlledDocument
    {
        $this->post(route('documents.store'), $extra + [
            'document_number' => 'QP-07', 'title' => 'Control of nonconforming product', 'document_type' => 'PROCEDURE',
            'review_interval_months' => 12, 'revision_code' => 'A', 'file' => $this->pdf(),
        ])->assertSessionHasNoErrors();

        return ControlledDocument::where('document_number', $extra['document_number'] ?? 'QP-07')->firstOrFail();
    }

    /** Creates a document and puts revision A in force through two typed-in approvers (sign-in off). */
    private function documentInForce(): ControlledDocument
    {
        $document = $this->createDocument();
        $revision = $document->revisions()->firstOrFail();
        $this->post(route('documents.revisions.submit', $revision), ['approver_names' => ['Quality manager', 'Works manager']])->assertSessionHasNoErrors();
        foreach ($revision->fresh()->approvals as $step) {
            $this->post(route('documents.revisions.decide', $step), ['decision' => 'APPROVED'])->assertSessionHasNoErrors();
        }

        return $document->fresh();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $role)->value('id'));

        return $user;
    }

    public function test_a_document_starts_as_a_draft_with_its_first_revision_and_file(): void
    {
        $document = $this->createDocument();

        $revision = $document->revisions()->sole();
        $this->assertSame(['DRAFT', 'DRAFT', 'A'], [$document->status, $revision->status, $revision->revision_code]);
        $this->assertNull($document->current_revision_id);
        Storage::disk($revision->storage_disk)->assertExists($revision->storage_path);
        $this->assertSame(hash_file('sha256', Storage::disk($revision->storage_disk)->path($revision->storage_path)), $revision->file_sha256);
        $this->get(route('documents.show', $document))->assertOk()->assertSee('Send for approval');
        $this->post(route('documents.store'), ['document_number' => 'QP-07', 'title' => 'Duplicate', 'document_type' => 'FORM', 'revision_code' => 'A', 'file' => $this->pdf()])->assertSessionHasErrors('document_number');
    }

    public function test_approvers_decide_in_order_and_the_last_approval_puts_the_revision_in_force(): void
    {
        $document = $this->createDocument();
        $revision = $document->revisions()->sole();
        $this->post(route('documents.revisions.submit', $revision), ['approver_names' => ['Quality manager', 'Works manager']])->assertSessionHasNoErrors();
        [$first, $second] = $revision->fresh()->approvals->all();

        $this->post(route('documents.revisions.decide', $second), ['decision' => 'APPROVED'])->assertSessionHasErrors('decision');
        $this->post(route('documents.revisions.decide', $first), ['decision' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->assertSame('IN_REVIEW', $revision->fresh()->status);
        $this->assertNull($document->fresh()->current_revision_id);

        $this->post(route('documents.revisions.decide', $second), ['decision' => 'APPROVED'])->assertSessionHasNoErrors();

        $document = $document->fresh();
        $this->assertSame(['ACTIVE', $revision->id, 'EFFECTIVE'], [$document->status, $document->current_revision_id, $revision->fresh()->status]);
        $this->assertSame('Quality manager, Works manager', $revision->fresh()->approved_by);
        $this->assertSame(today()->addYear()->toDateString(), $document->next_review_on->toDateString());
        $this->get(route('documents.revisions.file', $revision))->assertOk();
    }

    public function test_a_draft_cannot_be_submitted_without_an_approver_and_a_submitted_revision_is_fixed(): void
    {
        $document = $this->createDocument();
        $revision = $document->revisions()->sole();

        $this->post(route('documents.revisions.submit', $revision), [])->assertSessionHasErrors('approvers');
        $this->post(route('documents.revisions.submit', $revision), ['approver_names' => ['Quality manager']])->assertSessionHasNoErrors();
        $this->put(route('documents.revisions.update', $revision), ['change_reason' => 'Changed my mind'])->assertSessionHasErrors('revision');
        $this->post(route('documents.revisions.submit', $revision), ['approver_names' => ['Someone else']])->assertSessionHasErrors('revision');
    }

    public function test_a_new_revision_replaces_the_one_in_force_only_once_approved_and_only_one_is_open_at_a_time(): void
    {
        $document = $this->documentInForce();
        $a = $document->current_revision_id;

        $this->post(route('documents.revisions.store', $document), ['revision_code' => 'B', 'change_reason' => 'Add containment step', 'file' => $this->pdf('qp-07-b.pdf')])->assertSessionHasNoErrors();
        $b = $document->revisions()->where('revision_code', 'B')->firstOrFail();
        $this->post(route('documents.revisions.store', $document), ['revision_code' => 'C', 'change_reason' => 'Too soon', 'file' => $this->pdf()])->assertSessionHasErrors('document');
        $this->assertSame($a, $document->fresh()->current_revision_id, 'A stays in force while B is a draft');

        $this->post(route('documents.revisions.submit', $b), ['approver_names' => ['Quality manager']]);
        $this->post(route('documents.revisions.decide', $b->approvals()->firstOrFail()), ['decision' => 'APPROVED'])->assertSessionHasNoErrors();

        $this->assertSame($b->id, $document->fresh()->current_revision_id);
        $this->assertSame(['SUPERSEDED', 'EFFECTIVE'], [ControlledDocumentRevision::find($a)->status, $b->fresh()->status]);
        $this->assertNotNull(ControlledDocumentRevision::find($a)->superseded_at);
    }

    public function test_a_rejection_needs_a_reason_ends_that_revision_and_frees_the_document_for_another(): void
    {
        $document = $this->createDocument();
        $revision = $document->revisions()->sole();
        $this->post(route('documents.revisions.submit', $revision), ['approver_names' => ['Quality manager', 'Works manager']]);
        [$first, $second] = $revision->fresh()->approvals->all();

        $this->post(route('documents.revisions.decide', $first), ['decision' => 'REJECTED'])->assertSessionHasErrors('comments');
        $this->post(route('documents.revisions.decide', $first), ['decision' => 'REJECTED', 'comments' => 'Scope is unclear'])->assertSessionHasNoErrors();

        $this->assertSame('REJECTED', $revision->fresh()->status);
        $this->assertSame('CANCELLED', $second->fresh()->decision);
        $this->assertNull($document->fresh()->current_revision_id);
        $this->post(route('documents.revisions.store', $document), ['revision_code' => 'B', 'change_reason' => 'Rewritten', 'file' => $this->pdf()])->assertSessionHasNoErrors();
    }

    public function test_a_document_in_force_can_be_made_obsolete_and_can_then_no_longer_be_revised(): void
    {
        $document = $this->documentInForce();

        $this->post(route('documents.obsolete', $document), [])->assertSessionHasErrors('reason');
        $this->post(route('documents.obsolete', $document), ['reason' => 'Replaced by QP-09'])->assertSessionHasNoErrors();

        $document = $document->fresh();
        $this->assertSame(['OBSOLETE', 'OBSOLETE'], [$document->status, $document->currentRevision->status]);
        $this->assertNull($document->next_review_on);
        $this->post(route('documents.revisions.store', $document), ['revision_code' => 'B', 'change_reason' => 'x', 'file' => $this->pdf()])->assertSessionHasErrors('document');
    }

    public function test_a_review_date_comes_due_and_marking_it_reviewed_moves_it_on(): void
    {
        $document = $this->documentInForce();
        $document->update(['next_review_on' => today()->subDay()]);

        $this->get(route('documents.index', ['state' => 'due']))->assertOk()->assertSee('QP-07');
        $this->get(route('documents.show', $document))->assertOk()->assertSee('due for review');

        $this->post(route('documents.reviewed', $document))->assertSessionHasNoErrors();

        $document = $document->fresh();
        $this->assertSame(today()->addYear()->toDateString(), $document->next_review_on->toDateString());
        $this->assertSame(today()->toDateString(), $document->last_reviewed_on->toDateString());
        $this->assertFalse($document->isDueForReview());
    }

    public function test_with_sign_in_on_the_preparer_cannot_approve_and_only_the_assigned_person_decides(): void
    {
        config(['foundry.require_login' => true]);
        $author = $this->userWithRole('QUALITY_MANAGER');
        $approver = $this->userWithRole('QUALITY_MANAGER');
        $bystander = $this->userWithRole('QUALITY_MANAGER');
        $this->actingAs($author);
        $document = $this->createDocument();
        $revision = $document->revisions()->sole();

        $this->post(route('documents.revisions.submit', $revision), ['approvers' => [(string) $author->id]])->assertSessionHasErrors('approvers');
        $this->post(route('documents.revisions.submit', $revision), ['approvers' => [(string) $approver->id]])->assertSessionHasNoErrors();
        $step = $revision->fresh()->approvals()->firstOrFail();

        $this->post(route('documents.revisions.decide', $step), ['decision' => 'APPROVED'])->assertSessionHasErrors('decision');
        $this->actingAs($bystander)->post(route('documents.revisions.decide', $step), ['decision' => 'APPROVED'])->assertSessionHasErrors('decision');
        $this->actingAs($approver)->post(route('documents.revisions.decide', $step), ['decision' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->assertSame('EFFECTIVE', $revision->fresh()->status);
    }

    public function test_people_without_document_rights_see_only_what_is_in_force(): void
    {
        config(['foundry.require_login' => true]);
        $manager = $this->userWithRole('QUALITY_MANAGER');
        $viewer = $this->userWithRole('VIEWER');
        $this->actingAs($manager);
        $draft = $this->createDocument(['document_number' => 'WI-01', 'title' => 'Draft only']);
        $this->post(route('documents.revisions.submit', $draft->revisions()->sole()), ['approvers' => [(string) $this->userWithRole('QUALITY_MANAGER')->id]]);
        $inForce = ControlledDocument::create(['document_number' => 'QP-01', 'title' => 'In force', 'document_type' => 'PROCEDURE', 'status' => 'ACTIVE']);
        $revision = $inForce->revisions()->create(['revision_code' => 'A', 'status' => 'EFFECTIVE', 'original_filename' => 'qp-01.pdf', 'storage_disk' => config('foundry.attachments.disk'), 'storage_path' => 'documents/qp-01.pdf', 'mime_type' => 'application/pdf', 'file_size_bytes' => 10, 'file_sha256' => str_repeat('a', 64), 'effective_at' => now()]);
        $inForce->update(['current_revision_id' => $revision->id]);
        Storage::disk(config('foundry.attachments.disk'))->put('documents/qp-01.pdf', 'pdf');

        $this->actingAs($viewer);
        $this->get(route('documents.index'))->assertOk()->assertSee('QP-01')->assertDontSee('WI-01');
        $this->get(route('documents.show', $draft))->assertNotFound();
        $this->get(route('documents.revisions.file', $draft->revisions()->sole()))->assertNotFound();
        $this->get(route('documents.revisions.file', $revision))->assertOk();
        $this->get(route('documents.create'))->assertForbidden();
        $this->post(route('documents.obsolete', $inForce), ['reason' => 'x'])->assertForbidden();
    }

    public function test_the_document_page_renders_in_every_state(): void
    {
        $document = $this->createDocument();
        $revision = $document->revisions()->sole();
        $this->get(route('documents.show', $document))->assertOk()->assertSee('No revision is in force yet.')->assertSee('Submit for approval');

        $this->post(route('documents.revisions.submit', $revision), ['approver_names' => ['Quality manager']]);
        $this->get(route('documents.show', $document))->assertOk()->assertSee('Waiting')->assertSee('Quality manager')->assertSee('Reject');

        $this->post(route('documents.revisions.decide', $revision->approvals()->firstOrFail()), ['decision' => 'APPROVED']);
        $this->get(route('documents.show', $document))->assertOk()->assertSee('qp-07.pdf')->assertSee('New revision')->assertSee('Make obsolete');
        $this->get(route('documents.index'))->assertOk()->assertSee('QP-07')->assertSee('In force');

        $this->post(route('documents.obsolete', $document), ['reason' => 'No longer used']);
        $this->get(route('documents.show', $document))->assertOk()->assertSee('Do not use');
        $this->get(route('documents.index', ['state' => 'obsolete']))->assertOk()->assertSee('QP-07');
    }

    public function test_the_module_adds_its_menu_entry_search_type_and_permissions(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee('Controlled documents');
        $this->assertArrayHasKey('document', GlobalSearch::types());
        $this->assertArrayHasKey('documents.approve', config('permissions.catalog'));
        $this->assertContains('documents.manage', Role::where('code', 'QUALITY_MANAGER')->firstOrFail()->permissions());
    }
}
