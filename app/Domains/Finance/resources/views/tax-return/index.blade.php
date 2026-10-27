@extends('layouts.app')
@section('title', 'Tax return')
@push('styles')<style>@media print{.sidebar,.page-heading .actions,.no-print{display:none!important}}</style>@endpush
@section('content')
@php
    $m = fn (float $v) => $v < 0 ? '('.\App\Support\Formats::number(-$v, 2).')' : \App\Support\Formats::number($v, 2);
    $d = fn (string $date) => \Illuminate\Support\Carbon::parse($date)->displayDate();
    $register = fn (string $side, ?string $authority) => route('finance.tax-register', ['side' => $side, 'from' => $from, 'to' => $to, 'authority' => $authority ?? '-']);
@endphp
<div class="page-heading"><div><h1>Tax return</h1><p>{{ $d($from) }} to {{ $d($to) }}. Worked out from the tax registers: issued invoices and posted bills by their own date, voids left out. Check it against the authority's own form before filing.</p></div>
<div class="actions">@if (Route::has('ledger.journals.create'))<a class="btn" href="{{ route('ledger.journals.create') }}" title="Debit the tax account, credit the bank">Record a payment to the authority</a>@endif<button class="btn btn-primary" onclick="window.print()">Print</button></div></div>
<div class="panel no-print"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="month">Month</label><input type="month" id="month" name="month" value="{{ $month }}" onchange="this.form.submit()"></div>
    <button class="btn">Show</button>
</form></div></div>
@forelse ($authorities as $a)
<div class="panel"><div class="panel-header"><h2>{{ $a['authority'] ?? 'Tax with no authority named' }}</h2></div><div class="table-wrap"><table><tbody>
    <tr><td>Tax charged on our invoices (output)</td><td class="num"><a href="{{ $register('sales', $a['authority']) }}">{{ $m($a['output']) }}</a></td></tr>
    <tr><td>Less tax suppliers charged us (input)</td><td class="num"><a href="{{ $register('purchases', $a['authority']) }}">{{ $m(-$a['input']) }}</a></td></tr>
    @if ($a['withheld_by_customers'] != 0)<tr><td>Less the share customers kept back and paid themselves</td><td class="num">{{ $m(-$a['withheld_by_customers']) }}</td></tr>@endif
    @if ($a['withheld_by_us'] != 0)<tr><td>Plus the share we kept back from suppliers</td><td class="num">{{ $m($a['withheld_by_us']) }}</td></tr>@endif
    <tr><th>{{ $a['payable'] >= 0 ? 'Payable' : 'Carried forward or to claim' }}</th><th class="num">{{ $m(abs($a['payable'])) }}</th></tr>
    @if ($a['income_tax_withheld_by_us'] != 0 || $a['income_tax_withheld_by_customers'] != 0)
    <tr><td colspan="2" class="muted small" style="padding-top:14px">Income tax withheld (filed on its own statement)</td></tr>
    @if ($a['income_tax_withheld_by_us'] != 0)<tr><td>We withheld from suppliers, owed to the authority</td><td class="num">{{ $m($a['income_tax_withheld_by_us']) }}</td></tr>@endif
    @if ($a['income_tax_withheld_by_customers'] != 0)<tr><td>Customers withheld from us, ours to claim against our income tax</td><td class="num">{{ $m($a['income_tax_withheld_by_customers']) }}</td></tr>@endif
    @endif
</tbody></table></div></div>
@empty
<div class="panel"><div class="panel-body muted">No tax in this period.</div></div>
@endforelse
@endsection
