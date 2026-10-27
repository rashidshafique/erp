@extends('layouts.app')
@section('title', 'Record a tax certificate')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Record a tax certificate</h1><p>Pick the customer and the tax the certificate is for, then say how much of it each invoice carries. That part of the invoices is then settled.</p></div></div>
@error('certificate')<div class="alert alert-error">{{ $message }}</div>@enderror
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="customer">Customer</label><select id="customer" name="customer" onchange="this.form.tax && (this.form.tax.value = ''); this.form.submit()"><option value="">—</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected($customerId === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    @if ($customerId)
    <div class="field"><label for="tax">Tax</label><select id="tax" name="tax" onchange="this.form.submit()">@foreach ($taxes as $code => $t)<option value="{{ $code }}" @selected($taxCode === $code)>{{ $t['label'] }}{{ $t['authority'] ? ' · '.$t['authority'] : '' }}{{ $t['open'] > 0 ? ' · '.$m($t['open']).' waiting' : '' }}</option>@endforeach</select></div>
    @endif
</form></div></div>
@if ($customerId && $taxCode)
<form method="POST" action="{{ route('withholding-certificates.store') }}" x-data="{ alloc: {}, get used() { return Object.values(this.alloc).reduce((n, v) => n + (Number(v) || 0), 0); } }">@csrf
<input type="hidden" name="customer_id" value="{{ $customerId }}"><input type="hidden" name="tax_code" value="{{ $taxCode }}">
<div class="panel"><div class="panel-body form-grid">
    <label>Certificate date<input type="date" name="certificate_date" value="{{ old('certificate_date', today()->toDateString()) }}" required></label>
    <label>Their certificate number<input name="reference" value="{{ old('reference') }}" maxlength="100" required placeholder="Certificate or payment receipt number"></label>
</div></div>
<div class="panel"><div class="panel-header"><h2>Invoices it covers</h2></div>
<div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Date</th><th class="num">{{ $taxCode === \App\Domains\Finance\Models\WithholdingCertificate::OTHER ? 'Owing' : 'Withheld, not yet covered' }}</th><th>On this certificate</th></tr></thead><tbody>
@forelse ($invoices as $row)<tr><td><a href="{{ route('invoices.show', $row['invoice']) }}">{{ $row['invoice']->invoice_number }}</a></td><td>{{ $row['invoice']->invoice_date->displayShortDate() }}</td><td class="num">{{ $m($row['open']) }}</td>
<td><input type="number" step="any" min="0" max="{{ $row['open'] }}" name="alloc[{{ $row['invoice']->id }}]" x-model="alloc['{{ $row['invoice']->id }}']" @if ($taxCode !== \App\Domains\Finance\Models\WithholdingCertificate::OTHER) x-init="alloc['{{ $row['invoice']->id }}'] = {{ old('alloc.'.$row['invoice']->id, $row['open']) }}" @endif style="width:130px"></td></tr>
@empty<tr><td colspan="4" class="muted">No open invoice of this customer has this tax waiting for a certificate.</td></tr>@endforelse</tbody>
<tfoot><tr><td colspan="3" class="num muted">Certificate amount</td><td x-text="used.toFixed(2)"></td></tr></tfoot></table></div>
<div class="panel-body"><label>Notes<textarea name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea></label></div>
<div class="panel-body"><button class="btn btn-primary" type="submit" @if ($invoices->isEmpty()) disabled @endif>Record certificate</button></div></div>
</form>
@endif
@endsection
