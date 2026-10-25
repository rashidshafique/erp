{{-- The fields of a thermal test: a heat strip, the sample details, the cups run, then a white (tellurium) and a grey (stable) cup
     card with the key results as large tiles checked live against the heat's grade, and the cooling curves. Used by the record page and
     by the record-test dialog. $ip prefixes every id so the form can sit on a page with other forms; $mine is true when the old
     input belongs to this form; $fixedHeat locks the heat, otherwise $heats and $selected fill a heat picker; $gauges. --}}
@php
    $ip = $ip ?? '';
    $mine = $mine ?? true;
    $old = fn ($key, $default = null) => $mine ? old($key, $default) : $default;
    $f = fn ($cup, $k) => $old($cup.'.'.$k);
    $nowLocal = now()->format('Y-m-d\TH:i');
    // Thermal analysis units come first and one is chosen for a new test, preferring one whose calibration is in date.
    $isAnalyser = fn ($g) => $g->instrument_type === 'THERMAL_ANALYSER';
    $gauges = $gauges->sortBy(fn ($g) => ($isAnalyser($g) ? '0' : '1').$g->code)->values();
    $inDate = fn ($g) => ! in_array($g->calibrationStatus(), ['OVERDUE', 'NOT_CALIBRATED'], true);
    $defaultGauge = $gauges->first(fn ($g) => $isAnalyser($g) && $inDate($g)) ?? $gauges->first($isAnalyser);
    // The grade's ranges each result tile is checked against, as {c: [min, max], si: …, ce: …}.
    $ranges = fn ($grade) => $grade ? array_filter(['c' => [$grade->c_min, $grade->c_max], 'si' => [$grade->si_min, $grade->si_max], 'ce' => [$grade->ce_min, $grade->ce_max]],
        fn ($r) => $r[0] !== null || $r[1] !== null) : [];
    $fixedRanges = ($fixedHeat ?? null) ? $ranges($fixedHeat->ironGrade) : [];
    $rangeText = fn (array $r, string $unit = '%') => $r === [] ? 'No grade range' : rtrim(rtrim((string) ($r[0] ?? '…'), '0'), '.').'–'.rtrim(rtrim((string) ($r[1] ?? '…'), '0'), '.').$unit;
    $time = fn (string $p, string $k, string $field, string $label, ?string $default = null) => '<div class="cup-time"><label for="'.$ip.$p.'_'.$field.'">'.$label
        .' <button type="button" class="now-link" data-now="'.$ip.$p.'_'.$field.'">now</button></label><input id="'.$ip.$p.'_'.$field.'" name="'.$k.'['.$field.']" type="datetime-local" value="'.e($old($k.'.'.$field, $default)).'"></div>';
    $tile = fn (string $cup, string $key, string $label, string $tone, string $hint, array $attrs = [], ?string $spec = null) => '<div class="tf-tile tone-'.$tone.'"'.($spec ? ' data-spec-tile="'.$spec.'"' : '').'>'
        .'<div class="tile-top"><label for="'.$ip.$cup[0].'_'.$key.'">'.$label.'</label><span class="tile-badge" data-badge></span></div>'
        .'<input id="'.$ip.$cup[0].'_'.$key.'" name="'.$cup.'['.$key.']" type="number" step="any"'.collect($attrs)->map(fn ($v, $a) => ' '.$a.'="'.e($v).'"')->implode('').' value="'.e($f($cup, $key)).'"'.($spec ? ' data-spec="'.$spec.'"' : '').'>'
        .'<div class="tile-hint"'.($spec ? ' data-range-for="'.$spec.'"' : '').'>'.e($hint).'</div></div>';
    $small = fn (string $cup, string $key, string $label, string $sub, string $unit = '°C', array $attrs = []) => '<div class="tf-mini"><div><label for="'.$ip.$cup[0].'_'.$key.'">'.$label.'</label><small>'.e($sub).'</small></div>'
        .'<span class="mini-input"><input id="'.$ip.$cup[0].'_'.$key.'" name="'.$cup.'['.$key.']" type="number" step="any"'.collect($attrs)->map(fn ($v, $a) => ' '.$a.'="'.e($v).'"')->implode('').' value="'.e($f($cup, $key)).'"><span>'.$unit.'</span></span></div>';
    $submitted = $mine && count(old()) > 0;
    $showWhite = $submitted ? old('show_white') === '1' : true;
    $showGrey = $submitted ? old('show_grey') === '1' : true;
@endphp

