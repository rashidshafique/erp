@extends('layouts.app')

@section('title', 'Sales orders')
@section('breadcrumb', 'Sales orders')

@section('content')
<div class="page-heading">
    <div><h1>Sales orders</h1><p>From customer order through contract review to production and dispatch.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('sales-orders.create') }}">+ New sales order</a></div>
</div>


@php $statusTabs = ['OPEN' => 'Open'] + \App\Domains\Sales\Models\SalesOrder::STATUSES + ['' => 'All']; @endphp
<div class="list-toolbar">
    <x-status-tabs :options="$statusTabs" :counts="$counts" :current="$status ?? ''" />
    <form method="GET" role="search">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <input type="search" name="search" value="{{ $search }}" placeholder="Search by order number, customer or reference" aria-label="Search by order number, customer or reference">
        <button class="btn btn-primary" type="submit">Search</button>
    </form>
</div>

<div class="panel">
    @if ($orders->isEmpty())
        <div class="empty-state">{{ $search !== '' || $status ? 'No sales order matches.' : 'No sales orders yet.' }}</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Order</th><th>Customer</th><th>Customer ref</th><th>Order date</th><th>Delivery</th><th>Lines</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @foreach ($orders as $o)
                <tr>
                    <td><a href="{{ route('sales-orders.show', $o) }}"><strong>{{ $o->order_number }}</strong></a></td>
                    <td>{{ $o->customer?->name }}</td>
                    <td>{{ $o->customer_reference ?: '—' }}</td>
                    <td>{{ $o->order_date?->displayDate() }}</td>
                    <td>{{ $o->requested_delivery_date?->displayDate() ?? '—' }}</td>
                    <td>{{ $o->lines_count }}</td>
                    <td><span class="status {{ $o->status === 'DISPATCHED' ? 'status-completed' : ($o->status === 'CANCELLED' ? 'status-cancelled' : ($o->status === 'ON_HOLD' ? 'status-on-hold' : 'status-in-progress')) }}">{{ \App\Domains\Sales\Models\SalesOrder::STATUSES[$o->status] ?? str_replace('_', ' ', $o->status) }}</span></td>
                    <td><a class="btn btn-small" href="{{ route('sales-orders.show', $o) }}">Open</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="panel-body">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
