@extends('layouts.app')
@section('title', 'Purchase orders')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Purchase orders</h1><p>What has been ordered from suppliers and what has arrived. Receiving makes material lots that wait for the incoming checks.</p></div><div class="actions"><a class="btn" href="{{ route('purchase-orders.settings.edit') }}">Rules</a> <a class="btn btn-primary" href="{{ route('purchase-orders.create') }}">+ New purchase order</a></div></div>
@php $statusTabs = ['OPEN' => 'Open'] + \App\Domains\Purchasing\Models\PurchaseOrder::STATUSES + ['' => 'All']; @endphp
<div class="list-toolbar">
    <x-status-tabs :options="$statusTabs" :counts="$counts" :current="$status ?? ''" />
    <form method="GET" role="search">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        @if ($supplierId)<input type="hidden" name="supplier" value="{{ $supplierId }}">@endif
        <input type="search" name="search" value="{{ $search }}" placeholder="Search by PO number or supplier" aria-label="Search by PO number or supplier">
        <button class="btn btn-primary" type="submit">Search</button>
    </form>
    @if ($supplierName || $status === 'RECEIVING')<span class="muted small">@if ($supplierName)Supplier: <strong>{{ $supplierName }}</strong>@endif @if ($status === 'RECEIVING') Waiting for goods @endif · <a href="{{ route('purchase-orders.index') }}">Clear</a></span>@endif
</div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>PO number</th><th>Supplier</th><th>Order date</th><th>Expected</th><th>Status</th><th class="num">Total</th><th>Action</th></tr></thead><tbody>
@forelse ($orders as $o)
<tr><td><a href="{{ route('purchase-orders.show', $o) }}"><strong>{{ $o->po_number ?? 'Draft' }}</strong></a></td><td>{{ $o->supplier?->name }}</td><td>{{ $o->order_date->displayShortDate() }}</td><td @if ($o->isLate()) style="color:var(--danger)" @endif>{{ $o->expected_date?->displayShortDate() }}</td>
<td><span class="status {{ in_array($o->status, ['RECEIVED', 'CLOSED']) ? 'status-completed' : ($o->status === 'CANCELLED' ? 'status-cancelled' : 'status-on-hold') }}">{{ \App\Domains\Purchasing\Models\PurchaseOrder::STATUSES[$o->status] }}</span></td>
<td class="num">{{ $m($o->total) }} {{ $o->currency }}</td><td><a class="btn btn-small" href="{{ route('purchase-orders.show', $o) }}">Open</a></td></tr>
@empty<tr><td colspan="7" class="muted">{{ $search !== '' || $status || $supplierId ? 'No purchase order matches.' : 'No purchase order yet.' }}</td></tr>@endforelse</tbody></table></div>
<div class="panel-body">{{ $orders->links() }}</div></div>
@endsection
