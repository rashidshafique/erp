<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Services\Production\ChargeEntryEditor;
use App\Services\Production\ChargeRecorder;
use App\Services\Production\ChargeSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** F4·3's secondary path: one charge of the recipe entered at different kilograms, and a wrong entry corrected with a reason. */
class FurnaceChargeController extends Controller
{
    public function edit(Heat $heat, int $round, ChargeSchedule $schedule): View|RedirectResponse
    {
        $heat->loadMissing('chargeBatch.items.material', 'materialConsumptions');
        if (in_array($heat->status, ['CLOSED', 'CANCELLED'], true)) {
            return redirect()->route('furnace.heat.show', $heat)->with('error', 'Charges cannot be added to a closed heat.');
        }
        if ($heat->materialConsumptions->where('charge_round', $round)->isNotEmpty()) {
            return redirect()->route('furnace.heat.show', $heat)->with('error', 'That charge is already recorded.');
        }
        $items = $this->items($heat, $round);
        if ($items->isEmpty()) {
            return redirect()->route('furnace.heat.show', $heat)->with('error', 'The recipe sets no quantities for that charge.');
        }

        return view('furnace.charge-edit', ['heat' => $heat, 'round' => $round, 'items' => $items, 'schedule' => $schedule->for($heat)]);
    }

    public function store(Request $request, Heat $heat, int $round, ChargeRecorder $recorder): RedirectResponse
    {
        if (in_array($heat->status, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['lines' => 'Charges cannot be added to a closed heat.']);
        }
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.material_id' => ['required', 'uuid', 'exists:materials,id'],
            'lines.*.actual_qty_kg' => ['nullable', 'numeric', 'gte:0', 'max:999999'],
        ]);
        $lines = collect($data['lines'])->filter(fn ($l) => (float) ($l['actual_qty_kg'] ?? 0) > 0)->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Enter a weight above zero for at least one material.']);
        }

        $recorder->recordRound($heat, $round, $lines, $request->user()?->name ?? $heat->operator_name ?? 'Operator', now(),
            'Entered at different kilograms on the furnace tablet.');

        return redirect()->route('furnace.heat.show', $heat)
            ->with('success', ($round === 0 ? 'Start-up charge' : 'Charge #'.$round).' recorded. Inventory has not been deducted.');
    }

    /** A wrong entry is fixed by giving a reason; the entry stays on the record with the change and the reason noted. */
    public function correct(Request $request, Heat $heat, int $round, ChargeEntryEditor $editor): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
        ]);
        $entries = HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', $round)->get()->keyBy('id');
        $changed = 0;
        foreach ($data['entries'] as $id => $kg) {
            $entry = $entries->get($id);
            if (! $entry || $kg === null || abs((float) $kg - (float) $entry->actual_qty_kg) < 0.0005) {
                continue;
            }
            $editor->reweigh($heat, $entry, (float) $kg, $request->user()?->name ?? 'someone', $data['reason']);
            $changed++;
        }

        return back()->with('success', $changed === 0
            ? 'Nothing changed.'
            : ($round === 0 ? 'Start-up charge' : 'Charge #'.$round)." corrected on {$changed} ".($changed === 1 ? 'entry' : 'entries').'. The reason is on the record.');
    }

    /** @return Collection<int, \App\Models\ChargeBatchItem> */
    private function items(Heat $heat, int $round): Collection
    {
        return $heat->chargeBatch?->items->where('charge_stage', $round === 0 ? 'BED' : 'ROUND')->values() ?? collect();
    }
}
