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
    <div class="panel-header"><h2>Heat defaults</h2></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('furnaces.defaults', $furnace) }}">
            @csrf @method('PUT')
            <div class="form-grid">
                <div class="field">
                    <label for="is_default">Start new heats on this furnace</label>
                    <input type="hidden" name="is_default" value="0">
                    <label><input class="switch" type="checkbox" id="is_default" name="is_default" value="1" @checked($furnace->is_default)> Default furnace</label>
                </div>
                <div class="field">
                    <label for="default_iron_grade_id">Default iron grade</label>
                    <select id="default_iron_grade_id" name="default_iron_grade_id">
                        <option value="">No default grade</option>
                        @foreach ($grades as $grade)<option value="{{ $grade->id }}" @selected($furnace->default_iron_grade_id === $grade->id)>{{ $grade->code }} ({{ $grade->family }})</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="default_charge_batch_id">Default charge recipe</label>
                    <select id="default_charge_batch_id" name="default_charge_batch_id">
                        <option value="">No default recipe</option>
                        @foreach ($recipes as $r)<option value="{{ $r->id }}" @selected($furnace->default_charge_batch_id === $r->id)>{{ $r->code }} — {{ $r->name }} (Rev {{ $r->revision }})</option>@endforeach
                    </select>
                    @error('default_charge_batch_id')<div class="error-text">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="actions"><button class="btn btn-primary" type="submit">Save defaults</button></div>
        </form>
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
