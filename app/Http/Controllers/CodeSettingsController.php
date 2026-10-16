<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\Codes\CodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CodeSettingsController extends Controller
{
    public function edit(CodeGenerator $gen)
    {
        $previews = [];
        foreach (CodeGenerator::KINDS as $k => $meta) {
            $previews[$k] = $gen->preview($k);
        }
        $nextMark = $gen->toBase((int) (\DB::table('code_sequences')->where('key', 'mark')->value('next_value') ?? 1), CodeGenerator::markAlphabet(), CodeGenerator::markLength());

        return view('settings.codes', ['kinds' => CodeGenerator::KINDS, 'styles' => CodeGenerator::STYLES, 'previews' => $previews, 'nextMark' => $nextMark]);
    }

    public function update(Request $request)
    {
        $d = $request->validate([
            'code_style' => ['required', Rule::in(array_keys(CodeGenerator::STYLES))],
            'code_digits' => ['required', 'integer', 'between:3,8'],
            'mark_style' => ['required', Rule::in(array_keys(CodeGenerator::STYLES))],
            'mark_length' => ['required', 'integer', 'between:3,10'],
            'prefix' => ['array'], 'prefix.*' => ['nullable', 'string', 'regex:/^[A-Za-z]{1,5}$/'],
        ], ['prefix.*.regex' => 'Prefixes are 1 to 5 letters.']);

        AppSetting::setValue('codes.code_style', $d['code_style'], 'Style of generated product, pattern and tooling codes');
        AppSetting::setValue('codes.code_digits', (string) $d['code_digits'], 'Digits in the running number of generated codes');
        AppSetting::setValue('codes.mark_style', $d['mark_style'], 'Characters allowed in engraving marks');
        AppSetting::setValue('codes.mark_length', (string) $d['mark_length'], 'Length of engraving marks');
        AppSetting::setValue('codes.mark_avoid_lookalikes', $request->boolean('mark_avoid_lookalikes') ? '1' : '0', 'Leave out look-alike characters (0/O, 1/I, Q)');
        foreach (array_keys(CodeGenerator::KINDS) as $k) {
            $p = $d['prefix'][$k] ?? null;
            AppSetting::setValue("codes.prefix.$k", $p ? strtoupper($p) : CodeGenerator::KINDS[$k][1]);
        }

        return back()->with('success', 'Code settings saved. They apply to codes created from now on; existing codes and marks never change.');
    }
}
