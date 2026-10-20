<?php

namespace App\Http\Controllers;

use App\Support\WorkListRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkListRulesController extends Controller
{
    public function edit(): View
    {
        return view('settings.rules', [
            'title' => 'Work list rules', 'intro' => 'How the moulding work list gates the moulding floor.',
            'rules' => WorkListRules::RULES, 'values' => collect(WorkListRules::RULES)->map(fn ($r, $k) => WorkListRules::get($k))->all(),
            'action' => route('settings.work-lists'), 'backUrl' => route('work-lists.index'), 'backLabel' => 'Work lists',
            'note' => 'Where moulders often start before the list is issued, allow it. Runs started that way, or with no list at all, are marked on the run so planning can see how much work was not planned in advance.',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (WorkListRules::RULES as $key => [, , $options]) {
            $rules[$key] = ['sometimes', 'required', Rule::in(array_keys($options))];
        }
        foreach ($request->validate($rules) as $key => $value) {
            WorkListRules::set($key, $value);
        }

        return back()->with('success', 'Work list rules saved.');
    }
}
