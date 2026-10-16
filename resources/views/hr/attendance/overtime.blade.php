@extends('layouts.app')
@section('title', 'Overtime')
@section('breadcrumb', 'Overtime')
@section('content')
<div class="page-heading"><div><h1>Overtime</h1><p>Time past the end of the shift, and work on days off. Only approved minutes go to payroll.</p></div></div>
@include('hr.attendance._tabs')
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="from">From</label><input id="from" type="date" name="from" value="{{ $from }}"></div>
    <div class="field"><label for="to">To</label><input id="to" type="date" name="to" value="{{ $to }}"></div>
    <div class="field"><label for="status">Show</label><select id="status" name="status">@foreach (['PENDING' => 'Waiting', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'ALL' => 'All'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select></div>
    <button class="btn btn-primary">Filter</button></form></div></div>
<form method="POST" action="{{ route('hr.attendance.overtime.decide') }}">@csrf
<div class="panel">@if ($rows->isEmpty())<div class="empty-state">No overtime in this range.</div>@else
<div class="table-wrap"><table><thead><tr><th style="width:34px"></th><th>Day</th><th>Employee</th><th>Worked past / on day off</th><th>Overtime</th><th>Approve (minutes)</th><th>Status</th></tr></thead><tbody>
@foreach ($rows as $r)
<tr><td><input type="checkbox" name="ids[]" value="{{ $r->id }}"></td><td>{{ $r->work_date->format('D d M') }}</td>
<td><a href="{{ route('hr.employees.show', $r->employee) }}"><strong>{{ $r->employee->code }}</strong></a> {{ $r->employee->full_name }}</td>
<td class="small">{{ $r->check_in?->format('H:i') }} – {{ $r->check_out?->format('H:i') }} @if ($r->hasFlag('WORKED_OFF'))<span class="status status-in-progress">Day off</span>@endif</td>
<td>{{ \App\Models\AttendanceRecord::hm($r->overtime_minutes) }}</td>
<td><input type="number" name="minutes[{{ $r->id }}]" value="{{ $r->overtime_status === 'APPROVED' ? $r->overtime_approved_minutes : $r->overtime_minutes }}" min="0" max="{{ $r->overtime_minutes }}" style="width:90px;min-height:30px;padding:3px 6px"></td>
<td><span class="status {{ ['PENDING' => 'status-on-hold', 'APPROVED' => 'status-completed', 'REJECTED' => 'status-cancelled'][$r->overtime_status] ?? '' }}">{{ ucfirst(strtolower($r->overtime_status)) }}</span></td></tr>
@endforeach</tbody></table></div>
<div class="panel-body"><button class="btn btn-primary" name="decision" value="APPROVE">Approve ticked</button> <button class="btn" name="decision" value="REJECT">Reject ticked</button></div>
<div class="panel-body">{{ $rows->links() }}</div>@endif</div></form>
@endsection
