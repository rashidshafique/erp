@extends('layouts.app')
@section('title', 'Production queue')
@section('content')
<div class="page-heading"><div><h1>Production queue</h1><p>Where each open lot stands after the pour. Oldest first within each step.</p></div><div class="actions"><a class="btn" href="{{ route('finished-goods.index') }}">Finished goods ({{ $awaitingDispatch }} packed lines)</a></div></div>
@forelse($buckets as $b)
<div class="panel"><div class="panel-header"><h2>{{ $b['label'] }}</h2><span class="muted">{{ count($b['lots']) }} lot(s)</span></div>
<div class="table-wrap"><table><thead><tr><th>Lot</th><th>Product</th><th>Order</th><th>Pieces</th><th>Poured</th><th>Age</th><th>Cooling</th><th>Status</th></tr></thead><tbody>
@foreach($b['lots'] as $r)@php $l = $r['lot']; @endphp
<tr><td><a href="{{ route('lots.show', $l) }}">{{ $l->lot_number }}</a></td><td>{{ $l->product?->code }}</td><td>{{ $l->productionOrder?->production_order_number }}@if($r['urgent'])<div class="muted small">required {{ $r['urgent']->format('d M') }}</div>@endif</td>
<td>{{ $r['pieces'] }}</td><td>{{ $r['poured']?->format('d M H:i') ?? '—' }}</td><td>{{ $r['age_h'] !== null ? $r['age_h'].' h' : '—' }}</td>
<td>@if($r['cool'])@if($r['cool']['wait'] > 0)<span class="status status-in-progress">wait {{ $r['cool']['wait'] }} min</span>@else <span class="muted small">met</span>@endif @endif</td>
<td>{{ $l->status }}</td></tr>@endforeach</tbody></table></div></div>
@empty<div class="panel"><div class="panel-body muted">No open lots.</div></div>@endforelse
@endsection
