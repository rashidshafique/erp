<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Services\Production\BottomDrop;
use App\Services\Production\CupolaTimeline;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HeatTimelineController extends Controller
{
    /** Records the light-up, blast on, first tap and bottom drop of a cupola heat. A blank clears a time. */
    public function update(Request $request, Heat $heat, CupolaTimeline $timeline, BottomDrop $drop): RedirectResponse
    {
        abort_unless($heat->loadMissing('furnace')->isCupola(), 404);
        $messages = array_fill_keys(array_map(fn ($f) => $f.'.before_or_equal', ['actual_start_at', 'blast_on_at', 'first_tap_at', 'dropped_at']), 'That time is in the future. Enter the time it actually happened.');
        $times = $request->validate([
            'actual_start_at' => ['nullable', 'date', 'before_or_equal:+5 minutes'],
            'blast_on_at' => ['nullable', 'date', 'before_or_equal:+5 minutes'],
            'first_tap_at' => ['nullable', 'date', 'before_or_equal:+5 minutes'],
            'dropped_at' => ['nullable', 'date', 'before_or_equal:+5 minutes'],
            'actual_tapped_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ], $messages);
        $order = ['actual_start_at' => 'Light-up', 'blast_on_at' => 'Blast on', 'first_tap_at' => 'First tap', 'dropped_at' => 'Bottom drop'];
        $previous = null;
        foreach ($order as $field => $label) {
            if (! filled($times[$field] ?? null)) {
                continue;
            }
            if ($previous && strtotime($times[$field]) < strtotime($times[$previous])) {
                throw ValidationException::withMessages([$field => "{$label} cannot be earlier than ".strtolower($order[$previous]).'.']);
            }
            $previous = $field;
        }

        $times = collect($times)->map(fn ($v) => filled($v) ? $v : null)->all();

        // The bottom drop ends the campaign: a run that reaches the set share of its expected time is finished; a shorter one has to say
        // whether the campaign finished or was dropped early, and why.
        $outcome = $heat->drop_outcome;
        $judged = null;
        $dropChanged = false;
        $dropSent = array_key_exists('dropped_at', $times);
        if ($dropSent && $times['dropped_at'] === null) {
            $outcome = null;
        } elseif ($dropSent && $heat->dropped_at?->format('Y-m-d H:i') !== Carbon::parse($times['dropped_at'])->format('Y-m-d H:i')) {
            $start = filled($times['blast_on_at'] ?? null) ? $times['blast_on_at'] : ($times['actual_start_at'] ?? null);
            $start ??= ($heat->blast_on_at ?? $heat->actual_start_at)?->toDateTimeString();
            $judged = $drop->assess($heat, $start ? Carbon::parse($start) : null, Carbon::parse($times['dropped_at']));
            $outcome = 'FINISHED';
            $dropChanged = true;
            if ($judged && ! $judged['within']) {
                $answer = $request->input('drop_outcome');
                if (! in_array($answer, ['FINISHED', 'PREMATURE'], true)) {
                    return back()->withInput()->with('drop_check', $judged);
                }
                $outcome = $answer;
                if ($outcome === 'PREMATURE' && mb_strlen(trim((string) $request->input('drop_reason'))) < 3) {
                    return back()->withInput()->with('drop_check', $judged)->withErrors(['drop_reason' => 'Say why the campaign was dropped early.']);
                }
            }
        }

        $timeline->save($heat, $times);
        if ($dropChanged || ($dropSent && $times['dropped_at'] === null)) {
            $drop->record($heat->refresh(), $judged, $outcome, $request->string('drop_reason')->limit(1000, '')->toString());
        }

        return back()->with('success', 'Timeline saved.'.($outcome === 'PREMATURE' ? ' Recorded as dropped prematurely.' : ''));
    }

    /**
     * Closes the heat when the furnace is turned off, with the metal left over and where it went. A cupola closes at its bottom
     * drop, so for one this only records the leftover metal. A heat still pouring cannot close.
     */
    public function close(Request $request, Heat $heat): RedirectResponse
    {
        $d = $request->validate([
            'closed_at' => ['nullable', 'date', 'before_or_equal:+5 minutes'],
            'leftover_metal_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'leftover_use' => ['nullable', 'required_with:leftover_metal_kg', \Illuminate\Validation\Rule::in(array_keys(Heat::LEFTOVER_USES))],
            'close_note' => ['nullable', 'string', 'max:500'],
        ], ['leftover_use.required_with' => 'Say where the leftover metal went.']);
        if ($heat->status === 'CANCELLED' || ($heat->status === 'CLOSED' && ! $heat->isCupola())) {
            return back()->withErrors(['close' => "Heat {$heat->heat_number} is already ".strtolower($heat->status).'.']);
        }
        if ($heat->pours()->where('status', 'IN_PROGRESS')->exists()) {
            return back()->withErrors(['close' => 'A pour on this heat is still in progress. Complete it first.']);
        }
        if ($heat->isCupola() && ! $heat->dropped_at) {
            return back()->withErrors(['close' => 'A cupola heat closes at its bottom drop. Enter the drop time on the timeline first.']);
        }
        $leftover = ['leftover_metal_kg' => $d['leftover_metal_kg'] ?? null, 'leftover_use' => filled($d['leftover_metal_kg'] ?? null) ? $d['leftover_use'] : null, 'close_note' => $d['close_note'] ?? null];
        $heat->update($heat->isCupola() ? $leftover : $leftover + ['status' => 'CLOSED', 'closed_at' => isset($d['closed_at']) ? Carbon::parse($d['closed_at']) : now(), 'hold_reason' => null]);

        return back()->with('success', "Heat {$heat->heat_number} closed".($leftover['leftover_metal_kg'] ? ': '.\App\Support\Formats::number($leftover['leftover_metal_kg'], 0).' kg '.strtolower(Heat::LEFTOVER_USES[$leftover['leftover_use']]).'.' : '.'));
    }
}
