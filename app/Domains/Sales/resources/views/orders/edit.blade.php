@extends('layouts.app')

@section('title', 'Edit '.$order->order_number)
@section('breadcrumb', 'Sales orders / '.$order->order_number.' / Edit')

@section('content')
@include('partials.search-select')
@include('partials.quick-dialog', ['id' => 'qd-customer', 'title' => 'Add a customer', 'action' => route('customers.store'), 'submit' => 'Add customer', 'fields' => 'customers._quick-fields'])
@php
    $started = $order->lines->contains(fn ($l) => $l->productionOrders->where('status', '!=', 'CANCELLED')->isNotEmpty());
    $photos = \App\Models\Attachment::coverUrlsFor('product', $revisions->pluck('product_id')->merge($bareProducts->pluck('id'))->all());
    $rows = $order->lines->count() + 3;
@endphp
<div class="page-heading"><div><h1>Edit {{ $order->order_number }}</h1><p>Every change is written to the order's history with who made it{{ $needsReason ? ' and your reason' : '' }}.</p></div></div>

<form method="POST" action="{{ route('sales-orders.update', $order) }}">
@csrf @method('PUT')
<div class="panel"><div class="panel-header"><h2>Order</h2></div><div class="panel-body"><div class="form-grid" data-progressive="3">
    <div class="field"><label for="customer_id">Customer * <a href="{{ route('customers.create') }}" class="qd-add" data-quick="qd-customer" data-quick-target="customer_id">+ Add</a></label>
        <select id="customer_id" name="customer_id" required data-search-select>
            @foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $order->customer_id) === $c->id)>{{ $c->code }} — {{ $c->name }}</option>@endforeach
        </select>@if ($started)<div class="muted small">Production orders exist, so the customer cannot change.</div>@endif</div>
    <div class="field"><label for="customer_reference">Customer PO / reference</label><input id="customer_reference" name="customer_reference" maxlength="100" value="{{ old('customer_reference', $order->customer_reference) }}"></div>
    <div class="field"><label for="order_date">Order date *</label><input id="order_date" name="order_date" type="date" required value="{{ old('order_date', $order->order_date?->toDateString()) }}"></div>
    <div class="field"><label for="requested_delivery_date">Requested delivery</label><input id="requested_delivery_date" name="requested_delivery_date" type="date" value="{{ old('requested_delivery_date', $order->requested_delivery_date?->toDateString()) }}"></div>
    <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2">{{ old('notes', $order->notes) }}</textarea></div>
</div></div></div>

<div class="panel"><div class="panel-header"><h2>Lines</h2></div><div class="panel-body">
    <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Product revision</th><th>Quantity</th><th>Unit price</th><th>Line delivery</th><th>Remove</th></tr></thead>
        <tbody>
        @for ($i = 0; $i < $rows; $i++)
            @php
                $line = $order->lines[$i] ?? null;
                $locked = $line && $line->productionOrders->where('status', '!=', 'CANCELLED')->isNotEmpty();
                $chosen = old("lines.$i.product_revision_id", $line?->product_revision_id);
            @endphp
            <tr>
                <td>{{ $line?->line_number ?? $i + 1 }}@if ($line)<input type="hidden" name="lines[{{ $i }}][id]" value="{{ $line->id }}">@endif</td>
                <td>@if ($locked)
                        {{ $line->product?->code }} · rev {{ $line->productRevision?->revision }}<div class="muted small">Has production orders</div>
                    @else
                    <select name="lines[{{ $i }}][product_revision_id]" data-search-select="photos">
                        <option value="">— none —</option>
                        @foreach ($revisions as $r)<option value="{{ $r->id }}" data-sub="{{ $r->product?->code }} · rev {{ $r->revision }}" @isset($photos[$r->product_id]) data-img="{{ $photos[$r->product_id] }}" @endisset @selected($chosen === $r->id)>{{ $r->product?->name }}</option>@endforeach
                        @foreach ($bareProducts as $bp)<option value="product:{{ $bp->id }}" data-sub="{{ $bp->code }} · no revision yet" @isset($photos[$bp->id]) data-img="{{ $photos[$bp->id] }}" @endisset @selected($chosen === 'product:'.$bp->id)>{{ $bp->name }}</option>@endforeach
                    </select>@endif</td>
                <td><input name="lines[{{ $i }}][ordered_qty]" type="number" step="any" min="0.001" value="{{ old("lines.$i.ordered_qty", $line ? rtrim(rtrim((string) $line->ordered_qty, '0'), '.') : '') }}"></td>
                <td><input name="lines[{{ $i }}][unit_price]" type="number" step="any" min="0" value="{{ old("lines.$i.unit_price", $line ? rtrim(rtrim((string) $line->unit_price, '0'), '.') : '') }}"></td>
                <td><input name="lines[{{ $i }}][requested_delivery_date]" type="date" value="{{ old("lines.$i.requested_delivery_date", $line?->requested_delivery_date?->toDateString()) }}"></td>
                <td>@if ($line && ! $locked)<input type="checkbox" name="lines[{{ $i }}][remove]" value="1" aria-label="Remove line {{ $line->line_number }}">@endif</td>
            </tr>
        @endfor
        </tbody>
    </table></div>
    <p class="muted small">Leave unused lines empty. A line with production orders keeps its product and cannot go below what is scheduled or shipped.</p>
</div></div>

@if ($needsReason)
<div class="panel"><div class="panel-body"><div class="field"><label for="reason">Reason for the change *</label><input id="reason" name="reason" required minlength="5" maxlength="300" value="{{ old('reason') }}" placeholder="Why is the order being changed?"></div></div></div>
@endif
<button class="btn btn-primary" type="submit">Save changes</button>
<a class="btn" href="{{ route('sales-orders.show', $order) }}">Cancel</a>
</form>
@endsection
