@extends('layouts.app')
@section('title', $e->code.' '.$e->full_name)
@section('breadcrumb')<a href="{{ route('hr.employees.index') }}">Employees</a> / {{ $e->code }} @endsection
@section('content')
@php
    $photo = \App\Models\Attachment::coverFor('employee', $e->id);
    $opts = fn ($c, $fn = null) => $c->mapWithKeys(fn ($r) => [$r->id => $fn ? $fn($r) : $r->name])->all();
    $onb = $e->tasks->where('kind', 'ONBOARDING');
    $ext = $e->tasks->where('kind', 'EXIT');
    $docFiles = \App\Models\Attachment::where('attachable_type', 'employee_document')->whereIn('attachable_id', $e->documents->pluck('id'))->whereNull('removed_at')->get()->groupBy('attachable_id');
    $expiry = ['EXPIRED' => ['status-cancelled', 'Expired'], 'SOON' => ['status-on-hold', 'Expires soon'], 'OK' => ['status-completed', 'Valid']];
@endphp
<div class="page-heading">
    <div style="display:flex;gap:16px;align-items:center">
        @if ($photo)<img src="{{ route('attachments.download', $photo) }}" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:50%;border:1px solid var(--border);padding:0;min-height:0">@endif
        <div><h1>{{ $e->full_name }}</h1><p>{{ $e->code }} · {{ $e->designation?->name ?? 'No designation' }} · {{ $e->department?->name ?? 'No department' }} · @include('hr._chip', ['s' => $e->status])</p></div>
    </div>
    <div class="actions">
        @if ($e->status === 'ONBOARDING')<form method="POST" action="{{ route('hr.employees.start-work', $e) }}">@csrf<button class="btn btn-primary" type="submit">Finish onboarding</button></form>@endif
        <a class="btn" href="{{ route('hr.employees.index') }}">All employees</a>
    </div>
</div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@if ($openExit)<div class="alert alert-error">Leaving on {{ $openExit->last_working_day->format('d M Y') }} ({{ config('hr.exit_types')[$openExit->exit_type] }}). <a href="{{ route('hr.exits.show', $openExit) }}">Open the exit</a></div>@endif

<div class="tab-marker" data-tab-start="Summary"></div>
<div class="panel"><div class="panel-header"><h2>At a glance</h2></div><div class="panel-body">
<div class="form-grid">
    <div class="field"><label>Position</label><div>{{ $e->designation?->name ?? '—' }}{{ $e->grade ? ' · '.$e->grade->name : '' }}</div></div>
    <div class="field"><label>Department</label><div>{{ $e->department?->path() ?? '—' }}</div></div>
    <div class="field"><label>Location</label><div>{{ $e->location?->name ?? '—' }}</div></div>
    <div class="field"><label>Reports to</label><div>@if ($e->manager)<a href="{{ route('hr.employees.show', $e->manager) }}">{{ $e->manager->full_name }}</a>@else — @endif</div></div>
    <div class="field"><label>Cost centre</label><div>{{ $e->costCentre ? $e->costCentre->code.' · '.$e->costCentre->name : '—' }}</div></div>
    <div class="field"><label>Employment</label><div>{{ config('hr.employment_types')[$e->employment_type] ?? $e->employment_type }}</div></div>
    <div class="field"><label>Joined</label><div>{{ $e->joined_on->format('d M Y') }} · {{ $e->service() }}</div></div>
    <div class="field"><label>{{ $e->status === 'PROBATION' ? 'Probation ends' : 'Confirmed' }}</label><div>{{ $e->status === 'PROBATION' ? $e->probation_ends_on?->format('d M Y') : ($e->confirmed_on?->format('d M Y') ?? '—') }}</div></div>
    <div class="field"><label>Mobile</label><div>{{ $e->phone ?: '—' }}</div></div>
    <div class="field"><label>Work email</label><div>{{ $e->work_email ?: '—' }}</div></div>
    @if ($e->reports->isNotEmpty())<div class="field field-full"><label>Direct reports ({{ $e->reports->count() }})</label><div>@foreach ($e->reports as $r)<a href="{{ route('hr.employees.show', $r) }}">{{ $r->full_name }}</a>{{ $r->designation ? ' ('.$r->designation->name.')' : '' }}{{ ! $loop->last ? ', ' : '' }}@endforeach</div></div>@endif
</div></div></div>
@if ($onb->isNotEmpty() && $e->status === 'ONBOARDING')
<div class="panel"><div class="panel-header"><h2>Joining checklist</h2><span class="muted small">{{ $onb->whereNotNull('done_at')->count() }} of {{ $onb->count() }} done</span></div></div>
@endif

