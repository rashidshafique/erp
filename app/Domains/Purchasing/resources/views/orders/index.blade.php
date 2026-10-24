@extends('layouts.app')
@section('title', 'Purchase orders')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Purchase orders</h1><p>What has been ordered from suppliers and what has arrived. Receiving makes material lots that wait for the incoming checks.</p></div><div class="actions"><a class="btn" href="{{ route('purchase-orders.settings.edit') }}">Rules</a> <a class="btn btn-primary" href="{{ route('purchase-orders.create') }}">+ New purchase order</a></div></div>
<div class="panel"><div class="panel-body">
    <form method="GET" class="filters">
        <div class="field"><label for="supplier">Supplier</label><select id="supplier" name="supplier" onchange="this.form.submit()"><option value="">All suppliers</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($supplierId === $s->id)>{{ $s->name }} ({{ $s->code }})</option>@endforeach</select></div>
        <div class="field"><label for="status">Show</label><select id="status" name="status" onchange="this.form.submit()"><option value="">Everything</option><option value="OPEN" @selected($status === 'OPEN')>Waiting for goods</option>@foreach (\App\Domains\Purchasing\Models\PurchaseOrder::STATUSES as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select></div>
        @if ($supplierId || $status)<a class="btn" href="{{ route('purchase-orders.index') }}">Reset</a>@endif
    </form>
</div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Number</th><th>Supplier</th><th>Date</th><th>Expected</th><th>Total</th><th>Status</th></tr></thead><tbody>
@forelse ($orders as $o)
<tr><td><a href="{{ route('purchase-orders.show', $o) }}">{{ $o->po_number ?? 'Draft' }}</a></td><td>{{ $o->supplier?->name }}</td><td>{{ $o->order_date->displayShortDate() }}</td><td @if ($o->isLate()) style="color:var(--danger)" @endif>{{ $o->expected_date?->displayShortDate() }}</td><td>{{ $m($o->total) }} {{ $o->currency }}</td>
<td><span class="status {{ in_array($o->status, ['RECEIVED', 'CLOSED']) ? 'status-completed' : ($o->status === 'CANCELLED' ? 'status-cancelled' : 'status-on-hold') }}">{{ \App\Domains\Purchasing\Models\PurchaseOrder::STATUSES[$o->status] }}</span></td></tr>
@empty<tr><td colspan="6" class="muted">No purchase order yet.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body">{{ $orders->links() }}</div></div>
@endsection
