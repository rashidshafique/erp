@extends('layouts.app')

@section('title', 'Raise a corrective action')
@section('breadcrumb')<a href="{{ route('capa.index') }}">Corrective actions</a> / New @endsection

@section('content')
<div class="page-heading">
    <div><h1>Raise a corrective action</h1><p>For an NCR, use "Raise corrective action" on the NCR so it carries over what is already written. Use this for complaints, audit findings, supplier problems and anything else.</p></div>
    <div class="actions"><a class="btn" href="{{ route('capa.index') }}">Cancel</a></div>
</div>
<form method="POST" action="{{ route('capa.store') }}">@csrf
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="source_type">Raised because of *</label><select id="source_type" name="source_type">@foreach (\App\Models\CapaAction::SOURCES as $k => $l)<option value="{{ $k }}" @selected(old('source_type', $action->source_type) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="source_label">Reference</label><input id="source_label" name="source_label" maxlength="200" value="{{ old('source_label') }}" placeholder="e.g. complaint no., audit finding no., supplier name"></div>
        <div class="field field-full"><label for="title">What went wrong *</label><input id="title" name="title" required maxlength="200" value="{{ old('title') }}"></div>
        <div class="field field-full"><label for="description">Details</label><textarea id="description" name="description" rows="4" maxlength="4000">{{ old('description') }}</textarea></div>
        <div class="field"><label for="severity">Severity *</label><select id="severity" name="severity">@foreach (\App\Models\CapaAction::SEVERITIES as $k => $l)<option value="{{ $k }}" @selected(old('severity', 'MINOR') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="owner">Owner</label><input id="owner" name="owner" maxlength="150" value="{{ old('owner') }}"></div>
        <div class="field"><label for="due_on">Action due</label><input id="due_on" type="date" name="due_on" min="{{ today()->toDateString() }}" value="{{ old('due_on') }}"></div>
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Raise action</button></div>
</form>
@endsection
