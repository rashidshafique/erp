@extends('layouts.app')
@section('title', $invoice->invoice_number ?? 'Draft invoice')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); $cur = $invoice->currency; $label = $invoice->isCreditNote() ? 'Credit note' : 'Invoice'; @endphp
<div class="page-heading"><div><h1>{{ $label }} {{ $invoice->invoice_number ?? '(draft)' }}</h1><p>{{ $invoice->customer?->name }} · {{ $invoice->invoice_date->displayDate() }}@if ($invoice->due_date) · due {{ $invoice->due_date->displayDate() }}@endif
@if ($invoice->original) · against <a href="{{ route('invoices.show', $invoice->original) }}">{{ $invoice->original->invoice_number }}</a>@endif</p></div>
<div class="actions">
@if ($invoice->status === 'DRAFT')
    <a class="btn" href="{{ route('invoices.edit', $invoice) }}">Edit</a>
    <form method="POST" action="{{ route('invoices.issue', $invoice) }}" style="display:inline">@csrf<button class="btn btn-primary" onclick="return confirm('Issue this invoice? It gets its number and can no longer be edited.')">Issue</button></form>
    <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" style="display:inline">@csrf @method('DELETE')<button class="btn" onclick="return confirm('Delete this draft?')">Delete</button></form>
@elseif ($invoice->status === 'ISSUED')
    <a class="btn" href="{{ route('invoices.print', $invoice) }}" target="_blank">Print</a>
    @if (! $invoice->isCreditNote())
        @if ($invoice->balance() > 0)<a class="btn btn-primary" href="{{ route('receipts.create', ['customer' => $invoice->customer_id, 'invoice' => $invoice->id]) }}">Record payment</a>@endif
        <a class="btn" href="{{ route('invoices.credit', $invoice) }}">Credit note</a>
    @endif
@endif
</div></div>
<div class="panel"><div class="panel-body" style="display:flex;gap:28px;flex-wrap:wrap">
    <div><div class="muted small">Status</div><span class="status {{ $invoice->status === 'ISSUED' ? 'status-completed' : ($invoice->status === 'VOID' ? 'status-cancelled' : 'status-on-hold') }}">{{ $invoice->status }}</span></div>
    <div><div class="muted small">Total</div><strong>{{ $m($invoice->total) }} {{ $cur }}</strong></div>
    @if ($invoice->tax_withheld > 0)<div><div class="muted small">Withheld by the customer</div>{{ $m($invoice->tax_withheld) }}</div><div><div class="muted small">Customer pays</div><strong>{{ $m($invoice->payableToUs()) }}</strong></div>@endif
    @if (! $invoice->isCreditNote() && $invoice->status === 'ISSUED')
        <div><div class="muted small">Paid</div>{{ $m($invoice->paid()) }}</div><div><div class="muted small">Credited</div>{{ $m($invoice->credited()) }}</div>
        <div><div class="muted small">Owing</div><strong @if ($invoice->isOverdue()) style="color:var(--danger)" @endif>{{ $m($invoice->balance()) }}</strong></div>
    @endif
    @if ($invoice->customer_reference)<div><div class="muted small">Customer reference</div>{{ $invoice->customer_reference }}</div>@endif
    @if ($invoice->source_type && $invoice->source_type !== 'manual')<div><div class="muted small">Raised from</div>{{ str_replace('_', ' ', $invoice->source_type) }}</div>@endif
