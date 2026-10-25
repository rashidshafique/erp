@php
    $balance = app(\App\Services\Production\HeatBalance::class)->for($heat);
    $kg = fn ($v) => $v === null ? '—' : \App\Support\Formats::number((float) $v, 1).' kg';
@endphp
<div class="panel" id="balance">
    <div class="panel-header"><h2>Metal balance</h2><a class="btn btn-small" href="{{ route('heats.summary', $heat) }}">Heat summary</a></div>
    <div class="panel-body">
        @foreach ($balance['warnings'] as $warning)<div class="alert alert-warning">{{ $warning }}</div>@endforeach
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(112px,1fr));gap:8px 14px">
            <div><div class="muted small" title="Worked out from the recorded charges; metal materials only">Metal charged</div><strong>{{ $kg($balance['charged_kg'] ?: null) }}</strong></div>
            <div><div class="muted small">Tapped</div><strong>{{ $kg($balance['tapped_kg']) }}</strong></div>
            <div><div class="muted small">Poured ({{ $balance['pours'] }} {{ $balance['pours'] === 1 ? 'pour' : 'pours' }}@if ($balance['open_pours']), {{ $balance['open_pours'] }} open @endif)</div><strong>{{ $kg($balance['poured_kg'] ?: null) }}</strong>@if ($balance['poured_percent_of_charged'] !== null)<div class="muted small">{{ $balance['poured_percent_of_charged'] }} % of the charge</div>@endif</div>
            <div><div class="muted small">Tapped, not poured</div><strong>{{ $kg($balance['unpoured_kg']) }}</strong></div>
            <div><div class="muted small">First iron</div><strong>{{ $kg($balance['first_iron_kg'] ?: null) }}</strong></div>
            <div><div class="muted small">Castings made (net)</div><strong>{{ $kg($balance['net_casting_kg'] ?: null) }}</strong>@if ($balance['casting_yield_percent'] !== null)<div class="muted small">{{ $balance['casting_yield_percent'] }} % of the metal poured</div>@endif</div>
            <div><div class="muted small">Lots / on hold</div><strong>{{ $balance['lots'] }} / {{ $balance['held_lots'] }}</strong></div>
            @if ($heat->isCupola())<div><div class="muted small">Pouring rate</div><strong>{{ $balance['melt_rate_kg_per_hour'] !== null ? \App\Support\Formats::number($balance['melt_rate_kg_per_hour']).' kg/h' : '—' }}</strong></div>@endif
        </div>
    </div>
</div>

