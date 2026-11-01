@extends('layouts.app')
@section('title', $invoice ? 'Edit draft invoice' : 'New invoice')
@section('content')
@include('partials.search-select')
@php
    $taxJson = $taxRates->mapWithKeys(fn ($t) => [$t->id => (float) $t->rate_percent])->all();
    $startLines = old('lines', $lines);
    // With the Taxation module the taxes come from its rules: each line says whether it is goods or a service and its code.
    $taxPack = app(\App\Domains\Core\ModuleRegistry::class)->isEnabled('tax') ? \App\Support\TaxJurisdiction::pack() : null;
    // A discount box shows when the setting is on, or when a line already has a discount (a dispatch or order line can bring one).
    $discounts = \App\Domains\Sales\Services\PriceLists::discountsOn() || collect($startLines)->contains(fn ($l) => (float) ($l['discount_percent'] ?? 0) > 0);
@endphp
<div class="page-heading"><div><h1>{{ $invoice ? 'Edit draft invoice' : 'New invoice' }}</h1><p>{{ $notice ?? 'Free lines, or lines filled from a dispatch or a sales order. Nothing counts until the invoice is issued.' }}</p></div>
@if (! $invoice)<div class="actions">@if (Route::has('dispatches.index'))<a class="btn" href="{{ route('dispatches.index') }}">From a dispatch…</a>@endif @if (Route::has('sales-orders.index'))<a class="btn" href="{{ route('sales-orders.index') }}">From a sales order…</a>@endif</div>@endif</div>
<form method="POST" action="{{ $invoice ? route('invoices.update', $invoice) : route('invoices.store') }}" x-data="invoiceLines(@js($startLines), @js($taxJson), @js((object) $products), @js(Route::has('price-lists.lookup') ? route('price-lists.lookup') : null))" x-ref="form">@csrf @if ($invoice) @method('PUT') @endif
<input type="hidden" name="source_type" value="{{ old('source_type', $head['source_type'] ?? 'manual') }}"><input type="hidden" name="source_id" value="{{ old('source_id', $head['source_id'] ?? '') }}">
<div class="panel"><div class="panel-body form-grid">
    <label>Customer<select name="customer_id" required><option value="">—</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $head['customer_id'] ?? null) === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
    <label>Invoice date<input type="date" name="invoice_date" value="{{ old('invoice_date', $invoice?->invoice_date?->toDateString() ?? today()->toDateString()) }}" required></label>
    <label>Due date<input type="date" name="due_date" value="{{ old('due_date', $invoice?->due_date?->toDateString()) }}"><span class="muted small">Blank: from the customer's terms, or {{ \App\Domains\Finance\Services\FinanceRules::dueDays() }} days</span></label>
    <label>Currency<input name="currency" maxlength="10" value="{{ old('currency', $currency) }}"></label>
    <label>Payment terms<input name="payment_terms" maxlength="100" value="{{ old('payment_terms', $invoice?->payment_terms) }}" placeholder="Customer's terms"></label>
    <label>Customer reference<input name="customer_reference" maxlength="100" value="{{ old('customer_reference', $head['customer_reference'] ?? '') }}"></label>
    @if ($taxPack)
        <label>Place of supply<select name="place_of_supply"><option value="">Customer's region</option>@foreach ($taxPack->regions() as $r => $name)<option value="{{ $r }}" @selected(old('place_of_supply', $head['place_of_supply'] ?? null) === $r)>{{ $name }}</option>@endforeach</select><span class="muted small">Decides which region taxes the services on this invoice.</span></label>
    @endif
</div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th style="width:20%">Product</th><th style="width:26%">Description</th><th>Qty</th><th>Unit</th><th>Price</th>@if ($discounts)<th>Disc %</th>@endif @if ($taxPack)<th>Goods or service</th><th>{{ $taxPack->tariffLabel() }} / service code</th>@endif<th>{{ $taxPack ? 'Tax if no rule applies' : 'Tax' }}</th><th class="num">Amount</th><th></th></tr></thead><tbody>
<template x-for="(l, i) in lines" :key="i"><tr>
    <td><select :name="`lines[${i}][product_id]`" x-model="l.product_id" @change="pick(l, $event.target.value)" data-search-select style="width:100%" aria-label="Product"><option value="">Free text</option>@foreach ($products as $id => $p)<option value="{{ $id }}" data-sub="{{ $p['code'] }}">{{ $p['name'] }}</option>@endforeach</select></td>
    <td><input :name="`lines[${i}][description]`" x-model="l.description" maxlength="300" style="width:100%" :placeholder="l.product_id ? '' : 'Type what is sold'">
        <input type="hidden" :name="`lines[${i}][sales_order_line_id]`" :value="l.sales_order_line_id ?? ''"><input type="hidden" :name="`lines[${i}][dispatch_item_id]`" :value="l.dispatch_item_id ?? ''"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][quantity]`" x-model.number="l.quantity" style="width:90px"></td>
    <td><input :name="`lines[${i}][uom]`" x-model="l.uom" maxlength="20" style="width:70px"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][unit_price]`" x-model.number="l.unit_price" style="width:110px"></td>
    @if ($discounts)<td><input type="number" step="any" min="0" max="100" :name="`lines[${i}][discount_percent]`" x-model.number="l.discount_percent" style="width:70px"></td>@else<input type="hidden" :name="`lines[${i}][discount_percent]`" :value="l.discount_percent ?? 0">@endif
    @if ($taxPack)
        <td><select :name="`lines[${i}][supply_type]`" x-model="l.supply_type"><option value="GOODS">Goods</option><option value="SERVICE">Service</option></select><input type="hidden" :name="`lines[${i}][inventory_class]`" :value="l.inventory_class ?? ''"></td>
        <td><input :name="`lines[${i}][tariff_code]`" x-model="l.tariff_code" maxlength="20" style="width:110px"></td>
    @endif
    <td><select :name="`lines[${i}][tax_rate_id]`" x-model="l.tax_rate_id"><option value="">No tax</option>@foreach ($taxRates as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ \App\Support\Formats::number($t->rate_percent, 2) }}%)</option>@endforeach</select></td>
    <td class="num" x-text="money(net(l))"></td>
    <td><button type="button" class="btn btn-small" @click="lines.splice(i, 1)" x-show="lines.length > 1" aria-label="Remove line">✕</button></td>
