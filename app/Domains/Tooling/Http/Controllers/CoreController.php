<?php

namespace App\Domains\Tooling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CoreBoxCavity;
use App\Models\CoreDefinition;
use App\Models\ProductRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The cores the foundry makes: what each is, which core boxes make it and which products need it. */
class CoreController extends Controller
{
    public function index(Request $request): View
    {
        $cores = CoreDefinition::query()
            ->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('code', 'ilike', '%'.$request->input('search').'%')
                ->orWhere('name', 'ilike', '%'.$request->input('search').'%')))
            ->withCount(['cavities as boxes_count' => fn ($q) => $q->select(\DB::raw('count(distinct core_box_id)'))])
            ->orderBy('code')->paginate(50)->withQueryString();

        return view('tooling::cores.index', ['cores' => $cores]);
    }

    public function create(): View
    {
        return view('tooling::cores.form', ['core' => new CoreDefinition(['core_type' => 'STANDARD', 'status' => 'ACTIVE'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $core = CoreDefinition::create($this->validated($request));

        return redirect()->route('cores.show', $core)->with('success', "Core {$core->code} added. Add the core box that makes it.");
    }

    public function show(CoreDefinition $core): View
    {
        $core->ensureMark();

        return view('tooling::cores.show', [
            'core' => $core,
            'productRevisions' => ProductRevision::with('product')->where('status', '!=', 'OBSOLETE')->get()->sortBy(fn ($r) => [$r->product->code, $r->revision]),
            'cavities' => CoreBoxCavity::where('core_definition_id', $core->id)->with('coreBox')->orderBy('cavity_number')->get()->groupBy('core_box_id'),
        ]);
    }

    public function edit(CoreDefinition $core): View
    {
        return view('tooling::cores.form', ['core' => $core]);
    }

    public function update(Request $request, CoreDefinition $core): RedirectResponse
    {
        $core->update($this->validated($request, $core));

        return redirect()->route('cores.show', $core)->with('success', "{$core->code} saved.");
    }

    public function archive(CoreDefinition $core): RedirectResponse
    {
        if ($core->cavities()->whereHas('coreBox')->exists()) {
            return back()->withErrors(['archive' => "{$core->code} is made by a core box. Move that cavity to another core first."]);
        }
        $core->delete();

        return redirect()->route('cores.index')->with('success', "{$core->code} archived.");
    }

    public function restore(CoreDefinition $core): RedirectResponse
    {
        $core->restore();

        return redirect()->route('cores.show', $core)->with('success', "{$core->code} restored.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?CoreDefinition $core = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('core_definitions', 'code')->ignore($core?->id)],
            'name' => ['required', 'string', 'max:255'],
            'core_type' => ['required', Rule::in(array_keys(CoreDefinition::TYPES))],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'RETIRED'])],
            'process_type' => ['nullable', 'string', 'max:30'],
            'nominal_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'engineering_notes' => ['nullable', 'string', 'max:4000'],
        ], ['name.required' => 'Give the core a name.']);
        if (blank($data['code'] ?? null)) {
            unset($data['code']);
        }

        return $data;
    }
}
