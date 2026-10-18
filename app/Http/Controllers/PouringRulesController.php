<?php

namespace App\Http\Controllers;

use App\Support\PouringRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PouringRulesController extends Controller
{
    public function edit(): View
    {
        return view('settings.rules', [
            'title' => 'Pouring rules', 'intro' => 'How pouring, first iron and out-of-limit samples are handled.',
            'rules' => PouringRules::RULES, 'values' => collect(PouringRules::RULES)->map(fn ($r, $k) => PouringRules::get($k))->all(),
            'action' => route('settings.pouring'), 'backUrl' => route('pours.index'), 'backLabel' => 'Pours',
            'note' => 'A cupola runs continuously and is sampled while it melts, so its checks are advisory by default: the pour goes ahead, what was missing is recorded on the pour, and a sample that later fails holds the lots poured in the affected period. Induction heats and ductile iron are tapped as a batch and tested first, so they stay enforced.',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (PouringRules::RULES as $key => [, , $options]) {
            $rules[$key] = $options === null ? ['required', 'integer', 'min:0', 'max:1440'] : ['required', Rule::in(array_keys($options))];
        }
        foreach ($request->validate($rules) as $key => $value) {
            PouringRules::set($key, (string) $value);
        }

        return back()->with('success', 'Pouring rules saved.');
    }
}
