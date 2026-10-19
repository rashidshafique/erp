@php
    $svc = app(\App\Services\Quality\Measurements::class);
    $sets = \App\Models\MeasurementSet::with('measurements')->where('heat_id', $heat->id)->whereIn('kind', ['MECHANICAL', 'MICROSTRUCTURE'])->orderByDesc('taken_at')->get();
@endphp
<div class="panel">
    <div class="panel-header"><h2>Mechanical and microstructure tests</h2></div>
    <div class="panel-body">
        @if ($errors->has('test_MECHANICAL') || $errors->has('test_MICROSTRUCTURE'))
            <div class="alert alert-error">{{ $errors->first('test_MECHANICAL') ?: $errors->first('test_MICROSTRUCTURE') }}</div>
        @endif
        @if ($heat->ironGrade)
            <p class="muted small">Limits come from grade {{ $heat->ironGrade->code }} (set under Iron grades → Test requirements).</p>
        @else
            <p class="muted small">This heat has no iron grade, so results are recorded but not judged.</p>
        @endif
    </div>
    @if ($sets->isNotEmpty())
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Kind</th><th>Sample</th><th>Results</th><th>Result</th></tr></thead>
            <tbody>
            @foreach ($sets as $s)
                <tr><td>{{ $s->taken_at->displayShortDateTime() }}</td><td>{{ $s->kind }}</td>
                    <td>{{ $s->sample_reference }}<div class="muted small">{{ \App\Services\Quality\Measurements::SAMPLE_TYPES[$s->sample_type] ?? '' }}</div></td>
                    <td>@foreach ($s->measurements as $m){{ $m->name }}: <strong>{{ $m->value !== null ? rtrim(rtrim(number_format($m->value, 4, '.', ''), '0'), '.') : $m->text_value }}</strong> {{ $m->unit }}@if($m->passed === false) <span style="color:var(--danger)">✘</span>@elseif($m->passed) ✔@endif<br>@endforeach</td>
                    <td><span class="status {{ $s->result === 'PASS' ? 'status-completed' : ($s->result === 'FAIL' ? 'status-cancelled' : '') }}">{{ $s->result }}</span></td></tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
    @foreach (['MECHANICAL' => 'Record mechanical test', 'MICROSTRUCTURE' => 'Record microstructure'] as $kind => $title)
        <div class="panel-body" style="border-top:1px solid var(--border,#ddd)">
            <h3 style="font-size:15px">{{ $title }}</h3>
            <form method="POST" action="{{ route('heats.tests.store', [$heat, $kind]) }}" class="form-grid">@csrf
                <label>Sample reference<input name="sample_reference"></label>
                <label>Sample type<select name="sample_type"><option value="">—</option>@foreach (\App\Services\Quality\Measurements::SAMPLE_TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                <label>Tested by<input name="tested_by"></label>
                @include('gauges._select', ['plain' => true, 'id' => 'test_gauge_'.$kind, 'label' => 'Machine from the register', 'types' => $kind === 'MECHANICAL' ? ['TENSILE_MACHINE', 'HARDNESS_TESTER'] : null])
                <label>Machine (if not in the register)<input name="equipment_reference"></label>
                @foreach ($svc->formRows($kind, 'IRON_GRADE', $heat->iron_grade_id) as $r)
                    <label>{{ $r['name'] }}@if($r['unit']) ({{ $r['unit'] }})@endif
                        <input name="values[{{ $r['key'] }}]" @if($r['numeric']) type="number" step="any" @endif>
                        @if ($r['min'] !== null || $r['max'] !== null)<span class="muted small">limit {{ $r['min'] ?? '' }}–{{ $r['max'] ?? '' }}</span>@endif
                    </label>
                @endforeach
                <div><button class="btn btn-primary">Save</button></div>
            </form>
        </div>
    @endforeach
</div>
