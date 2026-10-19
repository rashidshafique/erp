@extends('layouts.app')
@section('title', 'Exits')
@section('breadcrumb', 'People exits')
@section('content')
<div class="page-heading"><div><h1>Leaving the company</h1><p>Resignations, terminations and retirements, from notice to clearance.</p></div></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="status">Show</label><select id="status" name="status">@foreach (['INITIATED' => 'Open', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled', 'ALL' => 'All'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select></div>
    <button class="btn btn-primary">Filter</button></form></div></div>
<div class="panel">@if ($exits->isEmpty())<div class="empty-state">No exits.</div>@else
<div class="table-wrap"><table><thead><tr><th>Employee</th><th>Department</th><th>Type</th><th>Last day</th><th>Status</th></tr></thead><tbody>
@foreach ($exits as $x)<tr><td><a href="{{ route('hr.exits.show', $x) }}"><strong>{{ $x->employee->code }}</strong></a> {{ $x->employee->full_name }}</td><td>{{ $x->employee->department?->name ?? '—' }}</td><td>{{ config('hr.exit_types')[$x->exit_type] }}</td><td>{{ $x->last_working_day->displayDate() }}</td><td>@include('hr::_chip', ['s' => $x->status])</td></tr>@endforeach
</tbody></table></div><div class="panel-body">{{ $exits->links() }}</div>@endif</div>
@endsection
