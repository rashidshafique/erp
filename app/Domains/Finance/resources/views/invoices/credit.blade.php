@extends('layouts.app')
@section('title', 'Credit note')
@section('content')
@php
    $taxJson = $taxRates->mapWithKeys(fn ($t) => [$t->id => (float) $t->rate_percent])->all();
    $start = old('lines', $invoice->lines->map(fn ($l) => ['description' => $l->description, 'quantity' => (float) $l->quantity, 'uom' => $l->uom, 'unit_price' => (float) $l->unit_price, 'tax_rate_id' => $l->tax_rate_id, 'sales_order_line_id' => $l->sales_order_line_id, 'dispatch_item_id' => null, 'max' => (float) $l->quantity])->all());
@endphp
<div class="page-heading"><div><h1>Credit note against {{ $invoice->invoice_number }}</h1><p>{{ $invoice->customer?->name }} · invoice total {{ \App\Support\Formats::number($invoice->total, 2) }} · already credited {{ \App\Support\Formats::number($invoice->credited(), 2) }}. Lower the quantity or price, or remove lines, to credit part of it.</p></div></div>
<form method="POST" action="{{ route('invoices.credit.store', $invoice) }}" x-data="invoiceLines(@js($start), @js($taxJson))">@csrf
<div class="panel"><div class="table-wrap"><table><thead><tr><th style="width:40%">Description</th><th>Qty</th><th>Price</th><th>Tax</th><th class="num">Credit</th><th></th></tr></thead><tbody>
<template x-for="(l, i) in lines" :key="i"><tr>
    <td><input :name="`lines[${i}][description]`" x-model="l.description" maxlength="300" style="width:100%"><input type="hidden" :name="`lines[${i}][uom]`" :value="l.uom ?? ''"><input type="hidden" :name="`lines[${i}][sales_order_line_id]`" :value="l.sales_order_line_id ?? ''"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][quantity]`" x-model.number="l.quantity" style="width:90px"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][unit_price]`" x-model.number="l.unit_price" style="width:110px"></td>
    <td><select :name="`lines[${i}][tax_rate_id]`" x-model="l.tax_rate_id"><option value="">No tax</option>@foreach ($taxRates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></td>
    <td class="num" x-text="money(net(l) + lineTax(l))"></td>
    <td><button type="button" class="btn btn-small" @click="lines.splice(i, 1)" x-show="lines.length > 1" aria-label="Remove line">✕</button></td>
</tr></template>
</tbody><tfoot><tr><th colspan="4" class="num">Credit total</th><th class="num" x-text="money(subtotal + tax)"></th><td></td></tr></tfoot></table></div></div>
<div class="panel"><div class="panel-body"><label>Reason<input name="reason" value="{{ old('reason') }}" maxlength="500" required placeholder="Rejected castings, price correction, returned goods…"></label></div>
<div class="panel-body"><button class="btn btn-primary" onclick="return confirm('Issue this credit note? It is final.')">Issue credit note</button> <a class="btn" href="{{ route('invoices.show', $invoice) }}">Cancel</a></div></div>
</form>
<script>
function invoiceLines(start, rates) {
    return {
        lines: start.map(l => ({ ...l, tax_rate_id: l.tax_rate_id ?? '' })),
        net(l) { return Math.round((Number(l.quantity) || 0) * (Number(l.unit_price) || 0) * 100) / 100; },
        lineTax(l) { return Math.round(this.net(l) * (rates[l.tax_rate_id] || 0)) / 100; },
        get subtotal() { return this.lines.reduce((n, l) => n + this.net(l), 0); },
        get tax() { return this.lines.reduce((n, l) => n + this.lineTax(l), 0); },
        money(v) { return (Math.round(v * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endsection
