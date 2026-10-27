@extends('layouts.app')

@section('title', 'Heat '.$heat->heat_number.' summary')
@section('breadcrumb')<a href="{{ route('heats.index') }}">Heats</a> / <a href="{{ route('heats.show', $heat) }}">{{ $heat->heat_number }}</a> / Summary @endsection

@section('content')
@php
    $kg = fn ($v) => $v === null || $v === 0.0 ? '—' : \App\Support\Formats::number((float) $v, 1).' kg';
    $time = fn ($v) => $v?->displayShortDateTime() ?? '—';
@endphp
<div class="page-heading">
    <div>
        <h1>Heat {{ $heat->heat_number }}</h1>
        <p>{{ $heat->furnace?->name ?? 'Unknown furnace' }} · {{ $heat->furnace?->furnace_type ?? $heat->process_type }} · {{ $heat->ironGrade?->code ?? 'no iron grade' }} · {{ $heat->status }}</p>
    </div>
    <div class="actions"><a class="btn" href="{{ route('heats.show', $heat) }}">Back to heat</a><button class="btn btn-primary" type="button" onclick="window.print()">Print</button></div>
</div>

<div class="panel"><div class="panel-header"><h2>Timeline</h2></div><div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px">
    <div><div class="muted small">Light-up</div><strong>{{ $time($heat->actual_start_at) }}</strong></div>
    <div><div class="muted small">Blast on</div><strong>{{ $time($heat->blast_on_at) }}</strong></div>
    <div><div class="muted small">First tap</div><strong>{{ $time($heat->first_tap_at) }}</strong></div>
    <div><div class="muted small">Bottom drop</div><strong>{{ $time($heat->dropped_at) }}</strong></div>
</div></div>

<div class="panel"><div class="panel-header"><h2>Metal balance</h2></div><div class="panel-body">
    @foreach ($balance['warnings'] as $warning)<div class="alert alert-warning">{{ $warning }}</div>@endforeach
    <p style="margin:0">Metal charged {{ $kg($balance['charged_kg']) }} · tapped {{ $kg($balance['tapped_kg']) }} · poured {{ $kg($balance['poured_kg']) }} in {{ $balance['pours'] }} {{ $balance['pours'] === 1 ? 'pour' : 'pours' }} (first iron {{ $kg($balance['first_iron_kg']) }}) · castings {{ $kg($balance['net_casting_kg']) }} net · {{ $balance['lots'] }} lots, {{ $balance['held_lots'] }} on hold.</p>
</div></div>

@if ($cokeAndFlux)
<div class="panel"><div class="panel-header"><h2>Coke and flux</h2></div><div class="panel-body">
    <div class="table-wrap"><table><thead><tr><th></th><th class="num">Planned</th><th class="num">Actual</th><th class="num">Difference</th><th class="num">Cost PKR</th></tr></thead><tbody>
        @foreach ($cokeAndFlux['kinds'] as $kind)
            <tr><td>{{ $kind['label'] }}</td><td class="num">{{ $kg($kind['planned_kg']) }}</td><td class="num">{{ $kg($kind['actual_kg']) }}</td>
                <td class="num">{{ $kind['planned_kg'] === null ? '—' : ($kind['actual_kg'] - $kind['planned_kg'] > 0 ? '+' : '').\App\Support\Formats::number($kind['actual_kg'] - $kind['planned_kg'], 1).' kg' }}</td>
                <td class="num">{{ \App\Support\Formats::number($kind['cost'], 2) }}</td></tr>
        @endforeach
    </tbody></table></div>
    <p style="margin:10px 0 0">Coke ratio {{ $cokeAndFlux['coke_ratio'] !== null ? '1 : '.\App\Support\Formats::number($cokeAndFlux['coke_ratio'], 1) : '—' }} <span class="muted small">(kg of metal per kg of coke)</span>
        · cost {{ $cokeAndFlux['cost_per_tonne'] !== null ? 'PKR '.\App\Support\Formats::number($cokeAndFlux['cost_per_tonne'], 2).' per tonne melted' : '—' }}</p>
    @if ($cokeAndFlux['extras'])
        <p class="small" style="margin:6px 0 0">Added outside the recipe: @foreach ($cokeAndFlux['extras'] as $extra){{ $loop->first ? '' : ' · ' }}{{ $extra['kind'] }} {{ $kg($extra['kg']) }} ({{ $extra['label'] }})@endforeach</p>
    @endif
    <p class="muted small" style="margin:6px 0 0">Planned is the recipe's coke and flux for the charges put in.</p>
</div></div>
@endif

<div class="panel"><div class="panel-header"><h2>Samples</h2></div><div class="panel-body">
    @if ($samples->isEmpty())<p class="muted" style="margin:0">No samples were recorded.</p>@else
    <div class="table-wrap"><table><thead><tr><th>Taken</th><th>Type</th><th>Reference</th><th>Result</th></tr></thead><tbody>
        @foreach ($samples as $s)<tr><td>{{ $s['at']->displayShortDateTime() }}</td><td>{{ $s['kind'] === 'THERMAL' ? 'Thermal analysis' : 'Chemistry' }}</td><td>{{ $s['reference'] ?: '—' }}</td><td>{{ ['PASS' => 'Pass', 'FAIL' => 'FAIL', 'UNJUDGED' => 'Not judged'][$s['result']] }}</td></tr>@endforeach
    </tbody></table></div>@endif
</div></div>

<div class="panel"><div class="panel-header"><h2>Pours and lots</h2></div><div class="panel-body">
    @forelse ($heat->pours as $pour)
        <div style="margin-bottom:16px">
            <strong><a href="{{ route('pours.show', $pour) }}">{{ $pour->pour_number }}</a></strong>
            <span class="muted small">{{ $time($pour->started_at) }} · {{ $pour->status }} · {{ $kg($pour->total_poured_weight_kg !== null ? (float) $pour->total_poured_weight_kg : null) }}@if ($pour->pour_temperature_c) · {{ (float) $pour->pour_temperature_c }} °C @endif @if ($pour->ladle_reference) · ladle {{ $pour->ladle_reference }} @endif</span>
            @if ($pour->is_first_iron)<span class="status status-on-hold">First iron</span>@endif
            @if ($pour->gate_note)<div class="small muted">{{ $pour->gate_note }}</div>@endif
            @if ($pour->containment_note)<div class="small" style="color:var(--danger)">Containment: {{ $pour->containment_note }}</div>@endif
            <ul style="margin:4px 0 0">
                @foreach ($pour->mouldAssemblies->flatMap->castingLots as $lot)<li class="small">{{ $lot->label }} · {{ $lot->product?->name }} · {{ $lot->quantity_poured }} pcs · {{ $lot->status }}</li>@endforeach
            </ul>
        </div>
    @empty
        <p class="muted" style="margin:0">No pours on this heat.</p>
    @endforelse
</div></div>
@endsection
