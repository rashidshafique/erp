<?php

namespace App\Http\Controllers;

use App\Models\ProcessControlPlan;
use App\Models\Product;
use App\Models\ProductionStageDefinition;
use App\Models\ProductRevision;
use App\Services\Production\ControlPlans;
use App\Support\ControlPlanRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ControlPlanController extends Controller
{
    public function __construct(private ControlPlans $plans) {}

    public function rules(): View
    {
        return view('settings.rules', [
            'title' => 'Control plan rules', 'intro' => 'How control plans are checked and what a pattern change does to them.',
            'rules' => ControlPlanRules::RULES, 'values' => collect(ControlPlanRules::RULES)->map(fn ($r, $k) => ControlPlanRules::get($k))->all(),
            'action' => route('settings.control-plans'), 'backUrl' => route('settings.process'), 'backLabel' => 'Our process',
            'note' => 'Every product follows Our process until a revision is given its own plan. A small foundry can leave the defaults: a pattern change only asks someone to confirm the plan still holds. Full PPAP asks for a new, approved revision.',
        ]);
    }

    public function saveRules(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (ControlPlanRules::RULES as $key => [, , $options]) {
            $rules[$key] = ['sometimes', 'required', Rule::in(array_keys($options))];
        }
        foreach ($request->validate($rules) as $key => $value) {
            ControlPlanRules::set($key, $value);
        }

        return back()->with('success', 'Control plan rules saved.');
    }

    public function saveDefault(Request $request): RedirectResponse
    {
        $this->plans->saveItems($this->plans->defaultPlan(), $this->rows($request));

        return back()->with('success', 'Default control plan saved. Every product that follows Our process uses it.');
    }

    public function customise(Product $product, ProductRevision $revision): RedirectResponse
    {
        $this->plans->customise($this->plan($product, $revision));

        return back()->with('success', 'Revision '.$revision->revision.' now has its own control plan, copied from Our process. Edit the rows, then approve it.');
    }

    public function save(Request $request, Product $product, ProductRevision $revision): RedirectResponse
    {
        $plan = $this->plan($product, $revision);
        abort_if($plan->follows_default, 422, 'This revision follows Our process; give it its own plan first.');
        $this->plans->saveItems($plan, $this->rows($request));

        return back()->with('success', 'Control plan saved as a draft. Approve it to use it.');
    }

    public function approve(Request $request, Product $product, ProductRevision $revision): RedirectResponse
    {
        $this->plans->approve($this->plan($product, $revision), $request->validate(['name' => ['required', 'string', 'max:100']])['name']);

        return back()->with('success', 'Control plan approved.');
    }

    public function confirm(Request $request, Product $product, ProductRevision $revision): RedirectResponse
    {
        $this->plans->confirmReview($this->plan($product, $revision), $request->validate(['name' => ['required', 'string', 'max:100']])['name']);

        return back()->with('success', 'Control plan confirmed after the pattern change.');
    }

    private function plan(Product $product, ProductRevision $revision): ProcessControlPlan
    {
        abort_unless($revision->product_id === $product->id, 404);

        return $this->plans->forRevision($revision);
    }

    /** @return list<array<string, mixed>> */
    private function rows(Request $request): array
    {
        $data = $request->validate([
            'rows' => ['nullable', 'array', 'max:100'],
            'rows.*.parameter_name' => ['nullable', 'string', 'max:200'],
            'rows.*.stage_definition_id' => ['nullable', Rule::exists(ProductionStageDefinition::class, 'id')],
            'rows.*.minimum_value' => ['nullable', 'numeric'],
            'rows.*.target_value' => ['nullable', 'numeric'],
            'rows.*.maximum_value' => ['nullable', 'numeric'],
            'rows.*.unit' => ['nullable', 'string', 'max:40'],
            'rows.*.control_method' => ['nullable', 'string', 'max:500'],
            'rows.*.monitoring_frequency' => ['nullable', 'string', 'max:50'],
            'rows.*.reaction_plan' => ['nullable', 'string', 'max:1000'],
        ]);

        return array_map(fn ($r) => array_map(fn ($v) => $v === '' ? null : $v, $r), $data['rows'] ?? []);
    }
}
