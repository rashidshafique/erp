<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Pattern;
use App\Models\ProductRevision;
use App\Models\SandSystem;
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
        $patterns = Pattern::query()
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
            'baselineOnly' => Pattern::whereHas('currentRevision', fn ($r) => $r->baselineWithoutDrawing())->count(),
        ]);
    }

    public function create()
    {
        return view('patterns.create', [
            'pattern' => new Pattern,
            'patternTypes' => [
                'MATCHPLATE',
                'COPE_DRAG',
                'LOOSE_PATTERN',
                'SPLIT_PATTERN',
                'OTHER',
            ],
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
                Rule::in(['MATCHPLATE', 'COPE_DRAG', 'LOOSE_PATTERN', 'SPLIT_PATTERN', 'OTHER']),
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

    /** The "also suits" sands, without the one the pattern was made for. */
    private function otherSands(array $data): ?array
    {
        $own = $data['sand_system'] ?? null;
        $others = array_values(array_filter($data['compatible_sands'] ?? [], fn ($x) => $x !== $own));

        return $others ?: null;
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

        return view('patterns.show', compact('pattern', 'productRevisions', 'revisionPhotos'));
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
        $previous = $pattern->currentRevision;
        $copyLayout = $request->boolean('copy_layout');

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
                .(($copied = $revision->cavityMap()->count()) ? " {$copied} ".($copied === 1 ? 'cavity was' : 'cavities were').' copied from the old layout; check and mark each one reviewed.' : ''));
    }
}
