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
<style>
    .el-card { background: var(--surface, #fff); border: 1px solid var(--border); border-radius: 10px; }
    .el-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid var(--border); }
    .el-tabs { display: flex; gap: 4px; flex-wrap: wrap; font-size: 13px; } .el-tabs a { padding: 4px 10px; border-radius: 6px; color: var(--muted); text-decoration: none; } .el-tabs a.on { background: #f0f1f3; color: var(--text); }
    .el-btn { padding: 7px 14px; border-radius: 6px; background: var(--surface, #fff); border: 1px solid var(--border); font-size: 13px; color: var(--text); text-decoration: none !important; cursor: pointer; white-space: nowrap; }
    .el-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
    .el-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; }
    .el-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .el-side { border-left: 1px solid var(--border); padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; font-size: 13px; }
    .el-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; font-size: 13px; } .el-filters input[type=search] { flex: 1; max-width: 320px; } .el-filters select { width: auto; }
    .el-table { margin: 0 -12px; overflow-x: auto; } .el-table table { width: 100%; } .el-table td { vertical-align: top; }
    .el-table tbody tr { cursor: pointer; } .el-table tbody tr:hover { background: #f5f8fe; }
    .el-sub { font-size: 12px; color: var(--muted); }
    .el-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #6b6e73; }
    .el-pill.el-blue { background: #eaf0fd; color: var(--accent); } .el-pill.el-ok { background: #e7f4ec; color: #1d7048; } .el-pill.el-warn { background: #fbf0dc; color: #94610a; } .el-pill.el-bad { background: #fbe7e5; color: #b3352c; }
    .el-h { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
    .el-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid var(--border); } .el-row:last-child { border-bottom: 0; } .el-row span:last-child { color: var(--muted); text-align: right; }
    .el-note { font-size: 12px; color: var(--muted); }
    .el-r { text-align: right; }
    @media (max-width: 1100px) { .el-grid { grid-template-columns: 1fr; } .el-side { border-left: 0; border-top: 1px solid var(--border); } }
    @media (max-width: 700px) { .el-bar, .el-main { padding-left: 16px; padding-right: 16px; } }
</style>
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
                <select name="customer" aria-label="Customer" onchange="this.form.submit()"><option value="">Customer: all</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(request('customer') === $c->id)>{{ $c->name }}</option>@endforeach</select>
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
