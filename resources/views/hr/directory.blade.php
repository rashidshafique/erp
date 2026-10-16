@extends('layouts.app')
@section('title', 'Directory')
@section('breadcrumb', 'People directory')
@section('content')
<div class="page-heading"><div><h1>Directory</h1><p>Who is who, and who reports to whom.</p></div></div>

<div class="tab-marker" data-tab-start="People"></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="search">Search</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Name, role or department"></div>
    <div class="field"><label for="department">Department</label><select id="department" name="department"><option value="">All departments</option>@foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department') === $d->id)>{{ $d->name }}</option>@endforeach</select></div>
    <div class="field"><label for="location">Location</label><select id="location" name="location"><option value="">All locations</option>@foreach ($locations as $l)<option value="{{ $l->id }}" @selected(request('location') === $l->id)>{{ $l->name }}</option>@endforeach</select></div>
    <button class="btn btn-primary">Filter</button><a class="btn" href="{{ route('directory.index') }}">Reset</a></form></div></div>
@if ($people->isEmpty())<div class="panel"><div class="empty-state">No one matches.</div></div>@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px">
@foreach ($people as $p)
@php $ph = \App\Models\Attachment::coverFor('employee', $p->id); @endphp
<div class="panel" style="margin:0"><div class="panel-body" style="display:flex;gap:12px;align-items:flex-start">
    @if ($ph)<img src="{{ route('attachments.download', $ph) }}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:50%;padding:0;min-height:0;border:1px solid var(--border)">@else<div style="width:48px;height:48px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex:none">{{ strtoupper(mb_substr($p->first_name, 0, 1).mb_substr((string) $p->last_name, 0, 1)) }}</div>@endif
    <div style="min-width:0"><strong>{{ $p->full_name }}</strong><div class="muted small">{{ $p->designation?->name ?? '—' }}</div><div class="small">{{ $p->department?->name }}{{ $p->location ? ' · '.$p->location->name : '' }}</div>
    @if ($p->phone)<div class="small">{{ $p->phone }}</div>@endif @if ($p->work_email)<div class="small">{{ $p->work_email }}</div>@endif</div>
</div></div>
@endforeach</div>@endif

<div class="tab-marker" data-tab-start="Reporting lines"></div>
<div class="panel"><div class="panel-body">
@if ($roots->isEmpty())<div class="empty-state">No one has been added yet.</div>@else
<ul class="org-tree">@foreach ($roots as $r)@include('hr._tree', ['node' => $r, 'byManager' => $byManager, 'depth' => 0])@endforeach</ul>@endif
</div></div>
<style>.org-tree, .org-tree ul { list-style: none; margin: 0; padding-left: 22px; border-left: 1px dashed var(--border); } .org-tree { border: 0; padding-left: 0; } .org-tree li { margin: 6px 0; }</style>
@endsection
