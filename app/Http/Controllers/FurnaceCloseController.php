<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Services\Production\BottomDrop;
use App\Services\Production\CupolaTimeline;
use App\Services\Production\FloorAlerts;
use App\Services\Production\HeatBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * F4·6 close a heat: furnace off, the metal balance, where the leftover went. Unaccounted metal is shown and told to the
 * planner; it never stops the close. A cupola closes at its bottom drop, and a drop short of the expected run still asks how
 * the campaign ended.
 */
class FurnaceCloseController extends Controller
{
    public function show(Heat $heat, HeatBalance $balance, BottomDrop $drop): View
    {
        $heat->loadMissing('furnace', 'ironGrade');
        $b = $balance->for($heat);
        $check = $heat->isCupola() && ! $heat->dropped_at ? $drop->assess($heat, $heat->blast_on_at ?? $heat->actual_start_at, now()) : null;

        return view('floor.furnace.close', [
            'heat' => $heat, 'b' => $b, 'tapped' => $heat->actual_tapped_weight_kg !== null ? (float) $heat->actual_tapped_weight_kg : ($b['poured_kg'] > 0 ? $b['poured_kg'] : null),
            'check' => $check && ! $check['within'] ? $check : null, 'closed' => in_array($heat->status, ['CLOSED', 'CANCELLED'], true) && (! $heat->isCupola() || $heat->dropped_at),
            'uses' => Heat::LEFTOVER_USES,
        ]);
    }

    public function store(Request $request, Heat $heat, HeatBalance $balance, CupolaTimeline $timeline, BottomDrop $drop, FloorAlerts $alerts): RedirectResponse
    {
        $d = $request->validate([
            'tapped_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'leftover_metal_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'leftover_use' => ['nullable', 'required_with:leftover_metal_kg', Rule::in(array_keys(Heat::LEFTOVER_USES))],
            'close_note' => ['nullable', 'string', 'max:500'],
            'drop_outcome' => ['nullable', Rule::in(['FINISHED', 'PREMATURE'])],
            'drop_reason' => ['nullable', 'string', 'max:1000'],
        ], ['leftover_use.required_with' => 'Say where the leftover metal went.']);
        $heat->loadMissing('furnace');
        if ($heat->status === 'CANCELLED' || ($heat->status === 'CLOSED' && (! $heat->isCupola() || $heat->dropped_at))) {
            throw ValidationException::withMessages(['close' => "Heat {$heat->heat_number} is already ".strtolower($heat->status).'.']);
        }
        if ($heat->pours()->where('status', 'IN_PROGRESS')->exists()) {
            throw ValidationException::withMessages(['close' => 'A pour on this heat is still in progress. Complete it first.']);
        }

        $at = now();
        $judged = null;
        $outcome = null;
        if ($heat->isCupola()) {
            $judged = $drop->assess($heat, $heat->blast_on_at ?? $heat->actual_start_at, $at);
            $outcome = 'FINISHED';
            if ($judged && ! $judged['within']) {
                $outcome = $d['drop_outcome'] ?? null;
                if (! $outcome) {
                    throw ValidationException::withMessages(['drop_outcome' => 'The run is shorter than expected. Say whether the campaign finished or was dropped early.']);
                }
                if ($outcome === 'PREMATURE' && mb_strlen(trim((string) ($d['drop_reason'] ?? ''))) < 3) {
                    throw ValidationException::withMessages(['drop_reason' => 'Say why the campaign was dropped early.']);
                }
            }
        }

        // With the tapped weight given, no leftover typed means none left, so the unaccounted metal can be worked out.
        $leftover = filled($d['leftover_metal_kg'] ?? null);
        $fields = [
            'leftover_metal_kg' => $leftover ? $d['leftover_metal_kg'] : (filled($d['tapped_kg'] ?? null) ? 0 : null), 'leftover_use' => $leftover ? $d['leftover_use'] : null,
            'close_note' => $d['close_note'] ?? null,
        ];
        if (filled($d['tapped_kg'] ?? null)) {
            $fields['actual_tapped_weight_kg'] = $d['tapped_kg'];
        }
        $heat->update($fields);
        if ($heat->isCupola()) {
            $timeline->save($heat, ['dropped_at' => $at]);
            $drop->record($heat->refresh(), $judged, $outcome, $d['drop_reason'] ?? null);
        } else {
            $heat->update(['status' => 'CLOSED', 'closed_at' => $at, 'hold_reason' => null]);
        }

        $unaccounted = $balance->for($heat->fresh())['unaccounted_kg'];
        $alerts->heatClosed($heat->fresh(), $unaccounted, $request->user());

        return redirect()->route('floor.furnace.next-heat')->with('success', "Heat {$heat->heat_number} closed.");
    }
}
