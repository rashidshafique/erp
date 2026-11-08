@extends('layouts.app')
@section('title', 'Supplier bills')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::money($v); @endphp
<div class="page-heading"><div><h1>Supplier bills</h1><p>Supplier bills and credit notes. Enter one from a purchase order or on its own.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('bills.create') }}">+ New bill</a></div></div>
<div class="panel"><div class="panel-body" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px">
    <div><div class="muted small">Owed to suppliers</div><strong style="font-size:22px">{{ $m($summary['owed']) }}</strong><div class="muted small">{{ $summary['owed_count'] }} open {{ \Illuminate\Support\Str::plural('bill', $summary['owed_count']) }}</div></div>
    <div><div class="muted small">Due in 7 days</div><strong style="font-size:22px">{{ $m($summary['soon']) }}</strong><div class="muted small">{{ $summary['soon_count'] }} {{ \Illuminate\Support\Str::plural('bill', $summary['soon_count']) }}</div></div>
    <div><div class="muted small">Overdue</div><strong style="font-size:22px;color:var(--danger)">{{ $m($summary['overdue']) }}</strong><div class="muted small">{{ $summary['overdue_count'] }} {{ \Illuminate\Support\Str::plural('bill', $summary['overdue_count']) }}</div></div>
    <div><div class="muted small">Drafts</div><strong style="font-size:22px">{{ $summary['drafts'] }}</strong><div class="muted small"><a href="{{ route('bills.index', ['status' => 'DRAFT']) }}">not yet posted</a></div></div>
</div></div>
<div class="panel"><div class="panel-body">
    <form method="GET" class="filters">
        <div class="field"><label for="supplier">Supplier</label><select id="supplier" name="supplier" onchange="this.form.submit()"><option value="">All suppliers</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($supplierId === $s->id)>{{ $s->name }} ({{ $s->code }})</option>@endforeach</select></div>
        <div class="field"><label for="status">Show</label><select id="status" name="status" onchange="this.form.submit()">@foreach (['' => 'Everything', 'DRAFT' => 'Drafts', 'POSTED' => 'Posted', 'OPEN' => 'Unpaid', 'OVERDUE' => 'Overdue', 'VOID' => 'Voided'] as $k => $l)<option value="{{ $k }}" @selected(($status ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
        @if ($supplierId || $status)<a class="btn" href="{{ route('bills.index') }}">Reset</a>@endif
    </form>
</div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Supplier</th><th>Number</th><th>Their number</th><th>Due</th><th>Total</th><th>Owing</th><th>Status</th></tr></thead><tbody>
@forelse ($bills as $b)
<tr><td>{{ $b->bill_date->displayShortDate() }}</td><td>{{ $b->supplier?->name }}</td><td><a href="{{ route('bills.show', $b) }}">{{ $b->bill_number ?? 'Draft' }}</a>@if ($b->isCreditNote())<div class="muted small">credit note</div>@endif</td><td>{{ $b->supplier_bill_number }}</td><td>{{ $b->due_date?->displayShortDate() }}</td><td>{{ $m($b->total) }}</td>
<td>@if ($b->balance() > 0)<span @if ($b->isOverdue()) style="color:var(--danger);font-weight:650" @endif>{{ $m($b->balance()) }}</span>@endif</td>
<td><span class="status {{ $b->status === 'POSTED' ? 'status-completed' : ($b->status === 'VOID' ? 'status-cancelled' : 'status-on-hold') }}">{{ $b->status === 'POSTED' && $b->balance() <= 0 && ! $b->isCreditNote() ? 'PAID' : $b->status }}</span></td></tr>
@empty<tr><td colspan="8" class="muted">No supplier bill yet.</td></tr>@endforelse</tbody></table></div>
@if (method_exists($bills, 'links'))<div class="panel-body">{{ $bills->links() }}</div>@endif</div>
@endsection
