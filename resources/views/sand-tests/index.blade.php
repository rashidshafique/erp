@extends('layouts.app')
@section('title', 'Sand lab')
@section('content')
<div class="page-heading"><div><h1>Sand lab</h1><p>Test the sand each shift. A failed or missing test is shown on the moulding run and when a run is started.</p></div></div>
@if($errors->has('sand'))<div class="alert alert-error">{{ $errors->first('sand') }}</div>@endif
@if($system)
<div class="panel"><div class="panel-body"><form method="GET" style="display:flex;gap:10px;align-items:center">Sand system
<select name="system" onchange="this.form.submit()">@foreach($systems as $s)<option value="{{ $s->id }}" @selected($s->id === $system->id)>{{ $s->name }}</option>@endforeach</select>
<span class="muted small">{{ $system->description }}</span></form></div></div>

<div class="panel"><div class="panel-header"><h2>Record a test</h2></div><div class="panel-body"><form method="POST" action="{{ route('sand-tests.store') }}" class="form-grid">@csrf
<input type="hidden" name="sand_system_id" value="{{ $system->id }}">
<label>Sample point<select name="sample_point"><option value="">—</option>@foreach($points as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
<label>Station<select name="moulding_station_id"><option value="">—</option>@foreach($stations as $st)<option value="{{ $st->id }}">{{ $st->code }}</option>@endforeach</select></label>
<label>Shift<select name="shift"><option value="">—</option>@foreach(['Morning','Evening','Night','General'] as $x)<option>{{ $x }}</option>@endforeach</select></label>
<label>Tested by<input name="tested_by"></label>
@include('maintenance::gauges._select', ['plain' => true, 'id' => 'sand_gauge', 'label' => 'Equipment from the register', 'types' => ['SAND_TEST', 'BALANCE']])
@foreach($rows as $r)<label>{{ $r['name'] }}@if($r['unit']) ({{ $r['unit'] }})@endif<input type="number" step="any" name="values[{{ $r['key'] }}]">@if($r['min'] !== null || $r['max'] !== null)<span class="muted small">limit {{ $r['min'] ?? '' }}–{{ $r['max'] ?? '' }}</span>@endif</label>@endforeach
<div><button class="btn btn-primary">Save test</button></div></form></div></div>

<div class="panel"><div class="panel-header"><h2>Recent tests</h2></div><div class="table-wrap"><table><thead><tr><th>When</th><th>Point</th><th>Shift</th><th>Results</th><th>Result</th></tr></thead><tbody>
@forelse($sets as $s)<tr><td>{{ $s->taken_at->displayShortDateTime() }}</td><td>{{ $s->sample_point }}</td><td>{{ $s->shift }}</td>
<td>@foreach($s->measurements as $m){{ $m->name }}: <strong>{{ rtrim(rtrim(number_format((float) $m->value, 4, '.', ''), '0'), '.') }}</strong> {{ $m->unit }}@if($m->passed === false) <span style="color:var(--danger)">✘</span>@endif<br>@endforeach</td>
<td><span class="status {{ $s->result === 'PASS' ? 'status-completed' : ($s->result === 'FAIL' ? 'status-cancelled' : '') }}">{{ $s->result }}</span></td></tr>
@empty<tr><td colspan="5" class="muted">No tests yet.</td></tr>@endforelse</tbody></table></div></div>

@include('limits._panel', ['type' => 'SAND_SYSTEM', 'id' => $system->id, 'title' => 'Limits for '.$system->name.' sand'])
@endif
@endsection
