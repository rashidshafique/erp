<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\HrPolicy;
use App\Models\HrPolicyAck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HrPolicyController extends Controller
{
    public function index(Request $request): View
    {
        $current = Employee::current()->count();
        $policies = HrPolicy::query()
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('title', 'ilike', '%'.trim($request->search).'%')->orWhere('code', 'ilike', '%'.trim($request->search).'%')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->withCount(['acks as acks_now' => fn ($q) => $q->whereColumn('hr_policy_acks.version', 'hr_policies.version')])
            ->orderBy('title')->paginate(25)->withQueryString();

        return view('hr.policies.index', compact('policies', 'current'));
    }

    public function create(): View
    {
        return view('hr.policies.create');
    }

    private function rules(): array
    {
        return ['title' => ['required', 'string', 'max:200'], 'category' => ['required', Rule::in(array_keys(config('hr.policy_categories')))], 'effective_on' => ['nullable', 'date'], 'requires_ack' => ['nullable', 'boolean'], 'body' => ['nullable', 'string', 'max:60000']];
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate($this->rules());
        $d['requires_ack'] = $request->boolean('requires_ack');
        $p = HrPolicy::create($d);

        return redirect()->route('hr.policies.show', $p)->with('success', "{$p->code} saved as a draft.");
    }

    public function show(HrPolicy $policy): View
    {
        $employees = Employee::current()->with('department', 'designation')->orderBy('first_name')->get();
        $acks = $policy->acks()->where('version', $policy->version)->get()->keyBy('employee_id');

        return view('hr.policies.show', compact('policy', 'employees', 'acks'));
    }

    public function update(Request $request, HrPolicy $policy): RedirectResponse
    {
        $d = $request->validate($this->rules());
        $d['requires_ack'] = $request->boolean('requires_ack');
        if ($policy->status === 'PUBLISHED' && $d['body'] !== $policy->body) {
            return back()->with('error', 'A published policy cannot be reworded. Start a new version instead.')->withInput();
        }
        $policy->update($d);

        return back()->with('success', 'Saved.');
    }

    public function publish(HrPolicy $policy): RedirectResponse
    {
        abort_if($policy->status === 'PUBLISHED', 422);
        if (blank($policy->body)) {
            return back()->with('error', 'Write the policy text before publishing.');
        }
        $policy->update(['status' => 'PUBLISHED', 'published_at' => now(), 'effective_on' => $policy->effective_on ?? now()->toDateString()]);

        return back()->with('success', 'Published. Everyone who has to acknowledge it is now listed below.');
    }

    /** New wording: version goes up and acknowledgements start again. */
    public function revise(HrPolicy $policy): RedirectResponse
    {
        abort_unless($policy->status === 'PUBLISHED', 422);
        $policy->update(['status' => 'DRAFT', 'version' => $policy->version + 1, 'published_at' => null]);

        return back()->with('success', "Version {$policy->version} is open for editing. Publish it when ready; acknowledgements start again.");
    }

    public function retire(HrPolicy $policy): RedirectResponse
    {
        $policy->update(['status' => 'RETIRED']);

        return back()->with('success', 'Policy retired.');
    }

    public function acknowledge(Request $request, HrPolicy $policy): RedirectResponse
    {
        abort_unless($policy->status === 'PUBLISHED', 422, 'Only a published policy can be acknowledged.');
        $d = $request->validate(['employee_ids' => ['required', 'array', 'min:1'], 'employee_ids.*' => ['uuid', 'exists:employees,id']]);
        $n = 0;
        foreach (array_unique($d['employee_ids']) as $id) {
            $ack = HrPolicyAck::firstOrCreate(['policy_id' => $policy->id, 'employee_id' => $id, 'version' => $policy->version], ['acknowledged_at' => now(), 'recorded_by_name' => Auth::user()?->name]);
            $n += $ack->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('success', $n === 1 ? 'Acknowledgement recorded.' : "$n acknowledgements recorded.");
    }
}
