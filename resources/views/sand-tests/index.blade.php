@extends('layouts.app')
@section('title', 'Sand lab')
@section('content')
<div class="page-heading"><div><h1>Sand lab</h1><p>Test the sand each shift. A failed or missing test is shown on the moulding run and when a run is started.</p></div></div>
@if($run)<div class="panel"><div class="panel-body">Testing for moulding run <strong class="code">{{ $run->run_number }}</strong>@if($run->patternRevision?->pattern) · {{ $run->patternRevision->pattern->name }} <span class="code muted">{{ $run->patternRevision->pattern->code }}</span>@endif · <a href="{{ route('moulding-runs.show', $run) }}">Back to the run</a></div></div>@endif
@if($errors->has('sand'))<div class="alert alert-error">{{ $errors->first('sand') }}</div>@endif
@if($system)
<div class="panel"><div class="panel-body"><form method="GET" style="display:flex;gap:10px;align-items:center">Sand system
<select name="system" onchange="this.form.submit()">@foreach($systems as $s)<option value="{{ $s->id }}" @selected($s->id === $system->id)>{{ $s->name }}</option>@endforeach</select>
<span class="muted small">{{ $system->description }}</span></form></div></div>

<div class="panel"><div class="panel-header"><h2>Record a test</h2></div><div class="panel-body"><form method="POST" action="{{ route('sand-tests.store') }}" class="form-grid">@csrf
<input type="hidden" name="sand_system_id" value="{{ $system->id }}">
@if($run)<input type="hidden" name="moulding_run_id" value="{{ $run->id }}">
<label>Link this test to<select name="link_to"><option value="run">This run — {{ $run->run_number }}</option>@if($run->patternRevision)<option value="pattern">The pattern only — {{ $run->patternRevision->pattern?->name }}</option>@endif</select></label>@endif
<label>Sample point<select name="sample_point"><option value="">—</option>@foreach($points as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
<label>Station<select name="moulding_station_id"><option value="">—</option>@foreach($stations as $st)<option value="{{ $st->id }}" @selected($run?->moulding_station_id === $st->id)>{{ $st->code }}</option>@endforeach</select></label>
<label>Shift<select name="shift"><option value="">—</option>@foreach(['Morning','Evening','Night','General'] as $x)<option @selected($run?->shift === $x)>{{ $x }}</option>@endforeach</select></label>
<label>Tested by<input name="tested_by"></label>
@include('maintenance::gauges._select', ['plain' => true, 'id' => 'sand_gauge', 'label' => 'Equipment from the register', 'types' => ['SAND_TEST', 'BALANCE']])
@foreach($rows as $r)<label>{{ $r['name'] }}@if($r['unit']) ({{ $r['unit'] }})@endif<input type="number" step="any" name="values[{{ $r['key'] }}]">@if($r['min'] !== null || $r['max'] !== null)<span class="muted small">limit {{ $r['min'] ?? '' }}–{{ $r['max'] ?? '' }}</span>@endif</label>@endforeach
<div><button class="btn btn-primary">Save test</button></div></form></div></div>

<div class="panel"><div class="panel-header"><h2>Recent tests</h2></div><div class="table-wrap"><table><thead><tr><th>When</th><th>Point</th><th>Shift</th><th>Linked to</th><th>Results</th><th>Result</th></tr></thead><tbody>
@forelse($sets as $s)<tr><td>{{ $s->taken_at->displayShortDateTime() }}</td><td>{{ $s->sample_point }}</td><td>{{ $s->shift }}</td><td>@if($s->mouldingRun)<a class="code" href="{{ route('moulding-runs.show', $s->mouldingRun) }}">{{ $s->mouldingRun->run_number }}</a>@endif @if($s->pattern)<div class="small">{{ $s->pattern->name }} <span class="code muted">{{ $s->pattern->code }}</span></div>@endif</td>
<td>@foreach($s->measurements as $m){{ $m->name }}: <strong>{{ rtrim(rtrim(number_format((float) $m->value, 4, '.', ''), '0'), '.') }}</strong> {{ $m->unit }}@if($m->passed === false) <span style="color:var(--danger)">✘</span>@endif<br>@endforeach</td>
<td><span class="status {{ $s->result === 'PASS' ? 'status-completed' : ($s->result === 'FAIL' ? 'status-cancelled' : '') }}">{{ $s->result }}</span></td></tr>
@empty<tr><td colspan="6" class="muted">No tests yet.</td></tr>@endforelse</tbody></table></div></div>

<div class="panel"><div class="panel-header"><h2>Recent mould hardness tests</h2><span class="muted small">Recorded on each run's Moulds tab.</span></div><div class="table-wrap"><table><thead><tr><th>When</th><th>Run</th><th>Mould</th><th>Lowest</th><th>Average</th><th>Result</th></tr></thead><tbody>
@forelse($mouldSets as $s)@php($by = $s->measurements->keyBy('characteristic_key'))<tr><td>{{ $s->taken_at->displayShortDateTime() }}</td><td>@if($s->mouldingRun)<a class="code" href="{{ route('moulding-runs.show', $s->mouldingRun) }}">{{ $s->mouldingRun->run_number }}</a>@endif</td><td class="code">{{ $s->mouldAssembly?->assembly_number ?? 'Whole run' }}</td>
<td class="code">{{ $by->has('hardness_b_min') ? rtrim(rtrim(number_format((float) $by['hardness_b_min']->value, 2, '.', ''), '0'), '.') : '—' }}</td><td class="code">{{ $by->has('hardness_b_avg') ? rtrim(rtrim(number_format((float) $by['hardness_b_avg']->value, 2, '.', ''), '0'), '.') : '—' }}</td>
<td><span class="status {{ $s->result === 'PASS' ? 'status-completed' : ($s->result === 'FAIL' ? 'status-cancelled' : '') }}">{{ $s->result }}</span></td></tr>
@empty<tr><td colspan="6" class="muted">No mould hardness tests yet.</td></tr>@endforelse</tbody></table></div></div>

@include('limits._panel', ['type' => 'SAND_SYSTEM', 'id' => $system->id, 'title' => 'Limits for '.$system->name.' sand'])
@endif
@endsection
