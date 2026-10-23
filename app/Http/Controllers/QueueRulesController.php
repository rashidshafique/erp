<?php

namespace App\Http\Controllers;

use App\Support\QueueRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QueueRulesController extends Controller
{
    public function edit(): View
    {
        $rules = QueueRules::all();

        return view('settings.rules', [
            'title' => 'Production queue rules', 'intro' => 'When a step of the production queue is flagged as work piling up.',
            'rules' => $rules, 'values' => collect($rules)->map(fn ($r, $k) => QueueRules::get($k))->all(),
            'action' => route('settings.queue'), 'backUrl' => route('lots.queue'), 'backLabel' => 'Production queue',
            'note' => 'A step turns amber, then red, by the age of its oldest lot since the pour. A step over its limit is shown red whatever its age. Leave a limit at 0 for no limit.',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        $names = [];
        foreach (QueueRules::all() as $key => [, $label, $options]) {
            $rules[$key] = $options === null ? ['nullable', 'integer', 'min:0', 'max:100000'] : ['required', Rule::in(array_keys($options))];
            $names[$key] = strtolower($label);
        }
        $rules['amber_hours'] = ['required', 'integer', 'min:1', 'max:2000'];
        $rules['red_hours'] = ['required', 'integer', 'min:1', 'max:2000', 'gt:amber_hours'];

        foreach ($request->validate($rules, ['red_hours.gt' => 'Red must come later than amber.'], $names) as $key => $value) {
            QueueRules::set($key, (string) ($value ?? 0));   // a blank limit is no limit
        }

        return back()->with('success', 'Production queue rules saved.');
    }
}
