@extends('layouts.app')
@section('title', 'Invoices')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::money($v); @endphp
<div class="page-heading"><div><h1>Invoices</h1><p>Sales invoices and credit notes. Raise one from a dispatch, from a sales order, or on its own.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('invoices.create') }}">+ New invoice</a></div></div>
<div class="panel"><div class="panel-body">
    <form method="GET" class="filters">
        <div class="field"><label for="customer">Customer</label><select id="customer" name="customer" onchange="this.form.submit()"><option value="">All customers</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected($customerId === $c->id)>{{ $c->name }} ({{ $c->code }})</option>@endforeach</select></div>
        <div class="field"><label for="status">Show</label><select id="status" name="status" onchange="this.form.submit()">@foreach (['' => 'Everything', 'DRAFT' => 'Drafts', 'ISSUED' => 'Issued', 'OPEN' => 'Unpaid', 'OVERDUE' => 'Overdue', 'VOID' => 'Voided'] as $k => $l)<option value="{{ $k }}" @selected(($status ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
        @if ($customerId || $status)<a class="btn" href="{{ route('invoices.index') }}">Reset</a>@endif
    </form>
</div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Number</th><th>Customer</th><th>Date</th><th>Due</th><th>Total</th><th>Owing</th><th>Status</th></tr></thead><tbody>
@forelse ($invoices as $i)
<tr><td><a href="{{ route('invoices.show', $i) }}">{{ $i->invoice_number ?? 'Draft' }}</a>@if ($i->isCreditNote())<div class="muted small">credit note</div>@endif</td><td>{{ $i->customer?->name }}</td><td>{{ $i->invoice_date->displayShortDate() }}</td><td>{{ $i->due_date?->displayShortDate() }}</td><td>{{ $m($i->total) }}</td>
<td>@if ($i->balance() > 0)<span @if ($i->isOverdue()) style="color:var(--danger);font-weight:650" @endif>{{ $m($i->balance()) }}</span>@endif</td>
<td><span class="status {{ $i->status === 'ISSUED' ? 'status-completed' : ($i->status === 'VOID' ? 'status-cancelled' : 'status-on-hold') }}">{{ $i->status === 'ISSUED' && $i->balance() <= 0 && ! $i->isCreditNote() ? 'PAID' : $i->status }}</span></td></tr>
@empty<tr><td colspan="7" class="muted">No invoice yet.</td></tr>@endforelse</tbody></table></div>
@if (method_exists($invoices, 'links'))<div class="panel-body">{{ $invoices->links() }}</div>@endif</div>
@endsection
