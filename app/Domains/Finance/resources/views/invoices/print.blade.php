<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $invoice->isCreditNote() ? 'Credit note' : 'Invoice' }} {{ $invoice->invoice_number }}</title>
<style>
body { font: 14px/1.45 system-ui, sans-serif; color: #111; max-width: 800px; margin: 24px auto; padding: 0 16px; }
h1 { font-size: 22px; margin: 0 0 4px; } table { border-collapse: collapse; width: 100%; margin: 14px 0; } th, td { border: 1px solid #bbb; padding: 6px 8px; text-align: left; vertical-align: top; } .num { text-align: right; }
.muted { color: #555; } .flag { border: 1px solid #b00; color: #b00; padding: 6px 10px; margin: 10px 0; } @media print { .noprint { display: none; } }
.parties { display: flex; gap: 24px; flex-wrap: wrap; } .parties > div { flex: 1; min-width: 240px; }
</style></head><body>
@php
    $n = fn ($v) => \App\Support\Formats::number((float) $v, 2);
    $pct = fn ($v) => \App\Support\Formats::number((float) $v, 2).'%';
    $brand = \App\Support\UiSettings::brand()['name'];
    $withCodes = $invoice->lines->contains(fn ($l) => filled($l->tariff_code));
    $breakdown = $invoice->taxBreakdown();
    $tariffLabel = \App\Support\TaxJurisdiction::tariffLabel();
    $span = $withCodes ? 5 : 4;
@endphp
<p class="noprint"><a href="#" onclick="window.print();return false">Print</a></p>
<h1>{{ $invoice->isCreditNote() ? 'Credit note' : ($breakdown->contains(fn ($t) => $t['amount'] > 0 && ! $t['withheld']) ? 'Sales tax invoice' : 'Invoice') }} {{ $invoice->invoice_number }}</h1>
<div class="muted">{{ $invoice->invoice_date->displayDate() }}@if ($invoice->due_date) · Due {{ $invoice->due_date->displayDate() }}@endif @if ($invoice->original) · Against invoice {{ $invoice->original->invoice_number }}@endif</div>
@if ($invoice->status === 'VOID')<div class="flag">This document is void.</div>@endif
<div class="parties">
    <div><div class="muted">From</div><strong>{{ $brand }}</strong>@foreach ($registrations['seller'] as $label => $number)<br>{{ $label }}: {{ $number }}@endforeach</div>
    <div><div class="muted">To</div><strong>{{ $invoice->customer?->name }}</strong>@if ($invoice->customer?->billing_address)<br>{!! nl2br(e($invoice->customer->billing_address)) !!}@endif
        @foreach ($registrations['party'] as $label => $number)<br>{{ $label }}: {{ $number }}@endforeach
        @if ($invoice->customer_reference)<br>Your reference: {{ $invoice->customer_reference }}@endif</div>
</div>
<table><thead><tr><th>#</th><th>Description</th>@if ($withCodes)<th>{{ $tariffLabel }}</th>@endif<th class="num">Quantity</th><th class="num">Unit price ({{ $invoice->currency }})</th><th class="num">Tax</th><th class="num">Value excl. tax</th></tr></thead><tbody>
@foreach ($invoice->lines as $l)<tr><td>{{ $l->line_number }}</td><td>{{ $l->description }}@if ($l->supply_type === 'SERVICE') <span class="muted">(service)</span>@endif</td>@if ($withCodes)<td>{{ $l->tariff_code }}</td>@endif<td class="num">{{ \App\Support\Formats::number($l->quantity, 3) }} {{ $l->uom }}</td><td class="num">{{ $n($l->unit_price) }}</td><td class="num">{{ $l->tax_percent > 0 ? $pct($l->tax_percent) : '' }}</td><td class="num">{{ $n($l->line_net) }}</td></tr>@endforeach
</tbody><tfoot>
<tr><th colspan="{{ $span + 1 }}" class="num">Value excluding tax</th><td class="num">{{ $n($invoice->subtotal) }}</td></tr>
@foreach ($breakdown->reject(fn ($t) => $t['withheld']) as $t)<tr><th colspan="{{ $span + 1 }}" class="num">{{ $t['label'] }} @ {{ $pct($t['rate']) }}@if ($t['authority']) ({{ $t['authority'] }})@endif</th><td class="num">{{ $n($t['amount']) }}</td></tr>@endforeach
@if ($breakdown->reject(fn ($t) => $t['withheld'])->isEmpty())<tr><th colspan="{{ $span + 1 }}" class="num">Tax</th><td class="num">{{ $n($invoice->tax_total) }}</td></tr>@endif
<tr><th colspan="{{ $span + 1 }}" class="num">Total including tax {{ $invoice->currency }}</th><th class="num">{{ $n($invoice->total) }}</th></tr>
@foreach ($breakdown->filter(fn ($t) => $t['withheld']) as $t)<tr><td colspan="{{ $span + 1 }}" class="num muted">Less {{ strtolower($t['label']) }} @ {{ $pct($t['rate']) }}</td><td class="num muted">({{ $n($t['amount']) }})</td></tr>@endforeach
@if ($invoice->tax_withheld > 0)<tr><th colspan="{{ $span + 1 }}" class="num">Payable to {{ $brand }}</th><th class="num">{{ $n($invoice->payableToUs()) }}</th></tr>@endif
</tfoot></table>
<p>Payment: {{ $invoice->payment_terms ?: 'as agreed' }}.</p>
@if ($invoice->notes)<p>{{ $invoice->notes }}</p>@endif
</body></html>
