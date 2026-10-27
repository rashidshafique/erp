@extends('layouts.app')
@section('title', 'Receivables')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Receivables</h1><p>What customers owe, by how late it is.</p></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Customer</th>@foreach ($buckets as $label)<th class="num">{{ $label }}</th>@endforeach<th class="num">Total</th><th class="num" title="Tax the customer kept back and still owes a certificate for; part of the total">Awaiting tax certificate</th><th class="num">On account</th></tr></thead><tbody>
@forelse ($rows as $r)<tr><td><a href="{{ route('receivables.statement', $r['customer']) }}">{{ $r['customer']->name }}</a></td>@foreach ($buckets as $k => $label)<td class="num" @if ($r['buckets'][$k] > 0 && $k !== 'current') style="color:var(--danger)" @endif>{{ $r['buckets'][$k] > 0 ? $m($r['buckets'][$k]) : '' }}</td>@endforeach<td class="num"><strong>{{ $m($r['total']) }}</strong></td><td class="num">@if ($r['withheld'] > 0)<a href="{{ route('withholding-certificates.create', ['customer' => $r['customer']->id]) }}">{{ $m($r['withheld']) }}</a>@endif</td><td class="num">{{ $r['on_account'] > 0 ? $m($r['on_account']) : '' }}</td></tr>
@empty<tr><td colspan="{{ count($buckets) + 4 }}" class="muted">Nobody owes anything.</td></tr>@endforelse</tbody>
@if ($rows)<tfoot><tr><th>All customers</th>@foreach ($buckets as $k => $label)<th class="num">{{ $m($totals[$k]) }}</th>@endforeach<th class="num">{{ $m($grand) }}</th><th class="num">{{ $m(array_sum(array_column($rows, 'withheld'))) }}</th><td></td></tr></tfoot>@endif</table></div></div>
@endsection
