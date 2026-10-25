@php
    $sampling = app(\App\Services\Production\HeatSamples::class);
    $samples = $sampling->timeline($heat);
    $due = $sampling->due($heat);
    $showType = false;   // the Type column (thermal or chemistry) is hidden for now; switch on to show it again
    $reading = fn (?float $v) => $v === null ? '—' : \App\Support\Formats::number($v, 2);
    // Today's samples show the time alone, 12-hour; earlier ones show their date as well.
    $takenAt = fn ($at) => $at->displayAt();
    $badge = ['PASS' => ['status-completed', 'PASS'], 'FAIL' => ['status-cancelled', 'FAIL'], 'UNJUDGED' => ['', 'NOT JUDGED']];
@endphp
<div class="panel" id="sampling">
    <div class="panel-header">
        <h2>Samples</h2>
        <span style="display:inline-flex;align-items:center;gap:8px">
        @if ($due)
            {{-- A live counter: the browser counts from the last sample (or the start of the run) and flips to overdue by itself. --}}
            <span class="status sample-clock {{ $due['overdue'] ? 'status-cancelled' : 'status-completed' }}" data-sample-clock data-since="{{ $due['since_at']->toIso8601String() }}" data-interval="{{ $due['interval'] }}" data-has-last="{{ $due['last'] ? 1 : 0 }}" role="timer" aria-live="off">
                @if ($due['overdue']){{ $due['last'] ? 'Sample overdue by '.abs($due['minutes_left']).' min' : 'No sample yet, '.$due['minutes_since'].' min into the run' }}@else Next sample due in {{ $due['minutes_left'] }} min @endif
            </span>
            <script>
            (function () {
                var el = document.querySelector('[data-sample-clock]');
                if (!el) return;
                var since = Date.parse(el.dataset.since), interval = parseInt(el.dataset.interval, 10) * 60, hasLast = el.dataset.hasLast === '1';
                var span = function (s) { var m = Math.floor(s / 60), r = s % 60; return (m >= 60 ? Math.floor(m / 60) + ' h ' + (m % 60) : m) + ' min ' + String(r).padStart(2, '0') + ' s'; };
                function tick() {
                    var elapsed = Math.max(0, Math.floor((Date.now() - since) / 1000)), over = elapsed > interval;
                    el.textContent = over ? (hasLast ? 'Sample overdue by ' + span(elapsed - interval) : 'No sample yet, ' + span(elapsed) + ' into the run') : 'Next sample due in ' + span(interval - elapsed);
                    el.classList.toggle('status-cancelled', over);
                    el.classList.toggle('status-completed', !over);
                }
                tick();
                setInterval(tick, 1000);
            })();
            </script>
        @endif
        @unless (in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
            <button type="button" class="btn btn-small sample-add" data-quick="qd-record-test" data-test-shortcut title="Record a test sample (press T)" aria-label="Record a test sample">+ Test <kbd>T</kbd></button>
        @endunless
        </span>
    </div>
    <style>
        .sample-clock { font-variant-numeric: tabular-nums; }
        .sample-add kbd { margin-left: 6px; padding: 0 5px; border: 1px solid var(--border); border-radius: 4px; background: var(--hover, #f2f4f7); color: var(--muted); font: 600 11px/16px ui-monospace, Menlo, monospace; }
        .sample-readings td.num, .sample-readings th.num { text-align: right; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; white-space: nowrap; }
    </style>
    <div class="panel-body">
        <p class="muted small" style="margin-top:0">Thermal analysis and chemistry, the latest first.
            @if ($heat->isCupola())A cupola is sampled while it melts, so each pour is judged against the sample before it, or the first one after it. A failing sample holds the lots poured in the period it covers.@endif</p>
        @if ($samples->isEmpty())
            <p class="muted">No samples yet.</p>
        @else
            <div class="table-wrap"><table class="sample-readings">
                <thead><tr><th>Taken</th>@if ($showType)<th>Type</th>@endif<th>Reference</th><th class="num">C %</th><th class="num">CE</th><th class="num">Si %</th><th>Result</th></tr></thead>
                <tbody>
                @foreach ($samples->reverse() as $s)
                    <tr>
                        <td>{{ $takenAt($s['at']) }}@if ($heat->first_tap_at)<div class="muted small">{{ (int) $heat->first_tap_at->diffInMinutes($s['at'], false) }} min from first tap</div>@endif</td>
                        @if ($showType)<td>{{ $s['kind'] === 'THERMAL' ? 'Thermal analysis' : 'Chemistry' }}</td>@endif
                        <td>{{ $s['reference'] ?: '—' }}</td>
                        <td class="num">{{ $reading($s['c']) }}</td><td class="num">{{ $reading($s['ce']) }}</td><td class="num">{{ $reading($s['si']) }}</td>
                        <td><span class="status {{ $badge[$s['result']][0] }}" @if ($s['detail']) title="{{ $s['detail'] }}" @endif>{{ $badge[$s['result']][1] }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif

        @unless (in_array($heat->status, ['CLOSED', 'CANCELLED'], true))
        <script>
        // T opens the record-a-test dialog, unless typing in a field, holding a modifier key or another dialog is open.
        document.addEventListener('keydown', function (e) {
            if ((e.key || '').toLowerCase() !== 't' || e.metaKey || e.ctrlKey || e.altKey || e.defaultPrevented) return;
            var t = e.target;
            if (t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable) || document.querySelector('dialog[open]')) return;
            var opener = document.querySelector('[data-test-shortcut]');
            if (opener) { e.preventDefault(); opener.click(); }
        });
        </script>
        @endunless

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

