@extends('layouts.app')
@section('title', 'Enquiries')
@section('breadcrumb', 'Enquiries')
@section('content')
@php $cls = ['NEW' => 'status-on-hold', 'FEASIBILITY' => 'status-on-hold', 'QUOTED' => 'status-in-progress', 'WON' => 'status-completed', 'LOST' => 'status-cancelled', 'CANCELLED' => 'status-cancelled']; @endphp
<div class="page-heading">
    <div><h1>Enquiries</h1><p>What customers ask for, whether we can make it, and what we quoted.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('enquiries.wizard') }}">+ New request (guided)</a> <a class="btn" href="{{ route('enquiries.create') }}">Quick enquiry</a></div>
</div>
<div class="panel"><div class="panel-body">
    <form method="GET" action="{{ route('enquiries.index') }}" class="filters">
        <div class="field"><label for="search">Search</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Code, title or customer reference"></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option>@foreach (['NEW', 'FEASIBILITY', 'QUOTED', 'WON', 'LOST', 'CANCELLED'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(strtolower($s)) }}</option>@endforeach</select></div>
        <div class="field"><label for="source">Kind</label><select id="source" name="source"><option value="">All</option>@foreach (\App\Models\Enquiry::SOURCES as $k => $l)<option value="{{ $k }}" @selected(request('source') === $k)>{{ $l }}</option>@endforeach</select></div>
        <button class="btn btn-primary" type="submit">Filter</button><a class="btn" href="{{ route('enquiries.index') }}">Reset</a>
    </form>
</div></div>
<div class="panel">
    <div class="panel-header"><h2>Enquiries</h2><span class="muted small">{{ $rows->total() }} records</span></div>
    @if ($rows->isEmpty())
        <div class="empty-state"><strong>No enquiries yet</strong><p>Record each request as it comes in, whether it is a repeat, a drawing, a sample or a concept.</p><a class="btn btn-primary" href="{{ route('enquiries.wizard') }}">Start a request</a></div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Code</th><th>Customer</th><th>Title</th><th>Kind</th><th>Parts</th><th>Due</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td><strong>{{ $r->code }}</strong></td><td>{{ $r->customer?->name }}</td>
                    <td>{{ $r->title }}@if ($r->customer_reference)<div class="muted small">Their ref {{ $r->customer_reference }}</div>@endif</td>
                    <td class="small">{{ \App\Models\Enquiry::SOURCES[$r->source_type] ?? $r->source_type }}</td>
                    <td>{{ $r->items_count }}</td>
                    <td class="small">{{ $r->due_on?->displayDate() ?? '—' }}</td>
                    <td><span class="status {{ $cls[$r->status] ?? '' }}">{{ $r->status }}</span></td>
                    <td><a href="{{ route('enquiries.show', $r) }}">Open →</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="pagination">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
