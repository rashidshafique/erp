@extends('layouts.app')
@section('title', 'My leave')
@section('breadcrumb', 'My leave')
@section('content')
@php
    $cls = ['PENDING' => 'status-on-hold', 'APPROVED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'CANCELLED' => 'status-cancelled'];
    $nf = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.') ?: '0';
@endphp
<div class="page-heading"><div><h1>My leave</h1><p>{{ $me ? $me->full_name.' · '.$me->code : 'Your login is not linked to an employee record.' }}</p></div>@if ($isHr)<div class="actions"><a class="btn" href="{{ route('hr.leave') }}">All leave (HR)</a></div>@endif</div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif

@if ($waiting->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Waiting for your decision</h2><span class="muted small">{{ $waiting->count() }}</span></div><div class="table-wrap"><table><thead><tr><th>Who</th><th>Leave</th><th>Dates</th><th>Days</th><th></th></tr></thead><tbody>
@foreach ($waiting as $r)
<tr><td><strong>{{ $r->employee->full_name }}</strong><div class="muted small">{{ $r->code }}</div></td><td>{{ $r->type->name }}</td>
<td>{{ $r->from_date->format('d M') }}{{ $r->to_date->ne($r->from_date) ? ' – '.$r->to_date->format('d M Y') : ' '.$r->from_date->format('Y') }}{{ $r->half_day ? ' (half day)' : '' }}@if ($r->reason)<div class="muted small">{{ $r->reason }}</div>@endif</td><td>{{ $nf($r->days) }}</td>
<td><form method="POST" action="{{ route('leave.decide', $r) }}" style="display:flex;gap:4px">@csrf<input name="note" placeholder="Note" style="min-height:28px;padding:2px 6px;max-width:120px"><button class="btn btn-small btn-primary" name="decision" value="APPROVE">Approve</button><button class="btn btn-small" name="decision" value="REJECT">Reject</button></form></td></tr>
@endforeach</tbody></table></div></div>
@endif

@if ($me || $isHr)
<div class="tab-marker" data-tab-start="Ask for leave"></div>
@if ($me)
<div class="metric-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:16px">
@foreach ($balances as $b)<div class="metric"><div class="metric-label">{{ $b['type']->name }}</div><div class="metric-value">{{ $nf($b['balance']) }}</div></div>@endforeach</div>
@endif
<div class="panel"><div class="panel-header"><h2>New request</h2></div><div class="panel-body">
<form method="POST" action="{{ route('leave.store') }}">@csrf<div class="form-grid">
@if ($isHr)@include('hr._sel', ['name' => 'employee_id', 'label' => 'For', 'items' => $people->mapWithKeys(fn ($p) => [$p->id => $p->code.' · '.$p->full_name])->all(), 'sel' => $me?->id, 'blank' => $me ? false : 'Choose…', 'required' => true])@endif
@include('hr._sel', ['name' => 'leave_type_id', 'label' => 'Leave type', 'items' => $types->mapWithKeys(fn ($t) => [$t->id => $t->name])->all(), 'sel' => '', 'blank' => 'Choose…', 'required' => true])
@include('hr._in', ['name' => 'from_date', 'label' => 'From', 'type' => 'date', 'val' => '', 'required' => true])
@include('hr._in', ['name' => 'to_date', 'label' => 'To', 'type' => 'date', 'val' => '', 'help' => 'Leave blank for one day.'])
@include('hr._in', ['name' => 'reason', 'label' => 'Reason', 'val' => ''])
<div class="field"><label><input type="checkbox" name="half_day" value="1"> Half day</label></div>
</div><p><button class="btn btn-primary">Send for approval</button></p></form></div></div>

<div class="tab-marker" data-tab-start="My requests"></div>
<div class="panel">@if ($mine->isEmpty())<div class="empty-state">No requests yet.</div>@else
<div class="table-wrap"><table><thead><tr><th>Request</th><th>Leave</th><th>Dates</th><th>Days</th><th>Status</th><th></th></tr></thead><tbody>
@foreach ($mine as $r)
<tr><td><strong>{{ $r->code }}</strong></td><td>{{ $r->type->name }}</td><td>{{ $r->from_date->format('d M') }}{{ $r->to_date->ne($r->from_date) ? ' – '.$r->to_date->format('d M Y') : ' '.$r->from_date->format('Y') }}</td><td>{{ $nf($r->days) }}</td>
<td><span class="status {{ $cls[$r->status] }}">{{ ucfirst(strtolower($r->status)) }}</span>@if ($r->decision_note)<div class="muted small">{{ $r->decision_note }}</div>@endif</td>
<td>@if ($r->status === 'PENDING' || ($r->status === 'APPROVED' && $r->from_date->isFuture()))<form method="POST" action="{{ route('leave.cancel', $r) }}" onsubmit="return confirm('Cancel this request?')">@csrf<button class="btn btn-small">Cancel</button></form>@endif</td></tr>
@endforeach</tbody></table></div>@endif</div>
@endif

@if ($team->isNotEmpty())
<div class="tab-marker" data-tab-start="My team"></div>
<div class="panel"><div class="table-wrap"><table><tbody>@foreach ($team as $t)<tr><td><strong>{{ $t->full_name }}</strong></td><td class="muted">{{ $t->designation?->name }}</td></tr>@endforeach</tbody></table></div></div>
@endif
@unless ($me || $isHr)<div class="panel"><div class="empty-state">Ask HR to link your login to your employee record so you can request leave.</div></div>@endunless
@endsection
