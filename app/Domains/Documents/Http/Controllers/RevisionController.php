<?php

namespace App\Domains\Documents\Http\Controllers;

use App\Domains\Documents\Models\ControlledDocument;
use App\Domains\Documents\Models\ControlledDocumentRevision;
use App\Domains\Documents\Models\DocumentApprovalRecord;
use App\Domains\Documents\Services\DocumentWorkflow;
use App\Domains\Documents\Support\DocumentAccess;
use App\Domains\Documents\Support\DocumentFiles;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevisionController extends Controller
{
    public function __construct(private DocumentWorkflow $workflow) {}

    /** @return list<mixed> */
    public static function fileRules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'max:'.config('foundry.attachments.max_kb'), 'extensions:'.implode(',', config('foundry.attachments.extensions'))];
    }

    /** @return array<string, string> */
    public static function fileMessages(): array
    {
        return [
            'file.max' => 'The file may be at most '.Uploads::megabytes((int) config('foundry.attachments.max_kb')).'.',
            'file.uploaded' => Uploads::failedMessage(),
            'file.extensions' => 'That file type is not allowed. Allowed: '.implode(', ', config('foundry.attachments.extensions')).'.',
        ];
    }

    public function store(Request $request, ControlledDocument $document): RedirectResponse
    {
        $data = $request->validate([
            'revision_code' => ['required', 'string', 'max:50', Rule::unique('controlled_document_revisions', 'revision_code')->where('controlled_document_id', $document->id)],
            'change_reason' => ['required', 'string', 'max:2000'], 'revision_summary' => ['nullable', 'string', 'max:2000'],
            'file' => self::fileRules(),
        ], self::fileMessages());
        $revision = $this->workflow->addRevision($document, ['revision_code' => trim($data['revision_code']), 'change_reason' => $data['change_reason'], 'revision_summary' => $data['revision_summary'] ?? null], $request->file('file'), Auth::user()?->name);

        return redirect()->route('documents.show', $document)->with('success', "Revision {$revision->revision_code} started as a draft.");
    }

    public function update(Request $request, ControlledDocumentRevision $revision): RedirectResponse
    {
        $data = $request->validate([
            'change_reason' => ['required', 'string', 'max:2000'], 'revision_summary' => ['nullable', 'string', 'max:2000'],
            'file' => self::fileRules(false),
        ], self::fileMessages());
        $this->workflow->updateDraft($revision, ['change_reason' => $data['change_reason'], 'revision_summary' => $data['revision_summary'] ?? null], $request->file('file'));

        return back()->with('success', 'Draft saved.');
    }

    public function submit(Request $request, ControlledDocumentRevision $revision): RedirectResponse
    {
        $this->workflow->submit($revision, $this->approversFrom($request));

        return redirect()->route('documents.show', $revision->document)->with('success', "Revision {$revision->revision_code} sent for approval.");
    }

    public function decide(Request $request, DocumentApprovalRecord $approval): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])], 'comments' => ['nullable', 'string', 'max:2000']]);
        $this->workflow->decide($approval, Auth::user(), $data['decision'], $data['comments'] ?? null);
        $revision = $approval->revision->fresh();

        return redirect()->route('documents.show', $revision->document)->with(
            $data['decision'] === 'REJECTED' ? 'warning' : 'success',
            match ($revision->status) {
                'EFFECTIVE' => "Revision {$revision->revision_code} is now in force.",
                'REJECTED' => "Revision {$revision->revision_code} was rejected.",
                default => 'Approval recorded. The next approver is waiting.',
            },
        );
    }

    /** What is in force may be read by anyone signed in; drafts and old revisions only by those who manage or approve documents. */
    public function file(ControlledDocumentRevision $revision): StreamedResponse
    {
        abort_unless($revision->status === 'EFFECTIVE' || DocumentAccess::seesEverything(Auth::user()), 404);

        return DocumentFiles::response($revision);
    }

    /**
     * Approvers in the order chosen. With sign-in on they are users who may approve; with it off, names typed in.
     *
     * @return list<array{id: ?string, name: string, role: ?string}>
     */
    private function approversFrom(Request $request): array
    {
        $out = [];
        if (config('foundry.require_login')) {
            foreach (array_filter((array) $request->input('approvers', [])) as $id) {
                $user = User::where('is_active', true)->find($id);
                if ($user && $user->hasPermission('documents.approve') && ! collect($out)->contains('id', (string) $user->id)) {
                    $out[] = ['id' => (string) $user->id, 'name' => $user->name, 'role' => null];
                }
            }

            return $out;
        }
        foreach (array_filter(array_map('trim', (array) $request->input('approver_names', []))) as $name) {
            $out[] = ['id' => null, 'name' => mb_substr($name, 0, 150), 'role' => null];
        }

        return $out;
    }
}
