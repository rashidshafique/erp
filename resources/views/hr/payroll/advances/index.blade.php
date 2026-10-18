@extends('layouts.app')
@section('title', 'Advances and loans')
@section('breadcrumb', 'Advances and loans')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number((float) $v); $cls = ['PENDING' => 'status-on-hold', 'ACTIVE' => 'status-in-progress', 'CLEARED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'CANCELLED' => 'status-cancelled']; @endphp
<div class="page-heading"><div><h1>Advances &amp; loans</h1><p>Paid out once approved, then taken off salary a little each month.</p></div><div class="actions"><a class="btn" href="{{ route('hr.payroll.index') }}">Payroll</a></div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
<div class="metrics"><div class="metric"><div class="metric-label">Still owed by staff</div><div class="metric-value">{{ $m($owed) }}</div><div class="metric-note">PKR, on the lists below</div></div></div>
<div class="tab-marker" data-tab-start="Advances &amp; loans"></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters"><div class="field"><label for="q">Find</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Name or code"></div>
<div class="field"><label for="st">Show</label><select id="st" name="status">@foreach (['OPEN' => 'Waiting and running', 'PENDING' => 'Waiting for approval', 'ACTIVE' => 'Being recovered', 'CLEARED' => 'Cleared', 'REJECTED' => 'Rejected', 'ALL' => 'All'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select></div><button class="btn btn-primary">Filter</button></form></div></div>
<div class="panel">@if ($rows->isEmpty())<div class="empty-state">Nothing here.</div>@else<div class="table-wrap"><table><thead><tr><th>Request</th><th>Employee</th><th>Amount</th><th>Instalment</th><th>Owed</th><th>Status</th></tr></thead><tbody>
@foreach ($rows as $a)<tr><td><a href="{{ route('hr.payroll.advances.show', $a) }}"><strong>{{ $a->code }}</strong></a><div class="muted small">{{ ucfirst(strtolower($a->kind)) }}</div></td><td>{{ $a->employee->code }} {{ $a->employee->full_name }}</td><td>{{ $m($a->amount) }}</td><td>{{ $m($a->installment_amount) }} × {{ $a->installments }}<div class="muted small">from {{ $a->first_recovery_month->format('M Y') }}</div></td>
<td>{{ $a->status === 'ACTIVE' ? $m($a->balance()) : '—' }}</td><td><span class="status {{ $cls[$a->status] }}">{{ ucfirst(strtolower($a->status)) }}</span>@if ($a->paused)<span class="status status-on-hold">Paused</span>@endif</td></tr>@endforeach</tbody></table></div>@endif</div>
<div class="tab-marker" data-tab-start="New request"></div>
<div class="panel"><div class="panel-body"><form method="POST" action="{{ route('hr.payroll.advances.store') }}">@csrf<div class="form-grid">
@include('hr._sel', ['blank' => false, 'name' => 'employee_id', 'label' => 'Employee', 'items' => $people->mapWithKeys(fn ($p) => [$p->id => $p->code.' · '.$p->full_name])->all(), 'sel' => old('employee_id'), 'blank' => 'Choose…', 'required' => true, 'keep' => true])
@include('hr._sel', ['blank' => false, 'name' => 'kind', 'label' => 'Kind', 'items' => ['ADVANCE' => 'Salary advance', 'LOAN' => 'Loan'], 'sel' => old('kind', 'ADVANCE'), 'required' => true, 'keep' => true])
@include('hr._in', ['name' => 'amount', 'label' => 'Amount (PKR)', 'type' => 'number', 'val' => old('amount'), 'required' => true, 'keep' => true]) @include('hr._in', ['name' => 'installments', 'label' => 'Paid back over (months)', 'type' => 'number', 'val' => old('installments', 1), 'required' => true, 'keep' => true])
@include('hr._in', ['name' => 'first_recovery_month', 'label' => 'First recovery in', 'type' => 'month', 'val' => old('first_recovery_month', now()->format('Y-m')), 'required' => true, 'keep' => true])
@include('hr._in', ['name' => 'reason', 'label' => 'Reason', 'val' => old('reason')])</div><p><button class="btn btn-primary">Record request</button></p></form></div></div>
@endsection
