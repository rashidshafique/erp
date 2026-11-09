{{-- The sales orders list, in the same layout as Enquiries, Customers and Products: a bar with status tabs, columns, export and
     new, a search and customer filter, a clickable table with status pills, and the open orders by status beside it. --}}
@extends('layouts.app')

@section('title', 'Sales orders')
@section('breadcrumb', 'Sales orders')

@section('content')
@php
    use App\Domains\Sales\Models\SalesOrder;
    $tabs = ['OPEN' => 'Open'] + SalesOrder::STATUSES + ['' => 'All'];
    $tones = ['DRAFT' => 'el-grey', 'UNDER_REVIEW' => 'el-warn', 'CONFIRMED' => 'el-blue', 'IN_PRODUCTION' => 'el-blue', 'ON_HOLD' => 'el-bad', 'DISPATCHED' => 'el-ok', 'CANCELLED' => 'el-grey'];
    $keep = fn (array $over) => request()->fullUrlWithQuery($over + ['page' => null]);
    $filtered = request()->hasAny(['search', 'customer']);
@endphp
@include('sales::orders._record-styles')
<div class="el-card">
    <div class="el-bar">
        <div style="display:flex;align-items:center;gap:16px;font-size:13px;flex-wrap:wrap">
            <span style="font-weight:600">Sales orders</span>
            <nav class="el-tabs">@foreach ($tabs as $k => $l)<a href="{{ $keep(['status' => $k ?: null]) }}" @class(['on' => ($status ?? '') === $k])>{{ $l }} {{ $counts[$k] ?? 0 }}</a>@endforeach</nav>
        </div>
        <div style="display:flex;gap:8px">
            <x-column-picker table="sales-orders" :columns="['customer' => 'Customer', 'ref' => 'Customer ref', 'date' => 'Order date', 'delivery' => 'Delivery', 'lines' => 'Lines', 'status' => 'Status']" />
            <a class="el-btn" href="{{ route('sales-orders.export', request()->query()) }}">Export</a>
            <a class="el-btn primary" href="{{ route('sales-orders.create') }}">New sales order</a>
        </div>
    </div>
    <div class="el-grid">
        <div class="el-main">
            <form method="GET" action="{{ route('sales-orders.index') }}" class="el-filters">
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <input type="search" name="search" value="{{ $search }}" placeholder="Search order number, customer or reference" aria-label="Search order number, customer or reference">
                <select data-search-select name="customer" aria-label="Customer" onchange="this.form.submit()"><option value="">Customer: all</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(request('customer') === $c->id)>{{ $c->name }}</option>@endforeach</select>
                <noscript><button class="el-btn" type="submit">Filter</button></noscript>
                @if ($filtered)<a class="el-note" href="{{ route('sales-orders.index', $status ? ['status' => $status] : []) }}">Clear</a>@endif
            </form>
            @if ($orders->isEmpty())
                <div class="empty-state"><strong>{{ $filtered || $status ? 'No sales order matches' : 'No sales orders yet' }}</strong><p>From customer order through contract review to production and dispatch.</p><a class="btn btn-primary" href="{{ route('sales-orders.create') }}">New sales order</a></div>
            @else
            <div class="el-table"><table data-table="sales-orders">
                <thead><tr><th>Order</th><th data-col="customer">Customer</th><th data-col="ref">Customer ref</th><th data-col="date">Order date</th><th data-col="delivery">Delivery</th><th class="el-r" data-col="lines">Lines</th><th data-col="status">Status</th></tr></thead>
                <tbody>
                @foreach ($orders as $o)
                    @php $late = in_array($o->status, SalesOrder::OPEN, true) && $o->requested_delivery_date && $o->requested_delivery_date->isBefore(today()); @endphp
                    <tr onclick="if (!event.target.closest('a')) location.href='{{ route('sales-orders.show', $o) }}'">
                        <td><a href="{{ route('sales-orders.show', $o) }}" style="color:inherit"><code>{{ $o->order_number }}</code></a></td>
                        <td data-col="customer">{{ $o->customer?->name }}</td>
                        <td data-col="ref">{{ $o->customer_reference ?: '—' }}</td>
                        <td data-col="date">{{ $o->order_date?->displayDate() }}</td>
                        <td data-col="delivery">@if ($o->requested_delivery_date)<span @class(['el-pill', 'el-bad' => $late])>{{ $o->requested_delivery_date->displayDate() }}@if ($late) · late @endif</span>@else<span class="el-sub">—</span>@endif</td>
                        <td class="el-r" data-col="lines">{{ $o->lines_count }}</td>
                        <td data-col="status"><span class="el-pill {{ $tones[$o->status] ?? '' }}">{{ SalesOrder::STATUSES[$o->status] ?? str_replace('_', ' ', $o->status) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="pagination"><div class="pagination-links">{{ $orders->links() }}</div></div>
            @endif
        </div>
        <aside class="el-side">
            <div><div class="el-h">Open orders</div><div style="margin-top:8px">
                @foreach (array_intersect_key(SalesOrder::STATUSES, array_flip(SalesOrder::OPEN)) as $k => $l)
                    <div class="el-row"><span>{{ $l }}</span><span>{{ $openByStatus[$k] ?? 0 }}</span></div>
                @endforeach
                <div class="el-row"><span>Past requested delivery</span><span>{{ $lateCount }}</span></div>
            </div></div>
        </aside>
    </div>
</div>
@endsection
