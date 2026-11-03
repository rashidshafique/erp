@extends('layouts.app')
@section('title', 'Invoicing')
@section('content')
<div class="page-heading"><div><h1>Invoicing</h1><p>Defaults for invoices and bills, matching, credit and advances. Tax and year end are under <a href="{{ route('finance.tax-year') }}">Tax &amp; year end</a>.</p></div></div>
<form method="POST" action="{{ route('finance.settings.update') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-body form-grid">
    <label>Days to payment<input type="number" min="0" max="365" name="due_days" value="{{ old('due_days', $dueDays) }}" required><span class="muted small">When the customer's terms give no days.</span></label>
    <label>Print layout for invoices<select name="invoice_template">@foreach ($templates as $k => $l)<option value="{{ $k }}" @selected(old('invoice_template', $template) === $k)>{{ $l }}</option>@endforeach</select><span class="muted small">An invoice can pick another on its page.</span></label>
    <label>Currency on new invoices<input name="currency" maxlength="10" value="{{ old('currency', $currency) }}"></label>
    <label>Supplier bill matching<select name="bill_match">@foreach (\App\Domains\Finance\Services\FinanceRules::MATCH_OPTIONS as $k => $l)<option value="{{ $k }}" @selected(old('bill_match', $billMatch) === $k)>{{ $l }}</option>@endforeach</select><span class="muted small">Bill lines vs the order: not over received, not over price.</span></label>
    <label>Credit limits<select name="credit_check">@foreach (\App\Domains\Finance\Services\FinanceRules::CREDIT_OPTIONS as $k => $l)<option value="{{ $k }}" @selected(old('credit_check', $creditCheck) === $k)>{{ $l }}</option>@endforeach</select><span class="muted small">Limits and holds are set on each customer. A hold always stops.</span></label>
    <label><span style="display:flex;gap:10px;align-items:center"><input type="hidden" name="credit_orders" value="0"><input class="switch" type="checkbox" name="credit_orders" value="1" @checked(old('credit_orders', $creditOrders))> Open orders count towards the limit</span><span class="muted small">Their value not yet invoiced, before tax.</span></label>
    <label>Overdue days before a customer is held<input type="number" min="0" max="365" name="credit_overdue_days" value="{{ old('credit_overdue_days', $creditOverdueDays) }}"><span class="muted small">0: overdue invoices are not checked.</span></label>
    <label>Billed price and lot cost<select name="bill_cost">@foreach (\App\Domains\Finance\Services\FinanceRules::BILL_COST_OPTIONS as $k => $l)<option value="{{ $k }}" @selected(old('bill_cost', $billCost) === $k)>{{ $l }}</option>@endforeach</select><span class="muted small">When a bill price differs from the order. Used stock keeps its cost.</span></label>
    <label>Money paid in advance<select name="advances">@foreach (\App\Domains\Finance\Services\FinanceRules::ADVANCE_OPTIONS as $k => $l)<option value="{{ $k }}" @selected(old('advances', $advances) === $k)>{{ $l }}</option>@endforeach</select><span class="muted small">Receipts on account and supplier advances. Money for an order only goes on that order.</span></label>
    <label>Matching tolerance (%)<input type="number" step="any" min="0" max="100" name="bill_tolerance" value="{{ old('bill_tolerance', $billTolerance) }}" required></label>
</div><div class="panel-body"><button class="btn btn-primary">Save</button></div></div></form>
@endsection
