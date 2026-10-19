@extends('layouts.app')
@section('title', 'Attendance exceptions')
@section('breadcrumb', 'Attendance exceptions')
@section('content')
<div class="page-heading"><div><h1>Attendance exceptions</h1><p>Absences and missing punches that need someone to settle them.</p></div></div>
@include('hr::attendance._tabs')
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="from">From</label><input id="from" type="date" name="from" value="{{ $from }}"></div>
    <div class="field"><label for="to">To</label><input id="to" type="date" name="to" value="{{ $to }}"></div>
    <div class="field"><label for="status">Show</label><select id="status" name="status">@foreach (['OPEN' => 'Open', 'RESOLVED' => 'Settled', 'ALL' => 'Both'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select></div>
    <button class="btn btn-primary">Filter</button></form></div></div>
<div class="panel">@if ($rows->isEmpty())<div class="empty-state">Nothing to settle.</div>@else
<div class="table-wrap"><table><thead><tr><th>Day</th><th>Employee</th><th>What happened</th><th>Settle it</th></tr></thead><tbody>
@foreach ($rows as $r)
<tr><td>{{ $r->work_date->format('D d M') }}</td>
<td><a href="{{ route('hr.employees.show', $r->employee) }}"><strong>{{ $r->employee->code }}</strong></a> {{ $r->employee->full_name }}<div class="muted small">{{ $r->employee->department?->name }}</div></td>
<td>@include('hr::_dayflags', ['flags' => $r->flags])<div class="small muted">{{ $r->check_in ? 'In '.$r->check_in->displayTime() : 'No check-in' }} · {{ $r->check_out ? 'Out '.$r->check_out->displayTime() : 'No check-out' }}</div></td>
<td>@if ($r->exception_status === 'OPEN')
<form method="POST" action="{{ route('hr.attendance.resolve', $r) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">@csrf
<select name="how" required style="min-height:30px;padding:3px 6px;max-width:220px"><option value="">Choose…</option><option value="PRESENT">Excused, count as present</option><option value="ABSENT">Confirm absent</option>@foreach ($types as $t)<option value="LEAVE:{{ $t->id }}">Charge to {{ $t->name }}</option>@endforeach</select>
<input name="note" placeholder="Note" style="min-height:30px;padding:3px 6px;max-width:150px"><button class="btn btn-small btn-primary">Settle</button>
<a class="btn btn-small" href="{{ route('hr.attendance', ['date' => $r->work_date->toDateString()]) }}">Fix times</a></form>
@else<span class="status status-completed">Settled</span> <span class="small muted">{{ $r->resolution }}</span>@endif</td></tr>
@endforeach</tbody></table></div><div class="panel-body">{{ $rows->links() }}</div>@endif</div>
@endsection
