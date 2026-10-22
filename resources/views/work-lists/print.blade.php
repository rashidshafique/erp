<!doctype html><html><head><meta charset="utf-8"><title>{{ $list->list_number }}</title>
<style>body{font-family:Arial,sans-serif;margin:24px;color:#000}h1{margin:0 0 4px}h2{margin:22px 0 4px;font-size:16px}table{width:100%;border-collapse:collapse;margin-top:6px}th,td{border:1px solid #000;padding:7px;font-size:13px;vertical-align:top}th{background:#eee;text-align:left}.sig{height:42px}.sub{font-size:12px;color:#333}@media print{button{display:none}h2{break-after:avoid}table{break-inside:auto}tr{break-inside:avoid}}</style></head><body>
<button onclick="window.print()">Print</button>
<h1>Moulding work list {{ $list->list_number }}</h1><div>{{ $list->list_date->format('l d M Y') }} · {{ $list->shift }} shift · Issued by {{ $list->issued_by ?? '—' }}</div>
@foreach($list->items->groupBy('moulding_station_id') as $items)
@php $st = $items->first()->station; @endphp
<h2>Station {{ $st->code }} — {{ $st->name }} <span class="sub">· {{ $st->isAutomated() ? 'one pattern at a time, in the order below' : 'up to '.$st->capacity().' patterns at once' }} · Team {{ $items->map(fn($i) => $i->team ? $i->team->code.' '.$i->team->lead_name : null)->filter()->unique()->implode(', ') ?: '—' }}</span></h2>
<table><thead><tr><th>#</th><th>Customer</th><th>Order</th><th>Product</th><th>Pattern / plate</th><th>Sand</th><th>Moulds</th><th>Made</th><th>Damaged</th><th>Lead's signature</th></tr></thead><tbody>
@foreach($items as $i)<tr><td>{{ $i->sequence }}</td><td>{{ $i->productionOrder?->customerLabel() ?? '—' }}</td><td>{{ $i->productionOrder?->production_order_number ?? 'not linked' }}<br>{{ $i->productionOrder?->order_type }}</td><td>{{ $i->productionOrder?->product?->code }}<br>{{ $i->productionOrder?->product?->name }}</td><td>{{ $i->patternRevision->shortCode() }}<br>{{ \App\Models\Pattern::typeName($i->patternRevision->pattern->pattern_type, true) }}</td><td>{{ $i->sand_system }}@if($i->sand_override)<br><strong>plate not made for this sand</strong>@endif</td><td><strong>{{ $i->planned_moulds }}</strong></td><td></td><td></td><td class="sig"></td></tr>
@if($i->instructions)<tr><td></td><td colspan="9">Note: {{ $i->instructions }}</td></tr>@endif @endforeach</tbody></table>
@endforeach
@if($list->notes)<p>{{ $list->notes }}</p>@endif</body></html>
