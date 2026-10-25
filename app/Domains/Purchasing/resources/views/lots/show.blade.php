@extends('layouts.app')

@section('title', 'Lot '.$lot->lot_number)
@section('breadcrumb')<a href="{{ route('materials.show', $material) }}">{{ $material->code }}</a> / Lot {{ $lot->lot_number }} @endsection

@section('content')
<div class="page-heading">
    <div><h1>Lot {{ $lot->lot_number }}</h1><p>{{ $material->code }} — {{ $material->name }} · {{ $lot->status }}@if($lot->supplier) · {{ $lot->supplier->name }}@endif</p></div>
    <div class="actions"><a class="btn" href="{{ route('incoming.plan.edit', $material) }}">Inspection plan</a><a class="btn" href="{{ route('materials.show', $material) }}">Back</a></div>
</div>


@if ($lot->status === 'QUARANTINE')
    <div class="panel"><div class="panel-header"><h2>Acceptance</h2></div><div class="panel-body">
        @forelse ($blockers as $blocker)<div class="alert alert-warning">{{ $blocker }}</div>@empty<p class="muted">The incoming rules are met. The lot can be accepted.</p>@endforelse
        <form method="POST" action="{{ route('incoming.lots.accept', $lot) }}">@csrf
            <div class="field"><label for="accepted_qty">Accepted quantity ({{ $material->base_uom }}) *</label><input id="accepted_qty" type="number" step="any" min="0" name="accepted_qty" required value="{{ old('accepted_qty', (float) $lot->received_qty) }}" style="max-width:200px"></div>
            <button class="btn btn-primary" type="submit" @disabled($blockers)>Accept lot</button>
        </form>
    </div></div>
@endif

<div class="panel"><div class="panel-header"><h2>Certificate</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('incoming.lots.certificate-type', $lot) }}">@csrf @method('PUT')
        <div class="field"><label for="certificate_type">Supplier certificate type</label>
            <select id="certificate_type" name="certificate_type" style="max-width:260px"><option value="">— not marked —</option>@foreach ($certificateTypes as $k => $l)<option value="{{ $k }}" @selected(old('certificate_type', $lot->certificate_type) === $k)>{{ $l }}</option>@endforeach</select></div>
        <button class="btn" type="submit">Save</button> <span class="muted small">Attach the certificate file below under the category Certificate.</span>
    </form>
</div></div>

<div class="panel"><div class="panel-header"><h2>Record an inspection</h2></div><div class="panel-body">
    @if ($material->inspectionItems->isEmpty())<p class="muted">This material has no inspection plan yet. <a href="{{ route('incoming.plan.edit', $material) }}">Add one</a>, or record a visual check below.</p>@endif
    <form method="POST" action="{{ route('incoming.inspections.store', $lot) }}">@csrf
        <div class="form-grid">
            <div class="field"><label for="inspected_on">Inspected on *</label><input id="inspected_on" type="date" name="inspected_on" required max="{{ today()->toDateString() }}" value="{{ old('inspected_on', today()->toDateString()) }}"></div>
            <div class="field"><label for="inspector">Inspector</label><input id="inspector" name="inspector" maxlength="150" value="{{ old('inspector') }}"></div>
            @if ($gauges->isNotEmpty())<div class="field"><label for="gauge_id">Instrument</label><select id="gauge_id" name="gauge_id"><option value="">—</option>@foreach ($gauges as $g)<option value="{{ $g->id }}" @selected(old('gauge_id') === $g->id)>{{ $g->code }} — {{ $g->name }}</option>@endforeach</select></div>@endif
        </div>
        @if ($material->inspectionItems->isNotEmpty())
        <div class="table-wrap"><table><thead><tr><th>Characteristic</th><th>Limits</th><th>Measured</th></tr></thead><tbody>
            @foreach ($material->inspectionItems as $item)
                <tr><td>{{ $item->characteristic }}@if($item->method)<div class="muted small">{{ $item->method }}</div>@endif</td>
                    <td>{{ $item->min_value !== null ? (float) $item->min_value : '' }} – {{ $item->max_value !== null ? (float) $item->max_value : '' }} {{ $item->unit }}</td>
                    <td><input type="number" step="any" name="values[{{ $item->id }}]" required value="{{ old('values.'.$item->id) }}" aria-label="{{ $item->characteristic }}" style="max-width:140px"></td></tr>
            @endforeach
        </tbody></table></div>
        @endif
        <div class="field field-full"><label for="notes">Notes</label><input id="notes" name="notes" maxlength="2000" value="{{ old('notes') }}"></div>
        <label><input type="checkbox" name="concession" value="1" @checked(old('concession'))> Accept by concession if any value is out of limits (notes required)</label>
        <div><button class="btn btn-primary" type="submit">Save inspection</button></div>
    </form>
</div></div>

<div class="panel"><div class="panel-header"><h2>Inspection history</h2></div>
    @forelse ($lot->inspections as $inspection)
        <div class="panel-body"><strong>{{ $inspection->inspected_on->displayDate() }}</strong> · {{ $results[$inspection->result] }}@if($inspection->inspector) · {{ $inspection->inspector }}@endif
            @if ($inspection->notes)<div class="muted small">{{ $inspection->notes }}</div>@endif
            @if ($inspection->results->isNotEmpty())<div class="table-wrap"><table><thead><tr><th>Characteristic</th><th>Limits</th><th>Measured</th><th></th></tr></thead><tbody>
                @foreach ($inspection->results as $r)<tr><td>{{ $r->characteristic }}</td><td>{{ $r->min_value !== null ? (float) $r->min_value : '' }} – {{ $r->max_value !== null ? (float) $r->max_value : '' }} {{ $r->unit }}</td><td>{{ (float) $r->measured_value }}</td><td>{{ $r->passed ? 'Pass' : 'Out of limits' }}</td></tr>@endforeach
            </tbody></table></div>@endif
        </div>
    @empty
        <div class="panel-body muted">No inspections recorded.</div>
    @endforelse
</div>
@include('attachments._panel', ['type' => 'material_lot', 'id' => $lot->id])
@endsection
