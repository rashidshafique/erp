@extends('layouts.app')
@section('title', $lot->label)
@section('content')
<div class="page-heading"><div><h1>{{ $lot->label }}</h1><p>{{ $lot->product?->code }} — {{ $lot->product?->name }} · <span class="status">{{ $lot->status }}</span></p></div>
<div class="actions"><a class="btn" href="{{ route('lots.traveller', $lot) }}">Traveller</a><a class="btn" href="{{ route('lots.certificate', $lot) }}">Certificate</a>
<a class="btn btn-primary" href="{{ route('lots.inspections.create', $lot) }}">+ Inspection</a></div></div>
<div class="tab-marker" data-tab-start="Summary"></div>
<div class="panel"><div class="panel-header"><h2>Summary</h2></div><div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px">
<div><div class="muted small">Heat</div>@if($heat)<a href="{{ route('heats.show', $heat) }}">{{ $heat->heat_number }}</a> ({{ $heat->ironGrade?->code ?? 'no grade' }})@else — @endif</div>
<div><div class="muted small">Production order</div>{{ $lot->productionOrder?->production_order_number ?? '—' }}</div>
<div><div class="muted small">Poured</div><strong>{{ $lot->quantity_poured }}</strong></div>
<div><div class="muted small">Rejected</div><strong>{{ $lot->quantity_rejected }}</strong></div>
<div><div class="muted small">Good</div><strong>{{ $lot->quantity_good }}</strong></div>
<div><div class="muted small">Still in process</div><strong>{{ $pieces }}</strong></div>
<div><div class="muted small">Packed</div><strong>{{ $packed }}</strong></div>
<div><div class="muted small">Gross / net kg</div>{{ $lot->gross_weight_kg }} / {{ $lot->net_casting_weight_kg }}</div></div></div>

<div class="panel"><div class="panel-header"><h2>Dispatch readiness</h2><span class="status {{ $readiness['ok'] ? 'status-completed' : 'status-cancelled' }}">{{ $readiness['ok'] ? 'CLEAR' : 'NOT CLEAR' }}</span></div>
<div class="panel-body"><ul>@foreach($readiness['checks'] as $c)<li>{{ $c['ok'] === true ? '✔' : ($c['ok'] === false ? '✘' : '•') }} <strong>{{ $c['label'] }}</strong> — {{ $c['detail'] }}</li>@endforeach</ul>
@if (($readiness['waivable'] ?? false) && Route::has('packing.waive-material-checks'))
<form method="POST" action="{{ route('packing.waive-material-checks', $lot) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px">@csrf
<input name="reason" placeholder="Why the chemistry and thermal analysis checks can be waived for this lot" maxlength="500" required style="min-width:340px;flex:1">
<button class="btn" type="submit" onclick="return confirm('Waive the chemistry and thermal analysis checks for this lot only?')">Waive for this lot</button></form>
@error('waiver')<div class="alert alert-error">{{ $message }}</div>@enderror @error('reason')<div class="alert alert-error">{{ $message }}</div>@enderror
@endif</div></div>

