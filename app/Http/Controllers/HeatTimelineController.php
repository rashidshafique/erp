<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Services\Production\CupolaTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HeatTimelineController extends Controller
{
    /** Records the light-up, blast on, first tap and bottom drop of a cupola heat. A blank clears a time. */
    public function update(Request $request, Heat $heat, CupolaTimeline $timeline): RedirectResponse
    {
        abort_unless($heat->loadMissing('furnace')->isCupola(), 404);
        $times = $request->validate([
            'actual_start_at' => ['nullable', 'date'],
            'blast_on_at' => ['nullable', 'date'],
            'first_tap_at' => ['nullable', 'date'],
            'dropped_at' => ['nullable', 'date'],
            'actual_tapped_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);
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

        $timeline->save($heat, collect($times)->map(fn ($v) => filled($v) ? $v : null)->all());

        return back()->with('success', 'Timeline saved.');
    }
}
