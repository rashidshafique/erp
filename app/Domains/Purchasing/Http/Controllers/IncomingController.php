<?php

namespace App\Domains\Purchasing\Http\Controllers;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Maintenance\Models\Gauge;
use App\Domains\Purchasing\Models\IncomingInspection;
use App\Domains\Purchasing\Services\IncomingGate;
use App\Domains\Purchasing\Support\IncomingRules;
use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialLot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IncomingController extends Controller
{
    public const CERTIFICATE_TYPES = ['EN10204_21' => 'EN 10204 2.1', 'EN10204_22' => 'EN 10204 2.2', 'EN10204_31' => 'EN 10204 3.1', 'EN10204_32' => 'EN 10204 3.2'];

    public function editSettings(): View
    {
        return view('settings.rules', [
            'title' => 'Incoming material rules', 'intro' => 'What a received lot needs before it can be accepted into stock.',
            'rules' => IncomingRules::RULES, 'values' => collect(IncomingRules::RULES)->map(fn ($r, $k) => IncomingRules::get($k))->all(),
            'action' => route('incoming.settings.update'), 'backUrl' => route('suppliers.index'), 'backLabel' => 'Suppliers',
            'note' => 'Both are off by default. A lot from a blocked supplier is always refused. Rules apply when a lot is accepted, not when it is recorded in quarantine.',
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (IncomingRules::RULES as $key => [, , $options]) {
            $rules[$key] = ['required', Rule::in(array_keys($options))];
        }
        foreach ($request->validate($rules) as $key => $value) {
            IncomingRules::set($key, $value);
        }

        return back()->with('success', 'Incoming material rules saved.');
    }

    public function editPlan(Material $material): View
    {
        return view('purchasing::plans.edit', ['material' => $material->load('inspectionItems')]);
    }

    /** Replaces the material's inspection plan with the rows submitted; blank rows are dropped. */
    public function updatePlan(Request $request, Material $material): RedirectResponse
    {
        $rows = collect($request->validate([
            'items' => ['nullable', 'array', 'max:40'],
            'items.*.characteristic' => ['nullable', 'string', 'max:120'],
            'items.*.method' => ['nullable', 'string', 'max:150'],
            'items.*.min_value' => ['nullable', 'numeric', 'between:-999999999,999999999'],
            'items.*.max_value' => ['nullable', 'numeric', 'between:-999999999,999999999'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
        ])['items'] ?? [])->filter(fn ($row) => filled($row['characteristic'] ?? null))->values();

        foreach ($rows as $i => $row) {
            if (! filled($row['min_value'] ?? null) && ! filled($row['max_value'] ?? null)) {
                throw ValidationException::withMessages(["items.$i.min_value" => "Give {$row['characteristic']} a minimum, a maximum, or both."]);
            }
            if (filled($row['min_value'] ?? null) && filled($row['max_value'] ?? null) && (float) $row['min_value'] > (float) $row['max_value']) {
                throw ValidationException::withMessages(["items.$i.max_value" => "The maximum for {$row['characteristic']} is below its minimum."]);
            }
        }

        DB::transaction(function () use ($material, $rows) {
            $material->inspectionItems()->delete();
            foreach ($rows as $i => $row) {
                $material->inspectionItems()->create([
                    'sort' => $i, 'characteristic' => $row['characteristic'], 'method' => $row['method'] ?? null,
                    'min_value' => $row['min_value'] ?? null, 'max_value' => $row['max_value'] ?? null, 'unit' => $row['unit'] ?? null,
                ]);
            }
        });

        return redirect()->route('materials.show', $material)->with('success', 'Inspection plan saved.');
    }

    public function showLot(MaterialLot $lot): View
    {
        $lot->load(['material.inspectionItems', 'supplier', 'inspections.results']);

        return view('purchasing::lots.show', [
            'lot' => $lot, 'material' => $lot->material, 'blockers' => $lot->status === 'ACCEPTED' ? [] : app(IncomingGate::class)->blockers($lot),
            'certificateTypes' => self::CERTIFICATE_TYPES, 'results' => IncomingInspection::RESULTS,
            'gauges' => $this->gauges(),
        ]);
    }

    public function updateCertificateType(Request $request, MaterialLot $lot): RedirectResponse
    {
        $lot->update($request->validate(['certificate_type' => ['nullable', Rule::in(array_keys(self::CERTIFICATE_TYPES))]]));

        return back()->with('success', 'Certificate type saved.');
    }

    /** Measures each characteristic in the material's plan against its limits. The result is Failed if any value is out, unless the inspector accepts it by concession. */
    public function storeInspection(Request $request, MaterialLot $lot): RedirectResponse
    {
        $items = $lot->material->inspectionItems;
        $data = $request->validate([
            'inspected_on' => ['required', 'date', 'before_or_equal:today'],
            'inspector' => ['nullable', 'string', 'max:150'],
            'gauge_id' => ['nullable', 'uuid', Rule::exists('gauges', 'id')->whereNull('deleted_at')],
            'concession' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'values' => [$items->isEmpty() ? 'nullable' : 'required', 'array'],
            'values.*' => ['required', 'numeric', 'between:-999999999,999999999'],
        ]);

        $rows = $items->map(function ($item) use ($data) {
            $measured = (float) ($data['values'][$item->id] ?? 0);
            $passed = ($item->min_value === null || $measured >= (float) $item->min_value) && ($item->max_value === null || $measured <= (float) $item->max_value);

            return [
                'characteristic' => $item->characteristic, 'method' => $item->method, 'min_value' => $item->min_value, 'max_value' => $item->max_value,
                'unit' => $item->unit, 'measured_value' => $measured, 'passed' => $passed,
            ];
        });

        if ($items->isNotEmpty() && collect($data['values'] ?? [])->keys()->intersect($items->pluck('id'))->count() !== $items->count()) {
            throw ValidationException::withMessages(['values' => 'Enter a measured value for every characteristic.']);
        }

        $allPassed = $rows->every(fn ($row) => $row['passed']);
        $result = $allPassed ? 'PASS' : (($data['concession'] ?? false) ? 'CONCESSION' : 'FAIL');
        if ($result === 'CONCESSION' && ! filled($data['notes'] ?? null)) {
            throw ValidationException::withMessages(['notes' => 'Say why the lot is accepted by concession.']);
        }

        DB::transaction(function () use ($lot, $data, $rows, $result) {
            $inspection = $lot->inspections()->create([
                'inspected_on' => $data['inspected_on'], 'result' => $result, 'inspector' => $data['inspector'] ?? null,
                'gauge_id' => $data['gauge_id'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            $rows->each(fn ($row) => $inspection->results()->create($row));
        });

        return back()->with($result === 'FAIL' ? 'error' : 'success', 'Inspection recorded: '.strtolower(IncomingInspection::RESULTS[$result]).'.');
    }

    /** Accepts a quarantined lot once the incoming rules are met. */
    public function acceptLot(Request $request, MaterialLot $lot): RedirectResponse
    {
        $data = $request->validate(['accepted_qty' => ['required', 'numeric', 'gt:0', 'lte:'.(float) $lot->received_qty]]);

        if ($lot->status !== 'QUARANTINE') {
            return back()->withErrors(['accepted_qty' => 'Only a lot in quarantine can be accepted.']);
        }
        if ($blockers = app(IncomingGate::class)->blockers($lot)) {
            return back()->withErrors(['accepted_qty' => implode(' ', $blockers)]);
        }

        $lot->update(['status' => 'ACCEPTED', 'accepted_qty' => $data['accepted_qty']]);

        return back()->with('success', "Lot {$lot->lot_number} accepted.");
    }

    private function gauges(): \Illuminate\Support\Collection
    {
        return app(ModuleRegistry::class)->isEnabled('maintenance')
            ? Gauge::where('status', 'IN_SERVICE')->orderBy('name')->get()
            : collect();
    }
}
