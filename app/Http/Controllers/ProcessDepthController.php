<?php

namespace App\Http\Controllers;

use App\Support\ProcessDepth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProcessDepthController extends Controller
{
    public function edit(): View
    {
        return view('settings.process-depth', ['presets' => ProcessDepth::PRESETS, 'current' => ProcessDepth::current(), 'rules' => ProcessDepth::rules(), 'values' => ProcessDepth::values()]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (! $request->filled('preset')) {
            ProcessDepth::save($request->validate(collect(ProcessDepth::rules())->map(fn ($r) => ['sometimes', 'required', Rule::in(array_keys($r[1]))])->all()));

            return back()->with('success', 'Process rules saved.');
        }
        $preset = $request->validate(['preset' => ['required', Rule::in(array_keys(ProcessDepth::PRESETS))]])['preset'];
        ProcessDepth::apply($preset);

        return back()->with('success', ProcessDepth::PRESETS[$preset][0].' process depth applied.');
    }
}
