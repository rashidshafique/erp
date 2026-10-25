@extends('layouts.app')

@section('title', 'Core stock')
@section('breadcrumb', 'Core stock')

@section('content')
@php $statusChip = ['MADE' => 'status-in-progress', 'STORED' => 'status-completed', 'USED_UP' => 'status-cancelled']; @endphp
<div class="page-heading">
    <div><h1>Core stock</h1><p>Cores made, kept and used. A core can be used the day it is made or taken from the shelf later.</p></div>
    <div class="actions">
        @if (\App\Support\SettingsNav::canOpen())<a class="btn" href="{{ route('settings.cores.edit') }}">Core rules</a>@endif
        <a class="btn" href="{{ route('cores.index') }}">Cores</a>
        <a class="btn btn-primary" href="{{ route('core-lots.create') }}">+ Cores made</a>
    </div>
</div>
<div class="panel"><div class="panel-body">
    <form method="GET" action="{{ route('core-lots.index') }}" class="filters">
        <div class="field"><label for="core">Core</label><select id="core" name="core"><option value="">All cores</option>@foreach ($cores as $c)<option value="{{ $c->id }}" @selected(request('core') === $c->id)>{{ $c->code }} · {{ $c->name }}</option>@endforeach</select></div>
        <div class="field"><label for="show">Show</label><select id="show" name="show"><option value="open">Lots with cores left</option><option value="all" @selected(request('show') === 'all')>All lots</option></select></div>
        <button class="btn btn-primary" type="submit">Filter</button><a class="btn" href="{{ route('core-lots.index') }}">Reset</a>
    </form>
</div></div>
@if (count($onHand))
<div class="metrics">
    @foreach ($cores->whereIn('id', array_keys($onHand)) as $c)
        <div class="metric"><div class="metric-label">{{ $c->name }}</div><div class="metric-value"><a href="{{ route('core-lots.index', ['core' => $c->id]) }}">{{ $onHand[$c->id] }}</a></div><div class="metric-note">on hand, ready to use</div></div>
    @endforeach
</div>
@endif
<div class="panel">
    @if ($lots->isEmpty())
        <div class="empty-state"><strong>No lots to show</strong><p>Record the cores when they are made.</p><a class="btn btn-primary" href="{{ route('core-lots.create') }}">+ Cores made</a></div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Lot</th><th>Core</th><th>Box</th><th>Made</th><th>Left / made</th><th>Where</th><th>Check</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($lots as $lot)
                <tr>
                    <td><a href="{{ route('core-lots.show', $lot) }}" class="mono">{{ $lot->lot_number }}</a></td>
                    <td>{{ $lot->coreDefinition->name }}<div class="muted small mono">{{ $lot->coreDefinition->code }}</div></td>
                    <td class="mono">{{ $lot->coreBox->code }}</td>
                    <td>{{ $lot->made_on->displayDate() }}</td>
                    <td>{{ $lot->quantity_made - (int) $lot->taken }} / {{ $lot->quantity_made }}</td>
                    <td>{{ $lot->location ?: '—' }}</td>
                    <td>{{ $lot->qc_status === 'NOT_REQUIRED' ? '—' : \App\Models\CoreLot::QC[$lot->qc_status] }}</td>
                    <td><span class="status {{ $statusChip[$lot->status] ?? '' }}">{{ \App\Models\CoreLot::STATUSES[$lot->status] ?? $lot->status }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="pagination"><div class="muted small">Showing {{ $lots->firstItem() }}–{{ $lots->lastItem() }} of {{ $lots->total() }}</div><div class="pagination-links">{{ $lots->links() }}</div></div>
    @endif
</div>
@endsection
