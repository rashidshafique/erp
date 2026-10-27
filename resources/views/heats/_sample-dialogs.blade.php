{{-- One read-only dialog per sample of the heat, laid out exactly like the record-test dialog it was entered in, with the saved
     values in the fields and the result and its reasons on top. Opened by [data-quick="sample-view-<id>"]: a thermal sample by its
     sample group (both cups in one dialog), a chemistry reading by its own id. --}}
@php
    $grade = $heat->ironGrade;
    $ceRange = $grade && ($grade->ce_min !== null || $grade->ce_max !== null)
        ? rtrim(rtrim((string) ($grade->ce_min ?? '…'), '0'), '.').'–'.rtrim(rtrim((string) ($grade->ce_max ?? '…'), '0'), '.').'%' : null;
    $heatInfo = 'Line: '.($heat->furnace?->code ?? '—').' · Grade: '.($grade?->code ?? 'none').($ceRange ? ' · Target CE: '.$ceRange : '');
    $num = fn ($v) => $v === null ? null : rtrim(rtrim((string) $v, '0'), '.');
    $when = fn ($at) => $at?->format('Y-m-d\TH:i');
    $badge = ['PASS' => 'status-completed', 'FAIL' => 'status-cancelled'];

    // A thermal sample in the record form's own field names: each cup's figures as the test worked them out.
    $thermalValues = function ($cups) use ($num, $when) {
        $first = $cups->first();
        $values = ['sample_reference' => $first->sample_reference, 'stage' => $first->stage, 'sample_point' => $first->sample_point,
            'inoculant_added_pct' => $num($first->inoculant_added_pct), 'taken_by' => $first->taken_by, 'notes' => $first->notes];
        foreach (['white' => 'TELLURIUM', 'grey' => 'STABLE'] as $key => $cupType) {
            $cup = $cups->firstWhere('cup_type', $cupType);
            $values['show_'.$key] = $cup ? '1' : '0';
            if (! $cup) {
                continue;
            }
            $values[$key] = ['carbon' => $num($cup->carbon_reported_pct ?? $cup->carbon_from_tl), 'ce' => $num($cup->ce_from_tl), 'silicon' => $num($cup->silicon_used),
                'tl_c' => $num($cup->tl_c), 'solidus_c' => $num($cup->solidus_c), 'peak_temp_c' => $num($cup->peak_temp_c),
                'te_min_c' => $num($cup->te_min_c), 'te_max_c' => $num($cup->te_max_c), 'recalescence_c' => $num($cup->recalescence_c),
                'cup_batch' => $cup->cup_batch, 'taken_at' => $when($cup->taken_at), 'test_started_at' => $when($cup->test_started_at), 'test_finished_at' => $when($cup->test_finished_at)];
        }

        return $values;
    };
    $chemistryValues = fn ($r) => collect(\App\Services\Quality\ChemistryJudge::COLUMNS)->mapWithKeys(fn ($col) => [$col => $num($r->$col)])->all()
        + ['sample_stage' => $r->sample_stage, 'test_method' => $r->test_method, 'sample_reference' => $r->sample_reference, 'tested_by' => $r->tested_by,
            'gauge_id' => $r->gauge_id, 'instrument_reference' => $r->instrument_reference, 'tested_at' => $when($r->tested_at), 'notes' => $r->notes];

    $thermalSamples = $heat->thermalTests()->with('gauge')->get()->groupBy(fn ($t) => $t->sample_group_id ?? $t->id);
    $readings = $heat->chemistryReadings()->get();
@endphp
@include('partials.quick-dialog-assets')
@include('thermal._fields-assets')
@include('partials.record-test-dialog-assets')
@pushOnce('dialogs', 'sample-view-assets')
<style>
    .sample-view fieldset { border: 0; margin: 0; padding: 0; min-width: 0; }
    .sample-view fieldset :disabled { color: var(--text); opacity: 1; -webkit-text-fill-color: var(--text); cursor: default; }
    .sample-view .now-link, .sample-view .curve-bar { display: none; }
    .sample-view .sv-result { display: flex; flex-wrap: wrap; gap: 6px 12px; align-items: baseline; margin-bottom: 12px; }
    .sample-view .sv-result ul { margin: 2px 0 0; padding-left: 18px; flex-basis: 100%; }
    .sample-view .sv-device { margin: 0 12px 0 auto; font-size: 13px; color: var(--muted); }
</style>
@endPushOnce

