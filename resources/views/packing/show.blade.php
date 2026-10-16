@extends('layouts.app')
@section('title', $packing->packing_number)
@section('content')
<div class="page-heading"><div><h1>{{ $packing->packing_number }}</h1><p>{{ $packing->status }} · {{ $packing->packed_at?->format('d M Y H:i') }} · {{ $packing->total_net_weight_kg }} kg net</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('dispatches.create') }}">Dispatch</a></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Lot</th><th>Product</th><th>Packed</th><th>Dispatched</th><th>Net kg</th></tr></thead><tbody>
@foreach($packing->items as $i)<tr><td><a href="{{ route('lots.show', $i->lot) }}">{{ $i->lot->lot_number }}</a></td><td>{{ $i->lot->product?->code }}</td><td>{{ $i->quantity_packed }}</td><td>{{ $ship->dispatchedQty($i) }}</td><td>{{ $i->net_weight_kg }}</td></tr>@endforeach</tbody></table></div></div>
@include('attachments._panel', ['type' => 'packing', 'id' => $packing->id])
@include('audit._history', ['type' => 'PackingRecord', 'id' => $packing->id])
@endsection
