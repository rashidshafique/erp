@extends('layouts.app')
@section('title', 'Record a receipt')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Record a receipt</h1><p>Pick the customer to see what they owe, then say how much of the money pays each invoice. Anything left stays on account.</p></div></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters"><div class="field"><label for="customer">Customer</label><select id="customer" name="customer" onchange="this.form.submit()"><option value="">—</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected($customerId === $c->id)>{{ $c->name }}</option>@endforeach</select></div></form></div></div>
@if ($customerId)
<form method="POST" action="{{ route('receipts.store') }}" x-data="{ amount: {{ (float) old('amount', 0) }}, alloc: {}, get used() { return Object.values(this.alloc).reduce((n, v) => n + (Number(v) || 0), 0); },
    fill() { let left = Number(this.amount) || 0; document.querySelectorAll('[data-owing]').forEach(el => { const take = Math.min(left, Number(el.dataset.owing)); this.alloc[el.dataset.id] = take > 0 ? take.toFixed(2) : ''; left -= take; }); } }">@csrf
<input type="hidden" name="customer_id" value="{{ $customerId }}">
<div class="panel"><div class="panel-body form-grid">
    <label>Date received<input type="date" name="received_on" value="{{ old('received_on', today()->toDateString()) }}" required></label>
    <label>Amount<input type="number" step="0.01" min="0.01" name="amount" x-model.number="amount" required></label>
    <label>Method<input name="method" list="methods" value="{{ old('method') }}" maxlength="30"><datalist id="methods"><option>Bank transfer</option><option>Cheque</option><option>Cash</option></datalist></label>
    <label>Reference<input name="reference" value="{{ old('reference') }}" maxlength="100" placeholder="Cheque or transfer number"></label>
</div></div>
<div class="panel"><div class="panel-header"><h2>Invoices to pay</h2><button type="button" class="btn btn-small" style="margin-left:auto" @click="fill()">Fill oldest first</button></div>
<div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th>Owing</th><th>Pay now</th></tr></thead><tbody>
@forelse ($open as $i)<tr><td><a href="{{ route('invoices.show', $i) }}">{{ $i->invoice_number }}</a></td><td>{{ $i->invoice_date->displayShortDate() }}</td><td @if ($i->isOverdue()) style="color:var(--danger)" @endif>{{ $i->due_date?->displayShortDate() }}</td><td data-owing="{{ $i->balance() }}" data-id="{{ $i->id }}">{{ $m($i->balance()) }}</td>
<td><input type="number" step="0.01" min="0" max="{{ $i->balance() }}" name="alloc[{{ $i->id }}]" x-model="alloc['{{ $i->id }}']" @if ($invoiceId === $i->id) x-init="alloc['{{ $i->id }}'] = {{ $i->balance() }}; if (!amount) amount = {{ $i->balance() }}" @endif style="width:130px"></td></tr>
@empty<tr><td colspan="5" class="muted">This customer owes nothing; the whole receipt will stay on account.</td></tr>@endforelse</tbody>
<tfoot><tr><td colspan="4" class="num muted">Put on invoices</td><td x-text="used.toFixed(2)"></td></tr><tr><td colspan="4" class="num muted">Left on account</td><td x-text="((Number(amount) || 0) - used).toFixed(2)"></td></tr></tfoot></table></div>
<div class="panel-body"><label>Notes<textarea name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea></label></div>
<div class="panel-body"><button class="btn btn-primary" type="submit">Record receipt</button></div></div>
</form>
@endif
@endsection
