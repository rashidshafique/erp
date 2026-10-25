@extends('layouts.app')
@section('title', $bill ? 'Edit draft supplier bill' : 'New supplier bill')
@section('content')
@php $startLines = old('lines', $lines); @endphp
<div class="page-heading"><div><h1>{{ $bill ? 'Edit draft supplier bill' : 'New supplier bill' }}</h1><p>{{ $notice ?? 'Enter what the supplier billed, free lines or filled from a purchase order. Nothing counts as owed until the bill is posted.' }}</p></div>
@if (! $bill && Route::has('purchase-orders.index'))<div class="actions"><a class="btn" href="{{ route('purchase-orders.index', ['status' => 'RECEIVING']) }}">From a purchase order…</a></div>@endif</div>
<form method="POST" action="{{ $bill ? route('bills.update', $bill) : route('bills.store') }}" x-data="billLines(@js($startLines))">@csrf @if ($bill) @method('PUT') @endif
<input type="hidden" name="purchase_order_id" value="{{ old('purchase_order_id', $head['purchase_order_id'] ?? '') }}">
<div class="panel"><div class="panel-body form-grid">
    <label>Supplier<select name="supplier_id" required><option value="">—</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id', $head['supplier_id'] ?? null) === $s->id)>{{ $s->name }}</option>@endforeach</select></label>
    <label>Supplier's bill number<input name="supplier_bill_number" maxlength="100" value="{{ old('supplier_bill_number', $bill?->supplier_bill_number) }}"><span class="muted small">Needed to post. The same number cannot be posted twice for a supplier.</span></label>
    <label>Bill date<input type="date" name="bill_date" value="{{ old('bill_date', $bill?->bill_date?->toDateString() ?? today()->toDateString()) }}" required></label>
    <label>Due date<input type="date" name="due_date" value="{{ old('due_date', $bill?->due_date?->toDateString()) }}"><span class="muted small">Blank: from the terms, or {{ \App\Domains\Finance\Services\FinanceRules::dueDays() }} days</span></label>
    <label>Currency<input name="currency" maxlength="10" value="{{ old('currency', $currency) }}"></label>
    <label>Payment terms<input name="payment_terms" maxlength="100" value="{{ old('payment_terms', $bill?->payment_terms ?? ($head['payment_terms'] ?? '')) }}"></label>
</div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th style="width:38%">Description</th><th>Qty</th><th>Unit</th><th>Price</th><th>Tax %</th><th class="num">Amount</th><th></th></tr></thead><tbody>
<template x-for="(l, i) in lines" :key="i"><tr>
    <td><input :name="`lines[${i}][description]`" x-model="l.description" maxlength="300" style="width:100%"><input type="hidden" :name="`lines[${i}][purchase_order_line_id]`" :value="l.purchase_order_line_id ?? ''"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][quantity]`" x-model.number="l.quantity" style="width:90px"></td>
    <td><input :name="`lines[${i}][uom]`" x-model="l.uom" maxlength="20" style="width:70px"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][unit_price]`" x-model.number="l.unit_price" style="width:110px"></td>
    <td><input type="number" step="any" min="0" max="100" :name="`lines[${i}][tax_percent]`" x-model.number="l.tax_percent" style="width:70px"></td>
    <td class="num" x-text="money(net(l))"></td>
    <td><button type="button" class="btn btn-small" @click="lines.splice(i, 1)" x-show="lines.length > 1" aria-label="Remove line">✕</button></td>
</tr></template>
</tbody>
<tfoot><tr><td colspan="5" class="num muted">Subtotal</td><td class="num" x-text="money(subtotal)"></td><td></td></tr><tr><td colspan="5" class="num muted">Tax</td><td class="num" x-text="money(tax)"></td><td></td></tr><tr><th colspan="5" class="num">Total</th><th class="num" x-text="money(subtotal + tax)"></th><td></td></tr></tfoot></table></div>
<div class="panel-body"><button type="button" class="btn btn-small" @click="lines.push({description: '', quantity: 1, uom: '', unit_price: 0, tax_percent: lines[0]?.tax_percent ?? 0, purchase_order_line_id: null})">+ Add a line</button></div></div>
<div class="panel"><div class="panel-body"><label>Notes<textarea name="notes" rows="2" maxlength="2000">{{ old('notes', $bill?->notes) }}</textarea></label></div>
<div class="panel-body"><button class="btn btn-primary" type="submit">Save draft</button> <a class="btn" href="{{ $bill ? route('bills.show', $bill) : route('bills.index') }}">Cancel</a></div></div>
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
