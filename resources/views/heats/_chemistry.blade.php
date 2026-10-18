@php
    $judge = app(\App\Services\Quality\ChemistryJudge::class);
    $readings = $heat->chemistryReadings()->get();
    $grade = $heat->ironGrade;
    $limitText = function ($key) use ($grade) {
        if (! $grade) return '';
        $min = $grade->{$key.'_min'}; $max = $grade->{$key.'_max'};
        if ($min === null && $max === null) return '';
        return ($min !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $min, 3), '0'), '.') : '') . '–' . ($max !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $max, 3), '0'), '.') : '');
    };
@endphp
<div class="panel" id="chemistry">
    <div class="panel-header"><h2>Chemical analysis</h2></div>
    <div class="panel-body">
        <p class="muted small">{{ $grade ? 'Judged against '.$grade->code.'.' : 'This heat has no iron grade, so results are saved but not judged.' }}
            Carbon equivalent is worked out as C + (Si + P) / 3.</p>

        @if ($readings->isNotEmpty())
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Stage</th><th>Tested</th>
                @foreach (\App\Services\Quality\ChemistryJudge::COLUMNS as $key => $col)<th>{{ \App\Models\IronGrade::ELEMENTS[$key] }}<div class="muted small">{{ $limitText($key) }}</div></th>@endforeach
                <th>CE</th><th>Result</th><th></th></tr></thead>
            <tbody>
            @foreach ($readings as $r)
                @php $v = $judge->judge($grade, $r->only(array_values(\App\Services\Quality\ChemistryJudge::COLUMNS)), $r->carbon_equivalent !== null ? (float) $r->carbon_equivalent : null); @endphp
                <tr>
                    <td>{{ $r->reading_number }}</td>
                    <td>{{ str_replace('_', ' ', $r->sample_stage) }}<div class="muted small">{{ $r->sample_reference }}</div></td>
                    <td>{{ $r->tested_at?->displayShortDateTime() }}<div class="muted small">{{ $r->tested_by }}</div></td>
                    @foreach (\App\Services\Quality\ChemistryJudge::COLUMNS as $key => $col)<td>{{ $r->$col !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $r->$col, 3), '0'), '.') : '—' }}</td>@endforeach
                    <td>{{ $r->carbon_equivalent !== null ? \App\Support\Formats::number((float) $r->carbon_equivalent, 2) : '—' }}</td>
                    <td>
                        @if ($v['ok'] === null)<span class="status">NOT JUDGED</span>
                        @elseif ($v['ok'])<span class="status status-completed">IN LIMITS</span>
                        @else<span class="status status-cancelled" title="{{ implode(' ', $v['findings']) }}">OUT OF LIMITS</span>@endif
                        @if ($r->is_verified)<div class="muted small">verified</div>@endif
                    </td>
                    <td>@unless ($r->is_verified)<form method="POST" action="{{ route('heats.chemistry.verify', [$heat, $r]) }}">@csrf<button class="btn" type="submit">Verify</button></form>@endunless</td>
                </tr>
                @if ($v['ok'] === false)<tr><td></td><td colspan="{{ 5 + count(\App\Services\Quality\ChemistryJudge::COLUMNS) }}" class="muted small" style="color:var(--danger)">{{ implode(' ', $v['findings']) }}</td></tr>@endif
            @endforeach
            </tbody>
        </table></div>
        @else
            <p class="muted">No analysis recorded yet.</p>
        @endif

        <h3 style="margin-top:22px;font-size:15px">Record an analysis</h3>
        @error('chemistry')<div class="alert alert-error">{{ $message }}</div>@enderror
        <form method="POST" action="{{ route('heats.chemistry.store', $heat) }}">@csrf
            <div class="form-grid">
                <div class="field"><label for="sample_stage">Sample taken *</label>
                    <select id="sample_stage" name="sample_stage">@foreach (['BASE' => 'Base iron', 'AFTER_TREATMENT' => 'After treatment', 'FINAL' => 'Final / ladle', 'PRE_POUR' => 'Pre-pour'] as $k => $l)<option value="{{ $k }}" @selected(old('sample_stage', 'AFTER_TREATMENT') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="test_method">Method *</label>
                    <select id="test_method" name="test_method">@foreach (['SPECTROMETER' => 'Spectrometer', 'CS_ANALYSER' => 'C/S analyser', 'WET_CHEMISTRY' => 'Wet chemistry', 'OTHER' => 'Other'] as $k => $l)<option value="{{ $k }}" @selected(old('test_method', 'SPECTROMETER') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="sample_reference">Sample reference</label><input id="sample_reference" name="sample_reference" maxlength="100" value="{{ old('sample_reference') }}"></div>
                <div class="field"><label for="tested_by">Tested by</label><input id="tested_by" name="tested_by" maxlength="100" value="{{ old('tested_by') }}"></div>
                <div class="field"><label for="chem_gauge">Instrument from the register</label><select id="chem_gauge" name="gauge_id"><option value="">—</option>@foreach (\App\Models\Gauge::where('status', 'IN_SERVICE')->orderBy('code')->get() as $g)<option value="{{ $g->id }}" @selected(old('gauge_id') === $g->id)>{{ $g->code }} · {{ $g->name }}@if (in_array($g->calibrationStatus(), ['OVERDUE', 'NOT_CALIBRATED'], true)) ({{ strtolower(\App\Services\Quality\CalibrationStatus::LABELS[$g->calibrationStatus()]) }})@endif</option>@endforeach</select></div>
                <div class="field"><label for="instrument_reference">Instrument (if not in the register)</label><input id="instrument_reference" name="instrument_reference" maxlength="100" value="{{ old('instrument_reference') }}"></div>
                <div class="field"><label for="tested_at">Tested at</label><input id="tested_at" name="tested_at" type="datetime-local" value="{{ old('tested_at') }}"></div>
                @foreach (\App\Services\Quality\ChemistryJudge::COLUMNS as $key => $col)
                    <div class="field"><label for="{{ $col }}">{{ \App\Models\IronGrade::ELEMENTS[$key] }} % <span class="muted small">{{ $limitText($key) }}</span></label>
                        <input id="{{ $col }}" name="{{ $col }}" type="number" step="0.0001" min="0" max="100" value="{{ old($col) }}"></div>
                @endforeach
                <div class="field field-full"><label for="chem_notes">Notes</label><input id="chem_notes" name="notes" maxlength="2000" value="{{ old('notes') }}"></div>
            </div>
            <button class="btn btn-primary" type="submit">Save analysis</button>
        </form>
    </div>
</div>