<div class="tab-marker" data-tab-start="Stages"></div>
<div class="panel"><div class="panel-header"><h2>Shop-floor stages</h2></div>
@if($cooling)<div class="panel-body small">Cooling before shakeout: minimum {{ $cooling['minimum'] }} min from pour completion — @if($cooling['wait'] > 0)<strong>wait until {{ $cooling['ready_at']->displayShortDateTime() }}</strong> ({{ $cooling['wait'] }} min left)@else <strong>cooling time met</strong>@endif</div>@endif
<div class="table-wrap"><table><thead><tr><th>Stage</th><th>In</th><th>Accepted</th><th>Rejected</th><th>Reason</th><th>Operator</th><th>When</th></tr></thead><tbody>
@forelse($lot->stageEvents->sortBy('created_at') as $e)<tr><td>{{ $e->stage?->name }}</td><td>{{ $e->quantity_in }}</td><td>{{ $e->quantity_accepted }}</td><td>{{ $e->quantity_rejected }}</td><td>{{ $e->rejection_reason }}</td><td>{{ $e->operator_name }}</td><td>{{ $e->created_at?->displayShortDateTime() }}</td></tr>
@empty<tr><td colspan="7" class="muted">No stage recorded yet.</td></tr>@endforelse</tbody></table></div>
@if(in_array($lot->status, ['IN_PRODUCTION','ON_HOLD']))
<div class="panel-body"><form method="POST" action="{{ route('lots.stages.store', $lot) }}" class="form-grid" x-data="{ check: @js((string) $stages->first()?->quality_notes) }">@csrf
<label>Stage<select name="stage_definition_id" required x-on:change="check = $event.target.selectedOptions[0].dataset.check">@foreach($stages as $s)<option value="{{ $s->id }}" data-check="{{ $s->quality_notes }}">{{ $s->name }}</option>@endforeach</select></label>
<div class="alert" style="grid-column:1/-1;margin:0;white-space:pre-line;display:none" x-show="check"><strong>Check:</strong> <span x-text="check"></span></div>
<label>Pieces in<input type="number" name="quantity_in" min="1" value="{{ $pieces }}" required></label>
<label>Accepted<input type="number" name="quantity_accepted" min="0" value="{{ $pieces }}" required></label>
<label>Rejected<input type="number" name="quantity_rejected" min="0" value="0" required></label>
<label>Reason if rejected<input name="rejection_reason" maxlength="100" placeholder="e.g. sand inclusion"></label>
<label>Early shakeout reason<input name="cooling_override_reason" maxlength="300" placeholder="only if cooling time not met"></label>
<label>Operator<input name="operator_name"></label><label>Shift<input name="shift_code"></label><label>Notes<input name="notes"></label>
<div><button class="btn btn-primary">Record stage</button></div></form></div>@endif</div>

<div class="tab-marker" data-tab-start="Quality & hold"></div>
<div class="panel"><div class="panel-header"><h2>Inspections and NCRs</h2></div><div class="table-wrap"><table><thead><tr><th>Record</th><th>Type</th><th>Status</th><th>Detail</th></tr></thead><tbody>
@foreach($lot->inspections as $i)<tr><td><a href="{{ route('inspections.show', $i) }}">{{ $i->inspection_number }}</a></td><td>{{ $i->inspection_type }}</td><td>{{ $i->status }}</td><td>{{ $i->quantity_accepted }} ok / {{ $i->quantity_rejected }} rej / {{ $i->quantity_rework }} rework</td></tr>@endforeach
@foreach($lot->ncrs as $n)<tr><td><a href="{{ route('ncrs.show', $n) }}">{{ $n->ncr_number }}</a></td><td>NCR</td><td>{{ $n->status }}</td><td>{{ $n->title }}</td></tr>@endforeach
@if($lot->inspections->isEmpty() && $lot->ncrs->isEmpty())<tr><td colspan="4" class="muted">None yet.</td></tr>@endif</tbody></table></div></div>

@if(!in_array($lot->status, ['DISPATCHED','CLOSED','SCRAPPED']))
<div class="panel"><div class="panel-header"><h2>Hold</h2></div><div class="panel-body">
@if($lot->status === 'ON_HOLD')<form method="POST" action="{{ route('lots.release', $lot) }}">@csrf<button class="btn">Release from hold</button></form>
@else<form method="POST" action="{{ route('lots.hold', $lot) }}" style="display:flex;gap:10px">@csrf<input name="reason" placeholder="Reason for hold" required style="flex:1"><button class="btn btn-danger">Put on hold</button></form>@endif</div></div>@endif
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'casting_lot', 'id' => $lot->id])
@include('attachments._panel', ['type' => 'casting_lot', 'id' => $lot->id])
@include('audit._history', ['type' => 'CastingLot', 'id' => $lot->id])
@endsection
