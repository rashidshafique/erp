@extends('layouts.app')
@section('title', $receipt->receipt_number)
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Receipt {{ $receipt->receipt_number }}</h1><p>{{ $receipt->customer?->name }} · {{ $receipt->received_on->displayDate() }} · {{ $receipt->method }} {{ $receipt->reference }}</p></div><div class="actions"><a class="btn" href="{{ route('receivables.statement', $receipt->customer_id) }}">Customer statement</a></div></div>
<div class="panel"><div class="panel-body" style="display:flex;gap:28px"><div><div class="muted small">Received</div><strong>{{ $m($receipt->amount) }}</strong></div><div><div class="muted small">On invoices</div>{{ $m($receipt->amount - $receipt->unallocated()) }}</div><div><div class="muted small">Left on account</div><strong>{{ $m($receipt->unallocated()) }}</strong></div></div>
@if ($receipt->notes)<div class="panel-body small">{{ $receipt->notes }}</div>@endif</div>
<div class="panel"><div class="panel-header"><h2>Paid invoices</h2></div><div class="table-wrap"><table><thead><tr><th>Invoice</th><th class="num">Amount</th></tr></thead><tbody>
@forelse ($receipt->allocations as $a)<tr><td><a href="{{ route('invoices.show', $a->invoice) }}">{{ $a->invoice->invoice_number }}</a></td><td class="num">{{ $m($a->amount) }}</td></tr>@empty<tr><td colspan="2" class="muted">Not put against any invoice yet.</td></tr>@endforelse</tbody></table></div></div>
@if ($receipt->unallocated() > 0 && $open->isNotEmpty())
<form method="POST" action="{{ route('receipts.allocate', $receipt) }}">@csrf<div class="panel"><div class="panel-header"><h2>Put the rest on invoices</h2></div><div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Owing</th><th>Pay now</th></tr></thead><tbody>
@foreach ($open as $i)<tr><td><a href="{{ route('invoices.show', $i) }}">{{ $i->invoice_number }}</a></td><td>{{ $m($i->balance()) }}</td><td><input type="number" step="0.01" min="0" max="{{ $i->balance() }}" name="alloc[{{ $i->id }}]" style="width:130px"></td></tr>@endforeach</tbody></table></div><div class="panel-body"><button class="btn btn-primary">Save</button></div></div></form>
@endif
@include('attachments._panel', ['type' => 'receipt', 'id' => $receipt->id])
@endsection
