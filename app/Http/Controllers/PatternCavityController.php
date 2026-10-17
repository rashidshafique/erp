<?php

namespace App\Http\Controllers;

use App\Models\MouldCavity;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use App\Services\PatternCavityMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatternCavityController extends Controller
{
    public function __construct(private PatternCavityMap $map) {}

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

        return "{$revision->code} was superseded on {$revision->superseded_at->format('d M Y')}".($current ? " by {$current}" : '')
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
