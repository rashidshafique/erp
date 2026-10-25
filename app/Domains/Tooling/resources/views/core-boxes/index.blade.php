@extends('layouts.app')

@section('title', 'Core boxes')
@section('breadcrumb', 'Core boxes')

@section('content')
@php $archived = request()->boolean('archived'); @endphp
<div class="page-heading">
    <div><h1>Core boxes</h1><p>The tooling that makes cores: new boxes, boxes modified from existing tooling, and the core made in each cavity.</p></div>
    <div class="actions"><a class="btn" href="{{ route('cores.index') }}">Cores</a> <a class="btn" href="{{ route('imports.create', ['entity' => 'core_box']) }}">Import</a> <a class="btn btn-primary" href="{{ route('core-boxes.create') }}">+ New core box</a></div>
</div>
<div class="panel"><div class="panel-body">
    <form method="GET" action="{{ route('core-boxes.index') }}" class="filters">
        <div class="field"><label for="search">Search code, name or mark</label><input id="search" name="search" value="{{ request('search') }}"></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach (['ACTIVE', 'INACTIVE', 'RETIRED'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select></div>
        <label class="check"><input type="checkbox" name="archived" value="1" @checked($archived)> Archived</label>
        <button class="btn btn-primary" type="submit">Filter</button><a class="btn" href="{{ route('core-boxes.index') }}">Reset</a>
    </form>
</div></div>
<div class="panel">
    @if ($boxes->isEmpty())
        <div class="empty-state"><strong>No core boxes found</strong><p>Add the box that makes a core.</p><a class="btn btn-primary" href="{{ route('core-boxes.create') }}">+ New core box</a></div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Core box</th><th>Revision</th><th>Cavities</th><th>Makes</th><th>Origin</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($boxes as $box)
                <tr>
                    <td><x-item :name="$box->name" :code="$box->code" :href="route('core-boxes.show', $box)" /></td>
                    <td class="mono">{{ $box->revision }}</td>
                    <td>{{ $box->cavities }}</td>
                    <td>{{ $box->cavityMap->pluck('coreDefinition.name')->unique()->implode(', ') ?: $box->coreDefinition?->name }}</td>
                    <td class="small">{{ \App\Models\CoreBox::ORIGINS[$box->origin] ?? $box->origin }}</td>
                    <td><span class="status {{ $box->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">{{ $box->status }}</span></td>
                    <td style="white-space:nowrap"><a href="{{ route('core-boxes.show', $box) }}">Open →</a>@if ($archived)<form method="POST" action="{{ route('core-boxes.restore', $box) }}" style="display:inline;margin-left:8px">@csrf<button class="btn btn-small" type="submit">Restore</button></form>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="pagination"><div class="muted small">Showing {{ $boxes->firstItem() }}–{{ $boxes->lastItem() }} of {{ $boxes->total() }}</div><div class="pagination-links">{{ $boxes->links() }}</div></div>
    @endif
</div>
@endsection
