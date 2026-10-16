@extends('layouts.app')
@section('title', $a->code)
@section('breadcrumb', 'Advances and loans')
@section('content')
@php $m = fn ($v) => number_format((float) $v); @endphp
<div class="page-heading"><div><h1>{{ $a->code }} — {{ ucfirst(strtolower($a->kind)) }}</h1><p><a href="{{ route('hr.employees.show', $a->employee) }}">{{ $a->employee->code }} {{ $a->employee->full_name }}</a> · {{ $a->status }}@if ($a->paused) · recovery paused @endif</p></div><div class="actions"><a class="btn" href="{{ route('hr.payroll.advances') }}">All requests</a></div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
<div class="metrics"><div class="metric"><div class="metric-label">Amount</div><div class="metric-value">{{ $m($a->amount) }}</div><div class="metric-note">{{ $a->installments }} × {{ $m($a->installment_amount) }} from {{ $a->first_recovery_month->format('M Y') }}</div></div>
<div class="metric"><div class="metric-label">Still owed</div><div class="metric-value">{{ $a->status === 'PENDING' ? '—' : $m($balance) }}</div><div class="metric-note">{{ $a->disbursed_on ? 'Paid out '.$a->disbursed_on->format('d M Y') : 'Not paid out yet' }}</div></div></div>
@if ($a->reason)<p class="muted">Reason: {{ $a->reason }}</p>@endif
@if ($a->status === 'PENDING')<div class="panel"><div class="panel-body" style="display:flex;gap:8px;flex-wrap:wrap"><form method="POST" action="{{ route('hr.payroll.advances.decide', $a) }}">@csrf<button class="btn btn-primary" name="decision" value="APPROVE">Approve and pay out</button></form>
<form method="POST" action="{{ route('hr.payroll.advances.decide', $a) }}" style="display:flex;gap:6px">@csrf<input name="why" placeholder="Reason (optional)"><button class="btn" name="decision" value="REJECT">Reject</button><button class="btn" name="decision" value="CANCEL">Cancel request</button></form></div></div>@endif
@if ($a->status === 'ACTIVE')<div class="panel"><div class="panel-body" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end"><form method="POST" action="{{ route('hr.payroll.advances.pause', $a) }}">@csrf<button class="btn">{{ $a->paused ? 'Resume recovery' : 'Pause recovery' }}</button></form>
<form method="POST" action="{{ route('hr.payroll.advances.adjust', $a) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end">@csrf<div class="field" style="margin:0"><label>Amount</label><input type="number" name="amount" step="0.01" required style="width:120px"></div><div class="field" style="margin:0"><label>Reason</label><input name="note" required style="width:200px"></div>
<button class="btn" name="kind" value="WAIVER" onclick="return confirm('Write this amount off?')">Write off</button><button class="btn" name="kind" value="ADJUST">Add to balance</button></form></div></div>@endif
<div class="panel"><div class="panel-header"><h2>Ledger</h2></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Entry</th><th style="text-align:right">Amount</th><th>Note</th></tr></thead><tbody>
@forelse ($a->ledger as $l)<tr><td>{{ $l->entry_date->format('d M Y') }}</td><td>{{ ucfirst(strtolower($l->kind)) }}</td><td style="text-align:right">{{ $l->amount < 0 ? '−' : '+' }}{{ $m(abs($l->amount)) }}</td><td>{{ $l->note }} <span class="muted small">{{ $l->created_by_name }}</span></td></tr>@empty<tr><td colspan="4" class="muted">Nothing yet.</td></tr>@endforelse</tbody></table></div></div>
@endsection
