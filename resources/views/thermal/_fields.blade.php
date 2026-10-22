{{-- The fields of a thermal test: sample details, then a white (tellurium) and a grey (stable) cup card. Used by the record page and
     by the record-test dialog. $ip prefixes every id so the form can sit on a page with other forms; $mine is true when the old
     input belongs to this form; $fixedHeat locks the heat, otherwise $heats and $selected fill a heat picker; $gauges. --}}
@php
    $ip = $ip ?? '';
    $mine = $mine ?? true;
    $old = fn ($key, $default = null) => $mine ? old($key, $default) : $default;
    $f = fn ($cup, $k) => $old($cup.'.'.$k);
    $nowLocal = now()->format('Y-m-d\TH:i');
    $timeFields = fn (string $p, string $k) => '<div class="cup-times">'
        .'<div class="field"><label for="'.$ip.$p.'_taken">Sample taken</label><div class="now-row"><input id="'.$ip.$p.'_taken" name="'.$k.'[taken_at]" type="datetime-local" value="'.e($old($k.'.taken_at', $nowLocal)).'"><button type="button" class="btn btn-small" data-now="'.$ip.$p.'_taken">Now</button></div></div>'
        .'<div class="field"><label for="'.$ip.$p.'_start">Test started</label><div class="now-row"><input id="'.$ip.$p.'_start" name="'.$k.'[test_started_at]" type="datetime-local" value="'.e($old($k.'.test_started_at')).'"><button type="button" class="btn btn-small" data-now="'.$ip.$p.'_start">Now</button></div></div>'
        .'<div class="field"><label for="'.$ip.$p.'_end">Test finished</label><div class="now-row"><input id="'.$ip.$p.'_end" name="'.$k.'[test_finished_at]" type="datetime-local" value="'.e($old($k.'.test_finished_at')).'"><button type="button" class="btn btn-small" data-now="'.$ip.$p.'_end">Now</button></div></div>'
        .'</div>';
@endphp

