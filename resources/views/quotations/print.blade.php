<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quotation {{ $q->code }}</title>
<style>
body { font: 14px/1.45 system-ui, sans-serif; color: #111; max-width: 800px; margin: 24px auto; padding: 0 16px; }
h1 { font-size: 22px; margin: 0 0 4px; } table { border-collapse: collapse; width: 100%; margin: 14px 0; } th, td { border: 1px solid #bbb; padding: 6px 8px; text-align: left; vertical-align: top; } .num { text-align: right; }
.muted { color: #555; } .flag { border: 1px solid #b00; color: #b00; padding: 6px 10px; margin: 10px 0; } @media print { .noprint { display: none; } }
</style></head><body>
@php
    $n = fn ($v) => number_format((float) $v, 2);
    $brand = \App\Support\UiSettings::brand()['name'];
    $total = $q->lines->sum(fn ($l) => $l->unit_price * $l->qty);
@endphp
<p class="noprint"><a href="#" onclick="window.print();return false">Print</a></p>
<h1>Quotation {{ $q->code }}</h1>
<div class="muted">{{ $brand }} · Version {{ $q->version }} · Issued {{ $q->issued_at?->format('d M Y') }}</div>
@if (in_array($q->status, ['EXPIRED', 'SUPERSEDED', 'REJECTED'], true))<div class="flag">This quotation is no longer open ({{ strtolower($q->status) }}).</div>@endif
<p><strong>{{ $q->enquiry->customer?->name }}</strong>@if ($q->enquiry->contact_name)<br>Attention: {{ $q->enquiry->contact_name }}@endif @if ($q->enquiry->customer_reference)<br>Your reference: {{ $q->enquiry->customer_reference }}@endif</p>
<table><thead><tr><th>#</th><th>Description</th><th>Drawing</th><th class="num">Quantity</th><th class="num">Unit price ({{ $q->currency }})</th><th class="num">Amount</th></tr></thead><tbody>
@foreach ($q->lines as $l)
<tr><td>{{ $l->line_no }}</td><td>{{ $l->item->description }}@if ($l->item->material_text)<div class="muted">{{ $l->item->material_text }}</div>@endif</td><td>{{ $l->item->drawing_ref }}{{ $l->item->drawing_rev ? ' rev '.$l->item->drawing_rev : '' }}</td><td class="num">{{ $l->qty }}</td><td class="num">{{ $n($l->unit_price) }}</td><td class="num">{{ $n($l->unit_price * $l->qty) }}</td></tr>
@endforeach
</tbody><tfoot><tr><th colspan="5" class="num">Total</th><th class="num">{{ $n($total) }}</th></tr></tfoot></table>
@if ($q->tooling_mode === 'UPFRONT')<p>Tooling charge, invoiced separately: <strong>{{ $q->currency }} {{ $n($q->upfrontToolingPrice()) }}</strong>.</p>
@elseif ($q->tooling_mode === 'AMORTISED')<p>Tooling cost is included in the unit price, spread over {{ $q->tooling_amortise_qty }} castings.</p>@endif
<p>Valid until <strong>{{ $q->valid_until?->format('d M Y') }}</strong>. Delivery: {{ $q->delivery_weeks ? $q->delivery_weeks.' weeks from order' : 'to be agreed' }}. Payment: {{ $q->payment_terms ?: 'to be agreed' }}.</p>
@if ($q->notes)<p>{{ $q->notes }}</p>@endif
@php $conds = $q->lines->filter(fn ($l) => $l->item->feasibility_status === 'CONDITIONAL' && $l->item->feasibility_notes); @endphp
@if ($conds->isNotEmpty())<p><strong>Conditions</strong></p><ul>@foreach ($conds as $l)<li>Item {{ $l->line_no }}: {{ $l->item->feasibility_notes }}</li>@endforeach</ul>@endif
<p class="muted">Prices are per casting, as cast and fettled unless stated. Production starts after the first samples are approved.</p>
</body></html>
