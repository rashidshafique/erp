<?php

namespace App\Domains\Documents\Http\Controllers;

use App\Domains\Documents\Models\ControlledDocument;
use App\Domains\Documents\Services\DocumentWorkflow;
use App\Domains\Documents\Support\DocumentAccess;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(private DocumentWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $all = DocumentAccess::seesEverything(Auth::user());
        $visible = fn () => ControlledDocument::query()->when(! $all, fn ($q) => $q->where('status', 'ACTIVE'));
        $state = $request->query('state', $all ? 'open' : 'active');
        $due = fn ($q) => $q->where('status', 'ACTIVE')->whereDate('next_review_on', '<=', today());
        $inReview = fn ($q) => $q->whereHas('revisions', fn ($r) => $r->where('status', 'IN_REVIEW'));

        $documents = $visible()->with('currentRevision', 'revisions')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('document_number', 'ilike', '%'.$request->query('q').'%')->orWhere('title', 'ilike', '%'.$request->query('q').'%')->orWhere('owner_name', 'ilike', '%'.$request->query('q').'%')))
            ->when($request->filled('type'), fn ($q) => $q->where('document_type', $request->query('type')))
            ->when($state === 'active', fn ($q) => $q->where('status', 'ACTIVE'))
            ->when($state === 'open', fn ($q) => $q->where('status', '!=', 'OBSOLETE'))
            ->when($state === 'review', $inReview)
            ->when($state === 'due', $due)
            ->when($state === 'obsolete', fn ($q) => $q->where('status', 'OBSOLETE'))
            ->orderBy('document_number')->paginate(25)->withQueryString();

        return view('documents::index', [
            'documents' => $documents, 'state' => $state, 'canSeeAll' => $all, 'canManage' => DocumentAccess::manages(Auth::user()),
            'counts' => [
                'active' => $visible()->where('status', 'ACTIVE')->count(),
                'review' => $all ? $inReview($visible())->count() : 0,
                'due' => $due($visible())->count(),
                'obsolete' => $all ? ControlledDocument::where('status', 'OBSOLETE')->count() : 0,
            ],
        ]);
    }

    public function create(): View
    {
        abort_unless(DocumentAccess::manages(Auth::user()), 403);

        return view('documents::create', ['document' => new ControlledDocument(['document_type' => 'PROCEDURE', 'review_interval_months' => 12])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'document_number' => ['required', 'string', 'max:100', Rule::unique('controlled_documents', 'document_number')],
            'title' => ['required', 'string', 'max:250'],
            'document_type' => ['required', Rule::in(array_keys(ControlledDocument::TYPES))],
            'owning_department' => ['nullable', 'string', 'max:100'], 'owner_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:4000'],
            'review_interval_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'revision_code' => ['required', 'string', 'max:50'],
            'revision_summary' => ['nullable', 'string', 'max:2000'],
            'file' => RevisionController::fileRules(),
        ], RevisionController::fileMessages());
        $document = $this->workflow->create(
            collect($data)->only(['document_number', 'title', 'document_type', 'owning_department', 'owner_name', 'description', 'review_interval_months'])->map(fn ($v) => is_string($v) ? trim($v) : $v)->all(),
            ['revision_code' => trim($data['revision_code']), 'revision_summary' => $data['revision_summary'] ?? null, 'change_reason' => 'First issue'],
            $request->file('file'),
            Auth::user()?->name,
        );

        return redirect()->route('documents.show', $document)->with('success', "{$document->document_number} created. Submit the draft for approval to put it in force.");
    }

    public function show(ControlledDocument $document): View
    {
        $user = Auth::user();
        $all = DocumentAccess::seesEverything($user);
        abort_unless($all || $document->status === 'ACTIVE', 404);
        $document->load('currentRevision', 'revisions.approvals');
        $open = $document->openRevision();
        $approvers = $open?->status === 'DRAFT' ? $this->approverChoices($open->prepared_by) : collect();

        return view('documents::show', [
            'document' => $document, 'open' => $open, 'canManage' => DocumentAccess::manages($user), 'canSeeAll' => $all, 'approvers' => $approvers,
            'nextCode' => $this->workflow->nextRevisionCode($document), 'loginRequired' => (bool) config('foundry.require_login'),
            'myUserId' => $user ? (string) $user->id : null, 'isAdmin' => $user?->isAdmin() ?? false,
        ]);
    }

    public function update(Request $request, ControlledDocument $document): RedirectResponse
    {
        $document->update($request->validate([
            'title' => ['required', 'string', 'max:250'], 'document_type' => ['required', Rule::in(array_keys(ControlledDocument::TYPES))],
            'owning_department' => ['nullable', 'string', 'max:100'], 'owner_name' => ['nullable', 'string', 'max:150'],
            'review_interval_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]));
        if ($document->status === 'ACTIVE' && $document->review_interval_months && ! $document->next_review_on) {
            $document->update(['next_review_on' => today()->addMonthsNoOverflow($document->review_interval_months)]);
        }

        return back()->with('success', 'Saved.');
    }

    public function obsolete(Request $request, ControlledDocument $document): RedirectResponse
    {
        $this->workflow->obsolete($document, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);

        return back()->with('success', "{$document->document_number} is now obsolete. Its files are kept for the record.");
    }

    public function reviewed(ControlledDocument $document): RedirectResponse
    {
        $this->workflow->markReviewed($document, Auth::user()?->name);

        return back()->with('success', 'Review recorded.'.($document->fresh()->next_review_on ? ' Next review '.$document->fresh()->next_review_on->displayDate().'.' : ''));
    }

    /** People who may approve, for the submit form. The person who prepared the revision is left out when sign-in is on. */
    private function approverChoices(?string $preparedBy)
    {
        return User::where('is_active', true)->orderBy('name')->get()
            ->filter(fn (User $u) => $u->hasPermission('documents.approve'))
            ->reject(fn (User $u) => config('foundry.require_login') && mb_strtolower($u->name) === mb_strtolower((string) $preparedBy))
            ->values();
    }
}
