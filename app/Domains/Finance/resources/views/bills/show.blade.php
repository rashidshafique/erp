@extends('layouts.app')
@section('title', $bill->bill_number ?? 'Draft supplier bill')
@section('content')
@php $m = fn ($v) => \App\Support\Formats::number($v, 2); $cur = $bill->currency; $label = $bill->isCreditNote() ? 'Credit note' : 'Bill'; @endphp
<div class="page-heading"><div><h1>{{ $label }} {{ $bill->bill_number ?? '(draft)' }}</h1><p>{{ $bill->supplier?->name }} · {{ $bill->bill_date->displayDate() }}@if ($bill->due_date) · due {{ $bill->due_date->displayDate() }}@endif
@if ($bill->original) · against <a href="{{ route('bills.show', $bill->original) }}">{{ $bill->original->bill_number }}</a>@endif</p></div>
<div class="actions">
@if ($bill->status === 'DRAFT')
    <a class="btn" href="{{ route('bills.edit', $bill) }}">Edit</a>
    <form method="POST" action="{{ route('bills.post', $bill) }}" style="display:inline">@csrf<button class="btn btn-primary" onclick="return confirm('Post this bill? It gets our number, counts as owed and can no longer be edited.')">Post</button></form>
    <form method="POST" action="{{ route('bills.destroy', $bill) }}" style="display:inline">@csrf @method('DELETE')<button class="btn" onclick="return confirm('Delete this draft?')">Delete</button></form>
@elseif ($bill->status === 'POSTED' && ! $bill->isCreditNote())
    @if ($bill->balance() > 0)<a class="btn btn-primary" href="{{ route('supplier-payments.create', ['supplier' => $bill->supplier_id, 'bill' => $bill->id]) }}">Record payment</a>@endif
    @if ($bill->balance() > 0 && ($advance = app(\App\Domains\Finance\Services\Advances::class)->forBill($bill)->sum(fn ($p) => $p->unallocated())) > 0)<form method="POST" action="{{ route('bills.apply-advances', $bill) }}" style="display:inline">@csrf<button class="btn" title="Money paid to this supplier that is not on a bill yet">Use advances ({{ $m($advance) }})</button></form>@endif
    <a class="btn" href="{{ route('bills.credit', $bill) }}">Credit note</a>
@endif
</div></div>
@if ($issues)<div class="alert alert-warning"><strong>Does not match the order:</strong> {{ implode(' ', $issues) }}@if (\App\Domains\Finance\Services\FinanceRules::billMatch() === 'BLOCK') It cannot be posted until it does.@endif</div>@endif
<div class="panel"><div class="panel-body" style="display:flex;gap:28px;flex-wrap:wrap">
    <div><div class="muted small">Status</div><span class="status {{ $bill->status === 'POSTED' ? 'status-completed' : ($bill->status === 'VOID' ? 'status-cancelled' : 'status-on-hold') }}">{{ $bill->status }}</span></div>
    <div><div class="muted small">Total</div><strong>{{ $m($bill->total) }} {{ $cur }}</strong></div>
    @if (! $bill->isCreditNote() && $bill->status === 'POSTED')
        <div><div class="muted small">Paid</div>{{ $m($bill->paid()) }}</div><div><div class="muted small">Credited</div>{{ $m($bill->credited()) }}</div>
        <div><div class="muted small">Owing</div><strong @if ($bill->isOverdue()) style="color:var(--danger)" @endif>{{ $m($bill->balance()) }}</strong></div>
    @endif
    @if ($bill->supplier_bill_number)<div><div class="muted small">Supplier's number</div>{{ $bill->supplier_bill_number }}</div>@endif
    @if ($bill->purchaseOrder)<div><div class="muted small">Purchase order</div><a href="{{ route('purchase-orders.show', $bill->purchaseOrder) }}">{{ $bill->purchaseOrder->po_number }}</a></div>@endif
</div>
@if ($bill->status === 'VOID')<div class="panel-body muted">Voided {{ $bill->voided_at?->displayShortDateTime() }}: {{ $bill->void_reason }}</div>@endif</div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Price</th><th>Tax</th><th class="num">Amount</th></tr></thead><tbody>
@foreach ($bill->lines as $l)<tr><td>{{ $l->line_number }}</td><td>{{ $l->description }}</td><td>{{ \App\Support\Formats::number($l->quantity, 3) }} {{ $l->uom }}</td><td>{{ $m($l->unit_price) }}</td><td>{{ $l->tax_percent > 0 ? \App\Support\Formats::number($l->tax_percent, 2).'%' : '—' }}</td><td class="num">{{ $m($l->line_net) }}</td></tr>@endforeach
</tbody><tfoot><tr><td colspan="5" class="num muted">Subtotal</td><td class="num">{{ $m($bill->subtotal) }}</td></tr><tr><td colspan="5" class="num muted">Tax</td><td class="num">{{ $m($bill->tax_total) }}</td></tr><tr><th colspan="5" class="num">Total</th><th class="num">{{ $m($bill->total) }}</th></tr></tfoot></table></div>
@if ($bill->notes)<div class="panel-body small">{{ $bill->notes }}</div>@endif</div>
@if ($bill->allocations->isNotEmpty() || $bill->creditNotes->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Payments and credits</h2></div><div class="table-wrap"><table><thead><tr><th>Document</th><th>Date</th><th class="num">Amount</th></tr></thead><tbody>
@foreach ($bill->allocations as $a)<tr><td><a href="{{ route('supplier-payments.show', $a->payment) }}">{{ $a->payment->payment_number }}</a></td><td>{{ $a->payment->paid_on->displayShortDate() }}</td><td class="num">{{ $m($a->amount) }}</td></tr>@endforeach
@foreach ($bill->creditNotes->where('status', 'POSTED') as $c)<tr><td><a href="{{ route('bills.show', $c) }}">{{ $c->bill_number }}</a> <span class="muted small">credit note</span></td><td>{{ $c->bill_date->displayShortDate() }}</td><td class="num">{{ $m($c->total) }}</td></tr>@endforeach
</tbody></table></div></div>
@endif
@if ($bill->status === 'POSTED' && ($bill->isCreditNote() || ($bill->paid() == 0 && $bill->credited() == 0)))
<div class="panel"><div class="panel-body"><form method="POST" action="{{ route('bills.void', $bill) }}" style="display:flex;gap:8px;flex-wrap:wrap">@csrf
<input name="reason" placeholder="Why this {{ strtolower($label) }} is void" maxlength="300" required style="min-width:300px;flex:1"><button class="btn" onclick="return confirm('Void this {{ strtolower($label) }}? The number stays used.')">Void</button></form></div></div>
@endif
@foreach (app(\App\Domains\Core\ModuleRegistry::class)->panels('finance.document') as $panel)@include($panel, ['document' => $bill])@endforeach
@include('attachments._panel', ['type' => 'supplier_bill', 'id' => $bill->id])
@include('audit._history', ['type' => 'SupplierBill', 'id' => $bill->id])
@endsection
