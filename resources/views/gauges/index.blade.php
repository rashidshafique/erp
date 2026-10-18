@extends('layouts.app')

@section('title', 'Gauges & calibration')
@section('breadcrumb', 'Gauges')

@section('content')
@php $state = request('state'); @endphp
<div class="page-heading">
    <div><h1>Gauges &amp; calibration</h1><p>Every measuring instrument, when it was last calibrated and when it is next due. Results can name the instrument they were measured with.</p></div>
    <div class="actions">
        <a class="btn" href="{{ route('settings.calibration.edit') }}">Calibration rules</a>
        <a class="btn btn-primary" href="{{ route('gauges.create') }}">+ Add instrument</a>
    </div>
</div>

<div class="panel"><div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">
    @foreach (['overdue' => ['Overdue', 'var(--danger)'], 'soon' => ['Due soon', 'var(--warning)'], 'never' => ['Never calibrated', 'var(--danger)'], 'out' => ['Out of service', 'var(--danger)']] as $key => [$label, $color])
        <a href="{{ route('gauges.index', ['state' => $key]) }}" style="text-decoration:none;color:inherit"><div class="muted small">{{ $label }}</div><strong style="font-size:22px;@if ($counts[$key]) color:{{ $color }} @endif">{{ $counts[$key] }}</strong></a>
    @endforeach
</div></div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" class="form-grid" style="align-items:end">
            <div class="field"><label for="q">Search code, name or serial number</label><input id="q" name="q" value="{{ request('q') }}"></div>
            <div class="field"><label for="type">Type</label><select id="type" name="type"><option value="">All</option>@foreach ($types as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="state">Calibration</label><select id="state" name="state"><option value="">All</option>@foreach (['overdue' => 'Overdue', 'soon' => 'Due soon', 'never' => 'Never calibrated', 'out' => 'Out of service'] as $k => $l)<option value="{{ $k }}" @selected($state === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label><input type="checkbox" name="retired" value="1" @checked(request('retired'))> Show retired</label></div>
            <div><button class="btn btn-primary" type="submit">Filter</button> <a class="btn" href="{{ route('gauges.index') }}">Clear</a></div>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Code</th><th>Instrument</th><th>Type</th><th>Location</th><th>Last calibrated</th><th>Next due</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($gauges as $gauge)
            <tr>
                <td class="mono"><a href="{{ route('gauges.show', $gauge) }}">{{ $gauge->code }}</a></td>
                <td>{{ $gauge->name }}<div class="muted small">{{ collect([$gauge->manufacturer, $gauge->model, $gauge->serial_number ? 'S/N '.$gauge->serial_number : null])->filter()->implode(' · ') }}</div></td>
                <td>{{ $types[$gauge->instrument_type] ?? $gauge->instrument_type }}</td>
                <td>{{ $gauge->location ?: '—' }}</td>
                <td>{{ $gauge->last_calibrated_on?->format('d M Y') ?? '—' }}</td>
                <td>{{ $gauge->next_due_on?->format('d M Y') ?? '—' }}</td>
                <td>@include('gauges._status', ['state' => $gauge->calibrationStatus()])</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No instruments yet. Add the first one to start the register.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="panel-body">{{ $gauges->links() }}</div>
</div>
@endsection
