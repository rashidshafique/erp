
@extends('layouts.app')

@section('title', 'Furnaces')

@section('content')
<div class="page-heading">
    <div>
        <h1>Furnaces</h1>
        <p>Manage cupola and induction melting equipment.</p>
    </div>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('furnaces.create') }}">+ Add furnace</a>
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
        <h2>Furnace register</h2>
        <span class="muted small">{{ $furnaces->total() }} records</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Furnace</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Capacity</th>
                    <th>Melting rate</th>
                    <th>Power</th>
                    <th>Linked equipment</th>
                    <th>Heats</th>
                </tr>
            </thead>
            <tbody>
                @forelse($furnaces as $furnace)
                    <tr>
                        <td><a href="{{ route('furnaces.show', $furnace) }}"><strong>{{ $furnace->code }}</strong></a></td>
                        <td>{{ $furnace->name }}</td>
                        <td>{{ $furnace->furnace_type }}</td>
                        <td>
                            <span class="status">{{ $furnace->status }}</span>
                        </td>
                        <td>
                            {{ $furnace->rated_capacity_kg !== null
                                ? \App\Support\Formats::number((float) $furnace->rated_capacity_kg, 1).' kg'
                                : '—' }}
                        </td>
                        <td>
                            {{ $furnace->nominal_melting_rate_kg_per_hour !== null
                                ? \App\Support\Formats::number((float) $furnace->nominal_melting_rate_kg_per_hour, 1).' kg/h'
                                : '—' }}
                        </td>
                        <td>
                            {{ $furnace->rated_power_kw !== null
                                ? \App\Support\Formats::number((float) $furnace->rated_power_kw, 1).' kW'
                                : '—' }}
                        </td>
                        <td>
                            @if($furnace->equipment)
                                {{ $furnace->equipment->code }} — {{ $furnace->equipment->name }}
                            @else
                                <span class="muted">Not linked</span>
                            @endif
                        </td>
                        <td>{{ $furnace->heats_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:32px" class="muted">
                            No furnaces registered yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="panel-body">{{ $furnaces->links() }}</div>
</div>
@endsection
