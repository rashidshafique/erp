@extends('layouts.app')
@section('title', 'Dispatches')
@section('content')
<div class="page-heading"><div><h1>Dispatches</h1><p>Goods that have left the foundry.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('dispatches.create') }}">+ New dispatch</a></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Dispatch</th><th>Customer</th><th>Challan</th><th>Lines</th><th>Net kg</th><th>Date</th></tr></thead><tbody>
@forelse($dispatches as $d)<tr><td><a href="{{ route('dispatches.show', $d) }}">{{ $d->dispatch_number }}</a></td><td>{{ $d->customer?->name }}</td><td>{{ $d->delivery_challan_number }}</td><td>{{ $d->items_count }}</td><td>{{ $d->total_net_weight_kg }}</td><td>{{ $d->dispatched_at?->displayShortDateTime() }}</td></tr>
@empty<tr><td colspan="6" class="muted">No dispatch yet.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $dispatches->links() }}</div></div>
@endsection
