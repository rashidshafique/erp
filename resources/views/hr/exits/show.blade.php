@extends('layouts.app')
@section('title', 'Exit '.$e->code)
@section('breadcrumb')<a href="{{ route('hr.exits.index') }}">Exits</a> / {{ $e->code }} @endsection
@section('content')
@php $open = $x->status === 'INITIATED'; $tasks = $e->tasks->where('kind', 'EXIT'); @endphp
<div class="page-heading"><div><h1>{{ $e->full_name }} is leaving</h1><p>{{ $e->code }} · {{ config('hr.exit_types')[$x->exit_type] }} · last day {{ $x->last_working_day->displayDate() }} · @include('hr._chip', ['s' => $x->status])</p></div>
<div class="actions"><a class="btn" href="{{ route('hr.employees.show', $e) }}">Employee record</a></div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif

<div class="tab-marker" data-tab-start="Clearance"></div>
<div class="panel"><div class="panel-header"><h2>Clearance checklist</h2><span class="muted small">{{ $tasks->whereNotNull('done_at')->count() }} of {{ $tasks->count() }} done</span></div>
<div class="table-wrap"><table><thead><tr><th style="width:44px"></th><th>Task</th><th>Owner</th><th>Done</th></tr></thead><tbody>
@forelse ($tasks as $t)
<tr><td>@if ($open)<form method="POST" action="{{ route('hr.tasks.toggle', $t) }}">@csrf<button class="btn btn-small">{!! $t->done_at ? '&#10003;' : '&#9675;' !!}</button></form>@else{!! $t->done_at ? '&#10003;' : '' !!}@endif</td>
<td @if ($t->done_at) style="text-decoration:line-through;color:var(--muted)" @endif>{{ $t->title }}</td><td>{{ \App\Models\EmployeeTask::OWNERS[$t->owner] ?? $t->owner }}</td><td class="muted small">{{ $t->done_at ? $t->done_at->displayDate().' · '.$t->done_by_name : '' }}</td></tr>
@empty<tr><td colspan="4" class="muted">No checklist items.</td></tr>@endforelse</tbody></table></div></div>
@if ($open)
<div class="panel"><div class="panel-body">
@if ($blockers)<p class="muted">Cannot complete yet: {{ implode('; ', $blockers) }}.</p>@endif
<form method="POST" action="{{ route('hr.exits.complete', $x) }}" style="display:inline" onsubmit="return confirm('Complete the exit? The person is marked as left and any linked login is switched off.')">@csrf<button class="btn btn-primary" @if ($blockers) disabled @endif>Complete exit</button></form>
<form method="POST" action="{{ route('hr.exits.cancel', $x) }}" style="display:inline" onsubmit="return confirm('Cancel this exit and keep the person?')">@csrf<button class="btn">Cancel exit</button></form>
</div></div>@endif

@if (\App\Support\HrAccess::isPayroll())
@php $st = $x->settlement; @endphp
<div class="tab-marker" data-tab-start="Final settlement"></div>
<div class="panel"><div class="panel-header"><h2>Final settlement</h2></div><div class="panel-body">
@if ($st)<p>Status: <strong>{{ $st->status }}</strong> · net {{ \App\Support\Formats::number($st->net) }} PKR. <a class="btn btn-small" href="{{ route('hr.payroll.settlements.show', $st) }}">Open settlement</a></p>
@else<p class="muted">Salary not yet paid, leave, gratuity and any loan balance are worked out here. It can be started before or after the exit is completed.</p><form method="POST" action="{{ route('hr.payroll.settlements.open', $x) }}">@csrf<button class="btn btn-primary">Calculate settlement</button></form>@endif
</div></div>
@endif

<div class="tab-marker" data-tab-start="Details"></div>
<div class="panel"><div class="panel-body">
<form method="POST" action="{{ route('hr.exits.update', $x) }}">@csrf @method('PUT')<fieldset @unless ($open) disabled @endunless style="border:0;padding:0;margin:0"><div class="form-grid">
    @include('hr._in', ['name' => 'notice_given_on', 'label' => 'Notice given on', 'type' => 'date', 'val' => $x->notice_given_on?->toDateString()])
    @include('hr._in', ['name' => 'last_working_day', 'label' => 'Last working day', 'type' => 'date', 'val' => $x->last_working_day->toDateString(), 'required' => true])
    @include('hr._sel', ['name' => 'rehire_eligible', 'label' => 'Eligible for rehire', 'items' => ['1' => 'Yes', '0' => 'No'], 'sel' => $x->rehire_eligible === null ? '' : (int) $x->rehire_eligible, 'blank' => 'Not decided'])
    <div class="field field-full"><label for="f_reason">Reason</label><input id="f_reason" name="reason" value="{{ old('reason', $x->reason) }}"></div>
    <div class="field field-full"><label for="f_ei">Exit interview notes</label><textarea id="f_ei" name="exit_interview" rows="5">{{ old('exit_interview', $x->exit_interview) }}</textarea></div>
</div>@if ($open)<p><button class="btn btn-primary">Save</button></p>@endif</fieldset></form>
<p class="muted small">Final settlement: {{ $x->settlement_status === 'PENDING' ? 'not prepared yet (comes with payroll)' : strtolower($x->settlement_status) }}.</p>
</div></div>
@endsection
