@extends('layouts.app')

@section('title', 'New sales order')
@section('breadcrumb', 'Sales orders / New')

@section('content')
@include('partials.search-select')
@include('partials.quick-dialog', ['id' => 'qd-customer', 'title' => 'Add a customer', 'action' => route('customers.store'), 'submit' => 'Add customer', 'fields' => 'customers._quick-fields'])
<div class="page-heading"><div><h1>New sales order</h1><p>Enter the customer's order. The contract review checks it against specifications, grades, furnaces and patterns.</p></div></div>


@if ($revisions->isEmpty() && $bareProducts->isEmpty())
    <div class="alert alert-error">There are no products to order yet. Add a product first.</div>
@else
<form method="POST" action="{{ route('sales-orders.store') }}">
@csrf
<div class="panel"><div class="panel-header"><h2>Order</h2></div><div class="panel-body"><div class="form-grid" data-progressive="3">
    <div class="field"><label for="customer_id">Customer * <a href="{{ route('customers.create') }}" class="qd-add" data-quick="qd-customer" data-quick-target="customer_id">+ Add</a></label>
        <select id="customer_id" name="customer_id" required data-search-select><option value="">Select customer</option>
            @foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id') === $c->id)>{{ $c->code }} — {{ $c->name }}</option>@endforeach
        </select></div>
    <div class="field"><label for="customer_reference">Customer PO / reference</label><input id="customer_reference" name="customer_reference" maxlength="100" value="{{ old('customer_reference') }}"></div>
    <div class="field"><label for="order_date">Order date *</label><input id="order_date" name="order_date" type="date" required value="{{ old('order_date', today()->toDateString()) }}"></div>
    <div class="field"><label for="requested_delivery_date">Requested delivery</label><input id="requested_delivery_date" name="requested_delivery_date" type="date" value="{{ old('requested_delivery_date') }}"></div>
    <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2">{{ old('notes') }}</textarea></div>
</div></div></div>

<div class="panel"><div class="panel-header"><h2>Lines</h2></div><div class="panel-body">
    <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Product revision</th><th>Quantity</th><th>Unit price</th><th>Line delivery</th></tr></thead>
        <tbody>
        @php($photos = \App\Models\Attachment::coverUrlsFor('product', $revisions->pluck('product_id')->merge($bareProducts->pluck('id'))->all()))
        @for ($i = 0; $i < 5; $i++)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td><select name="lines[{{ $i }}][product_revision_id]" data-search-select="photos" @if ($i === 0) required @endif>
                    <option value="">{{ $i === 0 ? 'Select product' : '— none —' }}</option>
                    @foreach ($revisions as $r)<option value="{{ $r->id }}" data-sub="{{ $r->product?->code }} · rev {{ $r->revision }}" @isset($photos[$r->product_id]) data-img="{{ $photos[$r->product_id] }}" @endisset @selected(old("lines.$i.product_revision_id") === $r->id)>{{ $r->product?->name }}</option>@endforeach
                    @foreach ($bareProducts as $bp)<option value="product:{{ $bp->id }}" data-sub="{{ $bp->code }} · no revision yet" @isset($photos[$bp->id]) data-img="{{ $photos[$bp->id] }}" @endisset @selected(old("lines.$i.product_revision_id") === 'product:'.$bp->id)>{{ $bp->name }}</option>@endforeach
                </select></td>
                <td><input name="lines[{{ $i }}][ordered_qty]" type="number" step="any" min="0.001" value="{{ old("lines.$i.ordered_qty") }}"></td>
                <td><input name="lines[{{ $i }}][unit_price]" type="number" step="any" min="0" value="{{ old("lines.$i.unit_price") }}"></td>
                <td><input name="lines[{{ $i }}][requested_delivery_date]" type="date" value="{{ old("lines.$i.requested_delivery_date") }}"></td>
            </tr>
        @endfor
        </tbody>
    </table></div>
    <p class="muted small">Leave unused lines empty.</p>
</div></div>
<button class="btn btn-primary" type="submit">Save order</button>
</form>
@endif
@endsection
