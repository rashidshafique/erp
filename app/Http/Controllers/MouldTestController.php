<?php

namespace App\Http\Controllers;

use App\Models\Gauge;
use App\Models\MouldAssembly;
use App\Models\MouldingRun;
use App\Models\SandSystem;
use App\Services\Quality\CalibrationStatus;
use App\Services\Quality\Measurements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Mould hardness (B scale) taken on the compacted moulds of a run: spots on the cope and drag, judged on the lowest and the average. */
class MouldTestController extends Controller
{
    public const SPOTS = 5;

    public function store(Request $request, MouldingRun $mouldingRun, Measurements $svc): RedirectResponse
    {
        $d = $request->validate([
            'mould_assembly_id' => ['nullable', 'uuid'],
            'cope' => ['nullable', 'array', 'max:'.self::SPOTS], 'cope.*' => ['nullable', 'numeric', 'min:0', 'max:150'],
            'drag' => ['nullable', 'array', 'max:'.self::SPOTS], 'drag.*' => ['nullable', 'numeric', 'min:0', 'max:150'],
            'gauge_id' => ['nullable', 'uuid', 'exists:gauges,id'], 'tested_by' => ['nullable', 'string', 'max:100'],
            'taken_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $mould = filled($d['mould_assembly_id'] ?? null) ? MouldAssembly::where('moulding_run_id', $mouldingRun->id)->find($d['mould_assembly_id']) : null;
        if (filled($d['mould_assembly_id'] ?? null) && ! $mould) {
            return back()->withErrors(['mould_test' => 'That mould does not belong to this run.'])->withInput();
        }

        $values = [];
        $spots = [];
        foreach (['cope', 'drag'] as $half) {
            foreach (array_values(array_filter($d[$half] ?? [], fn ($v) => $v !== null && $v !== '')) as $i => $reading) {
                $values["{$half}_spot_".($i + 1)] = $reading;
                $spots[] = (float) $reading;
            }
        }
        if (! $spots) {
            return back()->withErrors(['mould_test' => 'Enter at least one hardness reading.'])->withInput();
        }
        $values['hardness_b_min'] = (string) min($spots);
        $values['hardness_b_avg'] = (string) round(array_sum($spots) / count($spots), 2);

        $gauge = filled($d['gauge_id'] ?? null) ? Gauge::find($d['gauge_id']) : null;
        $calibrationWarning = app(CalibrationStatus::class)->check($gauge, 'mould_test');
        $sandSystemId = SandSystem::where('name', $mouldingRun->sand_system)->value('id');
        try {
            $set = $svc->record('MOULD', [
                'moulding_run_id' => $mouldingRun->id, 'mould_assembly_id' => $mould?->id, 'pattern_id' => $mouldingRun->patternRevision?->pattern_id,
                'moulding_station_id' => $mouldingRun->moulding_station_id, 'sand_system_id' => $sandSystemId, 'shift' => $mouldingRun->shift,
                'taken_at' => $d['taken_at'] ?? now(), 'tested_by' => $d['tested_by'] ?? null, 'notes' => $d['notes'] ?? null,
                'gauge_id' => $gauge?->id, 'equipment_reference' => $gauge?->code,
            ], $values, 'SAND_SYSTEM', $sandSystemId);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['mould_test' => $e->getMessage()])->withInput();
        }

        return redirect()->route('moulding-runs.show', $mouldingRun)
            ->with('success', 'Mould hardness recorded: '.$set->result.($set->findings ? ' — '.implode('; ', $set->findings) : '.'))
            ->with($calibrationWarning ? ['warning' => $calibrationWarning] : []);
    }
}