<div class="tf tf-root" data-ranges='@json((object) $fixedRanges)'>
<div class="tf-strip">
    @if ($fixedHeat ?? null)
        {{-- The heat, its line, grade and target CE are in the dialog title. --}}
        <input type="hidden" name="heat_id" value="{{ $fixedHeat->id }}">
    @else
        <div class="strip-pick"><label for="{{ $ip }}heat_id">Heat *</label>
            <select id="{{ $ip }}heat_id" name="heat_id" data-heat-select required><option value="">Select heat</option>
                @foreach ($heats as $h)<option value="{{ $h->id }}" data-ranges='@json((object) $ranges($h->ironGrade))' data-info="{{ isset($heatInfo) ? $heatInfo($h) : '' }}" @selected($old('heat_id', $selected) === $h->id)>{{ ['red' => '🔴 ', 'orange' => '🟠 ', 'green' => '🟢 '][$h->light()] ?? '' }}{{ $h->heat_number }} — {{ $h->furnace?->code }} — {{ $h->ironGrade?->code ?? 'no grade' }}</option>@endforeach
            </select></div>
    @endif
    <div class="strip-device"><label for="{{ $ip }}gauge_id">Analyser</label>
        <select id="{{ $ip }}gauge_id" name="gauge_id"><option value="">—</option>@foreach ($gauges as $g)<option value="{{ $g->id }}" @selected($old('gauge_id', $defaultGauge?->id) === $g->id)>{{ $g->code }} · {{ $g->name }}@if (in_array($g->calibrationStatus(), ['OVERDUE', 'NOT_CALIBRATED'], true)) ({{ strtolower(\App\Services\Quality\CalibrationStatus::LABELS[$g->calibrationStatus()]) }})@endif</option>@endforeach</select></div>
</div>

