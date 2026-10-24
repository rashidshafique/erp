@extends('layouts.app')
@section('title', 'Payables')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Payables</h1><p>What we owe suppliers, by how late it is.</p></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Supplier</th>@foreach ($buckets as $label)<th class="num">{{ $label }}</th>@endforeach<th class="num">Total</th><th class="num">Advances</th></tr></thead><tbody>
@forelse ($rows as $r)<tr><td><a href="{{ route('payables.statement', $r["supplier"]) }}">{{ $r["supplier"]->name }}</a></td>@foreach ($buckets as $k => $label)<td class="num" @if ($r['buckets'][$k] > 0 && $k !== 'current') style="color:var(--danger)" @endif>{{ $r['buckets'][$k] > 0 ? $m($r['buckets'][$k]) : '' }}</td>@endforeach<td class="num"><strong>{{ $m($r['total']) }}</strong></td><td class="num">{{ $r['advance'] > 0 ? $m($r['advance']) : '' }}</td></tr>
@empty<tr><td colspan="{{ count($buckets) + 3 }}" class="muted">We owe nothing.</td></tr>@endforelse</tbody>
@if ($rows)<tfoot><tr><th>All suppliers</th>@foreach ($buckets as $k => $label)<th class="num">{{ $m($totals[$k]) }}</th>@endforeach<th class="num">{{ $m($grand) }}</th><td></td></tr></tfoot>@endif</table></div></div>
@endsection
