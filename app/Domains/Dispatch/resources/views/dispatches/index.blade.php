@extends('layouts.app')
@section('title', 'Dispatches')
@section('content')
<div class="page-heading"><div><h1>Dispatches</h1><p>Goods that have left the foundry.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('dispatches.create') }}">+ New dispatch</a></div></div>
<div class="panel"><div class="panel-body">
    <form method="GET" class="filters">
        <div class="field"><label for="customer">Customer</label><select id="customer" name="customer" onchange="this.form.submit()"><option value="">All customers</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected($customerId === $c->id)>{{ $c->name }} ({{ $c->code }})</option>@endforeach</select></div>
        <noscript><button class="btn btn-primary" type="submit">Filter</button></noscript>
        @if ($customerId)<a class="btn" href="{{ route('dispatches.index') }}">Reset</a>@endif
    </form>
</div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Dispatch</th><th>Customer</th><th>Challan</th><th>Lines</th><th>Net kg</th><th>Date</th></tr></thead><tbody>
@forelse($dispatches as $d)<tr><td><a href="{{ route('dispatches.show', $d) }}">{{ $d->dispatch_number }}</a></td><td>{{ $d->customer?->name }}</td><td>{{ $d->delivery_challan_number }}</td><td>{{ $d->items_count }}</td><td>{{ $d->total_net_weight_kg }}</td><td>{{ $d->dispatched_at?->displayShortDateTime() }}</td></tr>
@empty<tr><td colspan="6" class="muted">{{ $customerId ? 'No dispatch for this customer yet.' : 'No dispatch yet.' }}</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $dispatches->links() }}</div></div>
@endsection
