@extends('layouts.app')
@section('title', 'New inspection')
@section('content')
<div class="page-heading"><div><h1>New inspection</h1><p>Lot {{ $lot->label }} — {{ $lot->product?->code }}</p></div></div>
<div class="panel"><div class="panel-body">
@if($plan)<p class="muted small">A FINAL inspection will load {{ $plan->items->count() }} characteristic(s) from the approved plan.</p>@else<p class="muted small">No approved final inspection plan for this product revision. You can add characteristics by hand.</p>@endif
<form method="POST" action="{{ route('lots.inspections.store', $lot) }}" class="form-grid">@csrf
<label>Type<select name="inspection_type">@foreach($types as $t)<option>{{ $t }}</option>@endforeach</select></label>
<label>After stage<select name="stage_definition_id"><option value="">—</option>@foreach($stages as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label>
<label>Sample size (pieces)<input type="number" name="sample_size" min="1" value="{{ old('sample_size', $lot->quantity_good) }}" required></label>
<label>Inspector<input name="inspector_name" value="{{ old('inspector_name') }}"></label>
<div><button class="btn btn-primary">Start inspection</button></div></form></div></div>
@endsection
