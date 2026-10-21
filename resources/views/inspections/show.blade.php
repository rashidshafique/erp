@extends('layouts.app')
@section('title', $inspection->inspection_number)
@section('content')
@php $open = $inspection->status === 'IN_PROGRESS'; @endphp
<div class="page-heading"><div><h1>{{ $inspection->inspection_number }}</h1><p>{{ $inspection->inspection_type }} · lot <a href="{{ route('lots.show', $inspection->lot) }}">{{ $inspection->lot->label }}</a> · {{ $inspection->quantity_inspected }} piece(s) · <span class="status">{{ $inspection->status }}</span></p></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@if(session("warning"))<div class="alert alert-warning">{{ session("warning") }}</div>@endif
<div class="tab-marker" data-tab-start="Results"></div>
<div class="panel"><div class="panel-header"><h2>Characteristics</h2></div>
@if($open)@include('partials.quick-dialog', ['id' => 'qd-defect', 'title' => 'Add a defect', 'action' => route('defects.store'), 'submit' => 'Add defect', 'fields' => 'defects._quick-fields'])@endif
<form method="POST" action="{{ route('inspections.results.save', $inspection) }}">@csrf
<div class="table-wrap"><table><thead><tr><th>Characteristic</th><th>Limits</th><th>Measured</th><th>Instrument</th><th>Pass?</th><th>Defect if failed</th><th>Notes</th></tr></thead><tbody>
@forelse($inspection->results as $r)<tr><td>{{ $r->test_name }}<div class="muted small">{{ $r->test_method }}</div></td><td>{{ $r->minimum_value ?? '' }} – {{ $r->maximum_value ?? '' }} {{ $r->unit }}</td>
<td>@if($r->result_type === 'NUMERIC')<input type="number" step="any" name="results[{{ $r->id }}][measured_value]" value="{{ $r->measured_value }}" @disabled(!$open)>@else<span class="muted small">—</span>@endif</td>
<td>@if($r->result_type === 'NUMERIC')<select name="results[{{ $r->id }}][gauge_id]" @disabled(!$open) style="max-width:190px"><option value="">—</option>@foreach($gauges as $g)<option value="{{ $g->id }}" @selected($r->gauge_id === $g->id)>{{ $g->code }} · {{ $g->name }}@if(in_array($g->calibrationStatus(), ['OVERDUE','NOT_CALIBRATED','OUT_OF_SERVICE'], true)) ({{ strtolower(\App\Services\Quality\CalibrationStatus::LABELS[$g->calibrationStatus()]) }})@endif</option>@endforeach</select>@else<span class="muted small">—</span>@endif</td>
<td>@if($r->result_type === 'NUMERIC')<strong>{{ $r->passed === null ? '' : ($r->passed ? 'PASS' : 'FAIL') }}</strong>@else<select name="results[{{ $r->id }}][passed]" @disabled(!$open)><option value="">—</option><option value="1" @selected($r->passed === true)>Pass</option><option value="0" @selected($r->passed === false)>Fail</option></select>@endif</td>
<td style="white-space:nowrap"><select name="results[{{ $r->id }}][quality_defect_id]" id="defect-{{ $r->id }}" data-quick-list="defect" @disabled(!$open)><option value="">—</option>@foreach($defects as $d)<option value="{{ $d->id }}" @selected($r->quality_defect_id === $d->id)>{{ $d->category }} · {{ $d->name }}</option>@endforeach</select>@if($open)<a href="{{ route('defects.index') }}" class="qd-add" data-quick="qd-defect" data-quick-target="defect-{{ $r->id }}">+ Add</a>@endif</td>
<td><input name="results[{{ $r->id }}][notes]" value="{{ $r->notes }}" @disabled(!$open)></td></tr>
@empty<tr><td colspan="7" class="muted">No characteristics yet. Add them below.</td></tr>@endforelse</tbody></table></div>
@if($open && $inspection->results->isNotEmpty())<div class="panel-body"><button class="btn btn-primary">Save results</button></div>@endif</form></div>

<div class="tab-marker" data-tab-start="Complete"></div>
@if($open)
<div class="panel"><div class="panel-header"><h2>Add a characteristic</h2></div><div class="panel-body"><form method="POST" action="{{ route('inspections.results.add', $inspection) }}" class="form-grid">@csrf
<label>Name<input name="test_name" required></label><label>Type<select name="result_type"><option value="NUMERIC">Numeric</option><option value="PASS_FAIL">Pass / fail</option></select></label>
<label>Min<input type="number" step="any" name="minimum_value"></label><label>Max<input type="number" step="any" name="maximum_value"></label><label>Unit<input name="unit"></label><label>Method<input name="test_method"></label>
<div><button class="btn">Add</button></div></form></div></div>

<div class="panel"><div class="panel-header"><h2>Complete inspection</h2></div><div class="panel-body"><p class="muted small">Save results first. The four quantities must add up to {{ $inspection->quantity_inspected }}. Any rejected or rework piece, or failed result, raises an NCR automatically.</p>
<form method="POST" action="{{ route('inspections.complete', $inspection) }}" class="form-grid">@csrf
<label>Accepted<input type="number" name="quantity_accepted" min="0" value="{{ old('quantity_accepted', $inspection->quantity_inspected) }}" required></label>
<label>Rejected<input type="number" name="quantity_rejected" min="0" value="{{ old('quantity_rejected', 0) }}" required></label>
<label>Rework<input type="number" name="quantity_rework" min="0" value="{{ old('quantity_rework', 0) }}" required></label>
<label>Pending<input type="number" name="quantity_pending" min="0" value="{{ old('quantity_pending', 0) }}" required></label>
<label>Disposition<select name="overall_disposition">@foreach(['ACCEPT','PARTIAL','REWORK','REJECT'] as $x)<option>{{ $x }}</option>@endforeach</select></label>
<label>NCR title (optional)<input name="ncr_title"></label><label>Remarks<input name="remarks"></label>
<div><button class="btn btn-primary">Complete</button></div></form></div></div>
@else
<div class="panel"><div class="panel-body">Disposition <strong>{{ $inspection->overall_disposition }}</strong> — {{ $inspection->quantity_accepted }} accepted, {{ $inspection->quantity_rejected }} rejected, {{ $inspection->quantity_rework }} rework, {{ $inspection->quantity_pending }} pending.
@foreach($inspection->ncrs as $n) <a href="{{ route('ncrs.show', $n) }}">{{ $n->ncr_number }}</a>@endforeach</div></div>
@endif
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'inspection', 'id' => $inspection->id])
@include('attachments._panel', ['type' => 'inspection', 'id' => $inspection->id])
@include('audit._history', ['type' => 'QualityInspection', 'id' => $inspection->id])
@endsection
