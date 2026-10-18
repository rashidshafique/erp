<?php

namespace App\Http\Controllers;

use App\Models\CapaAction;
use App\Models\NonconformanceReport;
use App\Services\Approvals\ApprovalGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CapaController extends Controller
{
    public function index(Request $request): View
    {
        $open = fn ($q) => $q->whereNotIn('status', ['CLOSED', 'CANCELLED']);
        $overdue = fn ($q) => $open($q)->where(fn ($w) => $w
            ->where(fn ($a) => $a->where('status', '!=', 'AWAITING_VERIFICATION')->whereDate('due_on', '<', today()))
            ->orWhere(fn ($b) => $b->where('status', 'AWAITING_VERIFICATION')->whereDate('verification_due_on', '<', today())));
        $state = $request->query('state', 'open');

        $actions = CapaAction::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'ilike', '%'.$request->query('q').'%')->orWhere('title', 'ilike', '%'.$request->query('q').'%')->orWhere('owner', 'ilike', '%'.$request->query('q').'%')))
            ->when($request->filled('source'), fn ($q) => $q->where('source_type', $request->query('source')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->query('severity')))
            ->when($state === 'open', $open)
            ->when($state === 'overdue', $overdue)
            ->when($state === 'verify', fn ($q) => $q->where('status', 'AWAITING_VERIFICATION'))
            ->when($state === 'closed', fn ($q) => $q->whereIn('status', ['CLOSED', 'CANCELLED']))
            ->orderByRaw("case when status in ('CLOSED','CANCELLED') then 1 else 0 end")->orderByRaw('coalesce(case when status = \'AWAITING_VERIFICATION\' then verification_due_on else due_on end, \'9999-12-31\')')->orderBy('code')
            ->paginate(25)->withQueryString();

        return view('capa.index', [
            'actions' => $actions, 'state' => $state,
            'counts' => ['open' => $open(CapaAction::query())->count(), 'overdue' => $overdue(CapaAction::query())->count(), 'verify' => CapaAction::where('status', 'AWAITING_VERIFICATION')->count(), 'closed' => CapaAction::where('status', 'CLOSED')->count()],
        ]);
    }

    public function create(Request $request): View
    {
        return view('capa.create', ['action' => new CapaAction(['source_type' => $request->query('source', 'OTHER'), 'severity' => 'MINOR'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_type' => ['required', Rule::in(array_keys(CapaAction::SOURCES))],
            'source_label' => ['nullable', 'string', 'max:200'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:4000'],
            'severity' => ['required', Rule::in(array_keys(CapaAction::SEVERITIES))],
            'owner' => ['nullable', 'string', 'max:150'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $capa = CapaAction::create($data + ['status' => 'OPEN', 'raised_by' => Auth::user()?->name]);

        return redirect()->route('capa.show', $capa)->with('success', "{$capa->code} raised. Record the root cause and the action to take.");
    }

    /** Raises an action from a nonconformance report, carrying over what the NCR already says. One open action per NCR. */
    public function fromNcr(NonconformanceReport $ncr): RedirectResponse
    {
        $existing = CapaAction::where('source_type', 'NCR')->where('source_id', $ncr->id)->whereNotIn('status', ['CLOSED', 'CANCELLED'])->first();
        if ($existing) {
            return redirect()->route('capa.show', $existing)->with('success', "{$existing->code} is already open for {$ncr->ncr_number}.");
        }
        $capa = CapaAction::create([
            'source_type' => 'NCR', 'source_id' => $ncr->id, 'source_label' => $ncr->ncr_number, 'title' => $ncr->title, 'description' => $ncr->description,
            'severity' => in_array(strtoupper((string) $ncr->severity), array_keys(CapaAction::SEVERITIES), true) ? strtoupper($ncr->severity) : 'MINOR',
            'containment_action' => $ncr->containment_action, 'root_cause' => $ncr->root_cause, 'corrective_action' => $ncr->corrective_action, 'preventive_action' => $ncr->preventive_action,
            'owner' => $ncr->assigned_to, 'due_on' => $ncr->target_completion_at?->toDateString(), 'status' => 'OPEN', 'raised_by' => Auth::user()?->name,
        ]);
        $this->refreshStatus($capa);

        return redirect()->route('capa.show', $capa)->with('success', "{$capa->code} raised from {$ncr->ncr_number}.");
    }

    public function show(CapaAction $capa): View
    {
        $ncr = $capa->source_type === 'NCR' && $capa->source_id ? NonconformanceReport::find($capa->source_id) : null;

        return view('capa.show', ['capa' => $capa, 'ncr' => $ncr]);
    }

    /** Saves the investigation and the plan. Status moves to "action in progress" once the action, owner and due date are all there. */
    public function update(Request $request, CapaAction $capa): RedirectResponse
    {
        if (! $capa->isOpen()) {
            return back()->withErrors(['capa' => 'A closed or cancelled action cannot be changed.']);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:4000'],
            'severity' => ['required', Rule::in(array_keys(CapaAction::SEVERITIES))],
            'containment_action' => ['nullable', 'string', 'max:2000'], 'root_cause' => ['nullable', 'string', 'max:2000'],
            'corrective_action' => ['nullable', 'string', 'max:2000'], 'preventive_action' => ['nullable', 'string', 'max:2000'],
            'owner' => ['nullable', 'string', 'max:150'], 'due_on' => ['nullable', 'date'],
        ]);
        $capa->update($data);
        $this->refreshStatus($capa);

        return back()->with('success', 'Saved.');
    }

    /** The action has been carried out; the effectiveness check is then due on the date given. */
    public function complete(Request $request, CapaAction $capa): RedirectResponse
    {
        if ($capa->status !== 'IN_PROGRESS') {
            return back()->withErrors(['capa' => 'Record the root cause, the corrective action, an owner and a due date first.']);
        }
        $data = $request->validate([
            'completed_on' => ['required', 'date', 'before_or_equal:today'],
            'implementation_notes' => ['nullable', 'string', 'max:2000'],
            'verification_due_on' => ['required', 'date', 'after_or_equal:completed_on'],
        ], ['verification_due_on.required' => 'Say when the effectiveness will be checked.']);
        $capa->update($data + ['status' => 'AWAITING_VERIFICATION']);

        return back()->with('success', 'Action recorded as done. Its effectiveness check is due '.$capa->verification_due_on->displayDate().'.');
    }

    /** The check that the action worked. Effective closes it; not effective sends it back for a better action. */
    public function verify(Request $request, CapaAction $capa): RedirectResponse
    {
        if ($capa->status !== 'AWAITING_VERIFICATION') {
            return back()->withErrors(['capa' => 'Only an action awaiting its effectiveness check can be verified.']);
        }
        $data = $request->validate([
            'verification_result' => ['required', Rule::in(['EFFECTIVE', 'NOT_EFFECTIVE'])],
            'verified_by' => ['required', 'string', 'max:150'],
            'verified_on' => ['required', 'date', 'before_or_equal:today'],
            'verification_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['verification_result'] === 'NOT_EFFECTIVE' && blank($data['verification_notes'] ?? null)) {
            throw ValidationException::withMessages(['verification_notes' => 'Say what showed the action did not work.']);
        }
        $entry = now()->displayDate().': '.str_replace('_', ' ', $data['verification_result']).' (checked by '.$data['verified_by'].')'.(filled($data['verification_notes'] ?? null) ? ' - '.$data['verification_notes'] : '');

        if ($data['verification_result'] === 'NOT_EFFECTIVE') {
            $capa->update(['status' => 'IN_PROGRESS', 'completed_on' => null, 'verification_due_on' => null, 'verification_result' => 'NOT_EFFECTIVE', 'verification_log' => trim($capa->verification_log."\n".$entry)]);

            return back()->with('warning', 'Recorded as not effective. The action is back in progress: revise the root cause or the action and complete it again.');
        }
        if (! app(ApprovalGate::class)->passes('capa.close', 'CapaAction', (string) $capa->id, $capa->code, $message)) {
            return back()->withErrors(['capa' => $message]);
        }
        $capa->update($data + ['status' => 'CLOSED', 'closed_at' => now(), 'verification_log' => trim($capa->verification_log."\n".$entry)]);

        return back()->with('success', "{$capa->code} closed: the action was effective.");
    }

    public function cancel(Request $request, CapaAction $capa): RedirectResponse
    {
        if (! $capa->isOpen()) {
            return back()->withErrors(['capa' => 'This action is already closed or cancelled.']);
        }
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:1000']], ['cancel_reason.required' => 'Say why the action is being cancelled.']);
        $capa->update($data + ['status' => 'CANCELLED', 'closed_at' => now()]);

        return back()->with('success', "{$capa->code} cancelled.");
    }

    private function refreshStatus(CapaAction $capa): void
    {
        if (! in_array($capa->status, ['OPEN', 'IN_PROGRESS'], true)) {
            return;
        }
        $ready = filled($capa->root_cause) && filled($capa->corrective_action) && filled($capa->owner) && $capa->due_on !== null;
        $capa->update(['status' => $ready ? 'IN_PROGRESS' : 'OPEN']);
    }
}
