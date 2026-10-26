@extends('layouts.app')
@section('title', $payment->payment_number)
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Payment {{ $payment->payment_number }}</h1><p>{{ $payment->supplier?->name }} · {{ $payment->paid_on->displayDate() }} · {{ $payment->method }} {{ $payment->reference }}</p></div><div class="actions"><a class="btn" href="{{ route('payables.statement', $payment->supplier_id) }}">Supplier statement</a></div></div>
<div class="panel"><div class="panel-body" style="display:flex;gap:28px"><div><div class="muted small">Paid</div><strong>{{ $m($payment->amount) }}</strong></div><div><div class="muted small">On bills</div>{{ $m($payment->amount - $payment->unallocated()) }}</div><div><div class="muted small">Left as an advance</div><strong>{{ $m($payment->unallocated()) }}</strong></div></div>
@if ($payment->notes)<div class="panel-body small">{{ $payment->notes }}</div>@endif</div>
<div class="panel"><div class="panel-header"><h2>Settled bills</h2></div><div class="table-wrap"><table><thead><tr><th>Bill</th><th class="num">Amount</th></tr></thead><tbody>
@forelse ($payment->allocations as $a)<tr><td><a href="{{ route('bills.show', $a->bill) }}">{{ $a->bill->bill_number }}</a></td><td class="num">{{ $m($a->amount) }}</td></tr>@empty<tr><td colspan="2" class="muted">Not put against any bill yet.</td></tr>@endforelse</tbody></table></div></div>
@if ($payment->unallocated() > 0 && $open->isNotEmpty())
<form method="POST" action="{{ route('supplier-payments.allocate', $payment) }}">@csrf<div class="panel"><div class="panel-header"><h2>Put the rest on bills</h2></div><div class="table-wrap"><table><thead><tr><th>Bill</th><th>Owing</th><th>Pay now</th></tr></thead><tbody>
@foreach ($open as $i)<tr><td><a href="{{ route('bills.show', $i) }}">{{ $i->bill_number }}</a></td><td>{{ $m($i->balance()) }}</td><td><input type="number" step="any" min="0" max="{{ $i->balance() }}" name="alloc[{{ $i->id }}]" style="width:130px"></td></tr>@endforeach</tbody></table></div><div class="panel-body"><button class="btn btn-primary">Save</button></div></div></form>
@endif
@foreach (app(\App\Domains\Core\ModuleRegistry::class)->panels('finance.document') as $panel)@include($panel, ['document' => $payment])@endforeach
@include('attachments._panel', ['type' => 'supplier_payment', 'id' => $payment->id])
@endsection
