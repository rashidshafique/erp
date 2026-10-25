@extends('layouts.app')

@section('title', $lot->lot_number)
@section('breadcrumb')<a href="{{ route('core-lots.index') }}">Core stock</a> / {{ $lot->lot_number }} @endsection

@section('content')
@php $statusChip = ['MADE' => 'status-in-progress', 'STORED' => 'status-completed', 'USED_UP' => 'status-cancelled']; @endphp
<div class="page-heading">
    <div><h1>Lot <span class="mono">{{ $lot->lot_number }}</span></h1><p><a href="{{ route('cores.show', $lot->coreDefinition) }}">{{ $lot->coreDefinition->name }}</a> · made in <a href="{{ route('core-boxes.show', $lot->coreBox) }}">{{ $lot->coreBox->code }}</a> · <span class="status {{ $statusChip[$lot->status] ?? '' }}">{{ \App\Models\CoreLot::STATUSES[$lot->status] ?? $lot->status }}</span></p></div>
    <div class="actions"><a class="btn" href="{{ route('core-lots.create', ['core' => $lot->core_definition_id]) }}">+ More of this core</a></div>
</div>
<div class="panel"><div class="panel-body">
    <dl class="tool-facts">
        <div><dt>Made</dt><dd>{{ $lot->quantity_made }}<span class="sub">{{ $lot->made_on->displayDate() }}</span></dd></div>
        <div><dt>Left</dt><dd>{{ $remaining }}</dd></div>
        <div><dt>Kept at</dt><dd>{{ $lot->location ?: '—' }}</dd></div>
        @if ($lot->qc_status !== 'NOT_REQUIRED')<div><dt>Check</dt><dd>{{ \App\Models\CoreLot::QC[$lot->qc_status] }}@if ($lot->qc_notes)<span class="sub">{{ $lot->qc_notes }}</span>@endif</dd></div>@endif
    </dl>
    @if ($lot->notes)<p class="muted">{{ $lot->notes }}</p>@endif
</div></div>

@if ($remaining > 0)
<div class="panel"><div class="panel-header"><h2>Take cores out</h2></div><div class="panel-body">
    @if (! $lot->isIssuable())<div class="alert alert-warning">This lot has not passed its check, so the cores cannot be used. They can still be scrapped.</div>@endif
    <form method="POST" action="{{ route('core-lots.take', $lot) }}" class="form-grid">@csrf
        <div class="field"><label for="quantity">Cores *</label><input id="quantity" type="number" name="quantity" required min="1" max="{{ $remaining }}" value="{{ old('quantity') }}"></div>
        <div class="field"><label for="kind">What happens to them *</label><select id="kind" name="kind">@foreach (\App\Models\CoreLotUse::KINDS as $k => $l)<option value="{{ $k }}" @selected(old('kind') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="used_on">Date *</label><input id="used_on" type="date" name="used_on" required min="{{ $lot->made_on->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" value="{{ old('used_on', today()->format('Y-m-d')) }}"></div>
        <div class="field"><label for="moulding_run_id">Moulding run</label><select id="moulding_run_id" name="moulding_run_id"><option value="">— none —</option>@foreach ($runs as $r)<option value="{{ $r->id }}" @selected(old('moulding_run_id') === $r->id)>{{ $r->run_number }} · {{ $r->run_date?->displayDate() }}</option>@endforeach</select></div>
        <div class="field field-full"><label for="notes">Notes</label><input id="notes" name="notes" maxlength="255" value="{{ old('notes') }}"></div>
        <div class="field"><button class="btn btn-primary" type="submit">Record</button></div>
    </form>
</div></div>
<div class="panel"><div class="panel-header"><h2>Where they are kept</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('core-lots.location', $lot) }}" style="display:flex;gap:8px;max-width:420px">@csrf @method('PUT')
        <input name="location" required maxlength="100" value="{{ old('location', $lot->location) }}" aria-label="Location" placeholder="Shelf, rack or bay"><button class="btn" type="submit">Save</button></form>
</div></div>
@endif

@if ($qcOn || $lot->qc_status !== 'NOT_REQUIRED')
<div class="panel"><div class="panel-header"><h2>Check</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('core-lots.check', $lot) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">@csrf @method('PUT')
        <div class="field"><label for="result">Result</label><select id="result" name="result"><option value="PASSED">Passed</option><option value="FAILED">Failed</option></select></div>
        <div class="field"><label for="qc_notes">Notes</label><input id="qc_notes" name="qc_notes" maxlength="255" value="{{ old('qc_notes', $lot->qc_notes) }}"></div>
        <button class="btn" type="submit">Record check</button>
    </form>
</div></div>
@endif

<div class="panel"><div class="panel-header"><h2>Taken out</h2></div><div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Cores</th><th>What</th><th>Moulding run</th><th>Notes</th></tr></thead>
    <tbody>
    @forelse ($lot->uses as $use)
        <tr><td>{{ $use->used_on->displayDate() }}</td><td>{{ $use->quantity }}</td><td>{{ \App\Models\CoreLotUse::KINDS[$use->kind] ?? $use->kind }}</td><td>@if ($use->mouldingRun)<a href="{{ route('moulding-runs.show', $use->mouldingRun) }}">{{ $use->mouldingRun->run_number }}</a>@else — @endif</td><td>{{ $use->notes }}</td></tr>
    @empty
        <tr><td colspan="5" class="muted">None taken out yet.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
@endsection
