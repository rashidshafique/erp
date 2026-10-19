@extends('layouts.app')
@section('title', $dispatch->dispatch_number)
@section('content')
<div class="page-heading"><div><h1>{{ $dispatch->dispatch_number }}</h1><p>{{ $dispatch->customer?->name }} · {{ $dispatch->dispatched_at?->displayDateTime() }} · challan {{ $dispatch->delivery_challan_number ?? '—' }} · {{ $dispatch->vehicle_number }}</p></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Lot</th><th>Product</th><th>Sales order</th><th>Qty</th><th>Net kg</th><th></th></tr></thead><tbody>
@foreach($dispatch->items as $i)<tr><td>{{ $i->packingItem->lot->lot_number }}</td><td>{{ $i->packingItem->lot->product?->code }}</td><td>{{ $i->salesOrderLine?->salesOrder?->order_number ?? '—' }}</td><td>{{ $i->quantity_dispatched }}</td><td>{{ $i->net_weight_kg }}</td><td><a href="{{ route('lots.certificate', $i->packingItem->lot) }}">Certificate</a></td></tr>@endforeach</tbody></table></div></div>
@include('attachments._panel', ['type' => 'dispatch', 'id' => $dispatch->id])
@include('audit._history', ['type' => 'Dispatch', 'id' => $dispatch->id])
@endsection
