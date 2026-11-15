<?php

namespace App\Domains\Tooling\Http\Controllers;

use App\Domains\Tooling\Models\ToolingLifeLimit;
use App\Domains\Tooling\Models\ToolingMaintenanceRecord;
use App\Http\Controllers\Controller;
use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\Customer;
use App\Models\Pattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Core boxes: new or modified from existing tooling, and how many cavities make each core. */
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
        $rows = $this->posted($request);
        $map = $rows ? $this->mapFromRows($rows['cores'], $rows['blocked']) : array_fill(1, (int) $data['cavities'], $data['core_definition_id']);
        $box = DB::transaction(function () use ($data, $map) {
            $box = CoreBox::create($data);
            $box->saveCavityMap($map);

            return $box;
        });

        return redirect()->route('core-boxes.show', $box)->with('success', "Core box {$box->code} added.");
    }

    public function show(CoreBox $coreBox): View
    {
        $coreBox->ensureMark();
        $coreBox->load('coreDefinition', 'cavityMap.coreDefinition', 'modifiedFromBox', 'modifiedFromPattern');

        return view('tooling::core-boxes.show', [
            'box' => $coreBox,
            'records' => ToolingMaintenanceRecord::where('core_box_id', $coreBox->id)->latest('reported_at')->limit(10)->get(),
            'life' => ToolingLifeLimit::where('core_box_id', $coreBox->id)->where('is_active', true)->first(),
            'derived' => CoreBox::where('modified_from_core_box_id', $coreBox->id)->orderBy('code')->get(),
            'customers' => Customer::active()->orderedByName()->get(),
        ]);
    }

    public function edit(CoreBox $coreBox): View
    {
        return view('tooling::core-boxes.form', $this->formData($coreBox));
    }

    public function update(Request $request, CoreBox $coreBox): RedirectResponse
    {
        $data = $this->validated($request, $coreBox);
        $rows = $this->posted($request);
        $saved = $coreBox->cavityMap()->pluck('core_definition_id', 'cavity_number')->all();
        $map = match (true) {
            $rows !== null => $this->mapFromRows($rows['cores'], $rows['blocked']),
            // More cavities start on the main core; fewer drop the highest numbers.
            default => collect(range(1, (int) $data['cavities']))->mapWithKeys(fn ($n) => [$n => $saved[$n] ?? $data['core_definition_id']])->all(),
        };
        ksort($map);
        $map = array_slice($map, 0, (int) $data['cavities'], true);
        DB::transaction(function () use ($coreBox, $data, $map) {
            $coreBox->update($data);
            $coreBox->saveCavityMap($map);
        });

        return redirect()->route('core-boxes.show', $coreBox)->with('success', "{$coreBox->code} saved.");
    }

    /** Who owns the box and where it is (design 18h). */
    public function updateOwnership(Request $request, CoreBox $coreBox): RedirectResponse
    {
        $data = $request->validate([
            'owner_type' => ['required', Rule::in(['FOUNDRY', 'CUSTOMER'])],
            'owner_customer_id' => ['required_if:owner_type,CUSTOMER', 'nullable', 'uuid', Rule::exists('customers', 'id')],
            'custody' => ['required', Rule::in(CoreBox::CUSTODIES)],
            'custody_location' => ['required_if:custody,LENT', 'nullable', 'string', 'max:150'],
            'ownership_reference' => ['nullable', 'string', 'max:100'],
            'return_notice_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ], [
            'owner_customer_id.required_if' => 'Choose the customer who owns the core box.',
            'custody_location.required_if' => 'Say who the core box is lent to.',
        ], ['custody' => 'where it is']);
        if ($data['owner_type'] === 'FOUNDRY') {
            $data['owner_customer_id'] = null;
        }
        $coreBox->update($data);

        return back()->with('success', "Ownership of {$coreBox->code} saved.");
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

    /**
     * The form's "Cores this box makes" rows, with rows for the same core merged, or null when the form did not send them
     * (an import, an older caller).
     *
     * @return array{cores: list<array{core: string, qty: int}>, blocked: int}|null
     */
    private function posted(Request $request): ?array
    {
        if (! $request->has('box_cores')) {
            return null;
        }
        $cores = collect($request->input('box_cores'))->filter(fn ($r) => filled($r['core'] ?? null))
            ->groupBy('core')->map(fn ($g, $core) => ['core' => (string) $core, 'qty' => (int) $g->sum('qty')])->values()->all();

        return ['cores' => $cores, 'blocked' => (int) $request->input('blocked', 0)];
    }

    /**
     * Cavities numbered in row order (A × 3, B × 1 → 1–3 A, 4 B), blocked cavities last and not in use.
     *
     * @param  list<array{core: string, qty: int}>  $rows
     * @return array<int, string|null>
     */
    private function mapFromRows(array $rows, int $blocked): array
    {
        $map = [];
        foreach ($rows as $row) {
            for ($i = 0; $i < $row['qty']; $i++) {
                $map[count($map) + 1] = $row['core'];
            }
        }
        for ($i = 0; $i < $blocked; $i++) {
            $map[count($map) + 1] = null;
        }

        return $map;
    }

    /**
     * A saved cavity map as form rows: each core once, in the order of its first cavity, with how many cavities make it.
     *
     * @param  array<int, string|null>  $map
     * @return list<array{core: string, qty: int}>
     */
    private function rowsFromMap(array $map): array
    {
        ksort($map);

        return collect($map)->filter()->countBy()->map(fn ($n, $core) => ['core' => (string) $core, 'qty' => $n])->values()->all();
    }

    /** @return array<string, mixed> */
    private function formData(CoreBox $box): array
    {
        $saved = $box->exists ? $box->cavityMap()->pluck('core_definition_id', 'cavity_number')->all() : [];

        return [
            'box' => $box,
            'boxCores' => $box->exists ? $this->rowsFromMap($saved) : [['core' => (string) $box->core_definition_id, 'qty' => 1]],
            'blocked' => $box->exists ? max(0, (int) $box->cavities - count($saved)) : 0,
            'cores' => CoreDefinition::where('status', 'ACTIVE')->orWhere('id', $box->core_definition_id)->orWhereIn('id', array_filter($saved))->orderBy('code')->get(),
            'otherBoxes' => CoreBox::when($box->exists, fn ($q) => $q->whereKeyNot($box->id))->orderBy('code')->get(),
            'patterns' => Pattern::orderBy('code')->get(['id', 'code', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?CoreBox $box = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('core_boxes', 'code')->ignore($box?->id)],
            'name' => ['required', 'string', 'max:255'],
            'core_definition_id' => ['required_without:box_cores', 'nullable', 'uuid', Rule::exists('core_definitions', 'id')->whereNull('deleted_at')],
            'revision' => ['required', 'string', 'max:20'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'RETIRED'])],
            'origin' => ['required', Rule::in(array_keys(CoreBox::ORIGINS))],
            'modified_from_core_box_id' => ['nullable', 'required_if:origin,MODIFIED_BOX', 'uuid', Rule::exists('core_boxes', 'id'), Rule::notIn([$box?->id])],
            'modified_from_pattern_id' => ['nullable', 'required_if:origin,MODIFIED_PATTERN', 'uuid', Rule::exists('patterns', 'id')],
            'material' => ['nullable', 'string', 'max:50'],
            'cavities' => ['required_without:box_cores', 'nullable', 'integer', 'min:1', 'max:200'],
            'nominal_core_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'commissioned_on' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'box_cores' => ['nullable', 'array'],
            'box_cores.*.core' => ['required', 'uuid', Rule::exists('core_definitions', 'id')->whereNull('deleted_at')],
            'box_cores.*.qty' => ['required', 'integer', 'min:1', 'max:200'],
            'blocked' => ['nullable', 'integer', 'min:0', 'max:199'],
        ], [
            'box_cores.*.core.required' => 'Choose the core on each row, or remove the row.',
            'box_cores.*.core.exists' => 'Choose the core on each row, or remove the row.',
            'box_cores.*.qty.min' => 'Each core needs at least one cavity.',
            'core_definition_id.required' => 'Choose the core this box makes.',
            'modified_from_core_box_id.required_if' => 'Choose the core box it was modified from.',
            'modified_from_pattern_id.required_if' => 'Choose the pattern it was modified from.',
        ]);
        if ($request->has('box_cores')) {
            $rows = $this->posted($request);
            $total = collect($rows['cores'])->sum('qty') + $rows['blocked'];
            if ($rows['cores'] === []) {
                throw ValidationException::withMessages(['box_cores' => 'Add the core this box makes.']);
            }
            if ($total > 200) {
                throw ValidationException::withMessages(['box_cores' => 'A box has at most 200 cavities.']);
            }
            $data['core_definition_id'] = $rows['cores'][0]['core'];
            $data['cavities'] = $total;
        }
        unset($data['box_cores'], $data['blocked']);
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
