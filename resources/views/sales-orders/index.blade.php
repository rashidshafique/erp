@extends('layouts.app')

@section('title', 'Sales orders')
@section('breadcrumb', 'Sales orders')

@section('content')
<div class="page-heading">
    <div><h1>Sales orders</h1><p>From customer order through contract review to production and dispatch.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('sales-orders.create') }}">+ New sales order</a></div>
</div>


<div class="panel"><div class="panel-body">
    <form method="GET" class="filters">
        <div class="field"><label for="search">Order number</label><input id="search" name="search" value="{{ request('search') }}"></div>
        <div class="field"><label for="status">Status</label>
            <select id="status" name="status"><option value="">All</option>
                @foreach (['DRAFT','UNDER_REVIEW','CONFIRMED','IN_PRODUCTION','ON_HOLD','DISPATCHED','CANCELLED'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ str_replace('_', ' ', $s) }}</option>
                @endforeach
            </select></div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn" href="{{ route('sales-orders.index') }}">Reset</a>
    </form>
</div></div>

<div class="panel">
    @if ($orders->isEmpty())
        <div class="empty-state">No sales orders yet.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Order</th><th>Customer</th><th>Customer ref</th><th>Order date</th><th>Delivery</th><th>Lines</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($orders as $o)
                <tr>
                    <td><a href="{{ route('sales-orders.show', $o) }}"><strong>{{ $o->order_number }}</strong></a></td>
                    <td>{{ $o->customer?->name }}</td>
                    <td>{{ $o->customer_reference ?: '—' }}</td>
                    <td>{{ $o->order_date?->displayDate() }}</td>
                    <td>{{ $o->requested_delivery_date?->displayDate() ?? '—' }}</td>
                    <td>{{ $o->lines_count }}</td>
                    <td><span class="status">{{ str_replace('_', ' ', $o->status) }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="panel-body">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
