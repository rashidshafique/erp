<?php

namespace App\Domains\Engineering\Http\Controllers;

use App\Domains\Engineering\Models\BomLine;
use App\Http\Controllers\Controller;
use App\Models\ProductRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BomLineController extends Controller
{
    public function store(Request $request, ProductRevision $revision): RedirectResponse
    {
        $data = $request->validate([
            'material_id' => ['required', 'uuid', Rule::exists('materials', 'id')->whereNull('deleted_at'),
                Rule::unique('bom_lines', 'material_id')->where('product_revision_id', $revision->id)],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'basis' => ['required', Rule::in(array_keys(BomLine::BASES))],
            'notes' => ['nullable', 'string', 'max:300'],
        ], ['material_id.unique' => 'That item is already on this revision\'s list; change its line instead.']);

        $revision->bomLines()->create($data);

        return $this->back($revision)->with('success', 'Item added to the bill of materials.');
    }

    public function update(Request $request, BomLine $line): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'basis' => ['required', Rule::in(array_keys(BomLine::BASES))],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);
        $line->update($data);

        return $this->back($line->productRevision)->with('success', 'Line saved.');
    }

    public function destroy(BomLine $line): RedirectResponse
    {
        $revision = $line->productRevision;
        $line->delete();

        return $this->back($revision)->with('success', 'Line removed.');
    }

    /** Copies another revision's list into this one when this one has none. */
    public function copy(Request $request, ProductRevision $revision): RedirectResponse
    {
        $data = $request->validate([
            'from_revision_id' => ['required', 'uuid', Rule::exists('product_revisions', 'id')->where('product_id', $revision->product_id)],
        ]);
        if ($revision->bomLines()->exists()) {
            return $this->back($revision)->withErrors(['bom' => 'This revision already has a list; remove its lines first.']);
        }
        $copied = BomLine::where('product_revision_id', $data['from_revision_id'])->get()
            ->each(fn (BomLine $l) => $revision->bomLines()->create($l->only(['material_id', 'quantity', 'basis', 'notes'])));

        return $this->back($revision)->with('success', $copied->count().' line(s) copied.');
    }

    private function back(ProductRevision $revision): RedirectResponse
    {
        return redirect()->to(route('products.show', $revision->product_id).'#bom');
    }
}
