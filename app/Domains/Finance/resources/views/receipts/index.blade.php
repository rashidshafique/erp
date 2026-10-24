@extends('layouts.app')
@section('title', 'Receipts')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Receipts</h1><p>Money received from customers and the invoices it paid.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('receipts.create') }}">+ Record a receipt</a></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Customer</th><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th><th>Not yet on an invoice</th></tr></thead><tbody>
@forelse ($receipts as $r)<tr><td><a href="{{ route('receipts.show', $r) }}">{{ $r->receipt_number }}</a></td><td>{{ $r->customer?->name }}</td><td>{{ $r->received_on->displayShortDate() }}</td><td>{{ $r->method }}</td><td>{{ $r->reference }}</td><td>{{ $m($r->amount) }}</td><td>@if ($r->unallocated() > 0)<strong>{{ $m($r->unallocated()) }}</strong>@endif</td></tr>
@empty<tr><td colspan="7" class="muted">No receipt yet.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $receipts->links() }}</div></div>
@endsection
