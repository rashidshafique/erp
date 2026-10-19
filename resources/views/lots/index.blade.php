@extends('layouts.app')
@section('title', 'Casting lots')
@section('content')
<div class="page-heading"><div><h1>Casting lots</h1><p>Every casting traced from pour to dispatch.</p></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
<div class="panel"><div class="panel-body">
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
<input name="search" value="{{ request('search') }}" placeholder="Lot number">
<select name="status"><option value="">All statuses</option>@foreach(['IN_PRODUCTION','READY','PACKED','DISPATCHED','ON_HOLD','SCRAPPED','CLOSED'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }} ({{ $counts[$s] ?? 0 }})</option>@endforeach</select>
<button class="btn">Filter</button></form></div>
<div class="table-wrap"><table><thead><tr><th>Lot</th><th>Product</th><th>Heat</th><th>Order</th><th>Poured</th><th>Good</th><th>Rejected</th><th>Status</th></tr></thead><tbody>
@forelse($lots as $l)
<tr><td><a href="{{ route('lots.show', $l) }}">{{ $l->lot_number }}</a></td><td>{{ $l->product?->code }}</td><td>{{ $l->pourMouldAssembly?->pour?->heat?->heat_number ?? '—' }}</td><td>{{ $l->productionOrder?->production_order_number ?? '—' }}</td><td>{{ $l->quantity_poured }}</td><td>{{ $l->quantity_good }}</td><td>{{ $l->quantity_rejected }}</td><td><span class="status">{{ $l->status }}</span></td></tr>
@empty<tr><td colspan="8" class="muted">No lots yet. Lots are created when a pour with assigned moulds is completed.</td></tr>@endforelse
</tbody></table></div>
<div class="panel-body">{{ $lots->links() }}</div></div>
@endsection
