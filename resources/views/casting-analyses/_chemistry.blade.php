@php($m = $rec['melt'])
<div class="panel">
    <div class="panel-header"><h2>{{ $rec['family_label'] }}: recommended melt</h2><span class="muted small">{{ $m['section_tier'] }}</span></div>
    <div class="panel-body">
        <p style="margin-top:0"><strong>Why this material.</strong> {{ $rec['family_reason'] }}</p>
        <div class="detail-grid">
            <div><div class="detail-label">Carbon equivalent</div><div class="detail-value">{{ $n($m['ce'][0], 1) }} to {{ $n($m['ce'][1], 1) }}</div></div>
            <div><div class="detail-label">Pouring temperature</div><div class="detail-value small">{{ $m['pour_text'] }}</div></div>
            <div><div class="detail-label">Inoculation</div><div class="detail-value small">{{ $m['inoculation'] }}</div></div>
            @if ($m['nodule_target'])
                <div><div class="detail-label">Nodule count target</div><div class="detail-value">≥ {{ $m['nodule_target'] }} per mm²</div></div>
            @endif
        </div>
        @foreach ($m['notes'] as $note)<p class="muted small" style="margin-bottom:0">{{ $note }}</p>@endforeach
    </div>
</div>

@if (! empty($m['conflicts']))
    <div class="alert alert-error"><strong>Conflict with the selected grade:</strong>
        <ul style="margin:6px 0 0 18px">@foreach ($m['conflicts'] as $c)<li>{{ $c }}</li>@endforeach</ul></div>
@endif

<div class="panel">
    <div class="panel-header"><h2>Chemistry window (mass %)</h2></div>
    <div class="table-wrap"><table class="no-row-click ca-wrap">
        <thead><tr><th>Element</th><th>From</th><th>To</th><th>Aim</th>@if ($a->grade)<th>Grade {{ $a->grade->code }}</th>@endif<th>Note</th></tr></thead>
        <tbody>@foreach ($m['rows'] as $r)
            <tr>
                <td><strong>{{ $r['element'] }}</strong></td>
                <td>{{ $r['min'] }}</td><td>{{ $r['max'] }}</td><td><strong>{{ $r['target'] }}</strong></td>
                @if ($a->grade)<td class="small">{{ isset($r['grade_min']) || isset($r['grade_max']) ? (($r['grade_min'] ?? '—').' to '.($r['grade_max'] ?? '—')) : '—' }}</td>@endif
                <td class="small">{{ $r['note'] }}</td>
            </tr>
        @endforeach</tbody>
    </table></div>
</div>

@if ($m['mg'])
<div class="panel">
    <div class="panel-header"><h2>Magnesium treatment</h2></div>
    <div class="panel-body">
        <p style="margin:0">{{ $m['mg']['text'] }}</p>
        <p class="muted small" style="margin-bottom:0">Base sulphur above 0.025 % wastes magnesium and risks low nodularity. Too much magnesium forms carbides; too little gives flake or compacted graphite.</p>
    </div>
</div>
@endif

<div class="panel">
    <div class="panel-header"><h2>Grades in your list that fit</h2></div>
    <div class="panel-body">
        @if (! empty($m['grade_candidates']))
            <div class="table-wrap"><table class="no-row-click ca-wrap">
                <thead><tr><th>Grade</th><th>Name</th><th>Min tensile</th><th>Min yield</th><th>Min elongation</th></tr></thead>
                <tbody>@foreach ($m['grade_candidates'] as $g)
                    <tr><td><strong>{{ $g['code'] }}</strong></td><td>{{ $g['name'] }}</td><td>{{ $g['min_tensile_mpa'] ?? '—' }} MPa</td><td>{{ $g['min_yield_mpa'] ?? '—' }} MPa</td><td>{{ $g['min_elongation_pct'] ?? '—' }} %</td></tr>
                @endforeach</tbody>
            </table></div>
            <p class="muted small" style="margin-bottom:0">Lowest strength that still meets the requirement first. Section size lowers the strength actually reached in a casting compared with a separately cast test bar, so keep a margin.</p>
        @else
            <p class="muted" style="margin:0">No active {{ $rec['family_label'] }} grade in your list meets the entered requirement. Add grades under Quality → Iron grades, or relax the requirement.</p>
        @endif
    </div>
</div>
