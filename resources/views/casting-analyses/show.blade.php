@extends('layouts.app')

@section('title', $a->code)
@section('breadcrumb')
<a href="{{ route('casting-analyses.index') }}">Casting analysis</a> / {{ $a->code }}
@endsection

@section('content')
<style>.ca-wrap td, .ca-wrap th { white-space: normal; vertical-align: top; } input[type=range], input[type=checkbox], input[type=radio] { accent-color: var(--accent); }</style>
@php
    $res = $a->result;
    $rec = $a->recommendation;
    $ok = $a->status === 'COMPLETE' && $res && $rec;
    $sevClass = ['HIGH' => 'status-cancelled', 'MEDIUM' => 'status-on-hold', 'LOW' => 'status-in-progress', 'INFO' => 'status-completed'];
    $roleLabel = ['PRIMARY' => 'Last to freeze', 'ISOLATED_SEVERE' => 'Cut off (severe)', 'ISOLATED' => 'Cut off'];
    $n = fn ($v, $d = 1) => number_format((float) $v, $d);
@endphp
<div class="page-heading">
    <div>
        <h1>{{ $a->name }}</h1>
        <p>{{ $a->code }} · {{ $a->original_name }} ({{ $a->source_type }}, {{ $n($a->file_size / 1024, 0) }} KB){!! $a->product ? ' · <a href="'.e(route('products.show', $a->product)).'">'.e($a->product->code).'</a>' : '' !!}</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('casting-analyses.index') }}">All analyses</a>
        <form method="POST" action="{{ route('casting-analyses.destroy', $a) }}" onsubmit="return confirm('Delete this analysis and its stored model?')" style="display:inline">
            @csrf @method('DELETE')
            <button class="btn btn-danger" type="submit">Delete</button>
        </form>
    </div>
</div>

@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif

@if (! $ok)
    <div class="alert alert-error"><strong>This analysis did not complete.</strong> {{ $a->error }}</div>
@endif

@if ($ok)
<div class="tab-marker" data-tab-start="Summary"></div>

<div class="metrics">
    <div class="metric">
        <div class="metric-label">Manufacturability</div>
        <div class="metric-value">{{ $rec['score']['total'] }} <span class="muted" style="font-size:15px">/ 100 · {{ $rec['score']['letter'] }}</span></div>
        <div class="metric-note">{{ ucfirst($rec['score']['verdict']) }}</div>
    </div>
    <div class="metric">
        <div class="metric-label">Casting weight</div>
        <div class="metric-value">{{ $n($rec['weight']['casting_kg'], 2) }} kg</div>
        <div class="metric-note">Poured {{ $n($rec['weight']['poured_kg'], 1) }} kg · yield {{ $n($rec['weight']['yield_pct'], 0) }}%</div>
    </div>
    <div class="metric">
        <div class="metric-label">Last place to freeze</div>
        <div class="metric-value">{{ $n($res['hotspots'][0]['modulus_cm'], 2) }} cm</div>
        <div class="metric-note">≈ {{ $n($rec['times']['hotspots_min'][$res['hotspots'][0]['id']], 1) }} min · {{ $n($res['modulus']['max_cm'] / max($res['modulus']['part_cm'], 1e-9), 1) }}× the average</div>
    </div>
    <div class="metric">
        <div class="metric-label">Cut-off hotspots</div>
        <div class="metric-value">{{ collect($res['hotspots'])->where('role', '!=', 'PRIMARY')->count() }}</div>
        <div class="metric-note">{{ $rec['family_label'] }} · confidence {{ strtolower($res['quality']['confidence']) }}</div>
    </div>
</div>

