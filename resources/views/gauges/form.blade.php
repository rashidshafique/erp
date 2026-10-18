@extends('layouts.app')

@section('title', $gauge->exists ? 'Edit '.$gauge->code : 'Add instrument')
@section('breadcrumb')<a href="{{ route('gauges.index') }}">Gauges</a> @if ($gauge->exists)/ <a href="{{ route('gauges.show', $gauge) }}">{{ $gauge->code }}</a> / Edit @else / New @endif @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $gauge->exists ? 'Edit '.$gauge->code : 'Add instrument' }}</h1><p>Calibration dates are set by recording a calibration on the instrument's page, not here.</p></div>
    <div class="actions"><a class="btn" href="{{ $gauge->exists ? route('gauges.show', $gauge) : route('gauges.index') }}">Cancel</a></div>
</div>
@php $v = fn ($f) => old($f, $gauge->{$f}); @endphp
<form method="POST" action="{{ $gauge->exists ? route('gauges.update', $gauge) : route('gauges.store') }}">
    @csrf @if ($gauge->exists) @method('PUT') @endif
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="code">Code</label><input id="code" name="code" maxlength="40" value="{{ $v('code') }}" placeholder="{{ $gauge->exists ? '' : 'Leave blank to generate: '.app(\App\Services\Codes\CodeGenerator::class)->preview('gauge') }}" style="text-transform:uppercase"></div>
        <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="150" value="{{ $v('name') }}" placeholder="e.g. Vernier caliper 0-150 mm"></div>
        <div class="field"><label for="instrument_type">Type *</label><select id="instrument_type" name="instrument_type">@foreach ($types as $k => $l)<option value="{{ $k }}" @selected($v('instrument_type') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="status">Status *</label><select id="status" name="status">@foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected($v('status') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="manufacturer">Manufacturer</label><input id="manufacturer" name="manufacturer" maxlength="100" value="{{ $v('manufacturer') }}"></div>
        <div class="field"><label for="model">Model</label><input id="model" name="model" maxlength="100" value="{{ $v('model') }}"></div>
        <div class="field"><label for="serial_number">Serial number</label><input id="serial_number" name="serial_number" maxlength="100" value="{{ $v('serial_number') }}"></div>
        <div class="field"><label for="range_description">Range / resolution</label><input id="range_description" name="range_description" maxlength="150" value="{{ $v('range_description') }}" placeholder="e.g. 0-150 mm, 0.02 mm"></div>
        <div class="field"><label for="location">Where it is kept</label><input id="location" name="location" maxlength="150" value="{{ $v('location') }}"></div>
        <div class="field"><label for="responsible_person">Responsible person</label><input id="responsible_person" name="responsible_person" maxlength="150" value="{{ $v('responsible_person') }}"></div>
        <div class="field"><label><input type="hidden" name="requires_calibration" value="0"><input type="checkbox" name="requires_calibration" value="1" @checked((bool) $v('requires_calibration'))> Needs calibration</label><div class="help">Tick this for anything whose reading matters to quality. Untick for tools that only indicate (a tape used for rough checks).</div></div>
        <div class="field"><label for="calibration_interval_days">Calibration interval (days)</label><input id="calibration_interval_days" name="calibration_interval_days" type="number" min="1" max="3650" value="{{ $v('calibration_interval_days') }}"><div class="help">365 for a year, 180 for six months.</div></div>
        <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="3" maxlength="2000">{{ $v('notes') }}</textarea></div>
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save instrument</button></div>
</form>
@endsection
