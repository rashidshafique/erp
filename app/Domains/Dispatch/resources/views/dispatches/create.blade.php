@extends('layouts.app')
@section('title', 'New dispatch')
@section('content')
<div class="page-heading"><div><h1>New dispatch</h1><p>Lots from another customer's order, with an open NCR, or not cleared are refused.</p></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
<form method="POST" action="{{ route('dispatches.store') }}">@csrf
<div class="panel"><div class="panel-body form-grid"><label>Customer<select name="customer_id" required><option value="">—</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id') === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
<label>Challan no.<input name="delivery_challan_number"></label><label>Carrier<input name="carrier_name"></label><label>Vehicle<input name="vehicle_number"></label><label>Driver<input name="driver_name"></label><label>Dispatched by<input name="dispatched_by"></label><label>Delivery address<input name="delivery_address"></label></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Packing</th><th>Lot</th><th>Product</th><th>Order customer</th><th>Left</th><th>Dispatch now</th></tr></thead><tbody>
@forelse($items as $i)<tr><td>{{ $i->packing?->packing_number }}</td><td>{{ $i->lot->lot_number }}</td><td>{{ $i->lot->product?->code }}</td><td>{{ $i->lot->productionOrder?->salesOrderLine?->salesOrder?->customer?->name ?? '—' }}</td><td>{{ $i->left }}</td><td><input type="number" name="qty[{{ $i->id }}]" min="0" max="{{ $i->left }}" value="0"></td></tr>
@empty<tr><td colspan="6" class="muted">Nothing packed is waiting for dispatch.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body"><button class="btn btn-primary">Record dispatch</button></div></div></form>
@endsection
