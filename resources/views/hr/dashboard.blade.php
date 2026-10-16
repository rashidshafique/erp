@extends('layouts.app')
@section('title', 'People overview')
@section('breadcrumb', 'People')
@section('content')
<div class="page-heading"><div><h1>People</h1><p>Headcount, what needs attention and recent movements.</p></div>
<div class="actions"><a class="btn" href="{{ route('directory.index') }}">Directory</a><a class="btn btn-primary" href="{{ route('hr.employees.create') }}">+ New employee</a></div></div>
<div class="metric-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:16px">
@foreach ([['Headcount', $stats['headcount']], ['Onboarding', $stats['onboarding']], ['On probation', $stats['probation']], ['Serving notice', $stats['notice']], ['Joined this month', $stats['joined']], ['Left this month', $stats['left']]] as [$l, $n])
<div class="metric"><div class="metric-label">{{ $l }}</div><div class="metric-value">{{ number_format($n) }}</div></div>
@endforeach
</div>

<div class="tab-marker" data-tab-start="Needs attention"></div>
@if ($work['today']->isNotEmpty())<p class="muted small" style="margin:0 0 10px">Today: @foreach ($work['today'] as $k => $n){{ config('hr.attendance.day_status')[$k] ?? $k }} {{ $n }}{{ ! $loop->last ? ' · ' : '' }}@endforeach</p>@endif
@php $none = collect($alerts)->every(fn ($a) => $a->isEmpty()) && $policyGaps->isEmpty() && ! $work['exceptions'] && ! $work['overtime'] && ! $work['leave']; @endphp
@if ($work['exceptions'] || $work['overtime'] || $work['leave'])
<div class="panel"><div class="panel-header"><h2>Waiting for HR</h2></div><div class="panel-body">
@if ($work['leave'])<a class="btn" href="{{ route('hr.leave') }}">{{ $work['leave'] }} leave request(s) to decide</a> @endif
@if ($work['exceptions'])<a class="btn" href="{{ route('hr.attendance.exceptions') }}">{{ $work['exceptions'] }} attendance exception(s)</a> @endif
@if ($work['overtime'])<a class="btn" href="{{ route('hr.attendance.overtime') }}">{{ $work['overtime'] }} overtime entr{{ $work['overtime'] === 1 ? 'y' : 'ies' }} to approve</a> @endif
</div></div>
@endif
@if ($none)<div class="panel"><div class="empty-state">Nothing needs attention.</div></div>@endif
@if ($alerts['probation']->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Probation ending</h2><span class="muted small">Next {{ config('hr.alert_days') }} days</span></div><div class="table-wrap"><table><tbody>
@foreach ($alerts['probation'] as $x)<tr><td><a href="{{ route('hr.employees.show', $x) }}">{{ $x->code }} · {{ $x->full_name }}</a></td><td>{{ $x->probation_ends_on->format('d M Y') }}</td><td>{!! $x->probation_ends_on->isPast() ? '<span class="status status-cancelled">Overdue</span>' : '' !!}</td></tr>@endforeach
</tbody></table></div></div>@endif
@if ($alerts['contracts']->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Contracts ending</h2></div><div class="table-wrap"><table><tbody>
@foreach ($alerts['contracts'] as $x)<tr><td><a href="{{ route('hr.employees.show', $x) }}">{{ $x->code }} · {{ $x->full_name }}</a></td><td>{{ $x->contract_ends_on->format('d M Y') }}</td></tr>@endforeach
</tbody></table></div></div>@endif
@if ($alerts['documents']->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Documents expiring</h2></div><div class="table-wrap"><table><tbody>
@foreach ($alerts['documents'] as $d)<tr><td><a href="{{ route('hr.employees.show', $d->employee) }}">{{ $d->employee->full_name }}</a></td><td>{{ config('hr.document_kinds')[$d->kind] ?? $d->kind }}{{ $d->number ? ' '.$d->number : '' }}</td><td>{{ $d->expires_on->format('d M Y') }}</td><td>{!! $d->expiryState() === 'EXPIRED' ? '<span class="status status-cancelled">Expired</span>' : '<span class="status status-on-hold">Soon</span>' !!}</td></tr>@endforeach
</tbody></table></div></div>@endif
@if ($alerts['tasks']->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Overdue checklist tasks</h2></div><div class="table-wrap"><table><tbody>
@foreach ($alerts['tasks'] as $t)<tr><td><a href="{{ route('hr.employees.show', $t->employee) }}">{{ $t->employee->full_name }}</a></td><td>{{ $t->title }}</td><td>{{ \App\Models\EmployeeTask::OWNERS[$t->owner] ?? $t->owner }}</td><td><span class="status status-cancelled">{{ $t->due_on->format('d M') }}</span></td></tr>@endforeach
</tbody></table></div></div>@endif
@if ($alerts['exits']->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>People leaving</h2></div><div class="table-wrap"><table><tbody>
@foreach ($alerts['exits'] as $x)<tr><td><a href="{{ route('hr.exits.show', $x) }}">{{ $x->employee->code }} · {{ $x->employee->full_name }}</a></td><td>{{ config('hr.exit_types')[$x->exit_type] }}</td><td>last day {{ $x->last_working_day->format('d M Y') }}</td></tr>@endforeach
</tbody></table></div></div>@endif
@if ($policyGaps->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Policies waiting for acknowledgement</h2></div><div class="table-wrap"><table><tbody>
@foreach ($policyGaps as $g)<tr><td><a href="{{ route('hr.policies.show', $g['policy']) }}">{{ $g['policy']->code }} · {{ $g['policy']->title }}</a></td><td>{{ $g['missing'] }} still to acknowledge</td></tr>@endforeach
</tbody></table></div></div>@endif

<div class="tab-marker" data-tab-start="Headcount"></div>
<div class="panel"><div class="panel-header"><h2>By department</h2></div><div class="panel-body">
@php $max = max(1, $depts->max('n')); @endphp
@forelse ($depts as $d)
<div style="display:flex;align-items:center;gap:12px;margin:6px 0"><div style="width:200px">{{ $d['name'] }}</div><div style="flex:1;max-width:420px;background:#eef2f4;border-radius:5px;height:14px"><div style="width:{{ round($d['n'] / $max * 100) }}%;background:var(--accent);height:14px;border-radius:5px"></div></div><strong>{{ $d['n'] }}</strong></div>
@empty<div class="empty-state">No employees yet.</div>@endforelse
@if ($unassigned)<p class="muted small">{{ $unassigned }} without a department.</p>@endif
</div></div>
<div class="panel"><div class="panel-header"><h2>By employment type</h2></div><div class="panel-body">
@foreach (config('hr.employment_types') as $k => $l)@if (($byType[$k] ?? 0) > 0)<div style="display:flex;gap:12px;margin:4px 0"><div style="width:200px">{{ $l }}</div><strong>{{ $byType[$k] }}</strong></div>@endif @endforeach
</div></div>

<div class="tab-marker" data-tab-start="Recent movements"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Who</th><th>Event</th></tr></thead><tbody>
@forelse ($recent as $r)<tr><td>{{ $r->effective_on->format('d M Y') }}</td><td><a href="{{ route('hr.employees.show', $r->employee_id) }}">{{ $r->employee?->full_name }}</a></td><td>{{ \App\Models\EmployeeEvent::KINDS[$r->kind] ?? $r->kind }}</td></tr>@empty<tr><td colspan="3" class="muted">Nothing yet.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
