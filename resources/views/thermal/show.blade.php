@extends('layouts.app')

@section('title', 'Thermal test')
@section('breadcrumb', 'Thermal tests / '.($test->sample_reference ?: $test->taken_at->displayShortDateTime()))

@php
    $pts = $test->curve ?? [];
    $step = max(1, (int) floor(count($pts) / 1500));
    $pts = array_values(array_filter($pts, fn ($p, $i) => $i % $step === 0, ARRAY_FILTER_USE_BOTH));
    $marks = array_filter(['TL' => $test->tl_c, 'TEmax' => $test->te_max_c, 'TEmin' => $test->te_min_c, 'Solidus' => $test->solidus_c], fn ($v) => $v !== null);
    $marks = array_map('floatval', $marks);
    $chartData = ['points' => $pts, 'marks' => $marks];
@endphp

@section('content')
<div class="page-heading">
    <div><h1>Thermal test — <x-heat-light :heat="$test->heat" />heat {{ $test->heat?->heat_number }}</h1><p>{{ $test->ironGrade?->code ?? 'No grade' }} · {{ $test->taken_at->displayDateTime() }} · {{ $test->cup_type === 'TELLURIUM' ? 'white (tellurium)' : 'grey (stable)' }} cup · {{ str_replace('_', ' ', $test->stage) }}@if ($test->gauge) · measured with <a href="{{ route('gauges.show', $test->gauge) }}">{{ $test->gauge->code }}</a>@endif</p></div>
    <div class="actions"><a class="btn" href="{{ route('thermal.index') }}">All tests</a><a class="btn" href="{{ route('thermal.create', ['heat_id' => $test->heat_id]) }}">New test on this heat</a></div>
</div>
<p class="muted small" style="margin:0 0 12px">
    Sample taken {{ $test->taken_at->displayDateTime() }}@if ($test->sample_point) from the {{ strtolower(\App\Models\ThermalAnalysisTest::SAMPLE_POINTS[$test->sample_point] ?? $test->sample_point) }}@endif
    @if ($test->test_started_at) · started {{ $test->test_started_at->displayDateTime() }}@endif
    @if ($test->test_finished_at) · finished {{ $test->test_finished_at->displayDateTime() }}@endif
    @if ($test->testDurationSeconds() !== null) ({{ intdiv($test->testDurationSeconds(), 60) }} min {{ $test->testDurationSeconds() % 60 }} s)@endif
    · entered {{ $test->created_at->displayDateTime() }}@if ($test->entered_by_name) by {{ $test->entered_by_name }}@endif
    @if ($test->cup_batch) · cup batch {{ $test->cup_batch }}@endif
</p>
@if ($test->isVoided())
    <div class="alert alert-error"><strong>Voided</strong> {{ $test->voided_at->displayDateTime() }}@if ($test->voided_by_name) by {{ $test->voided_by_name }}@endif: {{ $test->void_reason }}. It no longer counts for gates, certificates or calibration.</div>
@else
<div class="panel">
    <div class="panel-header"><h2>Wrong reading?</h2></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('thermal.void', $test) }}" class="form-inline">@csrf
            <div class="field"><label for="void_reason">Reason for voiding *</label><input id="void_reason" name="void_reason" maxlength="500" required placeholder="e.g. TL keyed in wrongly"></div>
            <button class="btn" type="submit">Void and enter corrected test</button>
        </form>
    </div>
</div>
@endif
@foreach ($test->siblings as $other)
    <div class="alert">Other cup from this sample: <a href="{{ route('thermal.show', $other) }}">{{ $other->cup_type === 'TELLURIUM' ? 'White (tellurium)' : 'Grey (stable)' }} cup</a> — {{ str_replace('_', ' ', $other->result) }}.</div>
@endforeach