<div class="panel"><div class="panel-header"><h2>Sample</h2></div><div class="panel-body"><div class="form-grid" data-progressive="3">
    @if ($fixedHeat ?? null)
        <div class="field"><label>Heat</label><input type="hidden" name="heat_id" value="{{ $fixedHeat->id }}"><div class="static-value"><x-heat-light :heat="$fixedHeat" />{{ $fixedHeat->heat_number }} <span class="muted small">{{ $fixedHeat->furnace?->code }} · {{ $fixedHeat->ironGrade?->code ?? 'no grade' }}</span></div></div>
    @else
    <div class="field"><label for="{{ $ip }}heat_id">Heat *</label>
        <select id="{{ $ip }}heat_id" name="heat_id" data-heat-select required><option value="">Select heat</option>
            @foreach ($heats as $h)<option value="{{ $h->id }}" @selected($old('heat_id', $selected) === $h->id)>{{ ['red' => '🔴 ', 'orange' => '🟠 ', 'green' => '🟢 '][$h->light()] ?? '' }}{{ $h->heat_number }} — {{ $h->furnace?->code }} — {{ $h->ironGrade?->code ?? 'no grade' }}</option>@endforeach
        </select></div>
    @endif
    <div class="field"><label for="{{ $ip }}sample_reference">Sample reference</label><input id="{{ $ip }}sample_reference" name="sample_reference" maxlength="60" value="{{ $old('sample_reference') }}"></div>
    <div class="field"><label for="{{ $ip }}stage">Stage *</label>
        <select id="{{ $ip }}stage" name="stage">@foreach (['BASE' => 'Base iron (before treatment)', 'POST_TREATMENT' => 'After treatment / inoculation', 'PRE_POUR' => 'Pre-pour check'] as $k => $l)<option value="{{ $k }}" @selected($old('stage', 'POST_TREATMENT') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="{{ $ip }}taken_by">Taken by</label><input id="{{ $ip }}taken_by" name="taken_by" maxlength="100" value="{{ $old('taken_by', auth()->user()?->name) }}"></div>
    <div class="field"><label for="{{ $ip }}sample_point">Sampled from</label><select id="{{ $ip }}sample_point" name="sample_point"><option value="">—</option>@foreach (\App\Models\ThermalAnalysisTest::SAMPLE_POINTS as $k => $l)<option value="{{ $k }}" @selected($old('sample_point') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="{{ $ip }}gauge_id">Analyser from the register</label><select id="{{ $ip }}gauge_id" name="gauge_id"><option value="">—</option>@foreach ($gauges as $g)<option value="{{ $g->id }}" @selected($old('gauge_id') === $g->id)>{{ $g->code }} · {{ $g->name }}@if (in_array($g->calibrationStatus(), ['OVERDUE', 'NOT_CALIBRATED'], true)) ({{ strtolower(\App\Services\Quality\CalibrationStatus::LABELS[$g->calibrationStatus()]) }})@endif</option>@endforeach</select></div>
    <div class="field"><label for="{{ $ip }}inoculant_added_pct">Inoculant added (%)</label><input id="{{ $ip }}inoculant_added_pct" name="inoculant_added_pct" type="number" step="0.01" min="0" max="2" value="{{ $old('inoculant_added_pct') }}"></div>
    <div class="field"><label for="{{ $ip }}notes">Notes</label><input id="{{ $ip }}notes" name="notes" maxlength="2000" value="{{ $old('notes') }}"></div>
</div></div></div>

<div class="cup-grid">
<div class="panel"><div class="panel-header"><h2>White cup <span class="muted small">(tellurium)</span></h2></div><div class="panel-body">
    <p class="muted small">The iron solidifies white, giving carbon, silicon and the liquidus and solidus. Leave the whole card empty if no white cup was run.</p>
    {!! $timeFields('w', 'white') !!}
    <div class="field"><label for="{{ $ip }}w_batch">Cup batch</label><input id="{{ $ip }}w_batch" name="white[cup_batch]" maxlength="60" value="{{ $f('white', 'cup_batch') }}"><span class="muted small">Lot number on the cup box.</span></div>
    <div class="form-grid" data-progressive="3">
        <div class="field"><label for="{{ $ip }}w_c">Carbon % (C)</label><input id="{{ $ip }}w_c" name="white[carbon]" type="number" step="0.0001" min="0" max="5" value="{{ $f('white', 'carbon') }}"></div>
        <div class="field"><label for="{{ $ip }}w_ce">CE (analyser)</label><input id="{{ $ip }}w_ce" name="white[ce]" type="number" step="0.0001" min="2" max="6" value="{{ $f('white', 'ce') }}"><span class="muted small">Left empty, CE is calculated from TL and recorded as calculated.</span></div>
        <div class="field"><label for="{{ $ip }}w_si">Silicon % (Si)</label><input id="{{ $ip }}w_si" name="white[silicon]" type="number" step="0.0001" min="0" max="6" value="{{ $f('white', 'silicon') }}"><span class="muted small">Left empty, the latest chemistry reading is used.</span></div>
        <div class="field"><label for="{{ $ip }}w_tl">TL, liquidus (°C)</label><input id="{{ $ip }}w_tl" name="white[tl_c]" type="number" step="0.1" value="{{ $f('white', 'tl_c') }}"></div>
        <div class="field"><label for="{{ $ip }}w_ts">TS, solidus (°C)</label><input id="{{ $ip }}w_ts" name="white[solidus_c]" type="number" step="0.1" value="{{ $f('white', 'solidus_c') }}"></div>
        <div class="field"><label for="{{ $ip }}w_peak">Peak temperature (°C)</label><input id="{{ $ip }}w_peak" name="white[peak_temp_c]" type="number" step="0.1" value="{{ $f('white', 'peak_temp_c') }}"><span class="muted small">Highest temperature in the cup.</span></div>
    </div>
    <details class="curve-box" @if ($f('white', 'curve_text')) open @endif><summary>Full cooling curve (optional)</summary>
        <div class="field"><label for="{{ $ip }}w_file">CSV file (seconds, temperature °C)</label><input id="{{ $ip }}w_file" name="white[curve_file]" type="file" accept=".csv,.txt"></div>
        <div class="field"><label for="{{ $ip }}w_text">…or paste the curve</label><textarea id="{{ $ip }}w_text" name="white[curve_text]" rows="5" placeholder="0,1320.0&#10;0.5,1319.1&#10;…">{{ $f('white', 'curve_text') }}</textarea></div>
        <span class="muted small">At least 40 readings. Anything you key in above is used in place of the value detected from the curve.</span>
    </details>
</div></div>

<div class="panel"><div class="panel-header"><h2>Grey cup <span class="muted small">(stable, no tellurium)</span></h2></div><div class="panel-body">
    <p class="muted small">The iron solidifies grey, giving the eutectic arrest used to judge inoculation. Leave the whole card empty if no grey cup was run.</p>
    {!! $timeFields('g', 'grey') !!}
    <div class="field"><label for="{{ $ip }}g_batch">Cup batch</label><input id="{{ $ip }}g_batch" name="grey[cup_batch]" maxlength="60" value="{{ $f('grey', 'cup_batch') }}"></div>
    <div class="form-grid" data-progressive="3">
        <div class="field"><label for="{{ $ip }}g_tl">TL, liquidus (°C)</label><input id="{{ $ip }}g_tl" name="grey[tl_c]" type="number" step="0.1" value="{{ $f('grey', 'tl_c') }}"></div>
        <div class="field"><label for="{{ $ip }}g_ts">TS, solidus (°C)</label><input id="{{ $ip }}g_ts" name="grey[solidus_c]" type="number" step="0.1" value="{{ $f('grey', 'solidus_c') }}"></div>
        <div class="field"><label for="{{ $ip }}g_temin">TEmin (°C)</label><input id="{{ $ip }}g_temin" name="grey[te_min_c]" type="number" step="0.1" value="{{ $f('grey', 'te_min_c') }}"></div>
        <div class="field"><label for="{{ $ip }}g_temax">TEmax (°C)</label><input id="{{ $ip }}g_temax" name="grey[te_max_c]" type="number" step="0.1" value="{{ $f('grey', 'te_max_c') }}"></div>
        <div class="field"><label for="{{ $ip }}g_recal">Recalescence (°C)</label><input id="{{ $ip }}g_recal" name="grey[recalescence_c]" type="number" step="0.1" value="{{ $f('grey', 'recalescence_c') }}"><span class="muted small">Left empty, it is worked out as TEmax − TEmin.</span></div>
        <div class="field"><label for="{{ $ip }}g_peak">Peak temperature (°C)</label><input id="{{ $ip }}g_peak" name="grey[peak_temp_c]" type="number" step="0.1" value="{{ $f('grey', 'peak_temp_c') }}"></div>
        <div class="field"><label for="{{ $ip }}g_c">Carbon % (C)</label><input id="{{ $ip }}g_c" name="grey[carbon]" type="number" step="0.0001" min="0" max="5" value="{{ $f('grey', 'carbon') }}"></div>
        <div class="field"><label for="{{ $ip }}g_ce">CE (analyser)</label><input id="{{ $ip }}g_ce" name="grey[ce]" type="number" step="0.0001" min="2" max="6" value="{{ $f('grey', 'ce') }}"><span class="muted small">Left empty, CE is calculated from TL and recorded as calculated.</span></div>
        <div class="field"><label for="{{ $ip }}g_si">Silicon % (Si)</label><input id="{{ $ip }}g_si" name="grey[silicon]" type="number" step="0.0001" min="0" max="6" value="{{ $f('grey', 'silicon') }}"><span class="muted small">Left empty, the white cup's Si is used.</span></div>
    </div>
    <details class="curve-box" @if ($f('grey', 'curve_text')) open @endif><summary>Full cooling curve (optional)</summary>
        <div class="field"><label for="{{ $ip }}g_file">CSV file (seconds, temperature °C)</label><input id="{{ $ip }}g_file" name="grey[curve_file]" type="file" accept=".csv,.txt"></div>
        <div class="field"><label for="{{ $ip }}g_text">…or paste the curve</label><textarea id="{{ $ip }}g_text" name="grey[curve_text]" rows="5" placeholder="0,1320.0&#10;0.5,1319.1&#10;…">{{ $f('grey', 'curve_text') }}</textarea></div>
        <span class="muted small">At least 40 readings. <a href="{{ route('thermal.sample') }}">Download a sample curve</a> to try it. Anything you key in above is used in place of the value detected from the curve.</span>
    </details>
</div></div>
</div>
