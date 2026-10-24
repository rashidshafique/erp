@extends('layouts.app')
@section('title', 'Supplier credit note')
@section('content')
@php $start = old('lines', $bill->lines->map(fn ($l) => ['description' => $l->description, 'quantity' => (float) $l->quantity, 'uom' => $l->uom, 'unit_price' => (float) $l->unit_price, 'tax_percent' => (float) $l->tax_percent, 'purchase_order_line_id' => null])->all()); @endphp
<div class="page-heading"><div><h1>Credit note against {{ $bill->bill_number }}</h1><p>{{ $bill->supplier?->name }} · bill total {{ \App\Support\Formats::number($bill->total, 2) }} · already credited {{ \App\Support\Formats::number($bill->credited(), 2) }}. Enter what the supplier's credit note says; lower the quantity or price, or remove lines, to credit part of it.</p></div></div>
<form method="POST" action="{{ route('bills.credit.store', $bill) }}" x-data="billLines(@js($start))">@csrf
<div class="panel"><div class="table-wrap"><table><thead><tr><th style="width:40%">Description</th><th>Qty</th><th>Price</th><th>Tax %</th><th class="num">Credit</th><th></th></tr></thead><tbody>
<template x-for="(l, i) in lines" :key="i"><tr>
    <td><input :name="`lines[${i}][description]`" x-model="l.description" maxlength="300" style="width:100%"><input type="hidden" :name="`lines[${i}][uom]`" :value="l.uom ?? ''"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][quantity]`" x-model.number="l.quantity" style="width:90px"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][unit_price]`" x-model.number="l.unit_price" style="width:110px"></td>
    <td><input type="number" step="any" min="0" max="100" :name="`lines[${i}][tax_percent]`" x-model.number="l.tax_percent" style="width:70px"></td>
    <td class="num" x-text="money(net(l) + lineTax(l))"></td>
    <td><button type="button" class="btn btn-small" @click="lines.splice(i, 1)" x-show="lines.length > 1" aria-label="Remove line">✕</button></td>
</tr></template>
</tbody><tfoot><tr><th colspan="4" class="num">Credit total</th><th class="num" x-text="money(subtotal + tax)"></th><td></td></tr></tfoot></table></div></div>
<div class="panel"><div class="panel-body form-grid"><label>Supplier's credit note number<input name="supplier_bill_number" value="{{ old('supplier_bill_number') }}" maxlength="100" required></label><label>Reason<input name="reason" value="{{ old('reason') }}" maxlength="500" required placeholder="Rejected material, price correction, returned goods…"></label></div>
<div class="panel-body"><button class="btn btn-primary" onclick="return confirm('Post this credit note? It is final.')">Post credit note</button> <a class="btn" href="{{ route('bills.show', $bill) }}">Cancel</a></div></div>
</form>
<script>
function billLines(start) {
    return {
        lines: start.map(l => ({ ...l, tax_percent: l.tax_percent ?? 0 })),
        net(l) { return Math.round((Number(l.quantity) || 0) * (Number(l.unit_price) || 0) * 100) / 100; },
        lineTax(l) { return Math.round(this.net(l) * (Number(l.tax_percent) || 0)) / 100; },
        get subtotal() { return this.lines.reduce((n, l) => n + this.net(l), 0); },
        get tax() { return this.lines.reduce((n, l) => n + this.lineTax(l), 0); },
        money(v) { return (Math.round(v * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endsection
