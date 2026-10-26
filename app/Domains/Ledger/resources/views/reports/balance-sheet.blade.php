@extends('layouts.app')
@section('title', 'Balance sheet')
@push('styles')<style>@media print{.sidebar,.page-heading .actions,.no-print{display:none!important}}</style>@endpush
@section('content')
@php
    $m = fn (?float $v) => $v === null ? '' : ($v < 0 ? '('.\App\Support\Formats::number(-$v, 2).')' : \App\Support\Formats::number($v, 2));
    $d = fn (string $date) => \Illuminate\Support\Carbon::parse($date)->displayDate();
    $cols = $compareAt ? 2 : 1;
    $link = ['to' => $asAt];
    $total = fn (string $label, string $key) => '<tr><th style="padding-left:12px;text-align:left">'.e($label).'</th><th class="num">'.$m($totals[$key][0]).'</th>'.($compareAt ? '<th class="num">'.$m($totals[$key][1]).'</th>' : '').'</tr>';
    $heading = fn (string $label) => '<tr><th colspan="'.($cols + 1).'" style="text-align:left">'.e($label).'</th></tr>';
@endphp
<div class="page-heading"><div><h1>Balance sheet</h1><p>As at {{ $d($asAt) }}, from the posted journals. Profit not yet closed to retained earnings is shown under equity.</p></div>
<div class="actions"><a class="btn" href="{{ route('ledger.profit-and-loss', ['to' => $asAt]) }}">Profit and loss</a><button class="btn btn-primary" onclick="window.print()">Print</button></div></div>
<div class="panel no-print"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="as_at">As at</label><input type="date" id="as_at" name="as_at" value="{{ $asAt }}"></div>
    <div class="field"><label for="compare_at">Beside it, as at</label><input type="date" id="compare_at" name="compare_at" value="{{ $compareAt }}"></div>
    <div class="field"><label><input type="checkbox" name="all" value="1" @checked(request()->boolean('all'))> Accounts with no balance too</label></div>
    <button class="btn">Show</button>
</form></div></div>
@if ($totals['assets'][0] !== $totals['claims'][0])<div class="alert alert-error">Assets and the claims on them differ by {{ $m(round($totals['assets'][0] - $totals['claims'][0], 2)) }}.</div>@endif
<div class="panel"><div class="table-wrap"><table><thead><tr><th></th><th class="num">{{ $d($asAt) }}</th>@if ($compareAt)<th class="num">{{ $d($compareAt) }}</th>@endif</tr></thead><tbody>
{!! $heading('Assets') !!}
@foreach ($assets as $section)@include('ledger::reports._section')@endforeach
{!! $total('Total assets', 'assets') !!}
{!! $heading('Liabilities') !!}
@foreach ($liabilities as $section)@include('ledger::reports._section')@endforeach
{!! $total('Total liabilities', 'liabilities') !!}
{!! $heading('Equity') !!}
@foreach ($equity as $section)@include('ledger::reports._section')@endforeach
<tr><td style="padding-left:12px">Profit and loss not yet closed</td><td class="num">{{ $m($totals['profit'][0]) }}</td>@if ($compareAt)<td class="num muted">{{ $m($totals['profit'][1]) }}</td>@endif</tr>
{!! $total('Total equity', 'equity') !!}
</tbody><tfoot>{!! $total('Total liabilities and equity', 'claims') !!}</tfoot></table></div></div>
@endsection
