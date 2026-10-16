@extends('layouts.app')
@section('title', 'Finished goods')
@section('content')
<div class="page-heading"><div><h1>Finished goods</h1><p>Packed castings waiting for dispatch, for customer orders and for stock.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('dispatches.create') }}">New dispatch</a></div></div>
@forelse($byProduct as $code => $g)
<div class="panel"><div class="panel-header"><h2>{{ $code }} — {{ $g['name'] }}</h2><span class="muted">{{ $g['qty'] }} pcs @if($g['stock_qty'] > 0)· {{ $g['stock_qty'] }} for stock @endif</span></div>
<div class="table-wrap"><table><thead><tr><th>Lot</th><th>Packing</th><th>Location</th><th>Qty</th><th>For</th><th>Packed</th></tr></thead><tbody>
@foreach($g['rows'] as $i)<tr><td><a href="{{ route('lots.show', $i->lot) }}">{{ $i->lot->lot_number }}</a></td><td><a href="{{ route('packing.show', $i->packing) }}">{{ $i->packing->packing_number }}</a></td><td>{{ $i->packing->storage_location ?? '—' }}</td><td>{{ $i->left }}</td>
<td>{{ $i->lot->productionOrder?->salesOrderLine ? ($i->lot->productionOrder->salesOrderLine->salesOrder->customer?->name.' · '.$i->lot->productionOrder->salesOrderLine->salesOrder->order_number) : 'Stock' }}</td><td>{{ $i->packing->packed_at?->format('d M') }}</td></tr>@endforeach</tbody></table></div></div>
@empty<div class="panel"><div class="panel-body muted">Nothing is waiting for dispatch.</div></div>@endforelse
@endsection
