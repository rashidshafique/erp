@extends('layouts.app')
@section('title', 'Employees')
@section('breadcrumb', 'Employees')
@section('content')
<div class="page-heading"><div><h1>Employees</h1><p>Everyone on the payroll, past and present.</p></div>
<div class="actions"><a class="btn" href="{{ route('directory.index') }}">Directory</a><a class="btn btn-primary" href="{{ route('hr.employees.create') }}">+ New employee</a></div></div>
<div class="panel"><div class="panel-body">
<form method="GET" class="filters">
    <div class="field"><label for="search">Search</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Name, code, CNIC or phone"></div>
    <div class="field"><label for="department">Department</label><select id="department" name="department"><option value="">All departments</option>@foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department') === $d->id)>{{ $d->name }}</option>@endforeach</select></div>
    <div class="field"><label for="location">Location</label><select id="location" name="location"><option value="">All locations</option>@foreach ($locations as $l)<option value="{{ $l->id }}" @selected(request('location') === $l->id)>{{ $l->name }}</option>@endforeach</select></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Current staff</option>@foreach (config('hr.statuses') as $k => $t)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $t }}</option>@endforeach</select></div>
    <button class="btn btn-primary" type="submit">Filter</button><a class="btn" href="{{ route('hr.employees.index') }}">Reset</a>
</form></div></div>
<div class="panel">
@if ($employees->isEmpty())<div class="empty-state">No employees found. <a href="{{ route('hr.employees.create') }}">Add the first one</a>.</div>
@else
<div class="table-wrap"><table>
<thead><tr><th>Code</th><th>Name</th><th>Designation</th><th>Department</th><th>Location</th><th>Joined</th><th>Status</th></tr></thead>
<tbody>@foreach ($employees as $x)
<tr><td><a href="{{ route('hr.employees.show', $x) }}"><strong>{{ $x->code }}</strong></a></td><td>{{ $x->full_name }}</td><td>{{ $x->designation?->name ?? '—' }}</td><td>{{ $x->department?->name ?? '—' }}</td><td>{{ $x->location?->name ?? '—' }}</td><td>{{ $x->joined_on->displayDate() }}</td><td>@include('hr._chip', ['s' => $x->status])</td></tr>
@endforeach</tbody></table></div>
<div class="panel-body">{{ $employees->links() }}</div>
@endif
</div>
@endsection
