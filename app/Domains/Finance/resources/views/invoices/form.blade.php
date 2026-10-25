@extends('layouts.app')
@section('title', $invoice ? 'Edit draft invoice' : 'New invoice')
@section('content')
@php
    $taxJson = $taxRates->mapWithKeys(fn ($t) => [$t->id => (float) $t->rate_percent])->all();
    $startLines = old('lines', $lines);
    // With the Taxation module the taxes come from its rules: each line says whether it is goods or a service and its code.
    $taxPack = app(\App\Domains\Core\ModuleRegistry::class)->isEnabled('tax') ? \App\Support\TaxJurisdiction::pack() : null;
@endphp
<div class="page-heading"><div><h1>{{ $invoice ? 'Edit draft invoice' : 'New invoice' }}</h1><p>{{ $notice ?? 'Free lines, or lines filled from a dispatch or a sales order. Nothing counts until the invoice is issued.' }}</p></div>
@if (! $invoice)<div class="actions">@if (Route::has('dispatches.index'))<a class="btn" href="{{ route('dispatches.index') }}">From a dispatch…</a>@endif @if (Route::has('sales-orders.index'))<a class="btn" href="{{ route('sales-orders.index') }}">From a sales order…</a>@endif</div>@endif</div>
<form method="POST" action="{{ $invoice ? route('invoices.update', $invoice) : route('invoices.store') }}" x-data="invoiceLines(@js($startLines), @js($taxJson))">@csrf @if ($invoice) @method('PUT') @endif
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
<div class="panel"><div class="table-wrap"><table><thead><tr><th style="width:36%">Description</th><th>Qty</th><th>Unit</th><th>Price</th>@if ($taxPack)<th>Goods or service</th><th>{{ $taxPack->tariffLabel() }} / service code</th>@endif<th>{{ $taxPack ? 'Tax if no rule applies' : 'Tax' }}</th><th class="num">Amount</th><th></th></tr></thead><tbody>
<template x-for="(l, i) in lines" :key="i"><tr>
    <td><input :name="`lines[${i}][description]`" x-model="l.description" maxlength="300" style="width:100%">
        <input type="hidden" :name="`lines[${i}][sales_order_line_id]`" :value="l.sales_order_line_id ?? ''"><input type="hidden" :name="`lines[${i}][dispatch_item_id]`" :value="l.dispatch_item_id ?? ''"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][quantity]`" x-model.number="l.quantity" style="width:90px"></td>
    <td><input :name="`lines[${i}][uom]`" x-model="l.uom" maxlength="20" style="width:70px"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][unit_price]`" x-model.number="l.unit_price" style="width:110px"></td>
    @if ($taxPack)
        <td><select :name="`lines[${i}][supply_type]`" x-model="l.supply_type"><option value="GOODS">Goods</option><option value="SERVICE">Service</option></select><input type="hidden" :name="`lines[${i}][inventory_class]`" :value="l.inventory_class ?? ''"></td>
        <td><input :name="`lines[${i}][tariff_code]`" x-model="l.tariff_code" maxlength="20" style="width:110px"></td>
    @endif
    <td><select :name="`lines[${i}][tax_rate_id]`" x-model="l.tax_rate_id"><option value="">No tax</option>@foreach ($taxRates as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ rtrim(rtrim(number_format($t->rate_percent, 3), '0'), '.') }}%)</option>@endforeach</select></td>
    <td class="num" x-text="money(net(l))"></td>
    <td><button type="button" class="btn btn-small" @click="lines.splice(i, 1)" x-show="lines.length > 1" aria-label="Remove line">✕</button></td>
</tr></template>
</tbody>
@php $span = $taxPack ? 7 : 5; @endphp
<tfoot><tr><td colspan="{{ $span }}" class="num muted">Subtotal</td><td class="num" x-text="money(subtotal)"></td><td></td></tr>@if ($taxPack)<tr><td colspan="{{ $span }}" class="num muted">Tax is worked out by the tax rules when the draft is saved.</td><td></td><td></td></tr>@else<tr><td colspan="{{ $span }}" class="num muted">Tax</td><td class="num" x-text="money(tax)"></td><td></td></tr><tr><th colspan="{{ $span }}" class="num">Total</th><th class="num" x-text="money(subtotal + tax)"></th><td></td></tr>@endif</tfoot></table></div>
<div class="panel-body"><button type="button" class="btn btn-small" @click="lines.push({description: '', quantity: 1, uom: '', unit_price: 0, tax_rate_id: lines[0]?.tax_rate_id ?? '', supply_type: 'GOODS', tariff_code: '', inventory_class: '', sales_order_line_id: null, dispatch_item_id: null})">+ Add a line</button></div></div>
<div class="panel"><div class="panel-body"><label>Notes on the invoice<textarea name="notes" rows="2" maxlength="2000">{{ old('notes', $invoice?->notes) }}</textarea></label></div>
<div class="panel-body"><button class="btn btn-primary" type="submit">Save draft</button> <a class="btn" href="{{ $invoice ? route('invoices.show', $invoice) : route('invoices.index') }}">Cancel</a></div></div>
</form>
<script>
function invoiceLines(start, rates) {
    return {
        lines: start.map(l => ({ ...l, tax_rate_id: l.tax_rate_id ?? '', supply_type: l.supply_type ?? 'GOODS', tariff_code: l.tariff_code ?? '', inventory_class: l.inventory_class ?? '' })),
        net(l) { return Math.round((Number(l.quantity) || 0) * (Number(l.unit_price) || 0) * 100) / 100; },
        lineTax(l) { return Math.round(this.net(l) * (rates[l.tax_rate_id] || 0)) / 100; },
        get subtotal() { return this.lines.reduce((n, l) => n + this.net(l), 0); },
        get tax() { return this.lines.reduce((n, l) => n + this.lineTax(l), 0); },
        money(v) { return (Math.round(v * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endsection
