<?php

namespace App\Http\Controllers;

use App\Models\MouldCavity;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use App\Models\Product;
use App\Services\PatternCavityMap;
use App\Services\Patterns\RevisionLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatternCavityController extends Controller
{
    public function __construct(private PatternCavityMap $map) {}

    /**
     * Saves a revision's whole cavity map from its grid: the cavity count, and one row per cavity. A row with a product is
     * created or updated; a row left without one, or beyond the count, is removed. A cavity already used on an assembled mould
     * keeps its product. Every row is checked first; nothing is saved if one fails.
     */
    public function saveMap(Request $request, Pattern $pattern, PatternRevision $revision, RevisionLayout $layout): RedirectResponse
    {
        abort_unless($revision->pattern_id === $pattern->id, 404);
        $request->validate([
            'cavities_per_mould' => ['required', 'integer', 'min:1', 'max:200'],
            'rows' => ['array'],
            'rows.*.product_revision_id' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                // A product with no revision is offered as "product:<id>"; it gets its first revision when the map is saved.
                $isProduct = is_string($value) && str_starts_with($value, 'product:');
                $id = $isProduct ? substr($value, 8) : $value;
                if (! is_string($id) || ! Str::isUuid($id) || ! DB::table($isProduct ? 'products' : 'product_revisions')->where('id', $id)->when($isProduct, fn ($q) => $q->whereNull('deleted_at'))->exists()) {
                    $fail('Choose a product from the list.');
                }
            }],
            'rows.*.cavity_role' => ['nullable', Rule::in(['WHOLE', 'PART'])],
            'rows.*.component_label' => ['nullable', 'string', 'max:100'],
            'rows.*.pieces_per_product' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'rows.*.casting_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'rows.*.poured_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'rows.*.notes' => ['nullable', 'string', 'max:2000'],
            'rows.*.cavity_mark' => ['nullable', 'string', 'max:20'],
        ], ['cavities_per_mould.required' => 'Enter how many cavities the layout has.', 'rows.*.cavity_mark.max' => 'A cavity mark is at most 20 characters.']);
        $count = (int) $request->input('cavities_per_mould');
        $rows = (array) $request->input('rows', []);
        $existing = $revision->cavityMap()->get()->keyBy('cavity_number');
        $used = MouldCavity::where('pattern_revision_id', $revision->id)->distinct()->pluck('cavity_number')->flip();
        $probe = (clone $revision)->forceFill(['cavities_per_mould' => $count]);

        $errors = [];
        $save = [];
        $remove = [];
        $marks = [];
        for ($n = 1; $n <= $count; $n++) {
            $row = array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v, (array) ($rows[$n] ?? []));
            $current = $existing[$n] ?? null;
            if (blank($row['product_revision_id'] ?? null)) {
                if ($current && isset($used[$n])) {
                    $errors["rows.{$n}.product_revision_id"] = "Cavity {$n}: it is used on an assembled mould, so it cannot be cleared.";
                } elseif ($current) {
                    $remove[] = $current;
                }

                continue;
            }
            $data = [
                'cavity_number' => $n, 'product_revision_id' => $row['product_revision_id'], 'cavity_role' => $row['cavity_role'] ?? 'WHOLE',
                'component_label' => ($row['cavity_role'] ?? 'WHOLE') === 'PART' ? ($row['component_label'] ?? null) : null,
                'pieces_per_product' => (int) ($row['pieces_per_product'] ?? 1), 'casting_weight_kg' => $row['casting_weight_kg'] ?? null,
                'poured_weight_kg' => $row['poured_weight_kg'] ?? null, 'notes' => $row['notes'] ?? null,
                'cavity_mark' => isset($row['cavity_mark']) ? mb_strtoupper(trim($row['cavity_mark'])) : null,
            ];
            if ($data['cavity_mark'] !== null) {
                if (isset($marks[$data['cavity_mark']])) {
                    $errors["rows.{$n}.cavity_mark"] = "Cavity {$n}: mark {$data['cavity_mark']} is already on cavity {$marks[$data['cavity_mark']]}.";
                }
                $marks[$data['cavity_mark']] ??= $n;
            }
            try {
                $this->map->assertDefinable($probe, str_starts_with((string) $data['product_revision_id'], 'product:') ? ['product_revision_id' => null] + $data : $data, $current?->id);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $errors["rows.{$n}.{$field}"] = "Cavity {$n}: {$messages[0]}";
                }
            }
            if ($current && isset($used[$n]) && $current->product_revision_id !== $data['product_revision_id']) {
                $errors["rows.{$n}.product_revision_id"] = "Cavity {$n}: it is used on an assembled mould, so its product cannot change. Record a new revision instead.";
            }
            $save[] = [$current, $data];
        }
        foreach ($existing as $n => $cavity) {
            if ($n > $count) {
                if (isset($used[$n])) {
                    $errors['cavities_per_mould'] = "Cavity {$n} is used on an assembled mould, so the count cannot go below {$n}.";
                } else {
                    $remove[] = $cavity;
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $layoutMessage = DB::transaction(function () use ($revision, $count, $save, $remove, $layout, $request) {
            $message = $layout->save($revision, ['cavities_per_mould' => $count], $request->input('correction_reason'));
            foreach ($remove as $cavity) {
                $cavity->delete();
            }
            // Marks moving between cavities are cleared first, so a swap never meets itself in the unique index.
            $moving = collect($save)->filter(fn ($pair) => $pair[0] && $pair[0]->cavity_mark !== null && $pair[0]->cavity_mark !== $pair[1]['cavity_mark'])->map(fn ($pair) => $pair[0]->id);
            if ($moving->isNotEmpty()) {
                PatternRevisionCavity::whereIn('id', $moving)->update(['cavity_mark' => null]);
            }
            foreach ($save as [$current, $data]) {
                if (str_starts_with((string) $data['product_revision_id'], 'product:')) {
                    $data['product_revision_id'] = Product::findOrFail(substr($data['product_revision_id'], 8))->linkableRevision()->id;
                }
                $current ? $current->update($data + ['needs_review' => false]) : $revision->cavityMap()->create($data);
            }

            return $message;
        });
        $linked = count($save);

        return redirect()->route('patterns.show', $pattern)->with('success', "Cavity map of {$revision->code} saved: {$linked} of {$count} ".($count === 1 ? 'cavity' : 'cavities').' linked to a product.'.($layoutMessage && str_contains($layoutMessage, 'corrected') ? ' '.$layoutMessage : ''));
    }

    /**
     * Saves the cavity map of a pattern that has no revision yet: its first revision (Rev 0) is recorded together with the map,
     * and not at all if the map is refused.
     */
    public function saveFirstMap(Request $request, Pattern $pattern, RevisionLayout $layout): RedirectResponse
    {
        return DB::transaction(fn () => $this->saveMap(
            $request,
            $pattern,
            $pattern->currentRevision()->first() ?? $pattern->addRevision(['change_description' => 'First revision, recorded with the cavity map']),
            $layout,
        ));
    }

    /** Maps a cavity on the revision picked in the form, which defaults to the pattern's current revision. */
    public function add(Request $request, Pattern $pattern): RedirectResponse
    {
        $revisionId = $request->validate([
            'pattern_revision_id' => ['required', 'uuid', Rule::exists('pattern_revisions', 'id')->where('pattern_id', $pattern->id)],
        ], ['pattern_revision_id.exists' => 'Choose a revision of this pattern.'])['pattern_revision_id'];

        return $this->store($request, $pattern, PatternRevision::findOrFail($revisionId));
    }

    public function store(Request $request, Pattern $pattern, PatternRevision $revision): RedirectResponse
    {
        abort_unless($revision->pattern_id === $pattern->id, 404);

        $data = $this->validated($request);
        $this->map->assertDefinable($revision, $data);
        $revision->cavityMap()->create($data);

        $response = redirect()->route('patterns.show', $pattern)
            ->with('success', "Cavity {$data['cavity_number']} of {$revision->label()} linked to its product.");

        // Mapping a superseded revision is allowed (its moulds still exist), but it is rarely what was meant.
        return $revision->isSuperseded() ? $response->with('warning', $this->supersededWarning($revision)) : $response;
    }

    /** Confirms a cavity copied from the previous layout still holds on the new revision. */
    public function review(Pattern $pattern, PatternRevision $revision, PatternRevisionCavity $cavity): RedirectResponse
    {
        abort_unless($revision->pattern_id === $pattern->id && $cavity->pattern_revision_id === $revision->id, 404);
        $cavity->update(['needs_review' => false]);

        return redirect()->route('patterns.show', $pattern)->with('success', "Cavity {$cavity->cavity_number} of {$revision->code} marked as reviewed.");
    }

    /** The warning shown against cavities on a superseded revision. */
    public static function supersededWarning(PatternRevision $revision): string
    {
        $current = PatternRevision::where('pattern_id', $revision->pattern_id)->where('is_current', true)->value('code');

        return "{$revision->code} was superseded on {$revision->superseded_at->displayDate()}".($current ? " by {$current}" : '')
            .'. Its cavities are kept for traceability; new moulds should use the current revision.';
    }

    public function update(Request $request, Pattern $pattern, PatternRevision $revision, PatternRevisionCavity $cavity): RedirectResponse
    {
        abort_unless($revision->pattern_id === $pattern->id && $cavity->pattern_revision_id === $revision->id, 404);

        $data = $this->validated($request);
        $this->map->assertDefinable($revision, $data, $cavity->id);

        // Changing the product of a cavity already used on a mould would break traceability.
        if ($cavity->product_revision_id !== $data['product_revision_id']
            && MouldCavity::where('pattern_revision_id', $revision->id)
                ->where('cavity_number', $cavity->cavity_number)->exists()) {
            return back()->withErrors([
                'product_revision_id' => 'This cavity is already used on a mould. Add a new pattern revision instead of changing its product.',
            ])->withInput();
        }

        $cavity->update($data);

        return redirect()->route('patterns.show', $pattern)->with('success', 'Cavity link updated.');
    }

    public function destroy(Pattern $pattern, PatternRevision $revision, PatternRevisionCavity $cavity): RedirectResponse
    {
        abort_unless($revision->pattern_id === $pattern->id && $cavity->pattern_revision_id === $revision->id, 404);

        if (MouldCavity::where('pattern_revision_id', $revision->id)
            ->where('cavity_number', $cavity->cavity_number)->exists()) {
            return back()->withErrors(['cavity' => 'This cavity is used on a mould and cannot be removed from the map.']);
        }

        $cavity->delete();

        return redirect()->route('patterns.show', $pattern)->with('success', 'Cavity link removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'cavity_number' => ['required', 'integer', 'min:1', 'max:10000'],
            'product_revision_id' => ['required', 'uuid', Rule::exists('product_revisions', 'id')],
            'cavity_role' => ['required', Rule::in(['WHOLE', 'PART'])],
            'component_label' => ['nullable', 'string', 'max:100'],
            'pieces_per_product' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'casting_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'poured_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999.9999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['pieces_per_product'] = $data['pieces_per_product'] ?? 1;
        if ($data['cavity_role'] === 'WHOLE') {
            $data['component_label'] = null;
        }

        return $data;
    }
}
