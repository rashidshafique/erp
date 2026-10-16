@extends('layouts.app')
@section('title', 'HR policies')
@section('breadcrumb', 'HR policies')
@section('content')
<div class="page-heading"><div><h1>HR policies</h1><p>Written rules, versions and who has acknowledged them.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('hr.policies.create') }}">+ New policy</a></div></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="search">Search</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Title or code"></div>
    <div class="field"><label for="category">Category</label><select id="category" name="category"><option value="">All</option>@foreach (config('hr.policy_categories') as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option>@foreach (['DRAFT', 'PUBLISHED', 'RETIRED'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(strtolower($s)) }}</option>@endforeach</select></div>
    <button class="btn btn-primary">Filter</button><a class="btn" href="{{ route('hr.policies.index') }}">Reset</a></form></div></div>
<div class="panel">@if ($policies->isEmpty())<div class="empty-state">No policies yet.</div>@else
<div class="table-wrap"><table><thead><tr><th>Code</th><th>Title</th><th>Category</th><th>Version</th><th>Effective</th><th>Acknowledged</th><th>Status</th></tr></thead><tbody>
@foreach ($policies as $p)<tr><td><a href="{{ route('hr.policies.show', $p) }}"><strong>{{ $p->code }}</strong></a></td><td>{{ $p->title }}</td><td>{{ config('hr.policy_categories')[$p->category] ?? $p->category }}</td><td>v{{ $p->version }}</td><td>{{ $p->effective_on?->format('d M Y') ?? '—' }}</td>
<td>@if ($p->requires_ack && $p->status === 'PUBLISHED'){{ $p->acks_now }} of {{ $current }}@else — @endif</td><td>@include('hr._chip', ['s' => $p->status])</td></tr>@endforeach
</tbody></table></div><div class="panel-body">{{ $policies->links() }}</div>@endif</div>
@endsection
