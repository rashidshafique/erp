@extends('layouts.app')
@section('title', $list->code)
@section('content')

@php
    $rows = old('items', $list->items->map(fn ($i) => ['product_id' => $i->product_id, 'basis' => $i->basis, 'min_quantity' => rtrim(rtrim((string) $i->min_quantity, '0'), '.') ?: '0', 'price' => (float) $i->price])->values()->all());
    $blank = ['product_id' => '', 'basis' => 'PIECE', 'min_quantity' => '0', 'price' => ''];
@endphp
<div class="page-heading"><div><h1>{{ $list->name }}</h1><p><span class="mono">{{ $list->code }}</span> · {{ $list->is_default ? 'Default list' : $list->customers->count().' customer(s)' }} · {{ $list->is_active ? 'On' : 'Off' }}@if ($list->valid_from || $list->valid_to) · {{ $list->valid_from?->displayDate() ?? 'always' }} to {{ $list->valid_to?->displayDate() ?? 'open' }}@endif</p></div>
<div class="actions"><a class="btn" href="{{ route('price-lists.index') }}">All lists</a></div></div>
@error('items')<div class="alert alert-error">{{ $message }}</div>@enderror
<form method="POST" action="{{ route('price-lists.items', $list) }}" x-data="{ rows: @js(array_values($rows) ?: [$blank]) }">@csrf @method('PUT')
<div class="panel"><div class="panel-header"><h2>Prices</h2><span class="muted small">A break applies from its quantity up: pieces for a price per piece, kg for a price per kg. 0 is the base price.</span></div>
<div class="table-wrap"><table><thead><tr><th style="width:45%">Product</th><th>Price</th><th></th><th>From quantity</th><th></th></tr></thead><tbody>
<template x-for="(r, i) in rows" :key="i"><tr>
    <td><select :name="`items[${i}][product_id]`" x-model="r.product_id" style="width:100%"><option value="">Select product</option>@foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->code }} · {{ $p->name }}</option>@endforeach</select></td>
    <td><input type="number" step="any" min="0" :name="`items[${i}][price]`" x-model="r.price" style="width:120px"></td>
    <td><select :name="`items[${i}][basis]`" x-model="r.basis">@foreach (\App\Domains\Sales\Models\PriceListItem::BASES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></td>
    <td><input type="number" step="any" min="0" :name="`items[${i}][min_quantity]`" x-model="r.min_quantity" style="width:100px"></td>
    <td><button type="button" class="btn btn-small" @click="rows.splice(i, 1)" aria-label="Remove">✕</button></td>
</tr></template></tbody></table></div>
<div class="panel-body"><button type="button" class="btn btn-small" @click="rows.push(@js($blank))">+ Add a price</button> <button class="btn btn-primary">Save prices</button></div></div>
</form>
<div class="panel"><div class="panel-header"><h2>Customers</h2></div><div class="panel-body">
@if ($list->is_default)<p class="muted small">Every customer without a list of its own buys from this one.</p>@endif
@foreach ($list->customers as $c)<form method="POST" action="{{ route('price-lists.assign', $c) }}" style="display:flex;gap:8px;align-items:center;margin-bottom:6px">@csrf @method('PUT')<input type="hidden" name="price_list_id" value=""><span>{{ $c->name }}</span><button class="btn btn-small">Take off</button></form>@endforeach
@if ($customers->isNotEmpty())
<form method="POST" x-data="{ customer: '' }" :action="customer ? '{{ url('price-lists/customers') }}/' + customer : '#'" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">@csrf @method('PUT')<input type="hidden" name="price_list_id" value="{{ $list->id }}">
<select x-model="customer" required><option value="">Give a customer this list…</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select><button class="btn">Add</button></form>
@endif
</div></div>
<div class="panel"><div class="panel-header"><h2>List details</h2></div><form method="POST" action="{{ route('price-lists.update', $list) }}">@csrf @method('PUT')<div class="panel-body form-grid">
    <label>Code<input name="code" maxlength="40" required value="{{ old('code', $list->code) }}"></label>
    <label>Name<input name="name" maxlength="120" required value="{{ old('name', $list->name) }}"></label>
    <label>Currency<input name="currency" maxlength="10" value="{{ old('currency', $list->currency) }}"></label>
    <label>Valid from<input type="date" name="valid_from" value="{{ old('valid_from', $list->valid_from?->toDateString()) }}"></label>
    <label>Valid to<input type="date" name="valid_to" value="{{ old('valid_to', $list->valid_to?->toDateString()) }}"></label>
    <label><span><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($list->is_active)> On</span></label>
    <label><span><input type="hidden" name="is_default" value="0"><input type="checkbox" name="is_default" value="1" @checked($list->is_default)> Default list</span></label>
    <label class="field-full">Notes<textarea name="notes" rows="2" maxlength="2000">{{ old('notes', $list->notes) }}</textarea></label>
</div><div class="panel-body"><button class="btn btn-primary">Save</button></div></form></div>
<div class="panel"><div class="panel-header"><h2>Copy for a price change</h2><span class="muted small">A new list with every price moved by a percent, switched off until you check it.</span></div>
<form method="POST" action="{{ route('price-lists.copy', $list) }}">@csrf<div class="panel-body form-grid">
    <label>New code<input name="code" maxlength="40" required></label><label>Name<input name="name" maxlength="120" required value="{{ $list->name }}"></label>
    <label>Change (%)<input type="number" step="any" name="percent" value="0"></label><label>Valid from<input type="date" name="valid_from"></label>
</div><div class="panel-body"><button class="btn">Copy</button></div></form></div>
<form method="POST" action="{{ route('price-lists.destroy', $list) }}">@csrf @method('DELETE')<button class="btn" onclick="return confirm('Delete this price list? Its customers fall back to the default list.')">Delete list</button></form>
@endsection