<div class="tab-marker" data-tab-start="Personal & pay"></div>
<form method="POST" action="{{ route('hr.employees.update', $e) }}">@csrf @method('PUT')
    @include('hr.employees._fields', ['e' => $e])
    <div class="panel"><div class="panel-header"><h2>Login</h2></div><div class="panel-body"><div class="form-grid">
        @include('hr._sel', ['name' => 'user_id', 'label' => 'Linked user account', 'items' => $opts($users, fn ($u) => $u->name.' · '.$u->email), 'sel' => $e->user_id, 'blank' => 'No login'])
        <div class="help field-full">A linked login is switched off automatically when the person leaves.</div>
    </div></div></div>
    <p><button class="btn btn-primary" type="submit">Save changes</button></p>
</form>

<div class="tab-marker" data-tab-start="Position & history"></div>
@if ($e->isCurrent() && ! $openExit)
<div class="panel"><div class="panel-header"><h2>Transfer or promote</h2><span class="muted small">Dated and recorded; may need approval</span></div><div class="panel-body">
<form method="POST" action="{{ route('hr.employees.transfer', $e) }}">@csrf
<div class="form-grid">
    @include('hr._sel', ['name' => 'kind', 'label' => 'This is a', 'items' => ['TRANSFER' => 'Transfer', 'PROMOTION' => 'Promotion'], 'sel' => 'TRANSFER', 'blank' => false, 'required' => true])
    @include('hr._in', ['name' => 'effective_on', 'label' => 'Effective from', 'type' => 'date', 'val' => now()->toDateString(), 'required' => true])
    @include('hr._sel', ['name' => 'department_id', 'label' => 'Department', 'items' => $opts($departments, fn ($d) => $d->path()), 'sel' => $e->department_id])
    @include('hr._sel', ['name' => 'designation_id', 'label' => 'Designation', 'items' => $opts($designations), 'sel' => $e->designation_id])
    @include('hr._sel', ['name' => 'grade_id', 'label' => 'Grade', 'items' => $opts($grades), 'sel' => $e->grade_id])
    @include('hr._sel', ['name' => 'location_id', 'label' => 'Location', 'items' => $opts($locations), 'sel' => $e->location_id])
    @include('hr._sel', ['name' => 'cost_centre_id', 'label' => 'Cost centre', 'items' => $opts($costCentres, fn ($c) => $c->code.' · '.$c->name), 'sel' => $e->cost_centre_id])
    @include('hr._sel', ['name' => 'manager_id', 'label' => 'Reports to', 'items' => $opts($managers->where('id', '!=', $e->id), fn ($m) => $m->code.' · '.$m->full_name), 'sel' => $e->manager_id])
    @include('hr._in', ['name' => 'note', 'label' => 'Note', 'val' => '', 'ph' => 'Reason or letter reference'])
</div><p><button class="btn btn-primary" type="submit">Record</button></p></form>
</div></div>
@endif
@if ($e->status === 'PROBATION')
<div class="panel"><div class="panel-header"><h2>Confirm after probation</h2><span class="muted small">Probation ends {{ $e->probation_ends_on?->format('d M Y') ?? '—' }}</span></div><div class="panel-body">
<form method="POST" action="{{ route('hr.employees.confirm', $e) }}">@csrf<div class="form-grid">
    @include('hr._in', ['name' => 'confirmed_on', 'label' => 'Confirmed from', 'type' => 'date', 'val' => now()->toDateString(), 'required' => true])
    @include('hr._in', ['name' => 'note', 'label' => 'Note', 'val' => ''])
</div><p><button class="btn btn-primary" type="submit">Confirm employee</button></p></form></div></div>
@endif
@if ($e->isCurrent() && ! $openExit)
<div class="panel"><div class="panel-header"><h2>Leaving the company</h2></div><div class="panel-body">
<form method="POST" action="{{ route('hr.employees.exit', $e) }}">@csrf<div class="form-grid">
    @include('hr._sel', ['name' => 'exit_type', 'label' => 'Type', 'items' => config('hr.exit_types'), 'sel' => 'RESIGNATION', 'blank' => false, 'required' => true])
    @include('hr._in', ['name' => 'notice_given_on', 'label' => 'Notice given on', 'type' => 'date', 'val' => now()->toDateString()])
    @include('hr._in', ['name' => 'last_working_day', 'label' => 'Last working day', 'type' => 'date', 'val' => now()->addDays(30)->toDateString(), 'required' => true])
    @include('hr._in', ['name' => 'reason', 'label' => 'Reason', 'val' => ''])
