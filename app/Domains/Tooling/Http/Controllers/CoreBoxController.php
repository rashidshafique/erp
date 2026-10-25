<?php

namespace App\Domains\Tooling\Http\Controllers;

use App\Domains\Tooling\Models\ToolingLifeLimit;
use App\Domains\Tooling\Models\ToolingMaintenanceRecord;
use App\Http\Controllers\Controller;
use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\Pattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Core boxes: new or modified from existing tooling, and the core each cavity makes. */
class CoreBoxController extends Controller
{
    public function index(Request $request): View
    {
        $boxes = CoreBox::query()->with('coreDefinition', 'cavityMap.coreDefinition')
            ->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('code', 'ilike', '%'.$request->input('search').'%')
                ->orWhere('name', 'ilike', '%'.$request->input('search').'%')->orWhere('mark_code', 'ilike', '%'.$request->input('search').'%')))
            ->orderBy('code')->paginate(50)->withQueryString();

        return view('tooling::core-boxes.index', ['boxes' => $boxes]);
    }

    public function create(Request $request): View
    {
        return view('tooling::core-boxes.form', $this->formData(new CoreBox([
            'status' => 'ACTIVE', 'origin' => 'NEW', 'revision' => 'A', 'cavities' => 1,
            'core_definition_id' => $request->input('core'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $box = DB::transaction(function () use ($data) {
            $box = CoreBox::create($data);
            $box->saveCavityMap(array_fill(1, (int) $box->cavities, $box->core_definition_id));

            return $box;
        });

        return redirect()->route('core-boxes.show', $box)->with('success', "Core box {$box->code} added."
            .($box->cavities > 1 ? ' Every cavity makes the same core; set the cavities that make a different one.' : ''));
    }

    public function show(CoreBox $coreBox): View
    {
        $coreBox->ensureMark();
        $coreBox->load('coreDefinition', 'cavityMap.coreDefinition', 'modifiedFromBox', 'modifiedFromPattern');

        return view('tooling::core-boxes.show', [
            'box' => $coreBox,
            'cores' => CoreDefinition::where('status', 'ACTIVE')->orWhereIn('id', $coreBox->cavityMap->pluck('core_definition_id'))->orderBy('code')->get(),
            'records' => ToolingMaintenanceRecord::where('core_box_id', $coreBox->id)->latest('reported_at')->limit(10)->get(),
            'life' => ToolingLifeLimit::where('core_box_id', $coreBox->id)->where('is_active', true)->first(),
            'derived' => CoreBox::where('modified_from_core_box_id', $coreBox->id)->orderBy('code')->get(),
        ]);
    }

    public function edit(CoreBox $coreBox): View
    {
        return view('tooling::core-boxes.form', $this->formData($coreBox));
    }

    public function update(Request $request, CoreBox $coreBox): RedirectResponse
    {
        $data = $this->validated($request, $coreBox);
        DB::transaction(function () use ($coreBox, $data) {
            $coreBox->update($data);
            // More cavities start on the main core; fewer drop the highest numbers.
            $map = $coreBox->cavityMap()->pluck('core_definition_id', 'cavity_number')->all();
            $coreBox->saveCavityMap(collect(range(1, (int) $coreBox->cavities))->mapWithKeys(fn ($n) => [$n => $map[$n] ?? $coreBox->core_definition_id])->all());
        });

        return redirect()->route('core-boxes.show', $coreBox)->with('success', "{$coreBox->code} saved.");
    }

    /** The core made in each cavity; a cavity left empty is not in use. */
    public function updateCavities(Request $request, CoreBox $coreBox): RedirectResponse
    {
        $data = $request->validate([
            'cores' => ['required', 'array'],
            'cores.*' => ['nullable', 'uuid', Rule::exists('core_definitions', 'id')->whereNull('deleted_at')],
        ]);
        $map = collect(range(1, (int) $coreBox->cavities))->mapWithKeys(fn ($n) => [$n => $data['cores'][$n] ?? null])->all();
        if (collect($map)->filter()->isEmpty()) {
            return back()->withErrors(['cores' => 'At least one cavity must make a core.']);
        }
        $coreBox->saveCavityMap($map);

        return back()->with('success', 'Cores per cavity saved.');
    }

    public function archive(CoreBox $coreBox): RedirectResponse
    {
        $coreBox->delete();

        return redirect()->route('core-boxes.index')->with('success', "{$coreBox->code} archived.");
    }

    public function restore(CoreBox $coreBox): RedirectResponse
    {
        $coreBox->restore();

        return redirect()->route('core-boxes.show', $coreBox)->with('success', "{$coreBox->code} restored.");
    }

    /** @return array<string, mixed> */
    private function formData(CoreBox $box): array
    {
        return [
            'box' => $box,
            'cores' => CoreDefinition::where('status', 'ACTIVE')->orWhere('id', $box->core_definition_id)->orderBy('code')->get(),
            'otherBoxes' => CoreBox::whereKeyNot($box->id ?? '')->orderBy('code')->get(),
            'patterns' => Pattern::orderBy('code')->get(['id', 'code', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?CoreBox $box = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('core_boxes', 'code')->ignore($box?->id)],
            'name' => ['required', 'string', 'max:255'],
            'core_definition_id' => ['required', 'uuid', Rule::exists('core_definitions', 'id')->whereNull('deleted_at')],
            'revision' => ['required', 'string', 'max:20'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'RETIRED'])],
            'origin' => ['required', Rule::in(array_keys(CoreBox::ORIGINS))],
            'modified_from_core_box_id' => ['nullable', 'required_if:origin,MODIFIED_BOX', 'uuid', Rule::exists('core_boxes', 'id'), Rule::notIn([$box?->id])],
            'modified_from_pattern_id' => ['nullable', 'required_if:origin,MODIFIED_PATTERN', 'uuid', Rule::exists('patterns', 'id')],
            'material' => ['nullable', 'string', 'max:50'],
            'cavities' => ['required', 'integer', 'min:1', 'max:200'],
            'nominal_core_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'commissioned_on' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ], [
            'core_definition_id.required' => 'Choose the core this box makes.',
            'modified_from_core_box_id.required_if' => 'Choose the core box it was modified from.',
            'modified_from_pattern_id.required_if' => 'Choose the pattern it was modified from.',
        ]);
        if (blank($data['code'] ?? null)) {
            unset($data['code']);
        }
        if ($data['origin'] !== 'MODIFIED_BOX') {
            $data['modified_from_core_box_id'] = null;
        }
        if ($data['origin'] !== 'MODIFIED_PATTERN') {
            $data['modified_from_pattern_id'] = null;
        }

        return $data;
    }
}
