{{-- The sales orders list, in the same layout as Enquiries, Customers and Products: a bar with status tabs, columns, export and
     new, a search and customer filter, a clickable table with status pills, and the open orders by status beside it. --}}
@extends('layouts.app')

@section('title', 'Sales orders')
@section('breadcrumb', 'Sales orders')

@section('content')
@php
    use App\Domains\Sales\Models\SalesOrder;
    $tabs = ['' => 'All', 'OPEN' => 'Open'] + SalesOrder::STATUSES;
    $tones = ['DRAFT' => 'el-grey', 'UNDER_REVIEW' => 'el-warn', 'CONFIRMED' => 'el-blue', 'IN_PRODUCTION' => 'el-blue', 'ON_HOLD' => 'el-bad', 'DISPATCHED' => 'el-ok', 'CANCELLED' => 'el-grey'];
    $keep = fn (array $over) => request()->fullUrlWithQuery($over + ['page' => null]);
    $filtered = request()->hasAny(['search', 'customer']);
@endphp
@include('sales::orders._record-styles')
@include('partials.list-ui')
@php $details = \App\Support\ListLayout::details(); @endphp
<div class="el-card ls-card">
    <div class="ls-head">
        <h1 class="ls-title">Sales orders</h1>
        <div class="ls-actions"><a class="ls-btn primary" href="{{ route('sales-orders.create') }}">New sales order</a></div>
    </div>
    <nav class="ls-tabs" aria-label="Order status">@foreach ($tabs as $k => $l)<a href="{{ $keep(['status' => $k ?: null]) }}" @class(['on' => ($status ?? '') === $k])>{{ $l }} <span class="n">{{ $counts[$k] ?? 0 }}</span></a>@endforeach</nav>
    <div class="ls-tools">
        <form method="GET" action="{{ route('sales-orders.index') }}" style="display:contents">
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <label class="ls-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" name="search" value="{{ $search }}" placeholder="Search order number, customer or reference" aria-label="Search order number, customer or reference"></label>
            <select data-search-select name="customer" aria-label="Customer" class="ls-select" onchange="this.form.submit()"><option value="">Customer: all</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(request('customer') === $c->id)>{{ $c->name }}</option>@endforeach</select>
            <noscript><button class="ls-tool" type="submit">Filter</button></noscript>
            @if ($filtered)<a class="ls-tool" href="{{ route('sales-orders.index', $status ? ['status' => $status] : []) }}">Clear</a>@endif
        </form>
        <span class="ls-right">
            <x-column-picker table="sales-orders" :columns="['customer' => 'Customer', 'ref' => 'Customer ref', 'date' => 'Order date', 'delivery' => 'Delivery', 'lines' => 'Lines', 'status' => 'Status']" />
            <a class="ls-tool ls-ico" href="{{ route('sales-orders.export', request()->query()) }}" title="Export" aria-label="Export"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v12m0 0-4-4m4 4 4-4M5 20h14"/></svg></a>
        </span>
    </div>
    <div class="el-grid" @style(['grid-template-columns: minmax(0, 1fr)' => ! $details])>
        <div class="el-main">
            @if ($orders->isEmpty())
                <div class="empty-state"><strong>{{ $filtered || $status ? 'No sales order matches' : 'No sales orders yet' }}</strong><p>From customer order through contract review to production and dispatch.</p><a class="btn btn-primary" href="{{ route('sales-orders.create') }}">New sales order</a></div>
            @else
            <div class="el-table"><table data-table="sales-orders">
                <thead><tr><th>Order</th><th data-col="customer">Customer</th><th data-col="ref">Customer ref</th><th data-col="date">Order date</th><th data-col="delivery">Delivery</th><th class="el-r" data-col="lines">Lines</th><th data-col="status">Status</th>@unless ($details)<th class="ls-dth">@include('partials.list-details-form')</th>@endunless</tr></thead>
                <tbody>
                @foreach ($orders as $o)
                    @php $late = in_array($o->status, SalesOrder::OPEN, true) && $o->requested_delivery_date && $o->requested_delivery_date->isBefore(today()); @endphp
                    <tr onclick="if (!event.target.closest('a,summary,details')) location.href='{{ route('sales-orders.show', $o) }}'">
                        <td><a href="{{ route('sales-orders.show', $o) }}" style="color:inherit"><code>{{ $o->order_number }}</code></a></td>
                        <td data-col="customer">{{ $o->customer?->name }}</td>
                        <td data-col="ref">{{ $o->customer_reference ?: '—' }}</td>
                        <td data-col="date">{{ $o->order_date?->displayDate() }}</td>
                        <td data-col="delivery">@if ($o->requested_delivery_date)<span @class(['el-pill', 'el-bad' => $late])>{{ $o->requested_delivery_date->displayDate() }}@if ($late) · late @endif</span>@else<span class="el-sub">—</span>@endif</td>
                        <td class="el-r" data-col="lines">{{ $o->lines_count }}</td>
                        <td data-col="status"><span class="el-pill {{ $tones[$o->status] ?? '' }}">{{ SalesOrder::STATUSES[$o->status] ?? str_replace('_', ' ', $o->status) }}</span></td>
                        @unless ($details)<td class="act"><details class="ls-menu right"><summary class="ls-more" aria-label="Actions for {{ $o->order_number }}">⋯</summary><div class="ls-pop" role="menu"><a role="menuitem" href="{{ route('sales-orders.show', $o) }}">Open order</a><a role="menuitem" href="{{ route('sales-orders.edit', $o) }}">Edit</a></div></details></td>@endunless
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="pagination"><div class="pagination-links">{{ $orders->links() }}</div></div>
            @endif
        </div>
        @if ($details)
        <aside class="el-side">
            <div class="ls-ph"><div class="el-h">Open orders</div>@include('partials.list-hide-form')</div>
            <div><div style="margin-top:8px">
                @foreach (array_intersect_key(SalesOrder::STATUSES, array_flip(SalesOrder::OPEN)) as $k => $l)
                    <div class="el-row"><span>{{ $l }}</span><span>{{ $openByStatus[$k] ?? 0 }}</span></div>
                @endforeach
                <div class="el-row"><span>Past requested delivery</span><span>{{ $lateCount }}</span></div>
            </div></div>
        </aside>
        @endif
    </div>
</div>
@endsection
