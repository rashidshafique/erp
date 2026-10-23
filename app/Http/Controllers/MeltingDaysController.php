<?php

namespace App\Http\Controllers;

use App\Support\MeltingCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Settings → Melting days: which days the furnace is lit, so a due date can be checked against the next pour. */
class MeltingDaysController extends Controller
{
    public function edit(): View
    {
        return view('settings.melting-days', ['s' => MeltingCalendar::settings(), 'upcoming' => MeltingCalendar::upcoming(8), 'description' => MeltingCalendar::describe()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $days = ['array'];
        $data = $request->validate([
            'mode' => ['required', Rule::in(array_keys(MeltingCalendar::MODES))],
            'weekdays' => $days, 'weekdays.*' => ['integer', Rule::in(array_keys(MeltingCalendar::DAYS))],
            'interval' => ['nullable', 'integer', 'between:1,30'],
            'anchor' => ['nullable', 'date'],
            'skip' => $days, 'skip.*' => ['integer', Rule::in(array_keys(MeltingCalendar::DAYS))],
            'after_pour_days' => ['required', 'integer', 'between:0,30'],
        ], ['anchor.date' => 'Enter a date the furnace is lit.']);

        if ($data['mode'] === 'WEEKDAYS' && empty($data['weekdays'])) {
            throw ValidationException::withMessages(['weekdays' => 'Tick at least one weekday the furnace is lit.']);
        }
        if ($data['mode'] === 'INTERVAL' && blank($data['anchor'] ?? null)) {
            throw ValidationException::withMessages(['anchor' => 'Enter a date the furnace is lit; the other days count from it.']);
        }
        if ($data['mode'] === 'INTERVAL' && count(array_unique($data['skip'] ?? [])) >= 7) {
            throw ValidationException::withMessages(['skip' => 'Leaving out every weekday leaves no melting day.']);
        }

        MeltingCalendar::save([
            'mode' => $data['mode'], 'weekdays' => array_map('intval', $data['weekdays'] ?? []), 'interval' => (int) ($data['interval'] ?? 2) ?: 2,
            'anchor' => $data['anchor'] ?? null, 'skip' => array_map('intval', $data['skip'] ?? []), 'after_pour_days' => (int) $data['after_pour_days'],
        ]);

        return back()->with('success', 'Melting days saved. '.MeltingCalendar::describe());
    }
}
