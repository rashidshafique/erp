@extends('layouts.app')

@section('title', $gauge->code.' · '.$gauge->name)
@section('breadcrumb')<a href="{{ route('gauges.index') }}">Gauges</a> / {{ $gauge->code }} @endsection

@section('content')
<div class="page-heading">
    <div>
        <h1>{{ $gauge->name }}</h1>
        <p><span class="mono">{{ $gauge->code }}</span> · {{ \App\Models\Gauge::TYPES[$gauge->instrument_type] ?? $gauge->instrument_type }} · @include('gauges._status', ['state' => $status])</p>
    </div>
    <div class="actions"><a class="btn" href="{{ route('gauges.index') }}">All instruments</a><a class="btn" href="{{ route('gauges.edit', $gauge) }}">Edit</a></div>
</div>

@if ($status === 'OUT_OF_SERVICE')<div class="alert alert-error">Out of service. Do not use it for results until it passes a calibration.</div>
@elseif ($status === 'OVERDUE')<div class="alert alert-error">Calibration was due {{ $gauge->next_due_on->displayDate() }}. Results measured with it since then will not stand up to an audit.</div>
@elseif ($status === 'NOT_CALIBRATED')<div class="alert alert-error">This instrument has never been calibrated. Record its first calibration below.</div>
@elseif ($status === 'DUE_SOON')<div class="alert alert-warning">Calibration is due on {{ $gauge->next_due_on->displayDate() }}.</div>@endif

<div class="panel"><div class="panel-header"><h2>Instrument</h2></div>
<div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px">
    @foreach ([
        'Manufacturer / model' => collect([$gauge->manufacturer, $gauge->model])->filter()->implode(' '),
        'Serial number' => $gauge->serial_number, 'Range / resolution' => $gauge->range_description, 'Kept at' => $gauge->location, 'Responsible' => $gauge->responsible_person,
        'Calibration interval' => $gauge->requires_calibration ? $gauge->calibration_interval_days.' days' : 'Not needed',
        'Last calibrated' => $gauge->last_calibrated_on?->displayDate(), 'Next due' => $gauge->next_due_on?->displayDate(),
    ] as $label => $value)
        <div><div class="muted small">{{ $label }}</div><strong>{{ $value ?: '—' }}</strong></div>
    @endforeach
    @if ($gauge->notes)<div style="grid-column:1/-1"><div class="muted small">Notes</div>{{ $gauge->notes }}</div>@endif
</div></div>

<div class="panel"><div class="panel-header"><h2>Record a calibration</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('gauges.calibrate', $gauge) }}" enctype="multipart/form-data">@csrf
        <div class="form-grid">
            <div class="field"><label for="calibrated_on">Calibrated on *</label><input id="calibrated_on" type="date" name="calibrated_on" required max="{{ today()->toDateString() }}" value="{{ old('calibrated_on', today()->toDateString()) }}"></div>
            <div class="field"><label for="result">Result *</label><select id="result" name="result">@foreach ($results as $k => $l)<option value="{{ $k }}" @selected(old('result', 'PASS') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="due_on">Next due</label><input id="due_on" type="date" name="due_on" value="{{ old('due_on') }}"><div class="help">Left blank it is worked out from the {{ $gauge->calibration_interval_days ?: '—' }}-day interval.</div></div>
            <div class="field"><label for="performed_by">Done by</label><input id="performed_by" name="performed_by" maxlength="150" value="{{ old('performed_by') }}" placeholder="Person or laboratory"></div>
            <div class="field"><label for="certificate_number">Certificate number</label><input id="certificate_number" name="certificate_number" maxlength="100" value="{{ old('certificate_number') }}"></div>
            <div class="field"><label for="certificate">Certificate file</label><input id="certificate" type="file" name="certificate" data-max-kb="{{ \App\Support\Uploads::effectiveMaxKb() }}"></div>
            <div class="field"><label><input type="hidden" name="external_lab" value="0"><input type="checkbox" name="external_lab" value="1" @checked(old('external_lab'))> Done by an outside laboratory</label></div>
            <div class="field field-full"><label for="as_found">As found (condition before adjustment)</label><input id="as_found" name="as_found" maxlength="2000" value="{{ old('as_found') }}" placeholder="e.g. reads 0.04 mm high at 100 mm"></div>
            <div class="field field-full"><label for="notes">Notes</label><input id="notes" name="notes" maxlength="2000" value="{{ old('notes') }}"></div>
        </div>
        <button class="btn btn-primary" type="submit">Save calibration</button>
        <span class="muted small">A failed calibration takes the instrument out of service.</span>
    </form>
</div></div>

<div class="panel"><div class="panel-header"><h2>Calibration history</h2></div>
    @if ($gauge->calibrations->isEmpty())
        <div class="panel-body muted">No calibrations recorded.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Result</th><th>Next due</th><th>Done by</th><th>Certificate</th><th>Notes</th></tr></thead>
            <tbody>
            @foreach ($gauge->calibrations as $c)
                <tr>
                    <td>{{ $c->calibrated_on->displayDate() }}</td>
                    <td><span class="status {{ $c->result === 'FAIL' ? 'status-cancelled' : 'status-completed' }}">{{ $results[$c->result] ?? $c->result }}</span></td>
                    <td>{{ $c->due_on?->displayDate() ?? '—' }}</td>
                    <td>{{ $c->performed_by ?: '—' }}@if ($c->external_lab)<div class="muted small">outside laboratory</div>@endif</td>
                    <td>{{ $c->certificate_number ?: '—' }}</td>
                    <td class="small">{{ collect([$c->as_found ? 'As found: '.$c->as_found : null, $c->notes])->filter()->implode(' · ') ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>

@include('attachments._panel', ['type' => 'gauge', 'id' => $gauge->id])
@endsection
