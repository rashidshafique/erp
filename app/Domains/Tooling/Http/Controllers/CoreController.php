<?php

namespace App\Domains\Tooling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Codes\CodeGenerator;
use App\Models\CoreBoxCavity;
use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\CoreLot;
use App\Models\ProductRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The cores the foundry makes: what each is, which core boxes make it and which products need it. */
class CoreController extends Controller
{
    /** The list itself is the tooling::core-list Livewire component; this is the page around it. */
    public function index(): View
    {
        return view('tooling::cores.index');
    }

    public function create(): View
    {
        return view('tooling::cores.form', ['core' => new CoreDefinition(['core_type' => 'STANDARD', 'status' => 'ACTIVE'])]);
    }

    /** From the core form, or the quick "+ New core…" dialog on the core box form, which asks for JSON. */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $mark = $data['mark_code'] ?? null;
        unset($data['mark_code']);
        $core = CoreDefinition::create($data + ['status' => 'ACTIVE']);
        $this->applyMark($core, $mark);
        if ($request->expectsJson()) {
            return response()->json(['id' => $core->id, 'label' => $core->code.' · '.$core->name], 201);
        }

        return redirect()->route('cores.show', $core)->with('success', "Core {$core->code} added. Add the core box that makes it.");
    }

    public function show(CoreDefinition $core): View
    {
        $core->ensureMark();

        $cavities = CoreBoxCavity::where('core_definition_id', $core->id)->with('coreBox')->orderBy('cavity_number')->get()->groupBy('core_box_id');
        $lots = CoreLot::where('core_definition_id', $core->id)->where('status', '!=', 'USED_UP')->get()->filter(fn (CoreLot $l) => $l->remaining() > 0);

        return view('tooling::cores.show', [
            'core' => $core,
            'madeIn' => $cavities->map(fn ($rows) => $rows->first()->coreBox)->filter()->sortBy(fn (CoreBox $b) => $b->status === 'ACTIVE' ? 0 : 1)->first(),
            'onHand' => ['cores' => $lots->sum(fn (CoreLot $l) => $l->remaining()), 'lots' => $lots->count()],
            'productRevisions' => ProductRevision::with('product')->where('status', '!=', 'OBSOLETE')->get()->sortBy(fn ($r) => [$r->product->code, $r->revision]),
            'cavities' => $cavities,
        ]);
    }

    public function edit(CoreDefinition $core): View
    {
        return view('tooling::cores.form', ['core' => $core]);
    }

    public function update(Request $request, CoreDefinition $core): RedirectResponse
    {
        $data = $this->validated($request, $core);
        $mark = $data['mark_code'] ?? null;
        unset($data['mark_code']);
        $core->update($data);
        $this->applyMark($core, $mark);

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

    /** Records an engraved mark typed on the form; blank leaves the automatic one. */
    private function applyMark(CoreDefinition $core, ?string $mark): void
    {
        if (filled($mark) && $mark !== $core->mark_code) {
            app(CodeGenerator::class)->changeMark('core_definition', $core, $mark);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?CoreDefinition $core = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('core_definitions', 'code')->ignore($core?->id)],
            'name' => ['required', 'string', 'max:255'],
            'core_type' => ['required', Rule::in(array_keys(CoreDefinition::TYPES))],
            'mark_code' => ['nullable', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9]+$/', function (string $attribute, mixed $value, \Closure $fail) use ($core): void {
                $taken = DB::table('tool_marks')->where('mark_code', strtoupper(trim($value)))
                    ->where(fn ($q) => $q->where('tool_type', '!=', 'core_definition')->orWhere('tool_id', '!=', (string) $core?->getKey()))->exists();
                if ($taken) {
                    $fail('That mark is already on another tool.');
                }
            }],
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
