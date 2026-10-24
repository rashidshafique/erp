@extends('layouts.app')
@section('title', $order->po_number ?? 'Draft purchase order')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); $q = fn ($v) => \App\Support\Formats::number($v, 3); @endphp
<div class="page-heading"><div><h1>Purchase order {{ $order->po_number ?? '(draft)' }}</h1><p>{{ $order->supplier?->name }} · {{ $order->order_date->displayDate() }}@if ($order->expected_date) · expected {{ $order->expected_date->displayDate() }}@endif</p></div>
<div class="actions">
@if ($order->status === 'DRAFT')
    <a class="btn" href="{{ route('purchase-orders.edit', $order) }}">Edit</a>
    <form method="POST" action="{{ route('purchase-orders.issue', $order) }}" style="display:inline">@csrf<button class="btn btn-primary" onclick="return confirm('Issue this order? It gets its number and can no longer be edited.')">Issue</button></form>
    <form method="POST" action="{{ route('purchase-orders.destroy', $order) }}" style="display:inline">@csrf @method('DELETE')<button class="btn" onclick="return confirm('Delete this draft?')">Delete</button></form>
@else
    <a class="btn" href="{{ route('purchase-orders.print', $order) }}" target="_blank">Print</a>
    @if (Route::has('bills.create') && ! in_array($order->status, ['CANCELLED']))<a class="btn" href="{{ route('bills.create', ['order' => $order->id]) }}">Enter supplier bill</a>@endif
@endif
</div></div>
<div class="panel"><div class="panel-body" style="display:flex;gap:28px;flex-wrap:wrap">
    <div><div class="muted small">Status</div><span class="status {{ in_array($order->status, ['RECEIVED', 'CLOSED']) ? 'status-completed' : ($order->status === 'CANCELLED' ? 'status-cancelled' : 'status-on-hold') }}">{{ \App\Domains\Purchasing\Models\PurchaseOrder::STATUSES[$order->status] }}</span></div>
    <div><div class="muted small">Total</div><strong>{{ $m($order->total) }} {{ $order->currency }}</strong></div>
    @if ($order->payment_terms)<div><div class="muted small">Terms</div>{{ $order->payment_terms }}</div>@endif
    @if ($order->supplier_reference)<div><div class="muted small">Supplier reference</div>{{ $order->supplier_reference }}</div>@endif
</div>
@if ($order->close_reason)<div class="panel-body muted">Closed {{ $order->closed_at?->displayShortDateTime() }}: {{ $order->close_reason }}</div>@endif</div>
@if ($order->canReceive())
<form method="POST" action="{{ route('purchase-orders.receive', $order) }}">@csrf
@endif
<div class="panel"><div class="table-wrap"><table><thead><tr><th>#</th><th>Description</th><th>Ordered</th><th>Received</th><th>Price</th><th>Tax</th><th class="num">Amount</th>@if ($order->canReceive())<th>Receive now</th>@endif</tr></thead><tbody>
@foreach ($order->lines as $l)<tr><td>{{ $l->line_number }}</td><td>{{ $l->description }}@if (! $l->material_id)<div class="muted small">free line: counted, no lot</div>@endif
@foreach ($l->lots as $lot)<div class="small"><a href="{{ route('materials.show', $lot->material_id) }}">{{ $lot->lot_number }}</a> <span class="muted">{{ strtolower($lot->status) }}, {{ $q($lot->received_qty) }}</span></div>@endforeach</td>
<td>{{ $q($l->ordered_qty) }} {{ $l->uom }}</td><td>{{ $q($l->received_qty) }}</td><td>{{ $m($l->unit_price) }}</td><td>{{ $l->tax_percent > 0 ? rtrim(rtrim(number_format($l->tax_percent, 3), '0'), '.').'%' : '—' }}</td><td class="num">{{ $m($l->line_net) }}</td>
@if ($order->canReceive())<td><input type="number" step="any" min="0" name="qty[{{ $l->id }}]" placeholder="{{ $q($l->outstanding()) }}" style="width:110px"></td>@endif</tr>@endforeach
</tbody><tfoot><tr><td colspan="6" class="num muted">Subtotal</td><td class="num">{{ $m($order->subtotal) }}</td>@if ($order->canReceive())<td></td>@endif</tr><tr><td colspan="6" class="num muted">Tax</td><td class="num">{{ $m($order->tax_total) }}</td>@if ($order->canReceive())<td></td>@endif</tr><tr><th colspan="6" class="num">Total</th><th class="num">{{ $m($order->total) }}</th>@if ($order->canReceive())<td></td>@endif</tr></tfoot></table></div>
@if ($order->notes)<div class="panel-body small">{{ $order->notes }}</div>@endif
@if ($order->canReceive())<div class="panel-body form-grid"><label>Date received<input type="date" name="received_on" value="{{ today()->toDateString() }}"></label><label>Supplier lot number<input name="supplier_lot_number" maxlength="100" placeholder="From the delivery note"></label></div>
<div class="panel-body"><button class="btn btn-primary">Receive goods</button> <span class="muted small">Each material line makes a lot in quarantine; accept it through the incoming checks as usual.</span></div>@endif</div>
@if ($order->canReceive())</form>@endif
@if (! in_array($order->status, ['DRAFT', 'CLOSED', 'CANCELLED']))
<div class="panel"><div class="panel-body"><form method="POST" action="{{ route('purchase-orders.close', $order) }}" style="display:flex;gap:8px;flex-wrap:wrap">@csrf
<input name="reason" placeholder="{{ $order->status === 'RECEIVED' ? 'Why close it (optional detail)' : 'Why nothing more is expected' }}" maxlength="300" required style="min-width:300px;flex:1"><button class="btn" onclick="return confirm('Close this order? No more goods can be received against it.')">Close order</button></form></div></div>
@endif
@include('attachments._panel', ['type' => 'purchase_order', 'id' => $order->id])
@include('audit._history', ['type' => 'PurchaseOrder', 'id' => $order->id])
@endsection
