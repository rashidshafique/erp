
@extends('layouts.app')

@section('title', 'Pour Register')

@section('content')
<div class="page-heading">
    <div>
        <h1>Pour Register</h1>
        <p>Track each pouring operation from heat selection to casting traceability.</p>
    </div>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('pours.create') }}">+ Create pour</a>
    </div>
</div>

<div class="metrics">
    <div class="metric">
        <div class="metric-label">Total pours</div>
        <div class="metric-value">{{ \App\Support\Formats::number($totalPours) }}</div>
        <div class="metric-note">All recorded pours</div>
    </div>
    <div class="metric">
        <div class="metric-label">Active pours</div>
        <div class="metric-value">{{ \App\Support\Formats::number($activePours) }}</div>
        <div class="metric-note">Planned or in progress</div>
    </div>
    <div class="metric">
        <div class="metric-label">Completed pours</div>
        <div class="metric-value">{{ \App\Support\Formats::number($completedPours) }}</div>
        <div class="metric-note">Marked completed</div>
    </div>
    <div class="metric">
        <div class="metric-label">Showing</div>
        <div class="metric-value">{{ $pours->count() }}</div>
        <div class="metric-note">Pours on this page</div>
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
        <h2>Pour history</h2>
        <span class="muted small">{{ $pours->total() }} records</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pour number</th>
                    <th>Heat</th>
                    <th>Furnace</th>
                    <th>Status</th>
                    <th>Planned time</th>
                    <th>Temperature</th>
                    <th>Poured weight</th>
                    <th>Assemblies</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($pours as $pour)
                    <tr>
                        <td>
                            <a href="{{ route('pours.show', $pour) }}">
                                <strong>{{ $pour->pour_number }}</strong>
                            </a>
                        </td>
                        <td><x-heat-light :heat="$pour->heat" />{{ $pour->heat?->heat_number ?? '—' }}</td>
                        <td>
                            @if($pour->heat?->furnace)
                                {{ $pour->heat->furnace->code }}
                                <div class="muted small">{{ $pour->heat->furnace->name }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="status status-{{ strtolower(str_replace('_', '-', $pour->status)) }}">
                                {{ str_replace('_', ' ', $pour->status) }}
                            </span>
                        </td>
                        <td>{{ $pour->planned_at?->displayDateTime() ?? '—' }}</td>
                        <td>
                            {{ $pour->pour_temperature_c !== null
                                ? \App\Support\Formats::number((float) $pour->pour_temperature_c, 0) . ' °C'
                                : '—' }}
                        </td>
                        <td>
                            {{ $pour->total_poured_weight_kg !== null
                                ? \App\Support\Formats::number((float) $pour->total_poured_weight_kg, 3) . ' kg'
                                : '—' }}
                        </td>
                        <td>{{ $pour->mould_assemblies_count }}</td>
                        <td>
                            <a class="btn" href="{{ route('pours.show', $pour) }}">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="muted" style="padding:32px;text-align:center">
                            No pours recorded yet. Create a pour to start tracking castings.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="panel-body">
        {{ $pours->links() }}
    </div>
</div>
@endsection
