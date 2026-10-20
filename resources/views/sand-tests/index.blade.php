@extends('layouts.app')
@section('title', 'Sand lab')
@section('content')
<div class="page-heading" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px"><div><h1>Sand lab</h1><p>Test the sand each shift. A failed or missing test is shown on the moulding run and when a run is started.</p></div>
<form method="GET">@if($run)<input type="hidden" name="moulding_run_id" value="{{ $run->id }}">@endif<select name="system" onchange="this.form.submit()" aria-label="Sand system"><option value="all" @selected(! $system)>Sand System: All Sands</option>@foreach($systems as $s)<option value="{{ $s->id }}" @selected($system?->id === $s->id)>Sand System: {{ $s->name }}</option>@endforeach</select></form></div>
@if($run)<div class="panel"><div class="panel-body">Testing for moulding run <strong class="code">{{ $run->run_number }}</strong>@if($run->patternRevision?->pattern) · {{ $run->patternRevision->pattern->name }} <span class="code muted">{{ $run->patternRevision->pattern->code }}</span>@endif · <a href="{{ route('moulding-runs.show', $run) }}">Back to the run</a></div></div>@endif
@if($errors->has('sand'))<div class="alert alert-error">{{ $errors->first('sand') }}</div>@endif
<div class="panel"><div class="panel-body"><details @if($run || $errors->has('sand')) open @endif><summary style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;list-style:none"><h2 style="margin:0;font-size:16px">Record a test</h2><span class="btn btn-primary">+ Record new test</span></summary>
<div style="margin-top:14px"><form method="POST" action="{{ route('sand-tests.store') }}" class="form-grid">@csrf
@if($run)<input type="hidden" name="moulding_run_id" value="{{ $run->id }}">
<label>Link this test to<select name="link_to"><option value="run">This run — {{ $run->run_number }}</option>@if($run->patternRevision)<option value="pattern">The pattern only — {{ $run->patternRevision->pattern?->name }}</option>@endif</select></label>@endif
<label>Sand system<select name="sand_system_id" required>@if(! $system)<option value="">Choose…</option>@endif @foreach($systems as $x)<option value="{{ $x->id }}" @selected($system?->id === $x->id)>{{ $x->name }}</option>@endforeach</select></label>
<label>Sample point<select name="sample_point"><option value="">—</option>@foreach($points as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
<label>Station<select name="moulding_station_id"><option value="">—</option>@foreach($stations as $st)<option value="{{ $st->id }}" @selected($run?->moulding_station_id === $st->id)>{{ $st->code }}</option>@endforeach</select></label>
<label>Shift<select name="shift"><option value="">—</option>@foreach(['Morning','Evening','Night','General'] as $x)<option @selected($run?->shift === $x)>{{ $x }}</option>@endforeach</select></label>
<label>Tested by<input name="tested_by"></label>
@include('maintenance::gauges._select', ['plain' => true, 'id' => 'sand_gauge', 'label' => 'Equipment from the register', 'types' => ['SAND_TEST', 'BALANCE']])
@foreach($rows as $r)<label>{{ $r['name'] }}@if($r['unit']) ({{ $r['unit'] }})@endif<input type="number" step="any" name="values[{{ $r['key'] }}]">@if($r['min'] !== null || $r['max'] !== null)<span class="muted small">limit {{ $r['min'] ?? '' }}–{{ $r['max'] ?? '' }}</span>@endif</label>@endforeach
<div><button class="btn btn-primary">Save test</button></div></form></div></details></div></div>

@php
    $fmt = fn ($v, $d = 4) => rtrim(rtrim(number_format((float) $v, $d, '.', ''), '0'), '.');
    $recent = $sets->concat($mouldSets)->sortByDesc('taken_at')->take(30);
@endphp
<div class="panel"><div class="panel-header"><h2>Recent tests &amp; measurements</h2><span class="muted small">Showing last {{ $recent->count() }} entries</span></div><div class="table-wrap"><table data-col-filter><thead><tr><th>When</th><th>Type</th><th>Sand system</th><th>Point / Mould</th><th>Shift / Run</th><th>Tested by</th><th style="text-align:right">Results</th></tr></thead><tbody>
@forelse($recent as $s)
@php($isMould = $s->kind === 'MOULD')
<tr><td>{{ $s->taken_at->displayShortDateTime() }}</td>
<td><span class="status {{ $isMould ? 'status-completed' : '' }}">{{ $isMould ? 'HARDNESS TEST' : 'SAND TEST' }}</span></td>
<td>{{ $systemNames[$s->sand_system_id] ?? '—' }}</td>
<td class="code">{{ $isMould ? ($s->mouldAssembly?->assembly_number ?? '—') : ($points[$s->sample_point] ?? $s->sample_point ?? '—') }}</td>
<td>@if($s->shift)<div>{{ $s->shift }}</div>@endif @if($s->mouldingRun)<a class="code" href="{{ route('moulding-runs.show', $s->mouldingRun) }}">{{ $s->mouldingRun->run_number }}</a>@endif @if($s->pattern)<div class="small">{{ $s->pattern->name }} <span class="code muted">{{ $s->pattern->code }}</span></div>@endif</td>
<td>{{ $s->tested_by ?: 'System' }}</td>
<td style="text-align:right">
@if($isMould)@php($by = $s->measurements->keyBy('characteristic_key'))<div class="code">Lowest: {{ $by->has('hardness_b_min') ? $fmt($by['hardness_b_min']->value, 2) : '—' }}, Avg: {{ $by->has('hardness_b_avg') ? $fmt($by['hardness_b_avg']->value, 2) : '—' }}</div>
@else @foreach($s->measurements as $m)<div class="code">{{ $m->name }}: <strong>{{ $fmt($m->value) }}</strong> {{ $m->unit }}@if($m->passed === false) <span style="color:var(--danger)">✘</span>@endif</div>@endforeach @endif
<span class="status {{ $s->result === 'PASS' ? 'status-completed' : ($s->result === 'FAIL' ? 'status-cancelled' : '') }}">{{ $s->result }}</span></td></tr>
@empty<tr><td colspan="7" class="muted">No tests yet.</td></tr>@endforelse</tbody></table></div></div>

@include('sand-tests._limits')
<script>
(function () {
    document.querySelectorAll('table[data-col-filter]').forEach(function (t) {
        var head = t.tHead.rows[0], bar = document.createElement('tr'), inputs = [];
        Array.prototype.forEach.call(head.cells, function (th) {
            var c = document.createElement('th'), i = null;
            if (th.textContent.trim()) { i = document.createElement('input'); i.type = 'search'; i.placeholder = 'Filter'; i.style.width = '100%'; i.setAttribute('aria-label', 'Filter ' + th.textContent.trim()); c.appendChild(i); }
            inputs.push(i); bar.appendChild(c);
        });
        t.tHead.appendChild(bar);
        function text(cell) { var v = cell.textContent; cell.querySelectorAll('input:not([type=checkbox]),select').forEach(function (f) { v += ' ' + f.value; }); return v.toLowerCase(); }
        function apply() {
            Array.prototype.forEach.call(t.tBodies[0].rows, function (r) {
                if (r.cells.length !== inputs.length) return;
                var ok = inputs.every(function (i, k) { return !i || !i.value.trim() || text(r.cells[k]).indexOf(i.value.trim().toLowerCase()) !== -1; });
                r.hidden = !ok;
            });
        }
        inputs.forEach(function (i) { if (i) i.addEventListener('input', apply); });
    });
})();
</script>
@endsection
