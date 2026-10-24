<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Purchase order {{ $order->po_number }}</title>
<style>
body { font: 14px/1.45 system-ui, sans-serif; color: #111; max-width: 800px; margin: 24px auto; padding: 0 16px; }
h1 { font-size: 22px; margin: 0 0 4px; } table { border-collapse: collapse; width: 100%; margin: 14px 0; } th, td { border: 1px solid #bbb; padding: 6px 8px; text-align: left; vertical-align: top; } .num { text-align: right; }
.muted { color: #555; } .flag { border: 1px solid #b00; color: #b00; padding: 6px 10px; margin: 10px 0; } @media print { .noprint { display: none; } }
</style></head><body>
@php $n = fn ($v) => \App\Support\Formats::number((float) $v, 2); $brand = \App\Support\UiSettings::brand()['name']; @endphp
<p class="noprint"><a href="#" onclick="window.print();return false">Print</a></p>
<h1>Purchase order {{ $order->po_number }}</h1>
<div class="muted">{{ $brand }} · {{ $order->order_date->displayDate() }}@if ($order->expected_date) · Wanted by {{ $order->expected_date->displayDate() }}@endif</div>
@if ($order->status === 'CANCELLED')<div class="flag">This order is cancelled.</div>@endif
<p><strong>{{ $order->supplier?->name }}</strong>@if ($order->supplier?->billing_address)<br>{!! nl2br(e($order->supplier->billing_address)) !!}@endif @if ($order->supplier_reference)<br>Your reference: {{ $order->supplier_reference }}@endif</p>
<table><thead><tr><th>#</th><th>Description</th><th class="num">Quantity</th><th class="num">Unit price ({{ $order->currency }})</th><th class="num">Tax</th><th class="num">Amount</th></tr></thead><tbody>
@foreach ($order->lines as $l)<tr><td>{{ $l->line_number }}</td><td>{{ $l->description }}</td><td class="num">{{ \App\Support\Formats::number($l->ordered_qty, 3) }} {{ $l->uom }}</td><td class="num">{{ $n($l->unit_price) }}</td><td class="num">{{ $l->tax_percent > 0 ? rtrim(rtrim(number_format($l->tax_percent, 3), '0'), '.').'%' : '' }}</td><td class="num">{{ $n($l->line_net) }}</td></tr>@endforeach
</tbody><tfoot><tr><th colspan="5" class="num">Subtotal</th><td class="num">{{ $n($order->subtotal) }}</td></tr><tr><th colspan="5" class="num">Tax</th><td class="num">{{ $n($order->tax_total) }}</td></tr><tr><th colspan="5" class="num">Total {{ $order->currency }}</th><th class="num">{{ $n($order->total) }}</th></tr></tfoot></table>
<p>Payment: {{ $order->payment_terms ?: 'as agreed' }}.</p>
@if ($order->notes)<p>{{ $order->notes }}</p>@endif
</body></html>
