@extends('layouts.app')

@section('title', 'Corrective actions')
@section('breadcrumb', 'Corrective actions')

@section('content')
<div class="page-heading">
    <div><h1>Corrective actions</h1><p>One register for every corrective and preventive action, whatever raised it: a nonconformance report, a customer complaint, an audit finding or a supplier problem. Each stays open until its effectiveness has been checked.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('capa.create') }}">+ Raise an action</a></div>
</div>

<div class="panel"><div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">
    @foreach (['open' => ['Open', null], 'overdue' => ['Overdue', 'var(--danger)'], 'verify' => ['Awaiting effectiveness check', 'var(--warning)'], 'closed' => ['Closed', null]] as $key => [$label, $color])
        <a href="{{ route('capa.index', ['state' => $key]) }}" style="text-decoration:none;color:inherit"><div class="muted small">{{ $label }}</div><strong style="font-size:22px;@if ($color && $counts[$key]) color:{{ $color }} @endif">{{ $counts[$key] }}</strong></a>
    @endforeach
</div></div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" class="form-grid" style="align-items:end">
            <div class="field"><label for="q">Search code, title or owner</label><input id="q" name="q" value="{{ request('q') }}"></div>
            <div class="field"><label for="state">Show</label><select id="state" name="state">@foreach (['open' => 'Open', 'overdue' => 'Overdue', 'verify' => 'Awaiting effectiveness check', 'closed' => 'Closed or cancelled', 'all' => 'All'] as $k => $l)<option value="{{ $k }}" @selected($state === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="source">Source</label><select id="source" name="source"><option value="">All</option>@foreach (\App\Models\CapaAction::SOURCES as $k => $l)<option value="{{ $k }}" @selected(request('source') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="severity">Severity</label><select id="severity" name="severity"><option value="">All</option>@foreach (\App\Models\CapaAction::SEVERITIES as $k => $l)<option value="{{ $k }}" @selected(request('severity') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div><button class="btn btn-primary" type="submit">Filter</button> <a class="btn" href="{{ route('capa.index') }}">Clear</a></div>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Action</th><th>Title</th><th>Source</th><th>Severity</th><th>Owner</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($actions as $capa)
            <tr>
                <td class="mono"><a href="{{ route('capa.show', $capa) }}">{{ $capa->code }}</a></td>
                <td>{{ $capa->title }}</td>
                <td>{{ \App\Models\CapaAction::SOURCES[$capa->source_type] ?? $capa->source_type }}<div class="muted small">{{ $capa->source_label }}</div></td>
                <td>{{ \App\Models\CapaAction::SEVERITIES[$capa->severity] ?? $capa->severity }}</td>
                <td>{{ $capa->owner ?: '—' }}</td>
                <td>@if ($capa->status === 'AWAITING_VERIFICATION'){{ $capa->verification_due_on?->format('d M Y') ?? '—' }}<div class="muted small">check</div>@else{{ $capa->due_on?->format('d M Y') ?? '—' }}@endif</td>
                <td>@include('capa._status', ['capa' => $capa])</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Nothing here.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="panel-body">{{ $actions->links() }}</div>
</div>
@endsection