<div class="tf-sample">
    <div class="field"><label for="{{ $ip }}sample_reference">Sample ref</label><input id="{{ $ip }}sample_reference" name="sample_reference" maxlength="60" value="{{ $old('sample_reference') }}"></div>
    <div class="field"><label for="{{ $ip }}stage">Sampling stage *</label>
        <select id="{{ $ip }}stage" name="stage">@foreach (['BASE' => 'Base iron (before treatment)', 'POST_TREATMENT' => 'After treatment / inoculation', 'PRE_POUR' => 'Pre-pour check'] as $k => $l)<option value="{{ $k }}" @selected($old('stage', 'POST_TREATMENT') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="{{ $ip }}sample_point">Sampled from</label><select id="{{ $ip }}sample_point" name="sample_point"><option value="">—</option>@foreach (\App\Models\ThermalAnalysisTest::SAMPLE_POINTS as $k => $l)<option value="{{ $k }}" @selected($old('sample_point') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="{{ $ip }}inoculant_added_pct">Inoculant added</label><span class="unit-input"><input id="{{ $ip }}inoculant_added_pct" name="inoculant_added_pct" type="number" step="any" min="0" max="2" value="{{ $old('inoculant_added_pct') }}"><span>%</span></span></div>
    <div class="field"><label for="{{ $ip }}taken_by">Technician</label><input id="{{ $ip }}taken_by" name="taken_by" maxlength="100" value="{{ $old('taken_by', auth()->user()?->name) }}"></div>
    <div class="field tf-wide"><label for="{{ $ip }}notes">Condition / ladle notes</label><input id="{{ $ip }}notes" name="notes" maxlength="2000" value="{{ $old('notes') }}"></div>
</div>

<div class="tf-cups-bar">
    <span class="bar-label">Cups run</span>
    <label class="cup-pill"><input type="checkbox" name="show_white" value="1" data-cup-toggle="white" @checked($showWhite)><span><i class="dot dot-white"></i> White cup (Te)</span></label>
    <label class="cup-pill"><input type="checkbox" name="show_grey" value="1" data-cup-toggle="grey" @checked($showGrey)><span><i class="dot dot-grey"></i> Grey cup (stable)</span></label>
    <span class="bar-actions"><button type="button" class="btn btn-small" data-all-now>All timestamps now</button><button type="button" class="link-btn" data-clear-cups>Clear</button></span>
</div>

<div class="cup-grid {{ $showWhite && $showGrey ? '' : 'single' }}">
<section class="cup-card" data-cup="white" @unless ($showWhite) hidden @endunless>
    <header title="The iron solidifies white, giving carbon, silicon and the liquidus and solidus."><span><i class="dot dot-white"></i> <strong>White cup</strong> <span class="mono muted">(tellurium)</span></span><span class="cup-tag">Metallurgical CE base</span></header>
    <div class="cup-body">
        <div class="cup-sub"><span>Solidification chemistry</span><span class="cup-verdict" data-verdict></span></div>
        <div class="tile-row">
            {!! $tile('white', 'carbon', 'Carbon (C)', 'amber', $rangeText($fixedRanges['c'] ?? []), ['min' => 0, 'max' => 5], 'c') !!}
            {!! $tile('white', 'ce', 'Carbon eq. (CE)', 'blue', $rangeText($fixedRanges['ce'] ?? []), ['min' => 2, 'max' => 6, 'title' => 'Left empty, CE is calculated from TL and recorded as calculated.'], 'ce') !!}
            {!! $tile('white', 'silicon', 'Silicon (Si)', 'green', $rangeText($fixedRanges['si'] ?? []), ['min' => 0, 'max' => 6, 'title' => 'Left empty, the latest chemistry reading is used.'], 'si') !!}
        </div>
        <div class="cup-sub"><span>Thermal inflection points</span></div>
        <div class="mini-row">
            {!! $small('white', 'tl_c', 'TL liquidus', 'First arrest') !!}
            {!! $small('white', 'solidus_c', 'TS solidus', 'End of freezing') !!}
            {!! $small('white', 'peak_temp_c', 'Peak temp', 'Highest recorded', '°C', ['title' => 'Highest temperature in the cup.']) !!}
        </div>
        <footer class="cup-foot">
            <div class="cup-batch"><label for="{{ $ip }}w_cup_batch">Cup batch</label><input id="{{ $ip }}w_cup_batch" name="white[cup_batch]" maxlength="60" value="{{ $f('white', 'cup_batch') }}"></div>
            {!! $time('w', 'white', 'taken_at', 'Sampled', $nowLocal) !!}{!! $time('w', 'white', 'test_started_at', 'Start') !!}{!! $time('w', 'white', 'test_finished_at', 'End') !!}
        </footer>
    </div>
</section>

<section class="cup-card" data-cup="grey" @unless ($showGrey) hidden @endunless>
    <header title="The iron solidifies grey, giving the eutectic arrest used to judge inoculation."><span><i class="dot dot-grey"></i> <strong>Grey cup</strong> <span class="mono muted">(stable, no tellurium)</span></span><span class="cup-tag tag-green">Inoculation and undercooling</span></header>
    <div class="cup-body">
        <div class="cup-sub"><span>Eutectic temperature evolution</span></div>
        <div class="tile-row">
            {!! $tile('grey', 'te_min_c', 'TEmin (°C)', 'plain', 'Lowest eutectic') !!}
            {!! $tile('grey', 'te_max_c', 'TEmax (°C)', 'plain', 'Highest after recalescence') !!}
            {!! $tile('grey', 'recalescence_c', 'Recalescence ΔT', 'amber', 'Blank: TEmax − TEmin', ['title' => 'Left empty, it is worked out as TEmax − TEmin.', 'data-recalescence' => '1']) !!}
        </div>
        <div class="cup-sub"><span>Thermal transitions and chemistry cross-check</span></div>
        <div class="mini-row mini-6">
            {!! $small('grey', 'tl_c', 'TL liquidus', 'First arrest') !!}
            {!! $small('grey', 'solidus_c', 'TS solidus', 'End of freezing') !!}
            {!! $small('grey', 'peak_temp_c', 'Peak temp', 'Highest recorded') !!}
            {!! $small('grey', 'carbon', 'Carbon', 'C', '%', ['min' => 0, 'max' => 5]) !!}
            {!! $small('grey', 'ce', 'CE', 'Analyser', '%', ['min' => 2, 'max' => 6, 'title' => 'Left empty, CE is calculated from TL and recorded as calculated.']) !!}
            {!! $small('grey', 'silicon', 'Silicon', 'Blank: white cup', '%', ['min' => 0, 'max' => 6, 'title' => "Left empty, the white cup's Si is used."]) !!}
        </div>
        <footer class="cup-foot">
            <div class="cup-batch"><label for="{{ $ip }}g_cup_batch">Cup batch</label><input id="{{ $ip }}g_cup_batch" name="grey[cup_batch]" maxlength="60" value="{{ $f('grey', 'cup_batch') }}"></div>
            {!! $time('g', 'grey', 'taken_at', 'Sampled', $nowLocal) !!}{!! $time('g', 'grey', 'test_started_at', 'Start') !!}{!! $time('g', 'grey', 'test_finished_at', 'End') !!}
        </footer>
    </div>
</section>
</div>

<details class="curve-bar" @if ($f('white', 'curve_text') || $f('grey', 'curve_text')) open @endif>
    <summary><span>Solidification cooling curves</span> <span class="muted small">optional · a CSV of seconds and °C per cup, or pasted readings</span></summary>
    <div class="curve-grid">
        <div data-cup="white" @unless ($showWhite) hidden @endunless>
            <strong class="small">White cup</strong>
            <div class="field"><label for="{{ $ip }}w_file">CSV file (seconds, temperature °C)</label><input id="{{ $ip }}w_file" name="white[curve_file]" type="file" accept=".csv,.txt"></div>
            <div class="field"><label for="{{ $ip }}w_text">…or paste the curve</label><textarea id="{{ $ip }}w_text" name="white[curve_text]" rows="3" placeholder="0,1320.0&#10;0.5,1319.1&#10;…">{{ $f('white', 'curve_text') }}</textarea></div>
        </div>
        <div data-cup="grey" @unless ($showGrey) hidden @endunless>
            <strong class="small">Grey cup</strong>
            <div class="field"><label for="{{ $ip }}g_file">CSV file (seconds, temperature °C)</label><input id="{{ $ip }}g_file" name="grey[curve_file]" type="file" accept=".csv,.txt"></div>
            <div class="field"><label for="{{ $ip }}g_text">…or paste the curve</label><textarea id="{{ $ip }}g_text" name="grey[curve_text]" rows="3" placeholder="0,1320.0&#10;0.5,1319.1&#10;…">{{ $f('grey', 'curve_text') }}</textarea></div>
        </div>
    </div>
    <p class="muted small">At least 40 readings. <a href="{{ route('thermal.sample') }}">Download a sample curve</a> to try it. Anything keyed in above is used in place of the value detected from the curve.</p>
</details>
</div>
