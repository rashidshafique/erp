@extends('layouts.app')
@section('title', $receipt->receipt_number)
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Receipt {{ $receipt->receipt_number }}</h1><p>{{ $receipt->customer?->name }} · {{ $receipt->received_on->displayDate() }} @if ($receipt->method || $receipt->reference) · {{ trim($receipt->method.' '.$receipt->reference) }}@endif@if ($receipt->salesOrder) · advance for {{ $receipt->salesOrder->order_number }}@endif</p></div><div class="actions"><a class="btn" href="{{ route('receivables.statement', $receipt->customer_id) }}">Customer statement</a></div></div>
@if ($receipt->isVoided())<div class="alert alert-error">Voided {{ $receipt->voided_at->displayDate() }}: {{ $receipt->void_reason }}. It no longer settles anything.</div>@endif
@if ($receipt->cheque)<div class="alert alert-warning">Paid by post-dated cheque <a href="{{ route('cheques.show', $receipt->cheque) }}">{{ $receipt->cheque->cheque_number ?: 'without a number' }}</a> dated {{ $receipt->cheque->cheque_date->displayDate() }} · {{ mb_strtolower(\App\Domains\Finance\Models\Cheque::STATUSES[$receipt->cheque->status]) }}.</div>@endif
<div class="panel"><div class="panel-body" style="display:flex;gap:28px">@if ($receipt->account_id && ($into = \App\Domains\Finance\Services\PaymentAccounts::names()[$receipt->account_id] ?? null))<div><div class="muted small">Into</div><strong>{{ $into }}</strong></div>@endif<div><div class="muted small">Received</div><strong>{{ $m($receipt->amount) }}</strong></div><div><div class="muted small">On invoices</div>{{ $m($receipt->amount - $receipt->unallocated()) }}</div><div><div class="muted small">Left on account</div><strong>{{ $m($receipt->unallocated()) }}</strong></div></div>
@if ($receipt->notes)<div class="panel-body small">{{ $receipt->notes }}</div>@endif</div>
<div class="panel"><div class="panel-header"><h2>Paid invoices</h2></div><div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Date</th><th class="num">Applied</th><th class="num">Still owing</th><th>Status</th></tr></thead><tbody>
@forelse ($receipt->allocations as $a)<tr><td><a href="{{ route('invoices.show', $a->invoice) }}">{{ $a->invoice->invoice_number }}</a></td><td>{{ $a->invoice->invoice_date->displayShortDate() }}</td><td class="num">{{ $m($a->amount) }}</td><td class="num">{{ $m($a->invoice->balance()) }}</td><td>{{ $a->invoice->balance() > 0 ? 'Short-paid or part-paid' : 'Paid' }}</td></tr>@empty<tr><td colspan="5" class="muted">Not put against any invoice yet.</td></tr>@endforelse</tbody></table></div></div>
@if ($receipt->unallocated() > 0 && $open->isNotEmpty())
<form method="POST" action="{{ route('receipts.allocate', $receipt) }}">@csrf<div class="panel"><div class="panel-header"><h2>Put the rest on invoices</h2></div><div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Owing</th><th>Pay now</th></tr></thead><tbody>
@foreach ($open as $i)<tr><td><a href="{{ route('invoices.show', $i) }}">{{ $i->invoice_number }}</a></td><td>{{ $m($i->balance()) }}</td><td><input type="number" step="any" min="0" max="{{ $i->balance() }}" name="alloc[{{ $i->id }}]" style="width:130px"></td></tr>@endforeach</tbody></table></div><div class="panel-body"><button class="btn btn-primary">Save</button></div></div></form>
@endif
@foreach (app(\App\Domains\Core\ModuleRegistry::class)->panels('finance.document') as $panel)@include($panel, ['document' => $receipt])@endforeach
@include('attachments._panel', ['type' => 'receipt', 'id' => $receipt->id])
@endsection
