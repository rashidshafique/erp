@extends('layouts.app')
@section('title', 'Final settlements')
@section('breadcrumb', 'Final settlements')
@section('content')
@php $m = fn ($v) => number_format((float) $v); $cls = ['DRAFT' => 'status-on-hold', 'APPROVED' => 'status-released', 'PAID' => 'status-completed']; @endphp
<div class="page-heading"><div><h1>Final settlements</h1><p>What a person is owed, and owes, when they leave.</p></div><div class="actions"><a class="btn" href="{{ route('hr.payroll.index') }}">Payroll</a><a class="btn" href="{{ route('hr.exits.index') }}">Exits &amp; clearance</a></div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
<div class="tab-marker" data-tab-start="Settlements"></div>
<div class="panel">@if ($rows->isEmpty())<div class="empty-state">No settlement has been started.</div>@else<div class="table-wrap"><table><thead><tr><th>Employee</th><th>Last day</th><th>Due</th><th>Recovered</th><th>Net</th><th>Status</th></tr></thead><tbody>
@foreach ($rows as $s)<tr><td><a href="{{ route('hr.payroll.settlements.show', $s) }}"><strong>{{ $s->employee->code }}</strong> {{ $s->employee->full_name }}</a><div class="muted small">{{ $s->employee->department?->name }}</div></td><td>{{ $s->last_working_day->format('d M Y') }}</td><td>{{ $m($s->total_due) }}</td><td>{{ $m($s->total_recovery) }}</td><td @if ($s->net < 0) style="color:var(--danger)" @endif><strong>{{ $m($s->net) }}</strong></td><td><span class="status {{ $cls[$s->status] }}">{{ ucfirst(strtolower($s->status)) }}</span></td></tr>@endforeach</tbody></table></div>@endif</div>
<div class="tab-marker" data-tab-start="Waiting to start{{ $waiting->count() ? ' ('.$waiting->count().')' : '' }}"></div>
<div class="panel"><div class="panel-header"><h2>Exits without a settlement</h2></div>@if ($waiting->isEmpty())<div class="empty-state">Every exit has a settlement.</div>@else<div class="table-wrap"><table><thead><tr><th>Employee</th><th>Last day</th><th>Exit</th><th></th></tr></thead><tbody>
@foreach ($waiting as $x)<tr><td>{{ $x->employee->code }} {{ $x->employee->full_name }}</td><td>{{ $x->last_working_day->format('d M Y') }}</td><td>{{ config('hr.exit_types')[$x->exit_type] ?? $x->exit_type }} · {{ $x->status === 'COMPLETED' ? 'completed' : 'in progress' }}</td>
<td><form method="POST" action="{{ route('hr.payroll.settlements.open', $x) }}">@csrf<button class="btn btn-small btn-primary">Calculate settlement</button></form></td></tr>@endforeach</tbody></table></div>@endif</div>
@endsection
