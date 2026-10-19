<?php

namespace App\Domains\Maintenance\Http\Controllers;

use App\Domains\Maintenance\Support\CalibrationRules;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalibrationRulesController extends Controller
{
    public function edit(): View
    {
        return view('settings.rules', [
            'title' => 'Calibration rules', 'intro' => 'What happens when a result is recorded with an instrument that is not in calibration.',
            'rules' => CalibrationRules::RULES, 'values' => collect(CalibrationRules::RULES)->map(fn ($r, $k) => CalibrationRules::get($k))->all(),
            'action' => route('settings.calibration'), 'backUrl' => route('gauges.index'), 'backLabel' => 'Gauges',
            'note' => 'A small foundry can start with a warning and tighten this to "refuse" when its certification calls for it. The choice applies to inspection results and chemistry readings that name an instrument from the gauge register.',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (CalibrationRules::RULES as $key => [, , $options]) {
            $rules[$key] = $options === null ? ['required', 'integer', 'min:0', 'max:365'] : ['required', Rule::in(array_keys($options))];
        }
        foreach ($request->validate($rules) as $key => $value) {
            CalibrationRules::set($key, (string) $value);
        }

        return back()->with('success', 'Calibration rules saved.');
    }
}
