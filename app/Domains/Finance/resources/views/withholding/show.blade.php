@extends('layouts.app')
@section('title', $certificate->certificate_number)
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="page-heading"><div><h1>Tax certificate {{ $certificate->certificate_number }}</h1><p>{{ $certificate->customer?->name }} · {{ $certificate->certificate_date->displayDate() }} · {{ $certificate->tax_label }}@if ($certificate->authority) · {{ $certificate->authority }}@endif</p></div><div class="actions"><a class="btn" href="{{ route('receivables.statement', $certificate->customer_id) }}">Customer statement</a></div></div>
@error('certificate')<div class="alert alert-error">{{ $message }}</div>@enderror
<div class="panel"><div class="panel-body" style="display:flex;gap:28px;flex-wrap:wrap">
    <div><div class="muted small">Status</div><span class="status {{ $certificate->isVoid() ? 'status-cancelled' : 'status-completed' }}">{{ $certificate->isVoid() ? 'VOID' : 'RECORDED' }}</span></div>
    <div><div class="muted small">Amount</div><strong>{{ $m($certificate->amount) }}</strong></div>
    <div><div class="muted small">Their reference</div>{{ $certificate->reference }}</div>
    @if ($certificate->isVoid())<div><div class="muted small">Voided</div>{{ $certificate->voided_at->displayDate() }}: {{ $certificate->void_reason }}</div>@endif
</div>
@if ($certificate->notes)<div class="panel-body small">{{ $certificate->notes }}</div>@endif</div>
<div class="panel"><div class="panel-header"><h2>Invoices settled</h2></div><div class="table-wrap"><table><thead><tr><th>Invoice</th><th class="num">Amount</th></tr></thead><tbody>
@foreach ($certificate->allocations as $a)<tr><td><a href="{{ route('invoices.show', $a->invoice) }}">{{ $a->invoice->invoice_number }}</a></td><td class="num">{{ $m($a->amount) }}</td></tr>@endforeach</tbody></table></div></div>
@unless ($certificate->isVoid())
<div class="panel"><div class="panel-header"><h2>Void</h2></div><div class="panel-body"><form method="POST" action="{{ route('withholding-certificates.void', $certificate) }}" style="display:flex;gap:8px;flex-wrap:wrap">@csrf
<input name="reason" required minlength="3" maxlength="300" placeholder="Why, e.g. entered against the wrong customer" style="flex:1;min-width:240px"><button class="btn" onclick="return confirm('Void this certificate? The tax goes back on the invoices as owing.')">Void certificate</button></form></div></div>
@endunless
@foreach (app(\App\Domains\Core\ModuleRegistry::class)->panels('finance.document') as $panel)@include($panel, ['document' => $certificate])@endforeach
@include('attachments._panel', ['type' => 'withholding_certificate', 'id' => $certificate->id])
@endsection