<div class="metrics">
    <div class="metric"><div class="metric-label">Result</div><div class="metric-value" style="font-size:22px">{{ str_replace('_', ' ', $test->result) }}</div></div>
    <div class="metric"><div class="metric-label">TL</div><div class="metric-value" style="font-size:22px">{{ $test->tl_c ?? '—' }} °C</div></div>
    <div class="metric"><div class="metric-label">TEmin / TEmax</div><div class="metric-value" style="font-size:22px">{{ $test->te_min_c }} / {{ $test->te_max_c }}</div></div>
    <div class="metric"><div class="metric-label">Recalescence</div><div class="metric-value" style="font-size:22px">{{ $test->recalescence_c }} °C</div></div>
    <div class="metric"><div class="metric-label">TS (solidus)</div><div class="metric-value" style="font-size:22px">{{ $test->solidus_c ?? '—' }} °C</div></div>
    <div class="metric"><div class="metric-label">Peak temperature</div><div class="metric-value" style="font-size:22px">{{ $test->peak_temp_c ?? '—' }} °C</div></div>
    <div class="metric"><div class="metric-label">C / Si</div><div class="metric-value" style="font-size:22px">{{ $test->carbon_reported_pct !== null ? rtrim(rtrim((string) $test->carbon_reported_pct, '0'), '.') : '—' }} / {{ $test->silicon_used !== null ? rtrim(rtrim((string) $test->silicon_used, '0'), '.') : '—' }}</div><div class="metric-note">{{ $test->carbon_reported_pct !== null ? 'C as shown by the analyser' : 'No carbon keyed in' }}</div></div>
    <div class="metric"><div class="metric-label">CE</div><div class="metric-value" style="font-size:22px">{{ $test->ce_from_tl ?? '—' }}</div><div class="metric-note">{{ $test->ceSourceLabel() ? 'CE '.$test->ceSourceLabel().'. ' : '' }}Carbon {{ $test->carbon_from_tl !== null ? rtrim(rtrim((string) $test->carbon_from_tl, '0'), '.') : '—' }}{{ $test->silicon_used !== null ? ' at Si '.$test->silicon_used : '' }}</div></div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Findings</h2></div>
    <div class="panel-body">
        @if (empty($test->findings))<p>All judged values are within the window.</p>@else<ul>@foreach ($test->findings as $f)<li>{{ $f }}</li>@endforeach</ul>@endif
        @if ($advice)
            <div class="alert {{ $advice['needed'] ? 'alert-error' : 'alert-success' }}">
                @if ($advice['needed'])
                    <strong>Add about {{ $advice['suggest_pct'] }} % inoculant:</strong> TEmin is {{ $advice['deficit_c'] }} °C below the floor{{ $advice['capped'] ? ' (limited to the plant maximum; check the iron before adding more)' : '' }}.
                    Based on {{ $advice['calibrated'] ? 'your history' : 'an uncalibrated default of '.$advice['gain'].' °C per 0.1 %' }}.
                @else TEmin meets the floor. @endif
            </div>
        @endif
        @if (! empty($pts))<p class="muted small">Always confirm the picked points on the chart. A very strong primary arrest (high-carbon hypereutectic iron) can be mistaken for the eutectic.</p>@endif
    </div>
</div>

@if (empty($pts))
<div class="panel">
    <div class="panel-header"><h2>Cooling curve</h2></div>
    <div class="panel-body"><p class="muted">{{ $test->entry_mode === 'MANUAL' ? 'This test was keyed in from the analyser readings, so there is no cooling curve to show. Record the test again with the curve to see the graph.' : 'No curve data stored for this test.' }}</p></div>
</div>
@else
<div class="panel">
    <div class="panel-header"><h2>Cooling curve</h2></div>
    <div class="panel-body">
        <div class="cc-controls" id="cc-controls">
            <div class="cc-presets" role="group" aria-label="Zoom range">
                <button type="button" data-view="full" class="active">Full curve</button>
                <button type="button" data-view="solid">Solidification range</button>
                <button type="button" data-view="eutectic">Eutectic arrest</button>
                <button type="button" data-view="custom">Custom</button>
            </div>
            <div class="cc-custom">
                <label>From <input type="number" id="cc-lo" step="1"> °C</label>
                <label>to <input type="number" id="cc-hi" step="1"> °C</label>
                <button type="button" id="cc-apply">Apply</button>
            </div>
            <label class="cc-check"><input type="checkbox" id="cc-rate"> Show cooling rate (dT/dt)</label>
        </div>
        <p class="muted small" id="cc-caption"></p>
        <svg id="cc-svg" role="img" aria-label="Cooling curve" style="width:100%;height:auto;display:block;touch-action:none"></svg>
    </div>
</div>

