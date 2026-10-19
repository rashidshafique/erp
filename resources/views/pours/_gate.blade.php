@php
    $gate = app(\App\Services\QualityGates::class)->pourGate($pour->heat);
    $grade = $pour->heat?->ironGrade;
    $mode = $pour->status === 'PLANNED' ? \App\Support\PouringRules::gateMode($pour->heat?->furnace?->furnace_type ?? $pour->heat?->process_type) : ($pour->gate_mode ?? 'ENFORCED');
    $canStart = $gate['ok'] || $mode !== 'ENFORCED';
@endphp
<div class="panel">
    <div class="panel-header">
        <h2>Pouring gate</h2>
        <span class="status {{ $gate['ok'] ? 'status-completed' : ($mode === 'ENFORCED' ? 'status-cancelled' : 'status-on-hold') }}">{{ $gate['ok'] ? 'CLEAR' : ($mode === 'ENFORCED' ? 'BLOCKED' : 'ADVISORY') }}</span>
    </div>
    <div class="panel-body">
        @if ($errors->has('gate') || $errors->has('complete'))
            <div class="alert alert-error">{{ $errors->first('gate') ?: $errors->first('complete') }}</div>
        @endif

        @if ($mode === 'ADVISORY' && ! $gate['ok'])<div class="alert alert-warning">Advisory only: the pour can start, and what is missing will be recorded on the pour. Take the samples when you can; a sample that fails later holds the lots poured in its period.</div>@endif
        @if ($mode === 'OFF')<div class="alert alert-warning">Checks before pouring are switched off for this furnace type (Settings → Pouring rules).</div>@endif
        @if ($pour->is_first_iron)<div class="alert alert-warning">First iron: lots made from this pour are held until QC passes.</div>@endif
        @if ($pour->containment_note)<div class="alert alert-error"><strong>Containment:</strong> {{ $pour->containment_note }} Release the lots from their hold once QC clears them.</div>@endif
        @if ($pour->gate_note)<div class="alert alert-warning">{{ $pour->gate_note }}</div>@endif

        <p class="muted small">
            Heat {{ $pour->heat?->heat_number }} — grade {{ $grade?->code ?? 'none set' }}.
            A heat with a grade needs a chemistry reading in the grade window and a recent passing thermal test.
        </p>
        @php $governing = app(\App\Services\Production\HeatSamples::class)->governing($pour); @endphp
        @if ($pour->started_at)
            <p class="small" style="margin:8px 0">
                <strong>Samples for this pour:</strong>
                @forelse ($governing as $kind => $g)
                    {{ $kind === 'THERMAL' ? 'Thermal analysis' : 'Chemistry' }} {{ $g['sample']['reference'] ?: '' }} ({{ $g['sample']['result'] === 'PASS' ? 'pass' : ($g['sample']['result'] === 'FAIL' ? 'FAIL' : 'not judged') }}), taken {{ $g['minutes'] }} min {{ $g['timing'] === 'BEFORE' ? 'before' : 'after' }} the pour started{{ $loop->last ? '.' : ';' }}
                @empty
                    none taken yet.
                @endforelse
            </p>
        @endif
        <ul>
            @foreach ($gate['checks'] as $c)
                <li>
                    <strong>{{ $c['label'] }}:</strong>
                    {{ $c['ok'] === true ? 'OK' : ($c['ok'] === false ? 'NOT OK' : 'n/a') }} — {{ $c['detail'] }}
                </li>
            @endforeach
        </ul>

        @if ($pour->heat)
            <a class="btn" href="{{ route('thermal.create', ['heat_id' => $pour->heat_id]) }}">Record thermal test</a>
        @endif

        @if ($pour->status === 'PLANNED')
            <form method="POST" action="{{ route('pours.start', $pour) }}" style="display:inline">
                @csrf
                <button class="btn btn-primary" type="submit" @disabled(! $canStart)>Start pour</button>
            </form>
        @elseif ($pour->status === 'IN_PROGRESS')
            <form method="POST" action="{{ route('pours.complete', $pour) }}" style="margin-top:14px">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label for="pour_temperature_c">Pour temperature (°C) *</label>
                        <input id="pour_temperature_c" name="pour_temperature_c" type="number" step="1" min="1000" max="1700" required value="{{ old('pour_temperature_c') }}">
                        @if ($grade && ($grade->pour_temp_min_c || $grade->pour_temp_max_c))
                            <span class="muted small">Window {{ $grade->pour_temp_min_c ?? '—' }} to {{ $grade->pour_temp_max_c ?? '—' }} °C</span>
                        @endif
                        @error('pour_temperature_c')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                    @include('maintenance::gauges._select', ['id' => 'pour_gauge', 'label' => 'Pyrometer', 'types' => ['THERMOCOUPLE']])
                    <div class="field">
                        <label for="total_poured_weight_kg">Total poured weight (kg) *</label>
                        <input id="total_poured_weight_kg" name="total_poured_weight_kg" type="number" step="0.1" min="0.1" required value="{{ old('total_poured_weight_kg') }}">
                        @error('total_poured_weight_kg')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="field field-full">
                        <label for="out_of_window_reason">Reason, only if the temperature is outside the window</label>
                        <input id="out_of_window_reason" name="out_of_window_reason" maxlength="500" value="{{ old('out_of_window_reason') }}">
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Complete pour</button>
            </form>
        @endif
    </div>
</div>