</div><p><button class="btn btn-danger" type="submit" onclick="return confirm('Start the exit process for this person?')">Start exit</button></p></form></div></div>
@endif
<div class="panel"><div class="panel-header"><h2>History</h2></div>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Event</th><th>What changed</th><th>By</th></tr></thead><tbody>
@foreach ($e->events as $ev)
<tr><td>{{ $ev->effective_on->format('d M Y') }}</td><td>{{ \App\Models\EmployeeEvent::KINDS[$ev->kind] ?? $ev->kind }}</td>
<td>@foreach (($ev->changes ?? []) as $c)@if (isset($c['label'])){{ $c['label'] }}: {{ $c['from'] ?? '—' }} → <strong>{{ $c['to'] ?? '—' }}</strong>@else{{ $c['from'] ?? '' }} → <strong>{{ $c['to'] ?? '' }}</strong>@endif<br>@endforeach{{ $ev->note }}</td><td class="muted">{{ $ev->created_by_name }}</td></tr>
@endforeach</tbody></table></div></div>

<div class="tab-marker" data-tab-start="Checklists"></div>
@foreach (['ONBOARDING' => ['Joining checklist', $onb], 'EXIT' => ['Leaving checklist', $ext]] as $kind => [$title, $list])
@if ($list->isNotEmpty() || $kind === 'ONBOARDING')
<div class="panel"><div class="panel-header"><h2>{{ $title }}</h2><span class="muted small">{{ $list->whereNotNull('done_at')->count() }} of {{ $list->count() }} done</span></div>
@if ($list->isNotEmpty())<div class="table-wrap"><table><thead><tr><th style="width:44px"></th><th>Task</th><th>Owner</th><th>Due</th><th>Done</th><th></th></tr></thead><tbody>
@foreach ($list as $t)
<tr><td><form method="POST" action="{{ route('hr.tasks.toggle', $t) }}">@csrf<button class="btn btn-small" type="submit" title="{{ $t->done_at ? 'Mark not done' : 'Mark done' }}">{!! $t->done_at ? '&#10003;' : '&#9675;' !!}</button></form></td>
<td @if ($t->done_at) style="text-decoration:line-through;color:var(--muted)" @endif>{{ $t->title }}</td><td>{{ \App\Models\EmployeeTask::OWNERS[$t->owner] ?? $t->owner }}</td>
<td>@if ($t->isOverdue())<span class="status status-cancelled">{{ $t->due_on->format('d M') }}</span>@else{{ $t->due_on?->format('d M Y') ?? '—' }}@endif</td>
<td class="muted small">{{ $t->done_at ? $t->done_at->format('d M Y').' · '.$t->done_by_name : '' }}</td>
<td><form method="POST" action="{{ route('hr.tasks.destroy', $t) }}" onsubmit="return confirm('Remove this task?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $t->title])</form></td></tr>
@endforeach</tbody></table></div>@endif
<div class="panel-body"><form method="POST" action="{{ route('hr.employees.tasks.store', $e) }}" class="form-grid">@csrf<input type="hidden" name="kind" value="{{ $kind }}">
    @include('hr._in', ['name' => 'title', 'label' => 'Add a task', 'val' => '', 'required' => true])
    @include('hr._sel', ['name' => 'owner', 'label' => 'Owner', 'items' => \App\Models\EmployeeTask::OWNERS, 'sel' => 'HR', 'blank' => false])
    @include('hr._in', ['name' => 'due_on', 'label' => 'Due', 'type' => 'date', 'val' => ''])
    <div class="field"><label>&nbsp;</label><button class="btn" type="submit">Add</button></div></form></div></div>
@endif
@endforeach

