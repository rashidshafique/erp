@extends('layouts.app')
@section('title', 'Payslip '.$s->employee_code)
@section('breadcrumb', 'Payslip')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number((float) $v, 2); $e = $s->employee; $earn = $s->lines->where('kind', 'EARNING'); $ded = $s->lines->where('kind', 'DEDUCTION'); $er = $s->lines->where('kind', 'EMPLOYER'); @endphp
<style>@media print { .sidebar, .topbar, .page-heading .actions, .no-print { display:none !important } .main, .content { margin:0 !important; padding:0 !important } body { background:#fff } }</style>
<div class="page-heading"><div><h1>Payslip — {{ $s->period->month->format('F Y') }}</h1><p>{{ $s->employee_code }} · {{ $s->employee_name }}</p></div><div class="actions"><a class="btn" href="{{ $back }}">Back</a><button class="btn btn-primary" onclick="window.print()">Print</button></div></div>
@if (! in_array($s->period->status, ['APPROVED', 'PAID'], true))<div class="alert" style="background:#fff8e1;border:1px solid #e6c36a;color:#7a5b00">Draft: this payroll is not approved yet, so figures can still change.</div>@endif
<div class="panel"><div class="panel-body">
<div class="detail-grid" style="margin-bottom:14px">
<div><div class="muted small">Employee</div><div class="detail-value">{{ $s->employee_name }}</div><div class="muted small">{{ $s->designation_name }} · {{ $s->department_name }}</div></div>
<div><div class="muted small">Days</div><div class="detail-value">{{ rtrim(rtrim(\App\Support\Formats::number($s->payable_days, 2), '0'), '.') }} paid of {{ rtrim(rtrim(\App\Support\Formats::number($s->period_days, 2), '0'), '.') }}</div><div class="muted small">{{ $s->absent_days + $s->unpaid_leave_days > 0 ? rtrim(rtrim(\App\Support\Formats::number($s->absent_days + $s->unpaid_leave_days, 2), '0'), '.').' day(s) unpaid' : 'No unpaid days' }}@if ($s->overtime_minutes) · overtime {{ intdiv($s->overtime_minutes, 60) }} h {{ $s->overtime_minutes % 60 }} m @endif</div></div>
<div><div class="muted small">Paid by</div><div class="detail-value">{{ config('payroll.pay_modes')[$s->pay_mode] ?? $s->pay_mode }}</div><div class="muted small">{{ $s->bank_name }} {{ $s->bank_account_no ? '· '.$s->bank_account_no : '' }}</div></div>
<div><div class="muted small">Status</div><div class="detail-value">{{ ucfirst(strtolower($s->status)) }}</div><div class="muted small">{{ $s->paid_on ? 'Paid '.$s->paid_on->displayDate() : '' }}</div></div></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px">
<div><h3 style="margin:0 0 6px">Earnings</h3><table><tbody>@foreach ($earn as $l)<tr><td>{{ $l->name }}</td><td style="text-align:right">{{ $m($l->amount) }}</td></tr>@endforeach<tr><td><strong>Total earnings</strong></td><td style="text-align:right"><strong>{{ $m($s->total_earnings) }}</strong></td></tr></tbody></table></div>
<div><h3 style="margin:0 0 6px">Deductions</h3><table><tbody>@forelse ($ded as $l)<tr><td>{{ $l->name }}</td><td style="text-align:right">{{ $m($l->amount) }}</td></tr>@empty<tr><td class="muted">None</td><td></td></tr>@endforelse<tr><td><strong>Total deductions</strong></td><td style="text-align:right"><strong>{{ $m($s->total_deductions) }}</strong></td></tr></tbody></table></div></div>
<p style="font-size:20px;margin:18px 0 4px"><strong>Net pay: PKR {{ $m($s->net) }}</strong></p>
@if (auth()->guest() || \App\Support\HrAccess::isPayroll())@if ($er->isNotEmpty())<p class="muted small">Employer contributions (not deducted from pay): {{ $er->map(fn ($l) => $l->name.' '.$m($l->amount))->implode(' · ') }}.</p>@endif @endif
</div></div>
@endsection
