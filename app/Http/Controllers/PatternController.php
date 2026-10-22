<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\MouldAssembly;
use App\Models\MouldCavity;
use App\Models\MouldingRun;
use App\Models\MouldingWorkListItem;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\Quotation;
use App\Models\SandSystem;
use App\Models\ToolingLifeLimit;
use App\Models\ToolingMaintenanceRecord;
use App\Services\Approvals\ApprovalGate;
use App\Services\Audit\Audit;
use App\Services\Codes\CodeGenerator;
use App\Services\Patterns\PatternOwnership;
use App\Services\Patterns\RevisionLayout;
use App\Services\ToolingLifeStatusService;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatternController extends Controller
{
    public function index(Request $request)
    {
        $archived = $request->boolean('archived');
        $patterns = Pattern::query()
            ->when($archived, fn ($q) => $q->onlyTrashed())
            ->with('currentRevision', 'ownerCustomer')
            ->withCount('revisions')
            ->when($request->filled('owner'), fn ($q) => in_array($request->input('owner'), array_keys(Pattern::OWNERS), true)
                ? $q->where('owner_type', $request->input('owner'))
                : $q->where('owner_customer_id', $request->input('owner')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->input('search'));

                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status'))
            )
            ->when($request->input('link') === 'unlinked', fn ($q) => $q->unlinked())
            ->when($request->input('revision') === 'baseline', fn ($q) => $q->whereHas('currentRevision', fn ($r) => $r->baselineWithoutDrawing()))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('patterns.index', [
            'patterns' => $patterns,
            'totalPatterns' => Pattern::count(),
            'activePatterns' => Pattern::where('status', 'ACTIVE')->count(),
            'otherPatterns' => Pattern::where('status', '!=', 'ACTIVE')->count(),
            'archivedPatterns' => Pattern::onlyTrashed()->count(),
            'archived' => $archived,
            'unlinkedPatterns' => Pattern::unlinked()->count(),
            'baselineOnly' => Pattern::whereHas('currentRevision', fn ($r) => $r->baselineWithoutDrawing())->count(),
            'owningCustomers' => Customer::withTrashed()->whereIn('id', Pattern::whereNotNull('owner_customer_id')->select('owner_customer_id'))->orderedByName()->get(),
        ]);
    }

    public function create()
    {
        return view('patterns.create', [
            'pattern' => new Pattern,
            'patternTypes' => array_keys(Pattern::TYPES),
            'sands' => SandSystem::names(),
            'patternStatuses' => ['ACTIVE', 'INACTIVE', 'RETIRED'],
            'revisionStatuses' => ['DRAFT', 'APPROVED', 'OBSOLETE'],
            'partingTypes' => [
                'HORIZONTAL',
                'VERTICAL',
                'SINGLE_SIDED',
                'OTHER',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'unique:patterns,code'],   // blank = generated
            'name' => ['required', 'string', 'max:255'],
            'pattern_type' => [
                'required',
                Rule::in(array_keys(Pattern::TYPES)),
            ],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'RETIRED'])],
            'material' => ['nullable', 'string', 'max:50'],
            'condition_grade' => ['nullable', Rule::in(array_keys(Pattern::CONDITIONS))],
            'mark_code' => ['nullable', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'length_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'width_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'height_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'sand_system' => ['nullable', Rule::in(SandSystem::allNames())],
            'preferred_moulding_station_id' => ['nullable', 'uuid', 'exists:moulding_stations,id'],
            'compatible_sands' => ['nullable', 'array'], 'compatible_sands.*' => [Rule::in(SandSystem::allNames())],
            'sand_note' => ['nullable', 'string', 'max:1000'],

            'revision' => ['required', 'string', 'max:20'],
            'revision_status' => ['required', Rule::in(['DRAFT', 'APPROVED', 'OBSOLETE'])],
            'parting_type' => ['nullable', 'string', 'max:30'],
            'shrinkage_percent' => ['nullable', 'numeric', 'min:0', 'max:9.999'],
            'tolerance_grade' => ['nullable', 'integer', Rule::in(PatternRevision::TOLERANCE_GRADES)],
            'machining_allowance_grade' => ['nullable', Rule::in(PatternRevision::ALLOWANCE_GRADES)],
            'cavities_per_mould' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'layers_per_mould' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'pattern_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'change_description' => ['nullable', 'string'],
            'engineering_notes' => ['nullable', 'string'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $mark = Arr::pull($data, 'mark_code');

        $pattern = DB::transaction(function () use ($data, $mark) {
            $pattern = new Pattern([
                'code' => filled($data['code'] ?? null) ? strtoupper(trim($data['code'])) : null,
                'name' => trim($data['name']),
                'pattern_type' => $data['pattern_type'],
                'status' => $data['status'],
                'material' => $data['material'] ?? null,
                'condition_grade' => $data['condition_grade'] ?? null,
                'condition_graded_on' => filled($data['condition_grade'] ?? null) ? today() : null,
                'length_mm' => $data['length_mm'] ?? null,
                'width_mm' => $data['width_mm'] ?? null,
                'height_mm' => $data['height_mm'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'sand_system' => $data['sand_system'] ?? null,
                'preferred_moulding_station_id' => $data['preferred_moulding_station_id'] ?? null,
                'compatible_sands' => $this->otherSands($data),
                'sand_note' => $data['sand_note'] ?? null,
            ]);
            $pattern->withoutMark = true;
            $pattern->save();

            $pattern->revisions()->create([
                'revision' => trim($data['revision']),
                'status' => $data['revision_status'],
                'parting_type' => $data['parting_type'] ?? null,
                'shrinkage_percent' => $data['shrinkage_percent'] ?? null,
                'tolerance_grade' => $data['tolerance_grade'] ?? null,
                'machining_allowance_grade' => $data['machining_allowance_grade'] ?? null,
                'cavities_per_mould' => $data['cavities_per_mould'] ?? null,
                'layers_per_mould' => $data['layers_per_mould'] ?? null,
                'pattern_weight_kg' => $data['pattern_weight_kg'] ?? null,
                'change_description' => $data['change_description'] ?? null,
                'engineering_notes' => $data['engineering_notes'] ?? null,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
            ]);

            if (filled($mark)) {
                app(CodeGenerator::class)->changeMark('pattern', $pattern, $mark);
            }

            return $pattern;
        });

        return redirect()
            ->route('patterns.show', $pattern)
            ->with('success', 'Pattern and initial revision created successfully.');
    }

    public function edit(Pattern $pattern)
    {
        $current = $pattern->currentRevision()->with('cavityMap')->first();

        return view('patterns.edit', [
            'pattern' => $pattern,
            'current' => $current,
            'assembled' => $current ? MouldAssembly::whereHas('layers.cavities', fn ($q) => $q->where('pattern_revision_id', $current->id))->count() : 0,
            'patternTypes' => array_keys(Pattern::TYPES),
            'patternStatuses' => ['ACTIVE', 'INACTIVE', 'RETIRED'],
        ]);
    }

    /**
     * Service life, inspection and maintenance standing of the pattern and its revisions, from the tooling life limits
     * and maintenance register.
     *
     * @return array{limits: Collection<int, array<string, mixed>>, lastDone: ?ToolingMaintenanceRecord, nextInspection: ?Carbon, openJobs: int}
     */
    private function toolLife(Pattern $pattern): array
    {
        $revisionIds = $pattern->revisions->pluck('id');
        $ofThisPattern = fn ($query) => $query->where(fn ($q) => $q->where('pattern_id', $pattern->id)->orWhereIn('pattern_revision_id', $revisionIds));

        $service = app(ToolingLifeStatusService::class);
        $limits = $ofThisPattern(ToolingLifeLimit::query()->where('is_active', true)->with('patternRevision'))->get()
            ->map(fn (ToolingLifeLimit $limit) => $service->calculate($limit));

        $records = fn () => $ofThisPattern(ToolingMaintenanceRecord::query());
        $nextDue = $records()->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->whereNotNull('next_inspection_due_on')->min('next_inspection_due_on')
            ?? $records()->where('status', 'COMPLETED')->whereNotNull('next_inspection_due_on')->max('next_inspection_due_on');

        return [
            'limits' => $limits,
            'lastDone' => $records()->where('status', 'COMPLETED')->latest('completed_at')->first(),
            'nextInspection' => $nextDue ? Carbon::parse($nextDue) : null,
            'openJobs' => $records()->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->count(),
        ];
    }

    /** Corrects the physical tooling record. Revisions, their codes and their layouts are not touched. */
    public function update(Request $request, Pattern $pattern): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('patterns', 'code')->ignore($pattern->id)],
            'name' => ['required', 'string', 'max:255'],
            'pattern_type' => ['required', Rule::in(array_keys(Pattern::TYPES))],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'RETIRED'])],
            'material' => ['nullable', 'string', 'max:50'],
            'condition_grade' => ['nullable', Rule::in(array_keys(Pattern::CONDITIONS))],
            'mark_code' => ['nullable', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'length_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'width_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'height_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
        $mark = Arr::pull($data, 'mark_code');
        $data['code'] = strtoupper(trim($data['code']));
        $data['name'] = trim($data['name']);
        if (array_key_exists('condition_grade', $data)) {
            $data['condition_grade'] = $data['condition_grade'] ?: null;
            if ($data['condition_grade'] !== $pattern->condition_grade) {
                $data['condition_graded_on'] = $data['condition_grade'] ? today() : null;
            }
        }
        $current = $pattern->currentRevision;
        $layout = $current && $request->filled('mould_arrangement') ? $this->validatedLayout($request) : null;

        // A new pattern type brings its usual arrangement, unless a different one was chosen for the current revision.
        $oldDefault = Pattern::TYPES[$pattern->pattern_type] ?? null;
        $newDefault = Pattern::TYPES[$data['pattern_type']] ?? 'OTHER';
        if ($current && $data['pattern_type'] !== $pattern->pattern_type && ($layout['mould_arrangement'] ?? $current->mould_arrangement) === $oldDefault) {
            $request->merge(['mould_arrangement' => $newDefault]);
            $layout = $this->validatedLayout($request);
        }

        $layoutMessage = DB::transaction(function () use ($pattern, $data, $current, $layout, $request, $mark) {
            $pattern->update($data);
            if (filled($mark)) {
                app(CodeGenerator::class)->changeMark('pattern', $pattern, $mark);
            }

            $message = $current && $layout ? app(RevisionLayout::class)->save($current, $layout, $request->input('correction_reason')) : null;
            $sizes = $current ? $this->syncFlaskSizes($request, $current) : null;

            return trim(($message ?? '').' '.($sizes ?? '')) ?: null;
        });

        return redirect()->route('patterns.show', $pattern)->with('success', "{$pattern->code} saved.".($layoutMessage ? ' '.$layoutMessage : ''));
    }

    /** The "also suits" sands, without the one the pattern was made for. */
    private function otherSands(array $data): ?array
    {
        $own = $data['sand_system'] ?? null;
        $others = array_values(array_filter($data['compatible_sands'] ?? [], fn ($x) => $x !== $own));

        return $others ?: null;
    }

    /**
     * Archives a pattern: it leaves the pattern list, its counts and the pickers (work lists, bulk moulding entry), but stays
     * on the runs, maintenance and life records that used it and can be restored. Not while it is being moulded or listed.
     */
    public function archive(Pattern $pattern): RedirectResponse
    {
        if ($pattern->isCustomers()) {
            return back()->withErrors(['archive' => "{$pattern->code} belongs to {$pattern->ownerLabel()}. Return it to them or scrap it (with their consent) instead of archiving it."]);
        }
        $revisionIds = $pattern->revisions()->pluck('id');
        $running = MouldingRun::whereIn('pattern_revision_id', $revisionIds)->whereIn('status', ['IN_PROGRESS', 'STARTED', 'RUNNING'])->pluck('run_number');
        $listed = MouldingWorkListItem::whereIn('pattern_revision_id', $revisionIds)->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->count();
        if ($running->isNotEmpty() || $listed) {
            return back()->withErrors(['archive' => "{$pattern->code} is still in use: "
                .collect([$running->isNotEmpty() ? 'moulding run '.$running->implode(', ').' is in progress' : null, $listed ? "{$listed} work-list ".($listed === 1 ? 'item is' : 'items are').' not finished' : null])->filter()->implode('; ')
                .'. Finish or remove those before archiving it.']);
        }
        $pattern->delete();

        return redirect()->route('patterns.index')->with('success', "{$pattern->code} archived. It is out of the pattern list and pickers; find it under Archived to restore it.");
    }

    public function restore(Pattern $pattern): RedirectResponse
    {
        if ($pattern->custody === 'SCRAPPED') {
            return back()->withErrors(['restore' => "{$pattern->code} was scrapped and cannot be restored."]);
        }
        $pattern->restore();
        if ($pattern->custody === 'RETURNED') {
            app(PatternOwnership::class)->update($pattern, ['custody' => 'IN_STORE', 'custody_location' => null], null, 'Back from the customer.');
        }

        return redirect()->route('patterns.show', $pattern)->with('success', "{$pattern->code} restored.");
    }

    /** Record which sand system a pattern was made for and which others it works on. */
    public function updateSand(Request $request, Pattern $pattern)
    {
        $data = $request->validate([
            'sand_system' => ['nullable', Rule::in(SandSystem::allNames())],
            'preferred_moulding_station_id' => ['nullable', 'uuid', 'exists:moulding_stations,id'],
            'compatible_sands' => ['nullable', 'array'], 'compatible_sands.*' => [Rule::in(SandSystem::allNames())],
            'sand_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $pattern->update(['sand_system' => $data['sand_system'] ?? null, 'preferred_moulding_station_id' => $data['preferred_moulding_station_id'] ?? null, 'compatible_sands' => $this->otherSands($data), 'sand_note' => $data['sand_note'] ?? null]);

        return back()->with('success', 'Sand system saved for '.$pattern->code.'.');
    }

    public function show(Pattern $pattern)
    {
        $pattern->load([
            'revisions' => fn ($query) => $query
                ->orderByDesc('is_current')
                ->orderByDesc('sequence')
                ->orderByDesc('created_at'),
            'revisions.cavityMap.productRevision.product',
            'revisions.cavityMap.copiedFrom',
            'revisions.flaskSizes',
        ]);
        $revisionPhotos = Attachment::where('attachable_type', 'pattern_revision')->whereIn('attachable_id', $pattern->revisions->pluck('id'))
            ->whereNull('removed_at')->get()->groupBy('attachable_id');

        $productRevisions = ProductRevision::query()
            ->with('product')
            ->where('status', '!=', 'OBSOLETE')
            ->get()
            ->sortBy(fn ($r) => $r->product?->code.' '.$r->revision)
            ->values();

        $bareProducts = Product::query()->whereDoesntHave('revisions', fn ($q) => $q->where('status', '!=', 'OBSOLETE'))->orderBy('code')->get();

        $assembledRevisions = MouldCavity::whereIn('pattern_revision_id', $pattern->revisions->pluck('id'))->distinct()->pluck('pattern_revision_id')->all();

        $pattern->load('ownerCustomer', 'ownershipQuotation', 'ownershipEvents');
        $ownership = app(PatternOwnership::class);
        $owner = [
            'customers' => Customer::where(fn ($q) => $q->active()->orWhere('id', $pattern->owner_customer_id))->orderedByName()->get(),
            'quotations' => Route::has('quotations.show') ? Quotation::orderByDesc('created_at')->limit(200)->get(['id', 'code', 'version']) : collect(),
            'payOff' => $ownership->payOffProgress($pattern),
            'custody' => $ownership->custodyLabel($pattern),
            'scrapRequest' => ApprovalRequest::where('action_key', 'pattern.scrap_customer_pattern')->where('subject_id', $pattern->id)->whereIn('status', ['PENDING', 'APPROVED'])->latest()->first(),
        ];

        $toolLife = $this->toolLife($pattern);

        $scope = fn () => ($pattern->trashed() ? Pattern::onlyTrashed() : Pattern::query())
            ->when(request('link') === 'unlinked', fn ($q) => $q->unlinked());
        $previousPattern = $scope()->where('code', '<', $pattern->code)->orderByDesc('code')->first(['id', 'code', 'name']);
        $nextPattern = $scope()->where('code', '>', $pattern->code)->orderBy('code')->first(['id', 'code', 'name']);

        return view('patterns.show', compact('pattern', 'productRevisions', 'bareProducts', 'revisionPhotos', 'assembledRevisions', 'owner', 'toolLife', 'previousPattern', 'nextPattern'));
    }

    /**
     * Sets how a revision is moulded: its arrangement, flask sections, casting levels and shared metal. Once moulds have been
     * assembled from it, a correction needs a reason (audited) and the assembled moulds keep the castings they recorded.
     */
    /**
     * Moves a revision between DRAFT, APPROVED and OBSOLETE. Approving goes through the 'pattern.approve_revision' approval
     * policy when one is switched on in the approval rules; otherwise it takes effect at once.
     */
    public function updateRevisionStatus(Request $request, Pattern $pattern, PatternRevision $revision): RedirectResponse
    {
        abort_unless($revision->pattern_id === $pattern->id, 404);
        $data = $request->validate(['status' => ['required', Rule::in(['DRAFT', 'APPROVED', 'OBSOLETE'])]]);

        if ($data['status'] === $revision->status) {
            return back();
        }
        if ($data['status'] === 'APPROVED') {
            $message = null;
            $label = 'Revision '.$revision->shortCode();
            if (! app(ApprovalGate::class)->passes('pattern.approve_revision', 'PatternRevision', (string) $revision->id, $label, $message)) {
                return back()->with('warning', $message);
            }
        }
        $from = $revision->status;
        $revision->update(['status' => $data['status']]);

        return back()->with('success', 'Revision '.$revision->shortCode()." is now {$data['status']} (was {$from}).");
    }

    public function editRevision(Pattern $pattern, PatternRevision $revision)
    {
        abort_unless($revision->pattern_id === $pattern->id, 404);

        return view('patterns.revision-edit', ['pattern' => $pattern, 'revision' => $revision, 'partingTypes' => PatternRevision::PARTING_TYPES]);
    }

    /**
     * Fills in or corrects the descriptive data of a revision: weights, drawing revision, allowances, cost, dates and notes.
     * A blank can be filled freely; changing or clearing a value already recorded needs a reason, which is audited. The code,
     * status, current flag and mould layout are not touched here.
     */
    public function updateRevisionDetails(Request $request, Pattern $pattern, PatternRevision $revision): RedirectResponse
    {
        abort_unless($revision->pattern_id === $pattern->id, 404);
        $data = $request->validate([
            'drawing_revision' => ['nullable', 'string', 'max:40'],
            'shrinkage_percent' => ['nullable', 'numeric', 'min:0', 'max:9.999'],
            'tolerance_grade' => ['nullable', 'integer', Rule::in(PatternRevision::TOLERANCE_GRADES)],
            'machining_allowance_grade' => ['nullable', Rule::in(PatternRevision::ALLOWANCE_GRADES)],
            'shrinkage_note' => ['nullable', 'string', 'max:2000'],
            'modification_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'cost_note' => ['nullable', 'string', 'max:2000'],
            'casting_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'pour_weight_kg' => array_merge(['nullable', 'numeric', 'gt:0', 'max:99999999.9999'], $request->filled('casting_weight_kg') ? ['gte:casting_weight_kg'] : []),
            'pieces_per_mould' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'parting_type' => ['nullable', Rule::in(PatternRevision::PARTING_TYPES)],
            'pattern_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'change_description' => ['nullable', 'string', 'max:2000'],
            'engineering_notes' => ['nullable', 'string'],
            'correction_reason' => ['nullable', 'string', 'max:500'],
        ], [
            'pour_weight_kg.gte' => 'The pour weight includes gating and risers, so it cannot be less than the casting weight.',
        ]);
        $reason = trim((string) Arr::pull($data, 'correction_reason'));

        $revision->fill($data);
        $changes = collect($revision->getDirty())->mapWithKeys(fn ($new, $field) => [$field => ['old' => $revision->getOriginal($field), 'new' => $new]]);
        if ($changes->isEmpty()) {
            return redirect()->route('patterns.show', $pattern)->with('success', "Nothing changed on {$revision->code}.");
        }

        $corrected = $changes->filter(fn ($change) => $change['old'] !== null && $change['old'] !== '')->keys();
        if ($corrected->isEmpty()) {
            $revision->save();
        } else {
            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages(['correction_reason' => 'Say why the recorded '.$corrected->map(fn ($f) => str_replace('_', ' ', $f))->implode(', ').' '.($corrected->count() === 1 ? 'is' : 'are').' being changed.']);
            }
            $revision->saveQuietly();
            app(Audit::class)->record('UPDATED', 'PatternRevision', $revision->id, $revision->code, $changes->all(), 'Details corrected: '.$reason);
        }

        return redirect()->route('patterns.show', $pattern)->with('success', "Details of {$revision->code} saved.");
    }

    public function updateLayout(Request $request, Pattern $pattern, PatternRevision $revision): RedirectResponse
    {
        abort_unless($revision->pattern_id === $pattern->id, 404);
        $message = DB::transaction(function () use ($request, $revision) {
            $layout = app(RevisionLayout::class)->save($revision, $this->validatedLayout($request), $request->input('correction_reason'));
            $sizes = $this->syncFlaskSizes($request, $revision);

            return trim(($layout ?? '').' '.($sizes ?? '')) ?: null;
        });

        return redirect()->route('patterns.show', $pattern)->with('success', $message ?? "Mould layout of {$revision->code} unchanged.");
    }

    /**
     * The flask sizes a revision fits, when its form sent them; the one it was designed for is marked. A single size is the
     * designed-for size. The change is kept in the audit trail. Returns what happened, or null when nothing changed.
     */
    private function syncFlaskSizes(Request $request, PatternRevision $revision): ?string
    {
        if (! $request->has('flask_sizes_sent')) {
            return null;
        }
        $d = $request->validate([
            'flask_size_ids' => ['nullable', 'array'], 'flask_size_ids.*' => ['uuid', Rule::exists('flask_sizes', 'id')],
            'primary_flask_size_id' => ['nullable', 'uuid', Rule::exists('flask_sizes', 'id')],
        ]);
        $ids = array_values(array_unique($d['flask_size_ids'] ?? []));
        $primary = $d['primary_flask_size_id'] ?? (count($ids) === 1 ? $ids[0] : null);
        if ($primary && ! in_array($primary, $ids, true)) {
            $ids[] = $primary;
        }
        $describe = fn ($sizes) => $sizes->map(fn ($s) => $s->name.($s->pivot->is_primary ? ' (designed for)' : ''))->implode(', ') ?: 'none';
        $before = $describe($revision->flaskSizes()->get());
        // Detached first: the database allows one designed-for size per revision at any moment.
        $revision->flaskSizes()->detach();
        $revision->flaskSizes()->attach(collect($ids)->mapWithKeys(fn ($id) => [$id => ['is_primary' => $id === $primary]])->all());
        $after = $describe($revision->flaskSizes()->get());
        if ($before === $after) {
            return null;
        }
        app(Audit::class)->record('UPDATED', 'PatternRevision', $revision->id, $revision->code, ['flask_sizes' => ['old' => $before, 'new' => $after]]);

        return "Flask sizes of {$revision->code}: {$after}.";
    }

    /**
     * The mould layout fields of a revision. A mould is closed by a bottom and a top flask, so N sections hold castings at no
     * more than N − 1 joints; a single block (a flaskless line) has one.
     *
     * @return array<string, mixed>
     */
    private function validatedLayout(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'nullable' : 'required';
        $data = $request->validate([
            'mould_arrangement' => [$required, Rule::in(array_keys(PatternRevision::ARRANGEMENTS))],
            'layers_per_mould' => ['nullable', 'integer', 'min:1', 'max:50'],
            'casting_levels' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cavities_per_mould' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'runner_weight_per_level_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'sprue_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'poured_weight_per_mould_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
        ]);
        if (! array_key_exists('mould_arrangement', $data) || $data['mould_arrangement'] === null) {
            return array_filter($data, fn ($v) => $v !== null);
        }
        $sections = (int) ($data['layers_per_mould'] ?? ($data['mould_arrangement'] === 'VERTICAL' ? 1 : 2));
        $data['layers_per_mould'] = $sections;
        $data['casting_levels'] = (int) ($data['casting_levels'] ?? max(1, $sections - 1));
        if ($data['casting_levels'] > max(1, $sections - 1)) {
            throw ValidationException::withMessages(['casting_levels' => 'A mould of '.$sections.' flask '.($sections === 1 ? 'section' : 'sections').' has at most '.max(1, $sections - 1).' '.(max(1, $sections - 1) === 1 ? 'joint' : 'joints').' holding castings: the bottom and top flasks close it.']);
        }

        return $data;
    }

    /**
     * Records a new revision of the tooling geometry and makes it current; the previous current revision is superseded and
     * kept. Repairs and repainting are not revisions: they go in the maintenance log.
     */
    public function storeRevision(Request $request, Pattern $pattern): RedirectResponse
    {
        $data = $request->validate([
            'change_description' => [$pattern->revisions()->exists() ? 'required' : 'nullable', 'string', 'max:2000'],
            'drawing_revision' => ['nullable', 'string', 'max:40'],
            'shrinkage_note' => ['nullable', 'string', 'max:2000'],
            'shrinkage_percent' => ['nullable', 'numeric', 'min:0', 'max:9.999'],
            'tolerance_grade' => ['nullable', 'integer', Rule::in(PatternRevision::TOLERANCE_GRADES)],
            'machining_allowance_grade' => ['nullable', Rule::in(PatternRevision::ALLOWANCE_GRADES)],
            'cavities_per_mould' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'modification_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'effective_from' => ['nullable', 'date'],
            'cost_note' => ['nullable', 'string', 'max:2000'],
            'casting_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'pour_weight_kg' => array_merge(['nullable', 'numeric', 'gt:0', 'max:99999999.9999'], $request->filled('casting_weight_kg') ? ['gte:casting_weight_kg'] : []),
            'pieces_per_mould' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('foundry.attachments.max_kb')],
        ], [
            'change_description.required' => 'Say what changed in the tooling geometry or layout.',
            'pour_weight_kg.gte' => 'The pour weight includes gating and risers, so it cannot be less than the casting weight.',
            'photo.max' => 'The photo may be at most '.Uploads::megabytes((int) config('foundry.attachments.max_kb')).'.',
            'photo.uploaded' => Uploads::failedMessage(),
            'photo.image' => 'The photo must be a JPG, PNG or WebP picture.',
            'photo.mimes' => 'The photo must be a JPG, PNG or WebP picture.',
        ]);
        $data = array_merge($data, $this->validatedLayout($request, partial: true));
        $data['change_description'] = $data['change_description'] ?? 'Initial revision';
        $previous = $pattern->currentRevision;
        $copyLayout = true;   // a new revision starts with the previous cavity map; change it on the grid

        $revision = DB::transaction(function () use ($pattern, $data, $request, $copyLayout) {
            $revision = $pattern->addRevision(Arr::except($data, ['photo']), $copyLayout);
            if ($request->hasFile('photo')) {
                $error = AttachmentController::saveFiles([$request->file('photo')], 'pattern_revision', $revision->id, 'PHOTO', "{$revision->code} photo");
                if ($error) {
                    throw ValidationException::withMessages(['photo' => $error]);
                }
            }

            return $revision;
        });

        return redirect()
            ->route('patterns.show', $pattern)
            ->with('success', "Revision {$revision->code} is now current.".($previous ? " {$previous->code} is superseded and kept." : '')
                .(($copied = $revision->cavityMap()->count()) ? " Its cavity map ({$copied} ".($copied === 1 ? 'cavity' : 'cavities').') was carried over; change it on the cavity map if the new layout differs.' : ''));
    }
}