<div class="tab-marker" data-tab-start="Attendance"></div>
@php $stc = ['PRESENT' => 'status-completed', 'HALF_DAY' => 'status-on-hold', 'ABSENT' => 'status-cancelled', 'LEAVE' => 'status-in-progress', 'OFF' => '', 'HOLIDAY' => '']; @endphp
<div class="panel"><div class="panel-header"><h2>{{ $month->format('F Y') }}</h2>
<div><a class="btn btn-small" href="{{ route('hr.employees.show', [$e, 'month' => $month->copy()->subMonth()->format('Y-m-01'), 'tab' => 'attendance']) }}">&larr;</a> <a class="btn btn-small" href="{{ route('hr.employees.show', [$e, 'month' => $month->copy()->addMonth()->format('Y-m-01'), 'tab' => 'attendance']) }}">&rarr;</a></div></div>
<div class="panel-body small">Present {{ $attSummary['PRESENT'] }} · Half days {{ $attSummary['HALF_DAY'] }} · Absent {{ $attSummary['ABSENT'] }} · Leave {{ $attSummary['LEAVE'] }} · Days off {{ $attSummary['OFF'] + $attSummary['HOLIDAY'] }} · Late {{ $attSummary['late'] }} times · Approved overtime {{ \App\Models\AttendanceRecord::hm($attSummary['overtime']) }}</div>
<div class="table-wrap"><table><thead><tr><th>Day</th><th>Status</th><th>In</th><th>Out</th><th>Worked</th><th></th></tr></thead><tbody>
@for ($d = $month->copy(); $d->month === $month->month; $d->addDay())
@php $r = $attRows->get($d->toDateString()); @endphp
<tr><td>{{ $d->format('D d') }}</td><td>@if ($r)<span class="status {{ $stc[$r->status] ?? '' }}">{{ config('hr.attendance.day_status')[$r->status] }}</span>@else<span class="muted">—</span>@endif</td>
<td>{{ $r?->check_in?->format('H:i') ?? '' }}</td><td>{{ $r?->check_out?->format('H:i') ?? '' }}</td><td>{{ $r ? \App\Models\AttendanceRecord::hm($r->worked_minutes) : '' }}</td><td>@if ($r)@include('hr._dayflags', ['flags' => $r->flags])@endif</td></tr>
@endfor</tbody></table></div></div>

<div class="tab-marker" data-tab-start="Leave"></div>
@php $nf = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.') ?: '0'; @endphp
<div class="metric-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:16px">
@foreach ($leaveBalances as $b)<div class="metric"><div class="metric-label">{{ $b['type']->name }}</div><div class="metric-value">{{ $nf($b['balance']) }}</div></div>@endforeach</div>
<div class="panel"><div class="panel-header"><h2>Requests</h2></div>
@if ($leaveRequests->isEmpty())<div class="empty-state">No leave yet.</div>@else
<div class="table-wrap"><table><thead><tr><th>Request</th><th>Leave</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead><tbody>
@foreach ($leaveRequests as $r)<tr><td>{{ $r->code }}</td><td>{{ $r->type->name }}</td><td>{{ $r->from_date->format('d M Y') }}{{ $r->to_date->ne($r->from_date) ? ' – '.$r->to_date->format('d M Y') : '' }}</td><td>{{ $nf($r->days) }}</td><td>{{ ucfirst(strtolower($r->status)) }}</td></tr>@endforeach
</tbody></table></div>@endif
<div class="panel-body"><form method="POST" action="{{ route('leave.store') }}">@csrf<input type="hidden" name="employee_id" value="{{ $e->id }}"><div class="form-grid">
@include('hr._sel', ['name' => 'leave_type_id', 'label' => 'Record leave', 'items' => $leaveTypes->mapWithKeys(fn ($t) => [$t->id => $t->name])->all(), 'sel' => '', 'blank' => 'Choose…', 'required' => true])
@include('hr._in', ['name' => 'from_date', 'label' => 'From', 'type' => 'date', 'val' => '', 'required' => true]) @include('hr._in', ['name' => 'to_date', 'label' => 'To', 'type' => 'date', 'val' => ''])
<div class="field"><label><input type="checkbox" name="half_day" value="1"> Half day</label><label><input type="checkbox" name="approved_now" value="1" checked> Approved</label></div>
</div><p><button class="btn btn-primary">Record leave</button></p></form></div></div>
<div class="panel"><div class="panel-header"><h2>Balance history</h2></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Leave</th><th>What</th><th>Days</th><th>Note</th></tr></thead><tbody>
@forelse ($ledger as $l)<tr><td>{{ $l->entry_date->format('d M Y') }}</td><td>{{ $l->type->code }}</td><td>{{ ucfirst(strtolower($l->kind)) }}</td><td @if ($l->days < 0) style="color:var(--danger)" @endif>{{ $l->days > 0 ? '+' : '' }}{{ $nf($l->days) }}</td><td class="muted small">{{ $l->note }}</td></tr>@empty<tr><td colspan="5" class="muted">Nothing yet.</td></tr>@endforelse
</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('hr.leave.adjust', $e) }}"><div class="form-grid">@csrf
@include('hr._sel', ['name' => 'leave_type_id', 'label' => 'Adjust a balance', 'items' => $leaveBalances->mapWithKeys(fn ($b) => [$b['type']->id => $b['type']->name])->all(), 'sel' => '', 'blank' => 'Choose…', 'required' => true])
@include('hr._in', ['name' => 'days', 'label' => 'Days (+ or −)', 'type' => 'number', 'val' => '', 'required' => true]) @include('hr._in', ['name' => 'note', 'label' => 'Why', 'val' => '', 'required' => true])
</div><p><button class="btn">Adjust</button></p></form></div></div>

