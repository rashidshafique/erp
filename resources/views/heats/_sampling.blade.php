@php
    $sampling = app(\App\Services\Production\HeatSamples::class);
    $samples = $sampling->timeline($heat);
    $due = $sampling->due($heat);
    $badge = ['PASS' => ['status-completed', 'PASS'], 'FAIL' => ['status-cancelled', 'FAIL'], 'UNJUDGED' => ['', 'NOT JUDGED']];
@endphp
<div class="panel" id="sampling">
    <div class="panel-header">
        <h2>Samples</h2>
        @if ($due)
            <span class="status {{ $due['overdue'] ? 'status-cancelled' : 'status-completed' }}">
                @if ($due['overdue']){{ $due['last'] ? 'Sample overdue by '.abs($due['minutes_left']).' min' : 'No sample yet, '.$due['minutes_since'].' min into the run' }}@else Next sample due in {{ $due['minutes_left'] }} min @endif
            </span>
        @endif
    </div>
    <div class="panel-body">
        <p class="muted small" style="margin-top:0">Thermal analysis and chemistry in the order they were taken.
            @if ($heat->isCupola())A cupola is sampled while it melts, so each pour is judged against the sample before it, or the first one after it. A failing sample holds the lots poured in the period it covers.@endif</p>
        @if ($samples->isEmpty())
            <p class="muted">No samples yet.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Taken</th><th>Type</th><th>Reference</th><th>Result</th></tr></thead>
                <tbody>
                @foreach ($samples as $s)
                    <tr>
                        <td>{{ $s['at']->displayShortDateTime() }}@if ($heat->first_tap_at)<div class="muted small">{{ (int) $heat->first_tap_at->diffInMinutes($s['at'], false) }} min from first tap</div>@endif</td>
                        <td>{{ $s['kind'] === 'THERMAL' ? 'Thermal analysis' : 'Chemistry' }}</td>
                        <td>{{ $s['reference'] ?: '—' }}</td>
                        <td><span class="status {{ $badge[$s['result']][0] }}" @if ($s['detail']) title="{{ $s['detail'] }}" @endif>{{ $badge[$s['result']][1] }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif

        @php $flagged = $heat->pours()->whereNotNull('containment_note')->orderBy('started_at')->get(); @endphp
        @if ($flagged->isNotEmpty())
            <div class="alert alert-error" style="margin-top:14px"><strong>Containment</strong>
                @foreach ($flagged as $p)<div><a href="{{ route('pours.show', $p) }}">{{ $p->pour_number }}</a> ({{ $p->started_at->displayTime() }}): {{ $p->containment_note }}</div>@endforeach
            </div>
        @endif

        <details style="margin-top:16px">
            <summary style="cursor:pointer"><strong>Import spectrometer results</strong></summary>
            <p class="muted small">Paste or upload the instrument's export: a header row (C, Si, Mn, S, P, Cr, Cu, Mo, Mg, and optionally Sample and Time), then one line per sample. Commas, tabs or semicolons all work.</p>
            @error('chemistry')<div class="alert alert-error">{{ $message }}</div>@enderror
            <form method="POST" action="{{ route('heats.chemistry.import', $heat) }}" enctype="multipart/form-data">@csrf
                <div class="form-grid">
                    <div class="field"><label for="imp_method">Method</label><select id="imp_method" name="test_method">@foreach (['SPECTROMETER' => 'Spectrometer', 'CS_ANALYSER' => 'C/S analyser', 'WET_CHEMISTRY' => 'Wet chemistry', 'OTHER' => 'Other'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label for="imp_stage">Samples taken</label><select id="imp_stage" name="sample_stage">@foreach (['BASE' => 'Base iron', 'AFTER_TREATMENT' => 'After treatment', 'FINAL' => 'Final / ladle', 'PRE_POUR' => 'Pre-pour'] as $k => $l)<option value="{{ $k }}" @selected($k === 'BASE')>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label for="imp_gauge">Instrument from the register</label><select id="imp_gauge" name="gauge_id"><option value="">—</option>@foreach (\App\Models\Gauge::where('status', 'IN_SERVICE')->orderBy('code')->get() as $g)<option value="{{ $g->id }}" @selected(old('gauge_id') === $g->id)>{{ $g->code }} · {{ $g->name }}@if (in_array($g->calibrationStatus(), ['OVERDUE', 'NOT_CALIBRATED'], true)) ({{ strtolower(\App\Services\Quality\CalibrationStatus::LABELS[$g->calibrationStatus()]) }})@endif</option>@endforeach</select></div>
                    <div class="field"><label for="imp_instrument">Instrument (if not in the register)</label><input id="imp_instrument" name="instrument_reference" maxlength="100"></div>
                    <div class="field"><label for="imp_file">File</label><input id="imp_file" type="file" name="results_file" accept=".csv,.txt,.tsv"></div>
                    <div class="field field-full"><label for="imp_text">Or paste the results</label><textarea id="imp_text" name="results" rows="5" style="font-family:ui-monospace,Menlo,monospace" placeholder="Sample,Time,C,Si,Mn,S,P&#10;S-1,2026-10-18 10:15,3.35,2.1,0.6,0.08,0.12"></textarea></div>
                </div>
                <button class="btn btn-primary" type="submit">Import</button>
            </form>
        </details>
    </div>
</div>

