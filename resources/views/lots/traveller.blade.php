<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Traveller {{ $lot->label }}</title>
<style>body{font-family:Arial,sans-serif;margin:24px;color:#000}.bar{margin-bottom:14px;display:flex;gap:10px;align-items:center}
.head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}h1{margin:0 0 4px}.head svg{max-width:260px;height:auto}
.facts{display:grid;grid-template-columns:repeat(4,1fr);gap:6px 16px;margin:12px 0;font-size:13px}.facts div span{display:block;font-size:11px;color:#444}
table{width:100%;border-collapse:collapse;margin-top:6px}th,td{border:1px solid #000;padding:6px;font-size:12px;vertical-align:top}th{background:#eee;text-align:left}
.n{width:22px;text-align:center}.num{width:52px}.who{width:90px}.sig{width:80px}.what{font-size:11px;color:#333;white-space:pre-line}.check{font-size:11px;margin-top:3px;white-space:pre-line}
.tags{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#444}.done td{background:#f6f6f6}.sign{margin-top:22px;display:flex;gap:40px;font-size:13px}.sign div{flex:1;border-top:1px solid #000;padding-top:4px}
@media print{.bar{display:none}body{margin:10mm}tr{break-inside:avoid}}</style></head><body>
<div class="bar"><button onclick="window.print()">Print</button><a href="{{ route('lots.show', $lot) }}">Back to lot</a></div>
@php $customer = $lot->productionOrder?->customer(); @endphp
<div class="head"><div><h1>Lot traveller {{ $lot->label }}</h1><div>{{ $lot->product?->code }} — {{ $lot->product?->name }}@if($lot->productRevision) · rev {{ $lot->productRevision->revision }}@endif</div></div>
{!! \App\Support\Code128::svg($lot->label, 44, 2) !!}</div>
<div class="facts">
<div><span>Customer</span>{{ $customer ? ($customer->short_name ?: $customer->name) : 'Stock' }}</div>
<div><span>Sales order</span>{{ $lot->productionOrder?->salesOrderLine?->salesOrder?->order_number ?? '—' }}</div>
<div><span>Production order</span>{{ $lot->productionOrder?->production_order_number ?? '—' }}</div>
<div><span>Heat / grade</span>{{ $heat?->heat_number ?? '—' }} / {{ $heat?->ironGrade?->code ?? '—' }}</div>
<div><span>Poured</span>{{ $lot->created_from_pour_at?->displayDate() ?? '—' }}</div>
<div><span>Pieces poured / in process</span>{{ $lot->quantity_poured }} / {{ $pieces }}</div>
<div><span>Casting weight kg</span>{{ $lot->net_casting_weight_kg ?? '—' }}</div>
<div><span>Moulds</span>{{ $moulds->map(fn ($m) => $m->assembly_number)->implode(', ') ?: '—' }}</div>
</div>

<table><thead><tr><th class="n">#</th><th>Step — what is done, what to check</th><th class="num">In</th><th class="num">Good</th><th class="num">Rejected</th><th>Reason</th><th class="who">Operator</th><th class="who">Date</th><th class="sig">Sign</th></tr></thead><tbody>
@foreach($steps as $i => $stage)
@php $events = $recorded->get($stage->id); @endphp
@include('lots._traveller-row', ['number' => $i + 1, 'stage' => $stage, 'events' => $events])
@endforeach
@foreach($offRoute as $stage)
@include('lots._traveller-row', ['number' => '+', 'stage' => $stage, 'events' => $recorded->get($stage->id)])
@endforeach
@if($steps->isEmpty() && $offRoute->isEmpty())<tr><td colspan="9">No steps on the process. Set them under Settings → Our process.</td></tr>@endif
</tbody></table>

<div class="sign"><div>Released for packing by</div><div>Date</div><div>Pieces packed</div></div>
</body></html>