@foreach ($thermalSamples as $group => $cups)
    @php $id = 'sample-view-'.$group; $ip = $id.'-'; $gauge = $cups->first()->gauge; @endphp
    @push('dialogs')
    <dialog id="{{ $id }}" class="qd qd-wide sample-view" aria-labelledby="{{ $id }}-title">
        <div class="qd-head"><h2 id="{{ $id }}-title" class="qd-heat-title">
            <span class="qd-heat"><x-heat-light :heat="$heat" /> Heat <strong>{{ $heat->heat_number }}</strong></span>
            <span class="qd-heat-meta">{{ $heatInfo }}</span>
        </h2>
            @if ($gauge)<span class="sv-device">Analyser: {{ $gauge->code }} · {{ $gauge->name }}</span>@endif
            <button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
        <div class="qd-tabs" role="tablist"><button type="button" role="tab" aria-selected="true">Thermal test</button></div>
        <div class="qd-body">
            @foreach ($cups->sortBy(fn ($c) => $c->cup_type === 'TELLURIUM' ? 0 : 1) as $cup)
                <div class="sv-result">
                    <strong>{{ $cup->cup_type === 'TELLURIUM' ? 'White cup' : 'Grey cup' }}</strong>
                    <span class="status {{ $badge[$cup->result] ?? '' }}">{{ str_replace('_', ' ', $cup->result ?? 'NOT JUDGED') }}</span>
                    @if ($cup->voided_at)<span class="status status-cancelled">VOID</span> <span class="muted small">{{ $cup->void_reason }}</span>@endif
                    @if ($cup->findings)<ul class="small">@foreach ($cup->findings as $finding)<li>{{ $finding }}</li>@endforeach</ul>@endif
                </div>
            @endforeach
            <fieldset disabled>
                @include('thermal._fields', ['ip' => $ip, 'mine' => false, 'fixedHeat' => $heat, 'heats' => collect(), 'selected' => null, 'gauges' => collect(), 'inDialog' => true, 'values' => $thermalValues($cups)])
            </fieldset>
            <div class="qd-foot"><a class="btn" href="{{ route('thermal.show', $cups->first()) }}">Cooling curves and full test</a><button type="button" class="btn btn-primary" data-close>Close</button></div>
        </div>
    </dialog>
    @endpush
@endforeach

@foreach ($readings as $r)
    @php
        $id = 'sample-view-'.$r->id;
        $verdict = app(\App\Services\Quality\ChemistryJudge::class)->judge($grade, $r->only(array_values(\App\Services\Quality\ChemistryJudge::COLUMNS)), $r->carbon_equivalent !== null ? (float) $r->carbon_equivalent : null);
    @endphp
    @push('dialogs')
    <dialog id="{{ $id }}" class="qd qd-wide sample-view" aria-labelledby="{{ $id }}-title">
        <div class="qd-head"><h2 id="{{ $id }}-title" class="qd-heat-title">
            <span class="qd-heat"><x-heat-light :heat="$heat" /> Heat <strong>{{ $heat->heat_number }}</strong></span>
            <span class="qd-heat-meta">{{ $heatInfo }}</span>
        </h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
        <div class="qd-tabs" role="tablist"><button type="button" role="tab" aria-selected="true">Chemical analysis</button></div>
        <div class="qd-body">
            <div class="sv-result">
                <strong>Reading {{ $r->reading_number }}</strong>
                <span class="status {{ $r->is_accepted === null ? '' : ($r->is_accepted ? 'status-completed' : 'status-cancelled') }}">{{ $r->is_accepted === null ? 'NOT JUDGED' : ($r->is_accepted ? 'PASS' : 'FAIL') }}</span>
                @if ($r->is_verified)<span class="muted small">verified</span>@endif
                @if ($r->is_accepted === false && $verdict['ok'] === false)<ul class="small">@foreach ($verdict['findings'] as $finding)<li>{{ $finding }}</li>@endforeach</ul>@endif
            </div>
            <p class="muted small">{{ $grade ? 'Judged against '.$grade->code.'.' : 'This heat has no iron grade, so results are saved but not judged.' }} Carbon equivalent is worked out as C + (Si + P) / 3.
                @if ($r->carbon_equivalent !== null) CE: <strong>{{ \App\Support\Formats::number((float) $r->carbon_equivalent, 2) }}</strong>@endif</p>
            <fieldset disabled>
                @include('heats._chemistry-fields', ['heat' => $heat, 'ip' => $id.'-', 'mine' => false, 'values' => $chemistryValues($r)])
            </fieldset>
            <div class="qd-foot"><button type="button" class="btn btn-primary" data-close>Close</button></div>
        </div>
    </dialog>
    @endpush
@endforeach
