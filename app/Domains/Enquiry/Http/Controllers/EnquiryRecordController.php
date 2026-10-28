<?php

namespace App\Domains\Enquiry\Http\Controllers;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Domains\Enquiry\Models\EnquiryQuestion;
use App\Domains\Enquiry\Services\EnquiryCloser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/** What is done on the enquiry record once it is sent (design 14b): the pattern decision, questions to the customer, the log, and closing it (14c). */
class EnquiryRecordController extends Controller
{
    public function pattern(Request $request, Enquiry $enquiry): RedirectResponse
    {
        abort_unless($enquiry->isOpen(), 422);
        $d = $request->validate([
            'pattern_route' => ['required', Rule::in(array_keys(Enquiry::PATTERN_ROUTES))],
            'pattern_id' => ['nullable', Rule::exists('patterns', 'id')->whereNull('deleted_at')],
        ]);
        if ($d['pattern_route'] === 'EXISTING' && blank($d['pattern_id'] ?? null)) {
            return back()->withErrors(['pattern_id' => 'Choose the pattern from the register.'])->withInput();
        }
        $before = $enquiry->pattern_route;
        $enquiry->update(['pattern_route' => $d['pattern_route'], 'pattern_id' => $d['pattern_route'] === 'EXISTING' ? $d['pattern_id'] : null]);
        if ($before !== $enquiry->pattern_route) {
            EnquiryEvent::system($enquiry, 'Tooling and pattern: '.Enquiry::PATTERN_ROUTES[$enquiry->pattern_route][0].($enquiry->pattern ? ' '.$enquiry->pattern->code : '').'.');
        }

        return back()->with('success', 'Pattern decision saved.');
    }

    public function question(Request $request, Enquiry $enquiry): RedirectResponse
    {
        abort_unless($enquiry->isOpen(), 422);
        $d = $request->validate(['question' => ['required', 'string', 'max:2000'], 'asked_on' => ['nullable', 'date']], [], ['question' => 'question']);
        $enquiry->questions()->create(['question' => $d['question'], 'asked_on' => $d['asked_on'] ?? today(), 'asked_by_name' => Auth::user()?->name]);

        return back()->with('success', 'Question added.');
    }

    public function answer(Request $request, Enquiry $enquiry, EnquiryQuestion $question): RedirectResponse
    {
        abort_unless($question->enquiry_id === $enquiry->id && $enquiry->isOpen(), 404);
        $d = $request->validate(['answer' => ['required', 'string', 'max:2000'], 'answered_on' => ['nullable', 'date']]);
        $question->update(['answer' => $d['answer'], 'answered_on' => $d['answered_on'] ?? today()]);

        return back()->with('success', 'Answer recorded.');
    }

    public function event(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $d = $request->validate([
            'kind' => ['required', Rule::in(['EMAIL', 'CALL', 'MEETING', 'NOTE'])],
            'direction' => ['nullable', Rule::in(['IN', 'OUT'])],
            'text' => ['required', 'string', 'max:3000'],
            'contact' => ['nullable', 'string', 'max:150'],
            'happened_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $enquiry->events()->create([
            'kind' => $d['kind'], 'direction' => $d['kind'] === 'NOTE' ? null : ($d['direction'] ?? null), 'text' => $d['text'],
            'contact' => $d['contact'] ?? null, 'user_name' => Auth::user()?->name, 'happened_on' => $d['happened_on'] ?? today(),
        ]);

        return back()->with('success', 'Added to the log.');
    }

    public function close(Request $request, Enquiry $enquiry, EnquiryCloser $closer): RedirectResponse
    {
        abort_unless($enquiry->isOpen(), 422);
        $d = $request->validateWithBag('close', [
            'outcome' => ['required', Rule::in(array_keys(Enquiry::OUTCOMES))],
            'close_reason' => ['nullable', Rule::in(array_keys(Enquiry::CLOSE_REASONS))],
            'winning_price' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'follow_up' => ['nullable', 'boolean'],
            'follow_up_on' => ['nullable', 'required_if:follow_up,1', 'date', 'after:today'],
        ], ['follow_up_on.required_if' => 'Choose the date to follow up.', 'follow_up_on.after' => 'The follow-up date must be after today.']);
        if (empty($d['follow_up'])) {
            $d['follow_up_on'] = null;
        }

        try {
            $result = $closer->close($enquiry, $d);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors(), 'close')->withInput();
        }

        if ($result['order'] && Route::has('sales-orders.show')) {
            return redirect()->route('sales-orders.show', $result['order'])->with('success', "{$enquiry->code} won. Sales order {$result['order']->order_number} created"
                .($result['development'] ? " and pattern development {$result['development']->code} opened." : '.'));
        }

        return redirect()->route('enquiries.show', $enquiry)->with('success', "{$enquiry->code} closed as ".strtolower(Enquiry::OUTCOMES[$d['outcome']][0]).'.');
    }
}
