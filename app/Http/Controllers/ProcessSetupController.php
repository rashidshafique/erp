<?php

namespace App\Http\Controllers;

use App\Support\ProcessSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** "How you work": questions first, then the rules they set beside the rules in force, then apply. */
class ProcessSetupController extends Controller
{
    public function edit(): View
    {
        return view('settings.process-setup', ['questions' => ProcessSetup::QUESTIONS, 'answers' => ProcessSetup::currentAnswers(), 'changes' => null]);
    }

    public function review(Request $request): View
    {
        $answers = $this->answers($request);

        return view('settings.process-setup', ['questions' => ProcessSetup::QUESTIONS, 'answers' => $answers, 'changes' => ProcessSetup::changes($answers)]);
    }

    public function update(Request $request): RedirectResponse
    {
        ProcessSetup::apply($this->answers($request));

        return redirect()->route('settings.process-depth.edit')->with('success', 'Your answers are set as the process rules. Any rule can still be changed below.');
    }

    /** @return array<string, string> */
    private function answers(Request $request): array
    {
        return $request->validate(collect(ProcessSetup::QUESTIONS)->map(fn ($q) => ['required', Rule::in(array_keys($q[1]))])->all(), [
            'required' => 'Answer every question.',
        ]);
    }
}
