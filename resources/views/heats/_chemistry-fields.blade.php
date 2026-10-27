{{-- The fields of a chemical analysis: how and when the sample was taken and tested, then one box per element. Used on the heat page
     and in the record-test dialog. $heat, $ip (prefix for every id), $mine (old input belongs to this form), $limitText (element
     key => the grade's limits as text). $values (optional) fills the fields from a saved reading to show it read-only. --}}
@php
    $ip = $ip ?? '';
    $mine = $mine ?? true;
    $values = $values ?? null;
    $old = fn ($key, $default = null) => $values !== null ? data_get($values, $key) : ($mine ? old($key, $default) : $default);
    $grade = $heat->ironGrade;
    $limitText = $limitText ?? function ($key) use ($grade) {
        if (! $grade) return '';
        $min = $grade->{$key.'_min'}; $max = $grade->{$key.'_max'};
        if ($min === null && $max === null) return '';
        return ($min !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $min, 3), '0'), '.') : '').'–'.($max !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $max, 3), '0'), '.') : '');
    };
@endphp
<div class="form-grid">
                <div class="field"><label for="{{ $ip }}sample_stage">Sample taken *</label>
                    <select id="{{ $ip }}sample_stage" name="sample_stage">@foreach (['BASE' => 'Base iron', 'AFTER_TREATMENT' => 'After treatment', 'FINAL' => 'Final / ladle', 'PRE_POUR' => 'Pre-pour'] as $k => $l)<option value="{{ $k }}" @selected($old('sample_stage', 'AFTER_TREATMENT') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="{{ $ip }}test_method">Method *</label>
                    <select id="{{ $ip }}test_method" name="test_method">@foreach (['SPECTROMETER' => 'Spectrometer', 'CS_ANALYSER' => 'C/S analyser', 'WET_CHEMISTRY' => 'Wet chemistry', 'OTHER' => 'Other'] as $k => $l)<option value="{{ $k }}" @selected($old('test_method', 'SPECTROMETER') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="{{ $ip }}sample_reference">Sample reference</label><input id="{{ $ip }}sample_reference" name="sample_reference" maxlength="100" value="{{ $old('sample_reference') }}"></div>
                <div class="field"><label for="{{ $ip }}tested_by">Tested by</label><input id="{{ $ip }}tested_by" name="tested_by" maxlength="100" value="{{ $old('tested_by') }}"></div>
                <div class="field"><label for="{{ $ip }}chem_gauge">Instrument from the register</label><select id="{{ $ip }}chem_gauge" name="gauge_id"><option value="">—</option>@foreach (\App\Models\Gauge::where('status', 'IN_SERVICE')->orderBy('code')->get() as $g)<option value="{{ $g->id }}" @selected($old('gauge_id') === $g->id)>{{ $g->code }} · {{ $g->name }}@if (in_array($g->calibrationStatus(), ['OVERDUE', 'NOT_CALIBRATED'], true)) ({{ strtolower(\App\Services\Quality\CalibrationStatus::LABELS[$g->calibrationStatus()]) }})@endif</option>@endforeach</select></div>
                <div class="field"><label for="{{ $ip }}instrument_reference">Instrument (if not in the register)</label><input id="{{ $ip }}instrument_reference" name="instrument_reference" maxlength="100" value="{{ $old('instrument_reference') }}"></div>
                <div class="field"><label for="{{ $ip }}tested_at">Tested at</label><input id="{{ $ip }}tested_at" name="tested_at" type="datetime-local" value="{{ $old('tested_at') }}"></div>
                @foreach (\App\Services\Quality\ChemistryJudge::COLUMNS as $key => $col)
                    <div class="field"><label for="{{ $ip }}{{ $col }}">{{ \App\Models\IronGrade::ELEMENTS[$key] }} % <span class="muted small">{{ $limitText($key) }}</span></label>
                        <input id="{{ $ip }}{{ $col }}" name="{{ $col }}" type="number" step="any" min="0" max="100" value="{{ $old($col) }}"></div>
                @endforeach
                <div class="field field-full"><label for="{{ $ip }}chem_notes">Notes</label><input id="{{ $ip }}chem_notes" name="notes" maxlength="2000" value="{{ $old('notes') }}"></div>
</div>
