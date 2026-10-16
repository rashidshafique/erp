@extends('layouts.app')
@section('title', 'My payslips')
@section('breadcrumb', 'My payslips')
@section('content')
<div class="page-heading"><div><h1>My payslips</h1><p>Your own pay, once each month's payroll is approved.</p></div></div>
@if (! $me)<div class="alert alert-error">Your login is not linked to an employee record, so there are no payslips to show. Ask HR to link it.</div>
@else<div class="panel">@if ($slips->isEmpty())<div class="empty-state">No approved payslip yet.</div>@else
<div class="table-wrap"><table><thead><tr><th>Month</th><th>Gross</th><th>Deductions</th><th>Net pay</th><th>Payment</th></tr></thead><tbody>
@foreach ($slips as $s)<tr><td><a href="{{ route('payslips.show', $s) }}"><strong>{{ $s->period->month->format('F Y') }}</strong></a></td><td>{{ number_format($s->gross) }}</td><td>{{ number_format($s->total_deductions) }}</td><td><strong>{{ number_format($s->net) }}</strong></td><td>{{ $s->status === 'PAID' ? 'Paid '.$s->paid_on?->format('d M') : ucfirst(strtolower($s->status)) }}</td></tr>@endforeach
</tbody></table></div>@endif</div>@endif
@endsection
