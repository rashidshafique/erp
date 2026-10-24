@extends('layouts.app')
@section('title', $order ? 'Edit draft purchase order' : 'New purchase order')
@section('content')
@php
    $startLines = old('lines', $lines);
    $matJson = $materials->mapWithKeys(fn ($x) => [$x->id => ['name' => $x->code.' '.$x->name, 'uom' => $x->base_uom, 'price' => (float) $x->standard_cost_per_uom]])->all();
@endphp
<div class="page-heading"><div><h1>{{ $order ? 'Edit draft purchase order' : 'New purchase order' }}</h1><p>Pick a material to fill the description, unit and a starting price, or type a free line. Nothing counts until the order is issued.</p></div></div>
<form method="POST" action="{{ $order ? route('purchase-orders.update', $order) : route('purchase-orders.store') }}" x-data="poLines(@js($startLines), @js($matJson))">@csrf @if ($order) @method('PUT') @endif
<div class="panel"><div class="panel-body form-grid">
    <label>Supplier<select name="supplier_id" required><option value="">—</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id', $head['supplier_id'] ?? null) === $s->id)>{{ $s->name }}</option>@endforeach</select></label>
    <label>Order date<input type="date" name="order_date" value="{{ old('order_date', $order?->order_date?->toDateString() ?? today()->toDateString()) }}" required></label>
    <label>Expected on<input type="date" name="expected_date" value="{{ old('expected_date', $order?->expected_date?->toDateString()) }}"></label>
    <label>Currency<input name="currency" maxlength="10" value="{{ old('currency', $order?->currency ?? \App\Support\Formats::all()['currency']) }}"></label>
    <label>Payment terms<input name="payment_terms" maxlength="100" value="{{ old('payment_terms', $order?->payment_terms) }}" placeholder="Net 30"></label>
    <label>Supplier reference<input name="supplier_reference" maxlength="100" value="{{ old('supplier_reference', $order?->supplier_reference) }}"></label>
</div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Material</th><th style="width:28%">Description</th><th>Qty</th><th>Unit</th><th>Price</th><th>Tax %</th><th class="num">Amount</th><th></th></tr></thead><tbody>
<template x-for="(l, i) in lines" :key="i"><tr>
    <td><select :name="`lines[${i}][material_id]`" x-model="l.material_id" @change="pick(l)"><option value="">Free line</option>@foreach ($materials as $x)<option value="{{ $x->id }}">{{ $x->code }} {{ $x->name }}</option>@endforeach</select></td>
    <td><input :name="`lines[${i}][description]`" x-model="l.description" maxlength="300" style="width:100%"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][ordered_qty]`" x-model.number="l.ordered_qty" style="width:90px"></td>
    <td><input :name="`lines[${i}][uom]`" x-model="l.uom" maxlength="20" style="width:70px"></td>
    <td><input type="number" step="any" min="0" :name="`lines[${i}][unit_price]`" x-model.number="l.unit_price" style="width:110px"></td>
    <td><input type="number" step="any" min="0" max="100" :name="`lines[${i}][tax_percent]`" x-model.number="l.tax_percent" style="width:70px"></td>
    <td class="num" x-text="money(net(l))"></td>
    <td><button type="button" class="btn btn-small" @click="lines.splice(i, 1)" x-show="lines.length > 1" aria-label="Remove line">✕</button></td>
</tr></template>
</tbody>
<tfoot><tr><td colspan="6" class="num muted">Subtotal</td><td class="num" x-text="money(subtotal)"></td><td></td></tr><tr><td colspan="6" class="num muted">Tax</td><td class="num" x-text="money(tax)"></td><td></td></tr><tr><th colspan="6" class="num">Total</th><th class="num" x-text="money(subtotal + tax)"></th><td></td></tr></tfoot></table></div>
<div class="panel-body"><button type="button" class="btn btn-small" @click="lines.push({material_id: '', description: '', ordered_qty: 1, uom: '', unit_price: 0, tax_percent: lines[0]?.tax_percent ?? 0})">+ Add a line</button></div></div>
<div class="panel"><div class="panel-body"><label>Notes to the supplier<textarea name="notes" rows="2" maxlength="2000">{{ old('notes', $order?->notes) }}</textarea></label></div>
<div class="panel-body"><button class="btn btn-primary" type="submit">Save draft</button> <a class="btn" href="{{ $order ? route('purchase-orders.show', $order) : route('purchase-orders.index') }}">Cancel</a></div></div>
</form>
<script>
function poLines(start, materials) {
    return {
        lines: start.map(l => ({ ...l, material_id: l.material_id ?? '', tax_percent: l.tax_percent ?? 0 })),
        pick(l) { const m = materials[l.material_id]; if (m) { l.description = m.name; l.uom = m.uom; if (!l.unit_price) l.unit_price = m.price || 0; } },
        net(l) { return Math.round((Number(l.ordered_qty) || 0) * (Number(l.unit_price) || 0) * 100) / 100; },
        lineTax(l) { return Math.round(this.net(l) * (Number(l.tax_percent) || 0)) / 100; },
        get subtotal() { return this.lines.reduce((n, l) => n + this.net(l), 0); },
        get tax() { return this.lines.reduce((n, l) => n + this.lineTax(l), 0); },
        money(v) { return (Math.round(v * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endsection
