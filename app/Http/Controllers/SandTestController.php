<?php

namespace App\Http\Controllers;

use App\Models\Gauge;
use App\Models\MeasurementSet;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\SandSystem;
use App\Services\Quality\CalibrationStatus;
use App\Services\Quality\Measurements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SandTestController extends Controller
{
    public function index(Request $request, Measurements $svc): View
    {
        $systems = SandSystem::where('status', 'ACTIVE')->orderBy('name')->get();
        $run = $request->filled('moulding_run_id') ? MouldingRun::with('patternRevision.pattern', 'station')->find($request->query('moulding_run_id')) : null;
        $system = $systems->firstWhere('id', $request->query('system')) ?? ($run ? $systems->firstWhere('name', $run->sand_system) : null) ?? $systems->first();

        return view('sand-tests.index', [
            'run' => $run, 'systems' => $systems, 'system' => $system, 'stations' => MouldingStation::where('status', 'ACTIVE')->orderBy('code')->get(),
            'rows' => $system ? $svc->formRows('SAND', 'SAND_SYSTEM', $system->id) : collect(),
            'sets' => $system ? MeasurementSet::with('measurements', 'mouldingRun', 'pattern')->where('kind', 'SAND')->where('sand_system_id', $system->id)->orderByDesc('taken_at')->limit(30)->get() : collect(),
            'points' => ['MIXER_OUTPUT' => 'Mixer output', 'MOULDING_STATION' => 'At moulding station', 'RETURN_SAND' => 'Return / system sand'],
        ]);
    }

    public function store(Request $request, Measurements $svc): RedirectResponse
    {
        $d = $request->validate([
            'sand_system_id' => ['required', 'uuid', 'exists:sand_systems,id'], 'sample_point' => ['nullable', 'string', 'max:40'],
            'moulding_station_id' => ['nullable', 'uuid', 'exists:moulding_stations,id'], 'shift' => ['nullable', 'string', 'max:30'],
            'taken_at' => ['nullable', 'date'], 'tested_by' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'],
            'gauge_id' => ['nullable', 'uuid', 'exists:gauges,id'],
            'moulding_run_id' => ['nullable', 'uuid', 'exists:moulding_runs,id'], 'link_to' => ['nullable', 'in:run,pattern'],
            'values' => ['required', 'array'], 'values.*' => ['nullable', 'string', 'max:200'],
        ]);
        $run = filled($d['moulding_run_id'] ?? null) ? MouldingRun::with('patternRevision')->find($d['moulding_run_id']) : null;
        $gauge = filled($d['gauge_id'] ?? null) ? Gauge::find($d['gauge_id']) : null;
        $calibrationWarning = app(CalibrationStatus::class)->check($gauge, 'sand');
        try {
            $set = $svc->record('SAND', [
                'sand_system_id' => $d['sand_system_id'], 'sample_point' => $d['sample_point'] ?? null, 'moulding_station_id' => $d['moulding_station_id'] ?? null,
                'shift' => $d['shift'] ?? null, 'taken_at' => $d['taken_at'] ?? now(), 'tested_by' => $d['tested_by'] ?? null, 'notes' => $d['notes'] ?? null,
                'gauge_id' => $gauge?->id, 'equipment_reference' => $gauge?->code,
                'moulding_run_id' => ($d['link_to'] ?? 'run') === 'run' ? $run?->id : null, 'pattern_id' => $run?->patternRevision?->pattern_id,
            ], $d['values'], 'SAND_SYSTEM', $d['sand_system_id']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['sand' => $e->getMessage()])->withInput();
        }

        $back = $run ? redirect()->route('moulding-runs.show', $run) : back();

        return $back->with('success', 'Sand test recorded: '.$set->result.($set->findings ? ' — '.implode('; ', $set->findings) : '.'))->with($calibrationWarning ? ['warning' => $calibrationWarning] : []);
    }
}
