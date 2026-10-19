@extends('layouts.app')
@section('title', $ncr->ncr_number)
@section('content')
@php $closed = $ncr->status === 'CLOSED'; @endphp
<div class="page-heading"><div><h1>{{ $ncr->ncr_number }}</h1><p>{{ $ncr->title }} · {{ $ncr->severity }} · <span class="status">{{ $ncr->status }}</span></p></div></div>
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="tab-marker" data-tab-start="Investigation"></div>
@if (Route::has('capa.index'))
@php $capa = \App\Models\CapaAction::where('source_type', 'NCR')->where('source_id', $ncr->id)->orderByDesc('created_at')->first(); @endphp
<div class="panel"><div class="panel-body" style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
@if($capa)<span>Corrective action <a href="{{ route('capa.show', $capa) }}">{{ $capa->code }}</a>: @include('capa::_status', ['capa' => $capa])</span>
@else<span class="muted">Tracks the root cause, the action and the check that it worked in the corrective action register.</span><form method="POST" action="{{ route('capa.from-ncr', $ncr) }}">@csrf<button class="btn">Raise corrective action</button></form>@endif
</div></div>
@endif
<div class="panel"><div class="panel-body"><p>{{ $ncr->description }}</p>
<p class="muted small">Lot <a href="{{ route('lots.show', $ncr->lot) }}">{{ $ncr->lot?->lot_number }}</a> · {{ $ncr->quantity_affected }} piece(s) affected @if($ncr->defect) · defect: {{ $ncr->defect->name }} @endif</p></div></div>
<div class="panel"><div class="panel-header"><h2>Investigation and disposition</h2></div><div class="panel-body">
<form method="POST" action="{{ route('ncrs.update', $ncr) }}" class="form-grid">@csrf @method('PUT')
<label>Containment<textarea name="containment_action" @disabled($closed)>{{ old('containment_action', $ncr->containment_action) }}</textarea></label>
<label>Root cause<textarea name="root_cause" @disabled($closed)>{{ old('root_cause', $ncr->root_cause) }}</textarea></label>
<label>Corrective action<textarea name="corrective_action" @disabled($closed)>{{ old('corrective_action', $ncr->corrective_action) }}</textarea></label>
<label>Preventive action<textarea name="preventive_action" @disabled($closed)>{{ old('preventive_action', $ncr->preventive_action) }}</textarea></label>
<label>Disposition<select name="disposition" @disabled($closed)><option value="">—</option>@foreach($dispositions as $d)<option @selected($ncr->disposition === $d)>{{ $d }}</option>@endforeach</select></label>
<label>Assigned to<input name="assigned_to" value="{{ old('assigned_to', $ncr->assigned_to) }}" @disabled($closed)></label>
@unless($closed)<div><button class="btn btn-primary">Save</button></div>@endunless</form></div></div>
@unless($closed)<div class="panel"><div class="panel-header"><h2>Close</h2></div><div class="panel-body"><form method="POST" action="{{ route('ncrs.close', $ncr) }}" class="form-grid">@csrf
<label>Closed by<input name="closed_by" required></label><label>Verification notes<input name="verification_notes"></label><div><button class="btn btn-danger">Close NCR</button></div></form></div></div>
@else<div class="panel"><div class="panel-body">Closed {{ $ncr->closed_at?->displayDateTime() }} by {{ $ncr->closed_by }}. {{ $ncr->verification_notes }}</div></div>@endunless
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'ncr', 'id' => $ncr->id])
@include('attachments._panel', ['type' => 'ncr', 'id' => $ncr->id])
@include('audit._history', ['type' => 'NonconformanceReport', 'id' => $ncr->id])
@endsection
