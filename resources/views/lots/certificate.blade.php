@extends('layouts.app')
@section('title', 'Certificate '.$lot->label)
@push('styles')<style>@media print{.sidebar,.page-heading .actions,.no-print{display:none!important}}</style>@endpush
@section('content')
<div class="page-heading"><div><h1>Material and inspection certificate</h1><p>Lot {{ $lot->label }}</p></div><div class="actions no-print"><button class="btn btn-primary" onclick="window.print()">Print</button></div></div>
@if(!$readiness['ok'])<div class="alert alert-error"><strong>DRAFT — not cleared for release.</strong> @foreach($readiness['reasons'] as $r)<div>{{ $r }}</div>@endforeach</div>@endif
@php $cust = $lot->productionOrder?->salesOrderLine?->salesOrder?->customer; @endphp
<div class="panel"><div class="panel-header"><h2>Casting</h2></div><div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
<div><div class="muted small">Customer</div>{{ $cust?->name ?? '—' }}</div>
<div><div class="muted small">Sales order</div>{{ $lot->productionOrder?->salesOrderLine?->salesOrder?->order_number ?? '—' }}</div>
<div><div class="muted small">Product</div>{{ $lot->product?->code }} {{ $lot->product?->name }}</div>
<div><div class="muted small">Heat / grade</div>{{ $heat?->heat_number ?? '—' }} / {{ $heat?->ironGrade?->code ?? '—' }}</div>
<div><div class="muted small">Quantity good</div>{{ $lot->quantity_good }} of {{ $lot->quantity_poured }}</div>
<div><div class="muted small">Poured</div>{{ $lot->created_from_pour_at?->displayDate() }}</div></div></div>
<div class="panel"><div class="panel-header"><h2>Chemical analysis</h2></div><div class="panel-body">
@if($chemistry)<table><thead><tr><th>C</th><th>Si</th><th>Mn</th><th>P</th><th>S</th><th>Cr</th><th>Cu</th><th>Mo</th><th>Mg</th><th>CE</th></tr></thead><tbody><tr>
@foreach(['carbon_percent','silicon_percent','manganese_percent','phosphorus_percent','sulphur_percent','chromium_percent','copper_percent','molybdenum_percent','magnesium_percent','carbon_equivalent'] as $f)<td>{{ $chemistry->$f ?? '—' }}</td>@endforeach</tr></tbody></table>
<p class="muted small">Reading #{{ $chemistry->reading_number ?? '' }} — {{ $chemistry->is_accepted ? 'within grade limits' : 'OUTSIDE grade limits' }}</p>@else<p class="muted">No chemistry recorded.</p>@endif</div></div>
<div class="panel"><div class="panel-header"><h2>Thermal analysis</h2></div><div class="table-wrap"><table><thead><tr><th>Sample</th><th>Cup</th><th>TL °C</th><th>TS °C</th><th>Result</th></tr></thead><tbody>
@forelse($thermal as $t)<tr><td>{{ $t->sample_reference }}</td><td>{{ $t->cup_type }}</td><td>{{ $t->tl_c ?? '—' }}</td><td>{{ $t->solidus_c ?? '—' }}</td><td>{{ $t->result }}</td></tr>@empty<tr><td colspan="5" class="muted">No thermal test.</td></tr>@endforelse</tbody></table></div></div>
@php $tests = $heat ? \App\Models\MeasurementSet::with('measurements')->where('heat_id', $heat->id)->whereIn('kind', ['MECHANICAL','MICROSTRUCTURE'])->orderBy('taken_at')->get() : collect(); @endphp
<div class="panel"><div class="panel-header"><h2>Mechanical and microstructure tests</h2></div><div class="table-wrap"><table><thead><tr><th>Sample</th><th>Characteristic</th><th>Limits</th><th>Result</th><th></th></tr></thead><tbody>
@forelse($tests as $s)@foreach($s->measurements as $m)<tr><td>{{ $s->sample_reference ?: $s->taken_at->displayShortDate() }}<div class="muted small">{{ \App\Services\Quality\Measurements::SAMPLE_TYPES[$s->sample_type] ?? '' }}</div></td><td>{{ $m->name }}</td><td>{{ $m->min_value ?? '' }} – {{ $m->max_value ?? '' }} {{ $m->unit }}</td><td>{{ $m->value !== null ? \App\Support\Formats::number($m->value, 2) : $m->text_value }} {{ $m->unit }}</td><td>{{ $m->passed === null ? '—' : ($m->passed ? 'PASS' : 'FAIL') }}</td></tr>@endforeach
@empty<tr><td colspan="5" class="muted">No mechanical or microstructure test recorded.</td></tr>@endforelse</tbody></table></div></div>
<div class="panel"><div class="panel-header"><h2>Inspection results</h2></div><div class="table-wrap"><table><thead><tr><th>Inspection</th><th>Characteristic</th><th>Limits</th><th>Measured</th><th>Result</th></tr></thead><tbody>
@foreach($lot->inspections as $i)@foreach($i->results as $r)<tr><td>{{ $i->inspection_number }}</td><td>{{ $r->test_name }}</td><td>{{ $r->minimum_value ?? '' }} – {{ $r->maximum_value ?? '' }} {{ $r->unit }}</td><td>{{ $r->measured_value ?? $r->text_result }}</td><td>{{ $r->passed === null ? '—' : ($r->passed ? 'PASS' : 'FAIL') }}</td></tr>@endforeach
<tr><td colspan="5" class="muted small">{{ $i->inspection_number }}: {{ $i->quantity_accepted }} accepted, {{ $i->quantity_rejected }} rejected, {{ $i->quantity_rework }} rework — inspector {{ $i->inspector_name ?? '—' }}</td></tr>@endforeach
@if($lot->inspections->isEmpty())<tr><td colspan="5" class="muted">No inspection recorded.</td></tr>@endif</tbody></table></div></div>
@endsection