</div>
@if ($invoice->status === 'VOID')<div class="panel-body muted">Voided {{ $invoice->voided_at?->displayShortDateTime() }}: {{ $invoice->void_reason }}</div>@endif</div>
@foreach ($taxChecks['blocks'] as $problem)<div class="alert alert-error">{{ $problem }}</div>@endforeach
@foreach ($taxChecks['warnings'] as $problem)<div class="alert alert-warning">{{ $problem }}</div>@endforeach
<div class="panel"><div class="table-wrap"><table><thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Price</th><th>Tax</th><th class="num">Amount</th></tr></thead><tbody>
@foreach ($invoice->lines as $l)<tr><td>{{ $l->line_number }}</td><td>{{ $l->description }}</td><td>{{ \App\Support\Formats::number($l->quantity, 3) }} {{ $l->uom }}</td><td>{{ $m($l->unit_price) }}</td><td>{{ $l->tax_percent > 0 ? rtrim(rtrim(number_format($l->tax_percent, 3), '0'), '.').'%' : '—' }}</td><td class="num">{{ $m($l->line_net) }}</td></tr>@endforeach
</tbody><tfoot><tr><td colspan="5" class="num muted">Subtotal</td><td class="num">{{ $m($invoice->subtotal) }}</td></tr><tr><td colspan="5" class="num muted">Tax</td><td class="num">{{ $m($invoice->tax_total) }}</td></tr><tr><th colspan="5" class="num">Total</th><th class="num">{{ $m($invoice->total) }}</th></tr></tfoot></table></div>
@if ($invoice->notes)<div class="panel-body small">{{ $invoice->notes }}</div>@endif</div>
@php $breakdown = $invoice->taxBreakdown(); @endphp
@if ($breakdown->count() > 1 || $breakdown->contains(fn ($t) => $t['authority'] || $t['withheld']))
<div class="panel"><div class="panel-header"><h2>Taxes</h2></div><div class="table-wrap"><table><thead><tr><th>Tax</th><th>Authority</th><th class="num">Rate</th><th class="num">On</th><th class="num">Amount</th></tr></thead><tbody>
@foreach ($breakdown as $t)<tr><td>{{ $t['label'] }}@if ($t['withheld']) <span class="muted small">withheld by the customer</span>@endif</td><td>{{ $t['authority'] ?? '—' }}</td><td class="num">{{ rtrim(rtrim(number_format($t['rate'], 3), '0'), '.') }}%</td><td class="num">{{ $m($t['base']) }}</td><td class="num">{{ $m($t['amount']) }}</td></tr>@endforeach
</tbody></table></div></div>
@endif
@if ($invoice->allocations->isNotEmpty() || $invoice->creditNotes->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Payments and credits</h2></div><div class="table-wrap"><table><thead><tr><th>Document</th><th>Date</th><th class="num">Amount</th></tr></thead><tbody>
@foreach ($invoice->allocations as $a)<tr><td><a href="{{ route('receipts.show', $a->receipt) }}">{{ $a->receipt->receipt_number }}</a></td><td>{{ $a->receipt->received_on->displayShortDate() }}</td><td class="num">{{ $m($a->amount) }}</td></tr>@endforeach
@foreach ($invoice->creditNotes->where('status', 'ISSUED') as $c)<tr><td><a href="{{ route('invoices.show', $c) }}">{{ $c->invoice_number }}</a> <span class="muted small">credit note</span></td><td>{{ $c->invoice_date->displayShortDate() }}</td><td class="num">{{ $m($c->total) }}</td></tr>@endforeach
</tbody></table></div></div>
@endif
@if ($invoice->status === 'ISSUED' && $invoice->paid() == 0 && $invoice->credited() == 0 || ($invoice->status === 'ISSUED' && $invoice->isCreditNote()))
<div class="panel"><div class="panel-body"><form method="POST" action="{{ route('invoices.void', $invoice) }}" style="display:flex;gap:8px;flex-wrap:wrap">@csrf
<input name="reason" placeholder="Why this {{ strtolower($label) }} is void" maxlength="300" required style="min-width:300px;flex:1"><button class="btn" onclick="return confirm('Void this {{ strtolower($label) }}? The number stays used.')">Void</button></form></div></div>
@endif
@include('attachments._panel', ['type' => 'invoice', 'id' => $invoice->id])
@include('audit._history', ['type' => 'Invoice', 'id' => $invoice->id])
@endsection
