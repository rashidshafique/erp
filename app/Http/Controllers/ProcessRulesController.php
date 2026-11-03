<?php

namespace App\Http\Controllers;

use App\Support\FloorRules;
use App\Support\ProcessDepth;
use App\Support\ProcessRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Settings > Process rules: the Basic and Full presets, then every rule on its own. */
class ProcessRulesController extends Controller
{
    public function edit(Request $request): View
    {
        $advanced = $request->boolean('advanced');

        return view('settings.rules', [
            'presets' => ProcessDepth::PRESETS, 'current' => ProcessDepth::current(), 'sections' => ProcessRules::sections($advanced), 'advanced' => $advanced,
            'hidden' => $advanced ? 0 : collect(ProcessRules::sections(true))->flatten(1)->count() - collect(ProcessRules::sections())->flatten(1)->count(),
            'waiting' => collect(FloorRules::RULES)->keys()->filter(fn (string $rule) => FloorRules::waitsForQuickFlow($rule))->values()->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (! $request->filled('preset')) {
            ProcessRules::save($request->validate(ProcessRules::validation(), ['red_hours.gt' => 'Red must come later than amber.']));

            return back()->with('success', 'Rules saved.');
        }
        $preset = $request->validate(['preset' => ['required', Rule::in(array_keys(ProcessDepth::PRESETS))]])['preset'];
        ProcessDepth::apply($preset);

        return back()->with('success', ProcessDepth::PRESETS[$preset][0].' applied.');
    }
}
