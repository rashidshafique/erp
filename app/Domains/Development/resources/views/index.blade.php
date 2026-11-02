@extends('layouts.app')
@section('title', 'Pattern developments')
@section('breadcrumb', 'Pattern developments')
@section('content')
@php $cls = ['OPEN' => 'status-in-progress', 'DONE' => 'status-completed', 'CANCELLED' => 'status-cancelled']; @endphp
<div class="page-heading">
    <div><h1>Pattern developments</h1><p>New and replacement patterns worked through their stages with the customer.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('developments.create') }}">+ Start development</a></div>
</div>
@php $tab = request('tab', ''); @endphp
<div class="tabs" style="display:flex;gap:16px;margin-bottom:12px">
    <a href="{{ route('developments.index') }}" @class(['muted' => $tab !== ''])>All</a>
    <a href="{{ route('developments.index', ['status' => 'OPEN']) }}" @class(['muted' => request('status') !== 'OPEN'])>Open {{ $tabs['open'] }}</a>
    <a href="{{ route('developments.index', ['tab' => 'waiting']) }}" @class(['muted' => $tab !== 'waiting'])>Waiting on tools {{ $tabs['waiting'] }}</a>
    <a href="{{ route('developments.index', ['tab' => 'closed']) }}" @class(['muted' => $tab !== 'closed'])>Closed {{ $tabs['closed'] }}</a>
</div>
<div class="panel"><div class="panel-body">
    <form method="GET" action="{{ route('developments.index') }}" class="filters">
        @if ($tab)<input type="hidden" name="tab" value="{{ $tab }}">@endif
        <div class="field"><label for="search">Search</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Code, part or tool"></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option>@foreach (\App\Domains\Development\Models\PatternDevelopment::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="type">Type</label><select id="type" name="type"><option value="">All</option>@foreach (config('pattern_developments.types') as $k => $t)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $t['name'] }}</option>@endforeach</select></div>
        <div class="field"><label for="customer_id">Customer</label><select id="customer_id" name="customer_id"><option value="">All</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(request('customer_id') === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="field"><label for="made_by">Made by</label><select id="made_by" name="made_by"><option value="">All</option>@foreach (config('pattern_developments.pattern_by') as $k => $l)<option value="{{ $k }}" @selected(request('made_by') === $k)>{{ $l }}</option>@endforeach</select></div>
        <button class="btn btn-primary" type="submit">Filter</button><a class="btn" href="{{ route('developments.index') }}">Reset</a>
    </form>
</div></div>
<div class="panel">
    <div class="panel-header"><h2>Developments</h2><span style="display:flex;gap:12px;align-items:center"><span class="muted small">{{ $rows->total() }} records</span>
        <x-column-picker table="developments" :columns="['customer' => 'Customer', 'type' => 'Type', 'stage' => 'Stage', 'tools' => 'Tools', 'waiting' => 'Waiting on', 'by' => 'Pattern by', 'owner' => 'Owner', 'due' => 'Due']" /></span></div>
    @if ($rows->isEmpty())
        <div class="empty-state"><strong>No pattern developments yet</strong><p>Start one from an enquiry, part, pattern or customer when a new pattern is needed.</p><a class="btn btn-primary" href="{{ route('developments.create') }}">Start development</a></div>
    @else
        <div class="table-wrap"><table data-table="developments">
            <thead><tr><th>Code</th><th>Part</th><th data-col="customer">Customer</th><th data-col="type">Type</th><th data-col="stage">Stage</th><th data-col="tools">Tools</th><th data-col="waiting">Waiting on</th><th data-col="by">Pattern by</th><th data-col="owner">Owner</th><th data-col="due">Due</th><th></th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td><strong>{{ $r->code }}</strong></td>
                    <td>{{ $r->title }}</td>
                    <td data-col="customer">{{ $r->customer?->name ?? '—' }}</td>
                    <td data-col="type" class="small">{{ $r->typeInfo()['name'] }}</td>
                    <td data-col="stage"><span class="status {{ $cls[$r->status] ?? '' }}">{{ $r->status === 'OPEN' ? $r->stageName() : \App\Domains\Development\Models\PatternDevelopment::STATUSES[$r->status] }}</span></td>
                    <td data-col="tools" class="small">{{ $r->toolSummary() }}@if ($r->isOpen() && $r->waitingOn())<br><span class="muted">{{ $r->waitingOn() }}</span>@endif</td>
                    <td data-col="waiting" class="small">{{ $r->isOpen() ? ($r->waitingOn() ?: '—') : '—' }}</td>
                    <td data-col="by" class="small">{{ config('pattern_developments.pattern_by.'.$r->pattern_by) }}</td>
                    <td data-col="owner" class="small">{{ $r->owner_name ?: '—' }}</td>
                    <td data-col="due" class="small">{{ $r->due_on?->displayDate() ?? '—' }}</td>
                    <td><a href="{{ route('developments.show', $r) }}">Open →</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="pagination">{{ $rows->links() }}</div>
    @endif
</div>
@yield('dialog')
@endsection
