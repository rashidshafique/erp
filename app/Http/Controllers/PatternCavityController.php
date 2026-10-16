<?php

namespace App\Http\Controllers;

use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use App\Services\PatternCavityMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatternCavityController extends Controller
{
    public function __construct(private PatternCavityMap $map)
    {
    }

    public function store(Request $request, Pattern $pattern, PatternRevision $revision): RedirectResponse
    {
        abort_unless($revision->pattern_id === $pattern->id, 404);

        $data = $this->validated($request);
        $this->map->assertDefinable($revision, $data);
        $revision->cavityMap()->create($data);

        return redirect()->route('patterns.show', $pattern)
            ->with('success', "Cavity {$data['cavity_number']} of revision {$revision->revision} linked to its product.");
    }

    public function update(Request $request, Pattern $pattern, PatternRevision $revision, PatternRevisionCavity $cavity): RedirectResponse
    {
        abort_unless($revision->pattern_id === $pattern->id && $cavity->pattern_revision_id === $revision->id, 404);

        $data = $this->validated($request);
        $this->map->assertDefinable($revision, $data, $cavity->id);

        // Changing the product of a cavity already used on a mould would break traceability.
        if ($cavity->product_revision_id !== $data['product_revision_id']
            && \App\Models\MouldCavity::where('pattern_revision_id', $revision->id)
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

        if (\App\Models\MouldCavity::where('pattern_revision_id', $revision->id)
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