@if (! empty($pay))@include('hr.payroll._employee', ['e' => $e, 'pay' => $pay])@endif
<div class="tab-marker" data-tab-start="Documents"></div>
<div class="panel"><div class="panel-header"><h2>Documents on record</h2></div>
@if ($e->documents->isNotEmpty())<div class="table-wrap"><table><thead><tr><th>Document</th><th>Number</th><th>Issued</th><th>Expires</th><th>File</th><th></th></tr></thead><tbody>
@foreach ($e->documents as $d)
@php $st = $d->expiryState(); @endphp
<tr><td>{{ config('hr.document_kinds')[$d->kind] ?? $d->kind }}{{ $d->title ? ' · '.$d->title : '' }}</td><td>{{ $d->number ?: '—' }}</td><td>{{ $d->issued_on?->format('d M Y') ?? '—' }}</td>
<td>{{ $d->expires_on?->format('d M Y') ?? '—' }} @if ($st && $st !== 'OK')<span class="status {{ $expiry[$st][0] }}">{{ $expiry[$st][1] }}</span>@endif</td>
<td>@foreach ($docFiles->get($d->id, collect()) as $f)<a href="{{ route('attachments.download', $f) }}" @if ($f->isInlineViewable()) target="_blank" @endif>{{ $f->original_name }}</a>@endforeach</td>
<td><form method="POST" action="{{ route('hr.documents.destroy', $d) }}" onsubmit="return confirm('Remove this document record?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $d->title ?: (config('hr.document_kinds')[$d->kind] ?? $d->kind)])</form></td></tr>
@endforeach</tbody></table></div>@endif
<div class="panel-body"><form method="POST" action="{{ route('hr.employees.documents.store', $e) }}" enctype="multipart/form-data"><div class="form-grid">
    @include('hr._sel', ['name' => 'kind', 'label' => 'Type', 'items' => config('hr.document_kinds'), 'sel' => 'CNIC', 'blank' => false, 'required' => true])
    @include('hr._in', ['name' => 'number', 'label' => 'Number', 'val' => ''])
    @include('hr._in', ['name' => 'issued_on', 'label' => 'Issued', 'type' => 'date', 'val' => ''])
    @include('hr._in', ['name' => 'expires_on', 'label' => 'Expires', 'type' => 'date', 'val' => ''])
    @include('hr._in', ['name' => 'title', 'label' => 'Title', 'val' => ''])
    <div class="field"><label for="f_file">File</label><input id="f_file" type="file" name="file" data-max-kb="{{ \App\Support\Uploads::effectiveMaxKb() }}"></div>
</div><p><button class="btn btn-primary" type="submit">Add document</button></p></form></div></div>

<div class="tab-marker" data-tab-start="Policies"></div>
<div class="panel"><div class="panel-header"><h2>Policy acknowledgements</h2></div>
@if ($policies->isEmpty())<div class="empty-state">No published policies need acknowledging.</div>@else
<div class="table-wrap"><table><thead><tr><th>Policy</th><th>Version</th><th>Status</th><th></th></tr></thead><tbody>
@foreach ($policies as $p)
@php $a = $p->acks->firstWhere('version', $p->version); @endphp
<tr><td><a href="{{ route('hr.policies.show', $p) }}">{{ $p->code }} · {{ $p->title }}</a></td><td>v{{ $p->version }}</td>
<td>@if ($a)<span class="status status-completed">Acknowledged {{ $a->acknowledged_at->format('d M Y') }}</span>@else<span class="status status-on-hold">Not yet</span>@endif</td>
<td>@unless ($a)<form method="POST" action="{{ route('hr.policies.acknowledge', $p) }}">@csrf<input type="hidden" name="employee_ids[]" value="{{ $e->id }}"><button class="btn btn-small">Record acknowledgement</button></form>@endunless</td></tr>
@endforeach</tbody></table></div>@endif</div>

<div class="tab-marker" data-tab-start="Files"></div>
@include('attachments._panel', ['type' => 'employee', 'id' => $e->id])
@endsection
