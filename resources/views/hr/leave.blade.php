@extends('layouts.app')
@section('title', 'Leave')
@section('breadcrumb', 'Leave')
@section('content')
@php
    $cls = ['PENDING' => 'status-on-hold', 'APPROVED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'CANCELLED' => 'status-cancelled'];
    $nf = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.') ?: '0';
    $tab = request('tab');
@endphp
<div class="page-heading"><div><h1>Leave</h1><p>Requests, balances, leave types and the yearly runs.</p></div><div class="actions"><a class="btn" href="{{ route('leave.my') }}">My leave &amp; approvals</a></div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
<style>.row-edit summary { cursor: pointer; color: var(--accent); font-weight: 600; }</style>

<div class="tab-marker" data-tab-start="Requests{{ $pending ? ' ('.$pending.')' : '' }}"></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="status">Show</label><select id="status" name="status">@foreach (['PENDING' => 'Waiting for a decision', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'CANCELLED' => 'Cancelled', 'ALL' => 'All'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="employee">Employee</label><select id="employee" name="employee"><option value="">Everyone</option>@foreach ($people as $p)<option value="{{ $p->id }}" @selected(request('employee') === $p->id)>{{ $p->code }} · {{ $p->full_name }}</option>@endforeach</select></div>
    <button class="btn btn-primary">Filter</button></form></div></div>
<div class="panel">@if ($requests->isEmpty())<div class="empty-state">No requests.</div>@else
<div class="table-wrap"><table><thead><tr><th>Request</th><th>Employee</th><th>Leave</th><th>Dates</th><th>Days</th><th>Status</th><th></th></tr></thead><tbody>
@foreach ($requests as $r)
<tr><td><strong>{{ $r->code }}</strong></td><td><a href="{{ route('hr.employees.show', $r->employee) }}">{{ $r->employee->full_name }}</a><div class="muted small">{{ $r->employee->department?->name }}</div></td><td>{{ $r->type->name }}</td>
<td>{{ $r->from_date->format('d M') }}{{ $r->to_date->ne($r->from_date) ? ' – '.$r->to_date->format('d M Y') : ' '.$r->from_date->format('Y') }}{{ $r->half_day ? ' (half day)' : '' }}@if ($r->reason)<div class="muted small">{{ $r->reason }}</div>@endif</td><td>{{ $nf($r->days) }}</td>
<td><span class="status {{ $cls[$r->status] }}">{{ ucfirst(strtolower($r->status)) }}</span>@if ($r->decided_by_name)<div class="muted small">{{ $r->decided_by_name }}</div>@endif</td>
<td style="min-width:240px">@if ($r->status === 'PENDING')
<form method="POST" action="{{ route('leave.decide', $r) }}" style="display:flex;gap:4px">@csrf<input name="note" placeholder="Note" style="min-height:28px;padding:2px 6px;max-width:110px"><button class="btn btn-small btn-primary" name="decision" value="APPROVE">Approve</button><button class="btn btn-small" name="decision" value="REJECT">Reject</button></form>
@elseif ($r->status === 'APPROVED')<form method="POST" action="{{ route('leave.cancel', $r) }}" onsubmit="return confirm('Cancel this approved leave and give the days back?')">@csrf<button class="btn btn-small">Cancel</button></form>@endif</td></tr>
@endforeach</tbody></table></div><div class="panel-body">{{ $requests->links() }}</div>@endif</div>
<div class="panel"><div class="panel-header"><h2>Record leave for someone</h2><span class="muted small">For leave already agreed, or a request on someone's behalf</span></div><div class="panel-body">
<form method="POST" action="{{ route('leave.store') }}">@csrf<div class="form-grid">
@include('hr._sel', ['name' => 'employee_id', 'label' => 'Employee', 'items' => $people->mapWithKeys(fn ($p) => [$p->id => $p->code.' · '.$p->full_name])->all(), 'sel' => '', 'blank' => 'Choose…', 'required' => true])
@include('hr._sel', ['name' => 'leave_type_id', 'label' => 'Leave type', 'items' => $types->where('active', true)->mapWithKeys(fn ($t) => [$t->id => $t->name])->all(), 'sel' => '', 'blank' => 'Choose…', 'required' => true])
@include('hr._in', ['name' => 'from_date', 'label' => 'From', 'type' => 'date', 'val' => '', 'required' => true]) @include('hr._in', ['name' => 'to_date', 'label' => 'To', 'type' => 'date', 'val' => '', 'help' => 'Leave blank for one day.'])
@include('hr._in', ['name' => 'reason', 'label' => 'Reason', 'val' => ''])
<div class="field"><label><input type="checkbox" name="half_day" value="1"> Half day</label></div>
<div class="field"><label><input type="checkbox" name="approved_now" value="1" checked> Approve straight away</label></div>
</div><p><button class="btn btn-primary">Record</button></p></form></div></div>

<div class="tab-marker" data-tab-start="Balances"></div>
<div class="panel">@if ($tracked->isEmpty())<div class="empty-state">No leave type keeps a balance yet.</div>@else
<div class="table-wrap"><table><thead><tr><th>Employee</th>@foreach ($tracked as $t)<th>{{ $t->code }}</th>@endforeach</tr></thead><tbody>
@foreach ($people as $p)<tr><td><a href="{{ route('hr.employees.show', $p) }}"><strong>{{ $p->code }}</strong></a> {{ $p->full_name }}</td>
@foreach ($tracked as $t)@php $b = optional(($sums[$p->id] ?? collect())->firstWhere('leave_type_id', $t->id))->d ?? 0; @endphp<td @if ($b < 0) style="color:var(--danger)" @endif>{{ $nf($b) }}</td>@endforeach</tr>@endforeach
</tbody></table></div>@endif</div>
<p class="muted small">{{ $tracked->map(fn ($t) => $t->code.' = '.$t->name)->implode(' · ') }}. Open an employee's Leave tab to adjust a balance.</p>

<div class="tab-marker" data-tab-start="Leave types"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Leave</th><th>Days a year</th><th>Entitlement</th><th>Carry forward</th><th>Rules</th><th></th></tr></thead><tbody>
@foreach ($types as $t)
<tr><td><strong>{{ $t->code }}</strong></td><td>{{ $t->name }} @unless ($t->active)<span class="status status-cancelled">Off</span>@endunless</td><td>{{ $t->annual_days > 0 ? $nf($t->annual_days) : '—' }}</td>
<td>{{ ['NONE' => 'No balance kept', 'MONTHLY' => 'Earned monthly', 'UPFRONT' => 'Given at the start of the year'][$t->accrual] }}</td><td>{{ $t->tracksBalance() ? 'up to '.$nf($t->carry_forward_max) : '—' }}</td>
<td class="small">{{ $t->paid ? 'Paid' : 'Unpaid' }}{{ $t->allow_half_day ? ', half days' : '' }}{{ $t->counts_days_off ? ', counts days off' : '' }}{{ $t->after_probation_only ? ', after probation' : '' }}{{ $t->gender ? ', '.($t->gender === 'F' ? 'women' : 'men').' only' : '' }}</td>
<td><details class="row-edit">@include('partials.icon-button', ['type' => 'edit', 'name' => $t->name, 'tag' => 'summary'])<form method="POST" action="{{ route('hr.leave.types.update', $t) }}" class="form-grid">@csrf @method('PUT')
@include('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => $t->code, 'required' => true]) @include('hr._in', ['name' => 'name', 'label' => 'Name', 'val' => $t->name, 'required' => true])
@include('hr._in', ['name' => 'annual_days', 'label' => 'Days a year', 'type' => 'number', 'val' => $t->annual_days, 'required' => true])
@include('hr._sel', ['name' => 'accrual', 'label' => 'Entitlement', 'items' => ['NONE' => 'No balance kept', 'MONTHLY' => 'Earned monthly', 'UPFRONT' => 'Given at the start of the year'], 'sel' => $t->accrual, 'blank' => false])
@include('hr._in', ['name' => 'carry_forward_max', 'label' => 'Carry forward up to', 'type' => 'number', 'val' => $t->carry_forward_max])
@include('hr._sel', ['name' => 'gender', 'label' => 'Only for', 'items' => ['F' => 'Women', 'M' => 'Men'], 'sel' => $t->gender, 'blank' => 'Everyone'])
<div class="field"><label><input type="checkbox" name="paid" value="1" @checked($t->paid)> Paid</label><label><input type="checkbox" name="allow_half_day" value="1" @checked($t->allow_half_day)> Half days allowed</label><label><input type="checkbox" name="counts_days_off" value="1" @checked($t->counts_days_off)> Days off count too</label><label><input type="checkbox" name="after_probation_only" value="1" @checked($t->after_probation_only)> After probation only</label><label><input type="checkbox" name="active" value="1" @checked($t->active)> In use</label></div>
<div class="field"><button class="btn btn-primary">Save</button></div></form></details></td></tr>
@endforeach</tbody></table></div>
<div class="panel-body"><p class="muted small">The days and rules above are starting values, not legal advice. Set them to what your province's labour law and your contracts say.</p>
<form method="POST" action="{{ route('hr.leave.types.store') }}">@csrf<div class="form-grid">
@include('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'required' => true, 'ph' => 'e.g. HL']) @include('hr._in', ['name' => 'name', 'label' => 'New leave type', 'val' => '', 'required' => true])
@include('hr._in', ['name' => 'annual_days', 'label' => 'Days a year', 'type' => 'number', 'val' => 0, 'required' => true])
@include('hr._sel', ['name' => 'accrual', 'label' => 'Entitlement', 'items' => ['NONE' => 'No balance kept', 'MONTHLY' => 'Earned monthly', 'UPFRONT' => 'Given at the start of the year'], 'sel' => 'NONE', 'blank' => false])
<div class="field"><label><input type="checkbox" name="paid" value="1" checked> Paid</label><label><input type="checkbox" name="allow_half_day" value="1" checked> Half days allowed</label></div>
</div><p><button class="btn btn-primary">Add leave type</button></p></form></div></div>

<div class="tab-marker" data-tab-start="Yearly runs"></div>
<div class="panel"><div class="panel-header"><h2>Bring entitlements up to date</h2></div><div class="panel-body">
<p class="muted">Gives everyone this year's up-front leave and every completed month of monthly leave. Safe to run again: nothing is added twice. The same run is available from the command line as <code>php artisan hr:leave-accrue</code>.</p>
<form method="POST" action="{{ route('hr.leave.accrue') }}">@csrf<button class="btn btn-primary">Run now</button></form></div></div>
<div class="panel"><div class="panel-header"><h2>Close a year</h2></div><div class="panel-body">
<p class="muted">Carries each balance forward up to the leave type's limit and writes off the rest. Run it once the year has ended, then run the entitlements for the new year.</p>
<form method="POST" action="{{ route('hr.leave.close-year') }}" class="form-grid" onsubmit="return confirm('Write off leave above the carry-forward limit for this year?')">@csrf
@include('hr._in', ['name' => 'year', 'label' => 'Year', 'type' => 'number', 'val' => $year - 1, 'required' => true])<div class="field"><label>&nbsp;</label><button class="btn">Close the year</button></div></form></div></div>
@if ($tab)<script>document.addEventListener('DOMContentLoaded', function () { var m = {requests:'Requests',balances:'Balances',types:'Leave types',runs:'Yearly runs'}[@json($tab)]; var b = [].slice.call(document.querySelectorAll('.tabbar button')).filter(function (x) { return x.textContent.trim().indexOf(m) === 0; })[0]; if (b) b.click(); });</script>@endif
@endsection