<style>
    .cc-controls { display:flex; flex-wrap:wrap; gap:14px; align-items:center; margin-bottom:8px; }
    .cc-presets { display:inline-flex; border:1px solid var(--border); border-radius:6px; overflow:hidden; }
    .cc-presets button { border:0; border-right:1px solid var(--border); background:transparent; padding:7px 12px; font:inherit; font-size:13px; cursor:pointer; color:var(--text); }
    .cc-presets button:last-child { border-right:0; }
    .cc-presets button.active { color:var(--accent-dark); font-weight:700; text-decoration:underline; text-underline-offset:4px; }
    .cc-presets button:disabled { opacity:.4; cursor:not-allowed; }
    .cc-custom { display:inline-flex; align-items:center; gap:8px; font-size:13px; }
    .cc-custom input { width:76px; padding:5px 6px; border:1px solid var(--border); border-radius:5px; font:inherit; }
    .cc-custom button { padding:6px 10px; border:1px solid var(--border); border-radius:5px; background:transparent; font:inherit; font-size:13px; cursor:pointer; }
    .cc-check { font-size:13px; display:inline-flex; gap:6px; align-items:center; }
</style>

<script id="cc-data" type="application/json">@json($chartData)</script>
<script>
(() => {
    const data = JSON.parse(document.getElementById('cc-data').textContent);
    const pts = data.points || [];
    const marks = data.marks || {};
    const svg = document.getElementById('cc-svg');
    if (!pts.length) { svg.outerHTML = '<p class="muted">No curve data stored for this test.</p>'; return; }

    const temps = pts.map(p => p[1]);
    const fullLo = Math.floor(Math.min(...temps) / 10) * 10;
    const fullHi = Math.ceil(Math.max(...temps) / 10) * 10;
    const pad = (v, d) => v + d;

    const views = {
        full: () => [fullLo, fullHi],
        solid: () => {
            const top = marks.TL ?? marks.TEmax, bottom = marks.Solidus ?? marks.TEmin;
            return (top == null || bottom == null) ? null : [bottom - 20, top + 20];
        },
        eutectic: () => {
            if (marks.TEmin == null || marks.TEmax == null) return null;
            const lo = Math.min(marks.TEmin, marks.TEmax), hi = Math.max(marks.TEmin, marks.TEmax);
            return [lo - 10, hi + 10];
        },
    };

    const buttons = [...document.querySelectorAll('#cc-controls [data-view]')];
    buttons.forEach(b => { if (views[b.dataset.view] && !views[b.dataset.view]()) b.disabled = true; });

    const loIn = document.getElementById('cc-lo'), hiIn = document.getElementById('cc-hi');
    const rateBox = document.getElementById('cc-rate');
    const caption = document.getElementById('cc-caption');
    let range = [fullLo, fullHi];
    let current = 'full';

    // Cooling rate (°C/s), central difference over a short window to tame noise.
    const win = Math.max(2, Math.round(pts.length / 150));
    const rate = pts.map((p, i) => {
        const a = Math.max(0, i - win), b = Math.min(pts.length - 1, i + win);
        const dt = pts[b][0] - pts[a][0];
        return dt > 0 ? (pts[b][1] - pts[a][1]) / dt : 0;
    });

    function niceStep(span, target) {
        const raw = span / target, mag = Math.pow(10, Math.floor(Math.log10(raw)));
        for (const m of [1, 2, 5, 10]) if (raw <= m * mag) return m * mag;
        return 10 * mag;
    }
    const fmt = v => Number.isInteger(v) ? String(v) : v.toFixed(Math.abs(v) < 10 ? 2 : 1);

    function draw() {
        const [lo, hi] = range;
        const showRate = rateBox.checked;
        const W = 760, topH = 320, rateH = showRate ? 110 : 0, H = topH + rateH;
        const L = 52, R = 96, T = 22, B = 28;
        svg.setAttribute('viewBox', `0 0 ${W} ${H}`);

        // x-range: time span in which the curve lies inside the chosen temperature window
        const inside = pts.map((p, i) => [p, i]).filter(([p]) => p[1] >= lo && p[1] <= hi);
        const vis = inside.length >= 2 ? inside : pts.map((p, i) => [p, i]);
        let t0 = vis[0][0][0], t1 = vis[vis.length - 1][0][0];
        if (current === 'full') { t0 = pts[0][0]; t1 = pts[pts.length - 1][0]; }
        if (t1 <= t0) t1 = t0 + 1;

        const x = t => L + (W - L - R) * (t - t0) / (t1 - t0);
        const y = v => T + (topH - T - B) * (1 - (v - lo) / (hi - lo));
        const clipY = v => Math.max(T, Math.min(topH - B, y(v)));
        let out = `<defs><clipPath id="cc-clip"><rect x="${L}" y="${T}" width="${W - L - R}" height="${topH - T - B}"/></clipPath></defs>`;
        out += `<rect x="${L}" y="${T}" width="${W - L - R}" height="${topH - T - B}" fill="none" stroke="var(--border)"/>`;

        const ys = niceStep(hi - lo, 7);
        for (let v = Math.ceil(lo / ys) * ys; v <= hi + 1e-9; v += ys) {
            out += `<line x1="${L}" x2="${W - R}" y1="${y(v).toFixed(1)}" y2="${y(v).toFixed(1)}" stroke="#e6eaf0"/>`;
            out += `<text x="${L - 6}" y="${(y(v) + 4).toFixed(1)}" text-anchor="end" font-size="11" fill="#5b6675">${fmt(v)}</text>`;
        }
        const xs = niceStep(t1 - t0, 8);
        for (let t = Math.ceil(t0 / xs) * xs; t <= t1 + 1e-9; t += xs) {
            out += `<line x1="${x(t).toFixed(1)}" x2="${x(t).toFixed(1)}" y1="${T}" y2="${topH - B}" stroke="#f0f2f6"/>`;
            out += `<text x="${x(t).toFixed(1)}" y="${topH - 10}" text-anchor="middle" font-size="11" fill="#5b6675">${fmt(t)}</text>`;
        }
        out += `<text x="${W - R + 4}" y="${topH - 10}" font-size="11" fill="#5b6675">time (s)</text>`;
        out += `<text x="${L - 6}" y="${T - 7}" text-anchor="end" font-size="11" fill="#5b6675">°C</text>`;

        // Marker lines drawn only inside the visible window; labels nudged apart.
        let lastLabelY = -99;
        Object.entries(marks).filter(([, v]) => v >= lo && v <= hi).sort((a, b) => y(a[1]) - y(b[1])).forEach(([name, v]) => {
            const yy = y(v);
            const ly = Math.max(yy + 4, lastLabelY + 13);
            lastLabelY = ly;
            out += `<line x1="${L}" x2="${W - R}" y1="${yy.toFixed(1)}" y2="${yy.toFixed(1)}" stroke="#b5532b" stroke-dasharray="4 3"/>`;
            out += `<text x="${W - R + 4}" y="${ly.toFixed(1)}" font-size="11" fill="#b5532b">${name} ${fmt(v)}</text>`;
        });

        const line = pts.map(p => `${x(p[0]).toFixed(1)},${y(p[1]).toFixed(1)}`).join(' ');
        out += `<polyline clip-path="url(#cc-clip)" points="${line}" fill="none" stroke="#1d4f91" stroke-width="1.8"/>`;

        if (showRate) {
            const r0 = topH + 8, r1 = H - B + 6;
            const inRange = vis.map(([, i]) => rate[i]);
            let rLo = Math.min(...inRange, 0), rHi = Math.max(...inRange, 0);
            if (rHi - rLo < 0.01) { rHi = rLo + 0.01; }
            const yr = v => r0 + (r1 - r0) * (1 - (v - rLo) / (rHi - rLo));
            out += `<rect x="${L}" y="${r0}" width="${W - L - R}" height="${r1 - r0}" fill="none" stroke="var(--border)"/>`;
            out += `<line x1="${L}" x2="${W - R}" y1="${yr(0).toFixed(1)}" y2="${yr(0).toFixed(1)}" stroke="#d6dbe3"/>`;
            out += `<text x="${L - 6}" y="${(yr(rHi) + 10).toFixed(1)}" text-anchor="end" font-size="10" fill="#5b6675">${rHi.toFixed(1)}</text>`;
            out += `<text x="${L - 6}" y="${(yr(rLo)).toFixed(1)}" text-anchor="end" font-size="10" fill="#5b6675">${rLo.toFixed(1)}</text>`;
            out += `<text x="${W - R + 4}" y="${r0 + 10}" font-size="11" fill="#2f7d4f">dT/dt °C/s</text>`;
            const rl = pts.map((p, i) => `${x(p[0]).toFixed(1)},${Math.max(r0, Math.min(r1, yr(rate[i]))).toFixed(1)}`).join(' ');
            out += `<clipPath id="cc-clip2"><rect x="${L}" y="${r0}" width="${W - L - R}" height="${r1 - r0}"/></clipPath>`;
            out += `<polyline clip-path="url(#cc-clip2)" points="${rl}" fill="none" stroke="#2f7d4f" stroke-width="1.4"/>`;
        }

        // Hover readout
        out += `<g id="cc-hover" style="display:none"><line id="cc-vl" y1="${T}" y2="${topH - B}" stroke="#7a8696" stroke-dasharray="2 2"/><circle id="cc-dot" r="3.5" fill="#1d4f91"/><text id="cc-tip" font-size="11" fill="#202a24"></text></g>`;
        svg.innerHTML = out;

        svg._m = {x, y, t0, t1, L, R, W, T, topH, B, lo, hi};
        const shown = current === 'full' ? 'whole curve' : `${fmt(lo)}–${fmt(hi)} °C`;
        caption.textContent = `Showing ${shown}, ${fmt(t0)}–${fmt(t1)} s. Hover the curve to read values.`;
    }

    svg.addEventListener('pointermove', e => {
        const m = svg._m; if (!m) return;
        const box = svg.getBoundingClientRect();
        const px = (e.clientX - box.left) * (m.W / box.width);
        const g = svg.querySelector('#cc-hover');
        if (px < m.L || px > m.W - m.R) { g.style.display = 'none'; return; }
        const t = m.t0 + (px - m.L) / (m.W - m.L - m.R) * (m.t1 - m.t0);
        let best = 0, bd = Infinity;
        pts.forEach((p, i) => { const d = Math.abs(p[0] - t); if (d < bd) { bd = d; best = i; } });
        const p = pts[best], xx = m.x(p[0]), yy = m.y(p[1]);
        if (yy < m.T || yy > m.topH - m.B) { g.style.display = 'none'; return; }
        g.style.display = '';
        g.querySelector('#cc-vl').setAttribute('x1', xx); g.querySelector('#cc-vl').setAttribute('x2', xx);
        const dot = g.querySelector('#cc-dot'); dot.setAttribute('cx', xx); dot.setAttribute('cy', yy);
        const tip = g.querySelector('#cc-tip');
        tip.textContent = `${p[1].toFixed(1)} °C · ${p[0].toFixed(1)} s · ${rate[best].toFixed(2)} °C/s`;
        const right = xx > m.W - m.R - 190;
        tip.setAttribute('x', right ? xx - 8 : xx + 8); tip.setAttribute('text-anchor', right ? 'end' : 'start');
        tip.setAttribute('y', Math.max(m.T + 12, yy - 8));
    });
    svg.addEventListener('pointerleave', () => { const g = svg.querySelector('#cc-hover'); if (g) g.style.display = 'none'; });

    function setView(name, custom) {
        current = name;
        range = name === 'custom' ? custom : views[name]();
        buttons.forEach(b => b.classList.toggle('active', b.dataset.view === name));
        loIn.value = Math.round(range[0]); hiIn.value = Math.round(range[1]);
        draw();
    }
    buttons.forEach(b => b.addEventListener('click', () => {
        if (b.dataset.view === 'custom') { setView('custom', [Number(loIn.value), Number(hiIn.value)]); }
        else setView(b.dataset.view);
    }));
    const applyCustom = () => {
        let lo = Number(loIn.value), hi = Number(hiIn.value);
        if (!isFinite(lo) || !isFinite(hi) || hi - lo < 2) return;
        setView('custom', [lo, hi]);
    };
    document.getElementById('cc-apply').addEventListener('click', applyCustom);
    [loIn, hiIn].forEach(i => i.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); applyCustom(); } }));
    rateBox.addEventListener('change', draw);

    // Open on the most useful view: the solidification range when it is known, else the full curve.
    setView(views.solid() ? 'solid' : 'full');
})();
</script>
@endif
@include('attachments._panel', ['type' => 'thermal_test', 'id' => $test->id])
@endsection
