@extends('layouts.app')
@section('title', 'Invoicing & tax')
@section('content')
<div class="page-heading"><div><h1>Invoicing & tax</h1><p>Defaults for new invoices and the tax rates a line can carry.</p></div></div>
<form method="POST" action="{{ route('finance.settings.update') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-body form-grid">
    <label>Days to payment<input type="number" min="0" max="365" name="due_days" value="{{ old('due_days', $dueDays) }}" required><span class="muted small">Used when the customer's terms do not give a number of days.</span></label>
    <label>Currency on new invoices<input name="currency" maxlength="10" value="{{ old('currency', $currency) }}"></label>
    <label>Supplier bill matching<select name="bill_match">@foreach (\App\Domains\Finance\Services\FinanceRules::MATCH_OPTIONS as $k => $l)<option value="{{ $k }}" @selected(old('bill_match', $billMatch) === $k)>{{ $l }}</option>@endforeach</select><span class="muted small">Checks a bill's lines against the purchase order: not more than was received, not above the order price.</span></label>
    <label>Money paid in advance<select name="advances">@foreach (\App\Domains\Finance\Services\FinanceRules::ADVANCE_OPTIONS as $k => $l)<option value="{{ $k }}" @selected(old('advances', $advances) === $k)>{{ $l }}</option>@endforeach</select><span class="muted small">Customer receipts left on account and supplier payments left as advances. Money that names an order only goes on that order's invoices or bills.</span></label>
    <label>Matching tolerance (%)<input type="number" step="any" min="0" max="100" name="bill_tolerance" value="{{ old('bill_tolerance', $billTolerance) }}" required></label>
</div><div class="panel-body"><button class="btn btn-primary">Save</button></div></div></form>
<div class="panel"><div class="panel-header"><h2>Tax rates</h2></div><div class="table-wrap"><table><thead><tr><th>Name</th><th>Rate</th><th>Default</th><th>On</th><th></th></tr></thead><tbody>
@forelse ($rates as $r)<tr><td>{{ $r->name }}</td><td>{{ \App\Support\Formats::number($r->rate_percent, 2) }}%</td>
<td><input type="hidden" name="is_default" value="0" form="rate-{{ $r->id }}"><input type="checkbox" name="is_default" value="1" form="rate-{{ $r->id }}" @checked($r->is_default)></td><td><input type="hidden" name="is_active" value="0" form="rate-{{ $r->id }}"><input type="checkbox" name="is_active" value="1" form="rate-{{ $r->id }}" @checked($r->is_active)></td>
<td><form id="rate-{{ $r->id }}" method="POST" action="{{ route('finance.tax-rates.update', $r) }}">@csrf @method('PUT')<button class="btn btn-small">Save</button></form></td></tr>
@empty<tr><td colspan="5" class="muted">No tax rate yet. Invoice lines carry no tax until you add one.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('finance.tax-rates.store') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">@csrf
<input name="name" placeholder="Name, e.g. Sales tax" maxlength="80" required><input type="number" step="any" min="0" max="100" name="rate_percent" placeholder="Rate %" required style="width:110px"><label style="margin:0"><input type="checkbox" name="is_default" value="1"> default</label><button class="btn btn-primary">Add</button></form></div></div>
@endsection
