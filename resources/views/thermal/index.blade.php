@extends('layouts.app')

@section('title', 'Thermal tests')
@section('breadcrumb', 'Thermal tests')

@section('content')
<div class="page-heading">
    <div><h1>Thermal analysis</h1><p>Cooling-curve tests that verify iron before it is poured.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('thermal.create') }}">+ Record test</a></div>
</div>

<div class="panel">
    <div class="panel-header"><h2>TL to CE relation</h2></div>
    <div class="panel-body">
        <p>CE = ({{ \App\Support\Formats::number($relation['a'], 1) }} − TL) / {{ \App\Support\Formats::number($relation['b'], 1) }}
            — {{ $relation['calibrated'] ? 'calibrated on '.$relation['n'].' heats' : 'uncalibrated default; calibrate from your own history' }}.</p>
        @if ($calibration)<p class="muted small">Last fit {{ $calibration->fitted_at->displayDateTime() }}, R² {{ $calibration->r_squared }}, residual SD {{ $calibration->residual_sd_ce }} CE.</p>@endif
        <form method="POST" action="{{ route('thermal.calibrate') }}">@csrf
            <button class="btn" type="submit">Calibrate from history</button>
            <span class="muted small">Needs 8 heats with both a cooling curve and a chemistry reading, spanning at least 0.15 CE.</span></form>
    </div>
</div>

<div class="panel">
    @if ($tests->isEmpty())
        <div class="empty-state">No tests yet.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Taken</th><th>Heat</th><th>Grade</th><th>Sample</th><th>Cup</th><th>C</th><th>Si</th><th>TL</th><th>TS</th><th>TEmin</th><th>Recal.</th><th>Peak</th><th>CE</th><th>Result</th></tr></thead>
            <tbody>
            @foreach ($tests as $t)
                <tr>
                    <td><a href="{{ route('thermal.show', $t) }}">{{ $t->taken_at->displayShortDateTime() }}</a></td>
                    <td>{{ $t->heat?->heat_number }}</td><td>{{ $t->ironGrade?->code ?? '—' }}</td><td>{{ $t->sample_reference ?: '—' }}@if ($t->entry_mode === 'MANUAL') <span class="muted small">(keyed in)</span>@endif</td>
                    <td>{{ $t->cup_type === 'TELLURIUM' ? 'White' : 'Grey' }}</td><td>{{ $t->carbon_reported_pct !== null ? rtrim(rtrim((string) $t->carbon_reported_pct, '0'), '.') : '—' }}</td><td>{{ $t->silicon_used !== null ? rtrim(rtrim((string) $t->silicon_used, '0'), '.') : '—' }}</td>
                    <td>{{ $t->tl_c ?? '—' }}</td><td>{{ $t->solidus_c ?? '—' }}</td><td>{{ $t->te_min_c ?? '—' }}</td><td>{{ $t->recalescence_c ?? '—' }}</td><td>{{ $t->peak_temp_c ?? '—' }}</td><td>{{ $t->ce_from_tl ?? '—' }}</td>
                    <td><span class="status {{ $t->result === 'PASS' ? 'status-completed' : ($t->result === 'FAIL' ? 'status-cancelled' : 'status-on-hold') }}">{{ str_replace('_', ' ', $t->result) }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="panel-body">{{ $tests->links() }}</div>
    @endif
</div>
@endsection
