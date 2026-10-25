@extends('layouts.app')

@section('title', 'Melting Heats')

@section('content')
<div class="page-heading">
    <div>
        <h1>Melting heats</h1>
        <p>Review heat records and enter manual power or process readings.</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ route('heats.create') }}">+ Create heat</a>
        </div>  
    </div>
</div>

@if(session('success'))
    <div class="panel">
        <div class="panel-body" style="color:var(--success)">
            {{ session('success') }}
        </div>
    </div>
@endif

<div class="panel">
    <div class="panel-header">
        <h2>Heat register</h2>
        <span class="muted small">{{ $heats->total() }} records</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Heat</th>
                    <th>Furnace</th>
                    <th>Process</th>
                    <th>Status</th>
                    <th>Target metal</th>
                    <th>Power limit</th>
                    <th>Power readings</th>
                    <th>Process readings</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($heats as $heat)
                    <tr>
                        <td><x-heat-light :heat="$heat" /><strong>{{ $heat->heat_number }}</strong></td>
                        <td>{{ $heat->furnace?->name ?? '—' }}</td>
                        <td>{{ $heat->furnace?->furnace_type ?? $heat->process_type }}</td>
                        <td><span class="status {{ $heat->drop_outcome === 'FINISHED' ? 'status-completed' : ($heat->drop_outcome === 'PREMATURE' ? 'status-cancelled' : '') }}" @if ($heat->drop_reason) title="{{ $heat->drop_reason }}" @endif>{{ $heat->outcomeLabel() ?? $heat->status }}</span></td>
                        <td>
                            {{ $heat->target_metal_weight_kg !== null
                                ? \App\Support\Formats::number((float) $heat->target_metal_weight_kg, 1).' kg'
                                : '—' }}
                        </td>
                        <td>
                            {{ $heat->operating_power_limit_kw !== null
                                ? \App\Support\Formats::number((float) $heat->operating_power_limit_kw, 1).' kW'
                                : '—' }}
                        </td>
                        <td>{{ $heat->power_readings_count }}</td>
                        <td>{{ $heat->process_readings_count }}</td>
                        <td>
                            <a class="btn" href="{{ route('heats.show', $heat) }}">
                                Open heat
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:32px" class="muted">
                            No heats have been recorded yet. Create a heat before entering readings.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="panel-body">{{ $heats->links() }}</div>
</div>
@endsection
