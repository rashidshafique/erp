@extends('layouts.app')

@section('title', 'Cores')
@section('breadcrumb', 'Cores')

@section('content')
@php $archived = request()->boolean('archived'); @endphp
<div class="page-heading">
    <div><h1>Cores</h1><p>Every core the foundry makes, the core boxes that make it and the products that need it.</p></div>
    <div class="actions"><a class="btn" href="{{ route('core-boxes.index') }}">Core boxes</a> <a class="btn" href="{{ route('imports.create', ['entity' => 'core_definition']) }}">Import</a> <a class="btn btn-primary" href="{{ route('cores.create') }}">+ New core</a></div>
</div>
<div class="panel"><div class="panel-body">
    <form method="GET" action="{{ route('cores.index') }}" class="filters">
        <div class="field"><label for="search">Search core code or name</label><input id="search" name="search" value="{{ request('search') }}"></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach (['ACTIVE', 'INACTIVE', 'RETIRED'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select></div>
        <label class="check"><input type="checkbox" name="archived" value="1" @checked($archived)> Archived</label>
        <button class="btn btn-primary" type="submit">Filter</button><a class="btn" href="{{ route('cores.index') }}">Reset</a>
    </form>
</div></div>
<div class="panel">
    @if ($cores->isEmpty())
        <div class="empty-state"><strong>No cores found</strong><p>Add a core, then the core box that makes it.</p><a class="btn btn-primary" href="{{ route('cores.create') }}">+ New core</a></div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Core</th><th>Type</th><th>Process</th><th>Weight</th><th>Core boxes</th><th>On hand</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($cores as $core)
                <tr>
                    <td><x-item :name="$core->name" :code="$core->code" :href="route('cores.show', $core)" /></td>
                    <td>{{ \App\Models\CoreDefinition::TYPES[$core->core_type] ?? $core->core_type }}</td>
                    <td>{{ $core->process_type ?: '—' }}</td>
                    <td>{{ $core->nominal_weight_kg !== null ? rtrim(rtrim($core->nominal_weight_kg, '0'), '.').' kg' : '—' }}</td>
                    <td>{{ $core->boxes_count }}</td>
                    <td>@if (($onHand[$core->id] ?? 0) > 0)<a href="{{ route('core-lots.index', ['core' => $core->id]) }}">{{ $onHand[$core->id] }}</a>@else 0 @endif</td>
                    <td><span class="status {{ $core->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">{{ $core->status }}</span></td>
                    <td style="white-space:nowrap"><a href="{{ route('cores.show', $core) }}">Open →</a>@if ($archived)<form method="POST" action="{{ route('cores.restore', $core) }}" style="display:inline;margin-left:8px">@csrf<button class="btn btn-small" type="submit">Restore</button></form>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="pagination"><div class="muted small">Showing {{ $cores->firstItem() }}–{{ $cores->lastItem() }} of {{ $cores->total() }}</div><div class="pagination-links">{{ $cores->links() }}</div></div>
    @endif
</div>
@endsection
