@extends('layouts.app')
@section('title', 'Tax & year end')
@section('content')
@php $registry = app(\App\Domains\Core\ModuleRegistry::class); $year = \App\Support\FinancialYear::containing(); @endphp
<div class="page-heading"><div><h1>Tax &amp; year end</h1><p>The financial year, closing the books, the tax rates an invoice line can carry, and the country's taxes, registrations and rules.</p></div>
<div class="actions"><a class="btn" href="{{ route('finance.settings') }}">Invoicing settings</a></div></div>
@if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
<div class="panel"><div class="panel-header"><h2>Financial year</h2></div>
<form method="POST" action="{{ route('finance.tax-year.update') }}">@csrf @method('PUT')<div class="panel-body form-grid">
    <label>The year starts in<select name="year_start_month">@foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected($startMonth === $m)>{{ \Illuminate\Support\Carbon::create(2000, $m, 1)->format('F') }}</option>@endforeach</select>
        <span class="muted small">The current year is {{ \App\Support\FinancialYear::label($year) }} ({{ $year['start']->displayDate() }} to {{ $year['end']->displayDate() }}). Profit and loss, account ledgers and the statements open on it.</span></label>
</div><div class="panel-body"><button class="btn btn-primary">Save</button></div></form></div>
@foreach ($registry->panels('finance.tax-year') as $panel)@if ($registry->isEnabled(strstr($panel, '::', true)))@include($panel)@endif @endforeach
<div class="panel"><div class="panel-header"><h2>Tax rates</h2></div><div class="table-wrap"><table><thead><tr><th>Name</th><th>Rate</th><th>Default</th><th>On</th><th></th></tr></thead><tbody>
@forelse ($rates as $r)<tr><td>{{ $r->name }}</td><td>{{ \App\Support\Formats::number($r->rate_percent, 2) }}%</td>
<td><input type="hidden" name="is_default" value="0" form="rate-{{ $r->id }}"><input type="checkbox" name="is_default" value="1" form="rate-{{ $r->id }}" @checked($r->is_default)></td><td><input type="hidden" name="is_active" value="0" form="rate-{{ $r->id }}"><input type="checkbox" name="is_active" value="1" form="rate-{{ $r->id }}" @checked($r->is_active)></td>
<td><form id="rate-{{ $r->id }}" method="POST" action="{{ route('finance.tax-rates.update', $r) }}">@csrf @method('PUT')<button class="btn btn-small">Save</button></form></td></tr>
@empty<tr><td colspan="5" class="muted">No tax rate yet. Invoice lines carry no tax until you add one.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('finance.tax-rates.store') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">@csrf
<input name="name" placeholder="Name, e.g. Sales tax" maxlength="80" required><input type="number" step="any" min="0" max="100" name="rate_percent" placeholder="Rate %" required style="width:110px"><label style="margin:0"><input type="checkbox" name="is_default" value="1"> default</label><button class="btn btn-primary">Add</button></form></div></div>
@endsection
