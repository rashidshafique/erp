<?php

namespace App\Domains\Tooling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CoreRequirement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Which cores a product revision needs, and how many to a casting. */
class CoreRequirementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'core_definition_id' => ['required', 'uuid', Rule::exists('core_definitions', 'id')->whereNull('deleted_at')],
            'product_revision_id' => ['required', 'uuid', Rule::exists('product_revisions', 'id')],
            'quantity_per_casting' => ['required', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['core_definition_id.required' => 'Choose a core.', 'product_revision_id.required' => 'Choose a product revision.']);

        $requirement = CoreRequirement::updateOrCreate(
            ['core_definition_id' => $data['core_definition_id'], 'product_revision_id' => $data['product_revision_id']],
            ['quantity_per_casting' => $data['quantity_per_casting'], 'notes' => $data['notes'] ?? null],
        );
        $requirement->load('coreDefinition', 'productRevision.product');
        $message = "{$requirement->coreDefinition->code} needed {$requirement->quantity_per_casting} per casting of {$requirement->productRevision->product->code}.";
        if (! $requirement->coreDefinition->hasActiveBox()) {
            $message .= ' No core box makes it yet.';
        }

        return back()->with('success', $message);
    }

    public function destroy(CoreRequirement $requirement): RedirectResponse
    {
        $requirement->delete();

        return back()->with('success', 'Core removed from the product.');
    }
}
