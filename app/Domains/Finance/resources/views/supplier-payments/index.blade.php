@extends('layouts.app')
@section('title', 'Supplier payments')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Supplier payments</h1><p>Money paid to suppliers and the bills it settled.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('supplier-payments.create') }}">+ Record a payment</a></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Payment</th><th>Supplier</th><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th><th>Not yet on a bill</th></tr></thead><tbody>
@forelse ($payments as $r)<tr><td><a href="{{ route('supplier-payments.show', $r) }}">{{ $r->payment_number }}</a></td><td>{{ $r->supplier?->name }}</td><td>{{ $r->paid_on->displayShortDate() }}</td><td>{{ $r->method }}</td><td>{{ $r->reference }}</td><td>{{ $m($r->amount) }}</td><td>@if ($r->unallocated() > 0)<strong>{{ $m($r->unallocated()) }}</strong>@endif</td></tr>
@empty<tr><td colspan="7" class="muted">No payment yet.</td></tr>@endforelse</tbody></table></div><div class="panel-body">{{ $payments->links() }}</div></div>
@endsection
