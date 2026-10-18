@extends('layouts.app')

@section('title', $furnace->name ?: $furnace->code)
@section('breadcrumb', 'Furnaces / '.$furnace->code)

@section('content')
@php $num = fn ($v, $unit, $dp = 1) => $v !== null ? \App\Support\Formats::number((float) $v, $dp).' '.$unit : '—'; @endphp
<div class="page-heading">
    <div>
        <h1>{{ $furnace->name ?: $furnace->code }}</h1>
        <p><span class="mono">{{ $furnace->code }}</span> · {{ ucfirst(strtolower(str_replace('_', ' ', $furnace->furnace_type))) }} · <span class="status">{{ $furnace->status }}</span></p>
    </div>
    <div class="actions"><a class="btn" href="{{ route('furnaces.index') }}">All furnaces</a></div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Furnace</h2></div>
    <div class="panel-body" style="display:flow-root">
        @include('attachments._cover', ['type' => 'furnace', 'id' => $furnace->id, 'size' => 160])
        <div class="detail-grid">
            <div><div class="detail-label">Rated capacity</div><div class="detail-value">{{ $num($furnace->rated_capacity_kg, 'kg') }}</div></div>
            <div><div class="detail-label">Melting rate</div><div class="detail-value">{{ $num($furnace->nominal_melting_rate_kg_per_hour, 'kg/h') }}</div></div>
            <div><div class="detail-label">Rated power</div><div class="detail-value">{{ $num($furnace->rated_power_kw, 'kW') }}</div></div>
            <div><div class="detail-label">Operating power limit</div><div class="detail-value">{{ $num($furnace->max_operating_power_kw, 'kW') }}</div></div>
            <div><div class="detail-label">Linked equipment</div><div class="detail-value">{{ $furnace->equipment ? $furnace->equipment->code.' · '.$furnace->equipment->name : '—' }}</div></div>
            <div><div class="detail-label">Heats</div><div class="detail-value">{{ \App\Support\Formats::number($furnace->heats_count) }}</div></div>
        </div>
        @if ($furnace->description)<p style="margin:16px 0 0">{{ $furnace->description }}</p>@endif
        @if ($furnace->notes)<p class="muted" style="margin:8px 0 0"><strong>Notes:</strong> {{ $furnace->notes }}</p>@endif
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Recent heats</h2></div>
    @if ($recentHeats->isEmpty())
        <div class="empty-state">No heats on this furnace yet.</div>
    @else
        <div class="table-wrap"><table><thead><tr><th>Heat</th><th>Status</th><th>Started</th><th>Tapped</th></tr></thead><tbody>
            @foreach ($recentHeats as $heat)
                <tr><td><a href="{{ route('heats.show', $heat) }}"><strong>{{ $heat->heat_number }}</strong></a></td><td><span class="status">{{ $heat->status }}</span></td>
                    <td>{{ $heat->actual_start_at?->displayDateTime() ?? '—' }}</td><td>{{ $heat->tapped_at?->displayDateTime() ?? '—' }}</td></tr>
            @endforeach
        </tbody></table></div>
    @endif
</div>

@include('attachments._panel', ['type' => 'furnace', 'id' => $furnace->id])
@endsection
