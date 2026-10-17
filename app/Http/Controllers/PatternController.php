<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\MouldAssembly;
use App\Models\MouldCavity;
use App\Models\MouldingRun;
use App\Models\MouldingWorkListItem;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\ProductRevision;
use App\Models\SandSystem;
use App\Services\Patterns\RevisionLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatternController extends Controller
{
    public function index(Request $request)
    {
        $archived = $request->boolean('archived');
        $patterns = Pattern::query()
            ->when($archived, fn ($q) => $q->onlyTrashed())
            ->with('currentRevision')
            ->withCount('revisions')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->input('search'));

                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status'))
            )
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
            'baselineOnly' => Pattern::whereHas('currentRevision', fn ($r) => $r->baselineWithoutDrawing())->count(),
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
            'length_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'width_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'height_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'sand_system' => ['nullable', Rule::in(SandSystem::allNames())],
            'compatible_sands' => ['nullable', 'array'], 'compatible_sands.*' => [Rule::in(SandSystem::allNames())],
            'sand_note' => ['nullable', 'string', 'max:1000'],

            'revision' => ['required', 'string', 'max:20'],
            'revision_status' => ['required', Rule::in(['DRAFT', 'APPROVED', 'OBSOLETE'])],
            'parting_type' => ['nullable', 'string', 'max:30'],
            'cavities_per_mould' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'layers_per_mould' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'pattern_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'change_description' => ['nullable', 'string'],
            'engineering_notes' => ['nullable', 'string'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $pattern = DB::transaction(function () use ($data) {
            $pattern = Pattern::create([
                'code' => filled($data['code'] ?? null) ? strtoupper(trim($data['code'])) : null,
                'name' => trim($data['name']),
                'pattern_type' => $data['pattern_type'],
                'status' => $data['status'],
                'material' => $data['material'] ?? null,
                'length_mm' => $data['length_mm'] ?? null,
                'width_mm' => $data['width_mm'] ?? null,
                'height_mm' => $data['height_mm'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'sand_system' => $data['sand_system'] ?? null,
                'compatible_sands' => $this->otherSands($data),
                'sand_note' => $data['sand_note'] ?? null,
            ]);

            $pattern->revisions()->create([
                'revision' => trim($data['revision']),
                'status' => $data['revision_status'],
                'parting_type' => $data['parting_type'] ?? null,
                'cavities_per_mould' => $data['cavities_per_mould'] ?? null,
                'layers_per_mould' => $data['layers_per_mould'] ?? null,
                'pattern_weight_kg' => $data['pattern_weight_kg'] ?? null,
                'change_description' => $data['change_description'] ?? null,
                'engineering_notes' => $data['engineering_notes'] ?? null,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => $data['effective_to'] ?? null,
            ]);

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

    /** Corrects the physical tooling record. Revisions, their codes and their layouts are not touched. */
    public function update(Request $request, Pattern $pattern): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('patterns', 'code')->ignore($pattern->id)],
            'name' => ['required', 'string', 'max:255'],
            'pattern_type' => ['required', Rule::in(array_keys(Pattern::TYPES))],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'RETIRED'])],
            'material' => ['nullable', 'string', 'max:50'],
            'length_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'width_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'height_mm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['code'] = strtoupper(trim($data['code']));
        $data['name'] = trim($data['name']);
        $current = $pattern->currentRevision;
        $layout = $current && $request->filled('mould_arrangement') ? $this->validatedLayout($request) : null;

        // A new pattern type brings its usual arrangement, unless a different one was chosen for the current revision.
        $oldDefault = Pattern::TYPES[$pattern->pattern_type] ?? null;
        $newDefault = Pattern::TYPES[$data['pattern_type']] ?? 'OTHER';
        if ($current && $data['pattern_type'] !== $pattern->pattern_type && ($layout['mould_arrangement'] ?? $current->mould_arrangement) === $oldDefault) {
            $request->merge(['mould_arrangement' => $newDefault]);
            $layout = $this->validatedLayout($request);
        }

        $layoutMessage = DB::transaction(function () use ($pattern, $data, $current, $layout, $request) {
            $pattern->update($data);

            return $current && $layout ? app(RevisionLayout::class)->save($current, $layout, $request->input('correction_reason')) : null;
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
        $pattern->restore();

        return redirect()->route('patterns.show', $pattern)->with('success', "{$pattern->code} restored.");
    }

    /** Record which sand system a pattern was made for and which others it works on. */
    public function updateSand(Request $request, Pattern $pattern)
    {
        $data = $request->validate([
            'sand_system' => ['nullable', Rule::in(SandSystem::allNames())],
            'compatible_sands' => ['nullable', 'array'], 'compatible_sands.*' => [Rule::in(SandSystem::allNames())],
            'sand_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $pattern->update(['sand_system' => $data['sand_system'] ?? null, 'compatible_sands' => $this->otherSands($data), 'sand_note' => $data['sand_note'] ?? null]);

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
        ]);
        $revisionPhotos = Attachment::where('attachable_type', 'pattern_revision')->whereIn('attachable_id', $pattern->revisions->pluck('id'))
            ->whereNull('removed_at')->get()->groupBy('attachable_id');

        $productRevisions = ProductRevision::query()
            ->with('product')
            ->where('status', '!=', 'OBSOLETE')
            ->get()
            ->sortBy(fn ($r) => $r->product?->code.' '.$r->revision)
            ->values();

        $assembledRevisions = MouldCavity::whereIn('pattern_revision_id', $pattern->revisions->pluck('id'))->distinct()->pluck('pattern_revision_id')->all();

        return view('patterns.show', compact('pattern', 'productRevisions', 'revisionPhotos', 'assembledRevisions'));
    }

    /**
     * Sets how a revision is moulded: its arrangement, flask sections, casting levels and shared metal. Once moulds have been
     * assembled from it, a correction needs a reason (audited) and the assembled moulds keep the castings they recorded.
     */
    public function updateLayout(Request $request, Pattern $pattern, PatternRevision $revision): RedirectResponse
    {
        abort_unless($revision->pattern_id === $pattern->id, 404);
        $message = app(RevisionLayout::class)->save($revision, $this->validatedLayout($request), $request->input('correction_reason'));

        return redirect()->route('patterns.show', $pattern)->with('success', $message ?? "Mould layout of {$revision->code} unchanged.");
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
            'change_description' => ['required', 'string', 'max:2000'],
            'drawing_revision' => ['nullable', 'string', 'max:40'],
            'shrinkage_note' => ['nullable', 'string', 'max:2000'],
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
        ]);
        $data = array_merge($data, $this->validatedLayout($request, partial: true));
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
