@extends('layouts.app')
@section('title', 'Profit and loss')
@push('styles')<style>@media print{.sidebar,.page-heading .actions,.no-print{display:none!important}}</style>@endpush
@section('content')
@php
    $m = fn (?float $v) => $v === null ? '' : ($v < 0 ? '('.\App\Support\Formats::number(-$v, 2).')' : \App\Support\Formats::number($v, 2));
    $d = fn (string $date) => \Illuminate\Support\Carbon::parse($date)->displayDate();
    $cols = $compare ? 2 : 1;
    $link = ['from' => $from, 'to' => $to];
    $total = fn (string $label, string $key) => '<tr><th style="padding-left:12px;text-align:left">'.e($label).'</th><th class="num">'.$m($totals[$key][0]).'</th>'.($compare ? '<th class="num">'.$m($totals[$key][1]).'</th>' : '').'</tr>';
@endphp
<div class="page-heading"><div><h1>Profit and loss</h1><p>{{ $d($from) }} to {{ $d($to) }}, from the posted journals. Figures in brackets are losses or credits against the line.</p></div>
<div class="actions"><a class="btn" href="{{ route('ledger.balance-sheet', ['as_at' => $to]) }}">Balance sheet</a><button class="btn btn-primary" onclick="window.print()">Print</button></div></div>
<div class="panel no-print"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="{{ $from }}"></div>
    <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="{{ $to }}"></div>
    <div class="field"><label for="compare">Compare with</label><select id="compare" name="compare"><option value="">Nothing</option><option value="previous" @selected($compareWith === 'previous')>The period before</option><option value="last_year" @selected($compareWith === 'last_year')>The same period a year earlier</option></select></div>
    <div class="field"><label><input type="checkbox" name="all" value="1" @checked(request()->boolean('all'))> Accounts with nothing posted too</label></div>
    <button class="btn">Show</button>
</form></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th></th><th class="num">{{ $d($from) }} – {{ $d($to) }}</th>@if ($compare)<th class="num">{{ $d($compare[0]) }} – {{ $d($compare[1]) }}</th>@endif</tr></thead><tbody>
@foreach ($income as $section)@include('ledger::reports._section')@endforeach
{!! $total('Total income', 'income') !!}
@if ($costOfSales)
@include('ledger::reports._section', ['section' => $costOfSales])
{!! $total('Gross profit', 'gross') !!}
@endif
@foreach ($expenses as $section)@include('ledger::reports._section')@endforeach
{!! $total('Total expenses', 'expenses') !!}
</tbody><tfoot>{!! $total($totals['net'][0] < 0 ? 'Net loss' : 'Net profit', 'net') !!}</tfoot></table></div></div>
@endsection