@if (! empty($res['quality']['warnings']))
    <div class="alert alert-error" style="background:#fff8e6;border-color:#f0d9a0;color:#7a5b00">
        <strong>Check before relying on this:</strong>
        <ul style="margin:6px 0 0 18px">@foreach ($res['quality']['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
    </div>
@endif

<div class="panel">
    <div class="panel-header"><h2>What to look at first</h2></div>
    <div class="table-wrap">
        <table class="ca-wrap">
            <tbody>
            @foreach ($rec['findings'] as $f)
                <tr>
                    <td style="width:90px"><span class="status {{ $sevClass[$f['severity']] }}">{{ $f['severity'] }}</span></td>
                    <td style="width:110px"><strong>{{ $f['area'] }}</strong></td>
                    <td>{{ $f['text'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if (! empty($rec['design_changes']))
<div class="panel">
    <div class="panel-header"><h2>Design changes that would help</h2></div>
    <div class="panel-body"><ul style="margin:0 0 0 18px">@foreach ($rec['design_changes'] as $d)<li style="margin-bottom:6px">{{ $d }}</li>@endforeach</ul></div>
</div>
@endif

<div class="panel">
    <div class="panel-header"><h2>How the score is made</h2><span class="muted small">weighted average</span></div>
    <div class="table-wrap">
        <table class="ca-wrap">
            <thead><tr><th>Part</th><th style="width:200px">Score</th><th>Weight</th><th>Why</th></tr></thead>
            <tbody>
            @foreach (['feeding' => 'Feeding and shrinkage', 'walls' => 'Wall thickness', 'uniformity' => 'Section uniformity', 'moulding' => 'Moulding and cores', 'yield' => 'Yield'] as $k => $label)
                @php($p = $rec['score']['parts'][$k])
                <tr>
                    <td><strong>{{ $label }}</strong></td>
                    <td><div style="background:#e8ece9;border-radius:4px;height:10px;position:relative"><div style="background:var(--accent);height:10px;border-radius:4px;width:{{ $p['score'] }}%"></div></div><span class="small">{{ $p['score'] }} / 100</span></td>
                    <td>{{ $p['weight'] }}</td>
                    <td class="small">{{ implode(' ', $p['why']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Change the choices and run again</h2></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('casting-analyses.rerun', $a) }}" class="form-grid" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('button[type=submit]').textContent='Analysing…'">
            @csrf
            @php($o = $a->options ?? [])
            <div class="field"><label>Units</label><select name="units">@foreach (['mm' => 'Millimetres', 'cm' => 'Centimetres', 'm' => 'Metres', 'in' => 'Inches'] as $k => $l)<option value="{{ $k }}" @selected($a->units === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label>Quality</label><select name="quality">@foreach (['DRAFT' => 'Draft', 'STANDARD' => 'Standard', 'FINE' => 'Fine'] as $k => $l)<option value="{{ $k }}" @selected($a->quality === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label>Iron family</label><select name="family">@foreach (['AUTO' => 'Let the analyser choose', 'GREY' => 'Grey iron', 'DUCTILE' => 'Ductile iron'] as $k => $l)<option value="{{ $k }}" @selected($a->family_requested === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label>Iron grade</label><select name="iron_grade_id"><option value="">— none —</option>@foreach (\App\Models\IronGrade::where('status', 'ACTIVE')->whereIn('family', ['GREY', 'DUCTILE'])->orderBy('code')->get(['id', 'code']) as $g)<option value="{{ $g->id }}" @selected($a->iron_grade_id === $g->id)>{{ $g->code }}</option>@endforeach</select></div>
            <div class="field"><label>Minimum tensile (MPa)</label><input type="number" name="min_tensile" value="{{ $o['min_tensile'] ?? '' }}"></div>
            <div class="field"><label>Minimum elongation (%)</label><input type="number" step="0.5" name="min_elongation" value="{{ $o['min_elongation'] ?? '' }}"></div>
            <div class="field"><label>Feeder type</label><select name="feeder">@foreach (['AUTO' => 'Automatic', 'CONVENTIONAL' => 'Sand riser', 'INSULATED' => 'Insulating sleeve', 'EXOTHERMIC' => 'Exothermic sleeve'] as $k => $l)<option value="{{ $k }}" @selected(($o['feeder'] ?? 'AUTO') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label>Minutes from treatment to last mould</label><input type="number" step="0.5" name="pour_window_min" value="{{ $o['pour_window_min'] ?? 10 }}"></div>
            <div class="field"><label>Chvorinov B (min/cm²)</label><input type="number" step="0.05" name="b_override" value="{{ $o['b_override'] ?? '' }}" placeholder="Default"></div>
            <div class="field" style="align-self:end"><button class="btn btn-primary" type="submit">Run again</button></div>
        </form>
    </div>
</div>

<div class="tab-marker" data-tab-start="Solidification"></div>
@include('casting-analyses._viewer', ['a' => $a, 'res' => $res, 'rec' => $rec, 'roleLabel' => $roleLabel, 'n' => $n])

<div class="tab-marker" data-tab-start="Feeding & tooling"></div>
@include('casting-analyses._tooling', ['a' => $a, 'res' => $res, 'rec' => $rec, 'n' => $n])

<div class="tab-marker" data-tab-start="Chemistry"></div>
@include('casting-analyses._chemistry', ['a' => $a, 'res' => $res, 'rec' => $rec, 'n' => $n])

<div class="tab-marker" data-tab-start="How it was worked out"></div>
@include('casting-analyses._method', ['a' => $a, 'res' => $res, 'rec' => $rec, 'n' => $n])
@endif
@endsection
