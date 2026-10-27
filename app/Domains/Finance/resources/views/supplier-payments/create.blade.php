@extends('layouts.app')
@section('title', 'Record a payment')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Record a payment</h1><p>Pick the supplier to see what we owe them, then say how much of the payment settles each bill. Anything left stays as an advance.</p></div></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters"><div class="field"><label for="supplier">Supplier</label><select id="supplier" name="supplier" onchange="this.form.submit()"><option value="">—</option>@foreach ($suppliers as $c)<option value="{{ $c->id }}" @selected($supplierId === $c->id)>{{ $c->name }}</option>@endforeach</select></div></form></div></div>
@if ($supplierId)
<form method="POST" action="{{ route('supplier-payments.store') }}" x-data="{ amount: {{ (float) old('amount', 0) }}, alloc: {}, get used() { return Object.values(this.alloc).reduce((n, v) => n + (Number(v) || 0), 0); },
    fill() { let left = Number(this.amount) || 0; document.querySelectorAll('[data-owing]').forEach(el => { const take = Math.min(left, Number(el.dataset.owing)); this.alloc[el.dataset.id] = take > 0 ? take.toFixed(2) : ''; left -= take; }); } }">@csrf
<input type="hidden" name="supplier_id" value="{{ $supplierId }}">
<div class="panel"><div class="panel-body form-grid">
    <label>Date paid<input type="date" name="paid_on" value="{{ old('paid_on', today()->toDateString()) }}" required></label>
    <label>Amount paid<input type="number" step="any" min="0" name="amount" x-model.number="amount" required></label>
    <label>Method<input name="method" list="methods" value="{{ old('method') }}" maxlength="30"><datalist id="methods"><option>Bank transfer</option><option>Cheque</option><option>Cash</option></datalist></label>
    <label>Reference<input name="reference" value="{{ old('reference') }}" maxlength="100" placeholder="Cheque or transfer number"></label>
    @if ($orders->isNotEmpty())<label>Advance for purchase order<select name="purchase_order_id"><option value="">— any bill —</option>@foreach ($orders as $o)<option value="{{ $o->id }}" @selected(old('purchase_order_id') === $o->id)>{{ $o->po_number ?? 'Draft order' }} · {{ $o->order_date?->displayShortDate() }}</option>@endforeach</select><span class="muted small">What is left as an advance then only goes on this order's bills.</span></label>@endif
</div></div>
<div class="panel"><div class="panel-header"><h2>Bills to pay</h2><button type="button" class="btn btn-small" style="margin-left:auto" @click="fill()">Fill oldest first</button></div>
<div class="table-wrap"><table><thead><tr><th>Bill</th><th>Date</th><th>Due</th><th>Owing</th><th>Pay now</th></tr></thead><tbody>
@forelse ($open as $i)<tr><td><a href="{{ route('bills.show', $i) }}">{{ $i->bill_number }}</a></td><td>{{ $i->bill_date->displayShortDate() }}</td><td @if ($i->isOverdue()) style="color:var(--danger)" @endif>{{ $i->due_date?->displayShortDate() }}</td><td data-owing="{{ $i->balance() }}" data-id="{{ $i->id }}">{{ $m($i->balance()) }}</td>
<td><input type="number" step="any" min="0" max="{{ $i->balance() }}" name="alloc[{{ $i->id }}]" x-model="alloc['{{ $i->id }}']" @if ($billId === $i->id) x-init="alloc['{{ $i->id }}'] = {{ $i->balance() }}; if (!amount) amount = {{ $i->balance() }}" @endif style="width:130px"></td></tr>
@empty<tr><td colspan="5" class="muted">We owe this supplier nothing; the whole payment will stay as an advance.</td></tr>@endforelse</tbody>
<tfoot><tr><td colspan="4" class="num muted">Put on bills</td><td x-text="used.toFixed(window.decimalPlaces)"></td></tr><tr><td colspan="4" class="num muted">Left as an advance</td><td x-text="((Number(amount) || 0) - used).toFixed(2)"></td></tr></tfoot></table></div>
<div class="panel-body"><label>Notes<textarea name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea></label></div>
<div class="panel-body"><button class="btn btn-primary" type="submit">Record payment</button></div></div>
</form>
@endif
@endsection