</tr></template>
</tbody>
@php $span = ($taxPack ? 8 : 6) + ($discounts ? 1 : 0); @endphp
<tfoot><tr><td colspan="{{ $span }}" class="num muted">Subtotal</td><td class="num" x-text="money(subtotal)"></td><td></td></tr>@if ($taxPack)<tr><td colspan="{{ $span }}" class="num muted">Tax is worked out by the tax rules when the draft is saved.</td><td></td><td></td></tr>@else<tr><td colspan="{{ $span }}" class="num muted">Tax</td><td class="num" x-text="money(tax)"></td><td></td></tr><tr><th colspan="{{ $span }}" class="num">Total</th><th class="num" x-text="money(subtotal + tax)"></th><td></td></tr>@endif</tfoot></table></div>
<div class="panel-body"><button type="button" class="btn btn-small" @click="lines.push({product_id: '', description: '', quantity: 1, uom: '', unit_price: 0, discount_percent: 0, tax_rate_id: lines[0]?.tax_rate_id ?? '', supply_type: 'GOODS', tariff_code: '', inventory_class: '', sales_order_line_id: null, dispatch_item_id: null})">+ Add a line</button></div></div>
@include('finance::invoices._charges', ['document' => 'invoice', 'adjustments' => old('adjustments', $invoice?->adjustments ?? [])])
<div class="panel"><div class="panel-body"><label>Notes on the invoice<textarea name="notes" rows="2" maxlength="2000">{{ old('notes', $invoice?->notes) }}</textarea></label></div>
<div class="panel-body"><button class="btn btn-primary" type="submit">Save draft</button> <a class="btn" href="{{ $invoice ? route('invoices.show', $invoice) : route('invoices.index') }}">Cancel</a></div></div>
</form>
<script>
function invoiceLines(start, rates, products, lookup) {
    return {
        lines: start.map(l => ({ ...l, product_id: l.product_id ?? '', tax_rate_id: l.tax_rate_id ?? '', supply_type: l.supply_type ?? 'GOODS', tariff_code: l.tariff_code ?? '', inventory_class: l.inventory_class ?? '' })),
        net(l) { return Math.round((Number(l.quantity) || 0) * (Number(l.unit_price) || 0) * (1 - Math.min(100, Math.max(0, Number(l.discount_percent) || 0)) / 100) * 100) / 100; },
        lineTax(l) { return Math.round(this.net(l) * (rates[l.tax_rate_id] || 0)) / 100; },
        get subtotal() { return this.lines.reduce((n, l) => n + this.net(l), 0); },
        get tax() { return this.lines.reduce((n, l) => n + this.lineTax(l), 0); },
        // A picked product fills the line: its name, unit, tax details and price. The price is the customer's list price when a
        // list prices it, else the last price it was sold at; the line stays editable either way.
        pick(l, id) {
            const p = products[id];
            if (! p) { return; }
            Object.assign(l, { description: (p.code ? p.code + ' ' : '') + p.name, uom: p.uom, supply_type: p.supply_type || 'GOODS', tariff_code: p.tariff_code ?? '', inventory_class: p.inventory_class ?? '' });
            if (p.price !== null) { l.unit_price = p.price; }
            if (! lookup) { return; }
            const form = this.$refs.form;
            const q = new URLSearchParams({ product: 'product:' + id, customer: form.customer_id.value, date: form.invoice_date.value, qty: Number(l.quantity) || 1, uom: l.uom === 'kg' ? 'kg' : 'piece' });
            fetch(lookup + '?' + q, { headers: { Accept: 'application/json' } }).then(r => r.ok ? r.json() : { price: null }).then(j => {
                if (j.price !== null && l.product_id === id) { l.unit_price = Number(j.price); }
            }).catch(() => {});
        },
        money(v) { return (Math.round(v * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endsection
