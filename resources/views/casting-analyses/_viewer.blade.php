<style>
    .ca-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 14px; }
    @media (max-width: 1024px) { .ca-grid { grid-template-columns: 1fr; } }
    .ca-ctl { display: flex; flex-wrap: wrap; gap: 8px 14px; align-items: end; margin-bottom: 10px; }
    .ca-ctl label { font-size: 11px; font-weight: 700; display: block; margin-bottom: 3px; }
    .ca-ctl select, .ca-ctl input[type=range] { min-height: 28px; padding: 2px 6px; width: auto; }
    .ca-ctl input[type=range] { padding: 0; min-width: 150px; }
    .ca-canvas { width: 100%; height: auto; display: block; background: #f3f6f4; border: 1px solid var(--border); border-radius: 6px; image-rendering: pixelated; }
    #ca3d { image-rendering: auto; cursor: grab; touch-action: none; aspect-ratio: 4 / 3; }
    .ca-legend { display: flex; align-items: center; gap: 8px; font-size: 11px; color: var(--muted); margin-top: 6px; }
    .ca-bar { height: 10px; flex: 1; border-radius: 5px; background: linear-gradient(90deg, #2c4fb3, #1fa3c9, #3fbf6f, #f0d73c, #e8452c); }
    .ca-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; vertical-align: -1px; }
</style>
<div class="panel">
    <div class="panel-header"><h2>Freezing map</h2><span class="muted small" id="caStatus">loading…</span></div>
    <div class="panel-body">
        <div class="ca-grid">
            <div>
                <div class="ca-ctl">
                    <div><label for="caMode">Show</label>
                        <select id="caMode"><option value="M">Section modulus</option><option value="T">Freezing time</option><option value="L">Liquid remaining (animate)</option></select></div>
                    <div><label for="caAxis">Slice across</label>
                        <select id="caAxis"><option value="2">Z (top view)</option><option value="1">Y (front view)</option><option value="0">X (side view)</option></select></div>
                    <div><label for="caSlice">Slice position</label><input id="caSlice" type="range" min="0" max="100" value="50"></div>
                    <div id="caTimeBox" hidden><label for="caTime">Time <span id="caTimeLbl"></span></label><input id="caTime" type="range" min="0" max="100" value="0"></div>
                    <div id="caPlayBox" hidden><button class="btn btn-small" type="button" id="caPlay">▶ Play</button></div>
                </div>
                <canvas id="caSliceCv" class="ca-canvas" width="10" height="10"></canvas>
                <div class="ca-legend" id="caLegend"><span>low</span><div class="ca-bar"></div><span>high</span></div>
            </div>
            <div>
                <div class="ca-ctl"><div><label>Hot zones in 3-D (drag to rotate)</label></div><div><button class="btn btn-small" type="button" id="caSpin">Pause</button></div></div>
                <canvas id="ca3d" class="ca-canvas" width="640" height="480"></canvas>
                <div class="ca-legend" style="flex-wrap:wrap">
                    <span><span class="ca-dot" style="background:#e8452c"></span>last to freeze</span>
                    <span><span class="ca-dot" style="background:#b02bd1"></span>cut off (severe)</span>
                    <span><span class="ca-dot" style="background:#f08a1c"></span>cut off</span>
                    <span><span class="ca-dot" style="background:#b8c2bd"></span>part outline</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Hotspots</h2><span class="muted small">{{ count($res['hotspots']) }} found</span></div>
    <div class="table-wrap">
        <table class="no-row-click ca-wrap">
            <thead><tr><th>#</th><th>Role</th><th>Modulus</th><th>Freezes in</th><th>Neck modulus</th><th>Neck ÷ hotspot</th><th>Local wall</th><th>Volume it serves</th><th>Position (mm)</th></tr></thead>
            <tbody>
            @foreach ($res['hotspots'] as $h)
                <tr>
                    <td><strong>{{ $h['id'] }}</strong></td>
                    <td><span class="status {{ $h['role'] === 'PRIMARY' ? 'status-completed' : ($h['role'] === 'ISOLATED_SEVERE' ? 'status-cancelled' : 'status-on-hold') }}">{{ $roleLabel[$h['role']] }}</span></td>
                    <td>{{ $n($h['modulus_cm'], 2) }} cm</td>
                    <td>{{ $n($rec['times']['hotspots_min'][$h['id']], 1) }} min</td>
                    <td>{{ $h['neck_modulus_cm'] !== null ? $n($h['neck_modulus_cm'], 2).' cm' : '—' }}</td>
                    <td>{{ $h['neck_ratio'] !== null ? $n($h['neck_ratio'] * 100, 0).'%' : '—' }}</td>
                    <td>{{ $n($h['inscribed_radius_mm'] * 2, 0) }} mm</td>
                    <td>{{ $n($h['group_volume_cm3'], $h['group_volume_cm3'] < 10 ? 1 : 0) }} cm³ ({{ $n($h['volume_share_pct'], $h['volume_share_pct'] < 10 ? 1 : 0) }}%)</td>
                    <td class="small">{{ implode(', ', array_map(fn ($v) => $n($v, 0), $h['centre_mm'])) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="panel-body small muted">
        Modulus = volume ÷ cooling surface of the section (Chvorinov). Freezing time = B × M². A hotspot is <em>cut off</em> when the section that joins it to the rest has less than {{ round(config('casting.modulus.neck_ok_ratio') * 100) }}% of its modulus: that neck freezes first and the hotspot is left without feed metal. Modulus is measured to about ±8%, so smaller differences are not reported.
    </div>
</div>

<script>
(function () {
    var V = @json($a->viewer);
    var URL_FIELD = @json(route('casting-analyses.field', $a));
    var nx = V.dims[0], ny = V.dims[1], nz = V.dims[2];
    var field = null;
    var st = document.getElementById('caStatus');
    var cv = document.getElementById('caSliceCv'), ctx = cv.getContext('2d');
    var mode = document.getElementById('caMode'), axis = document.getElementById('caAxis'), slice = document.getElementById('caSlice');
    var timeR = document.getElementById('caTime'), timeBox = document.getElementById('caTimeBox'), playBox = document.getElementById('caPlayBox'), play = document.getElementById('caPlay');
    var legend = document.getElementById('caLegend');
    var tMax = V.b * V.m_max_cm * V.m_max_cm;
    var ROLE = { PRIMARY: '#e8452c', ISOLATED_SEVERE: '#b02bd1', ISOLATED: '#f08a1c' };

    function ramp(t) {   // 0..1 -> blue, cyan, green, yellow, red
        var s = [[44, 79, 179], [31, 163, 201], [63, 191, 111], [240, 215, 60], [232, 69, 44]];
        t = Math.max(0, Math.min(0.9999, t)) * (s.length - 1);
        var i = Math.floor(t), f = t - i, a = s[i], b = s[i + 1];
        return [a[0] + (b[0] - a[0]) * f, a[1] + (b[1] - a[1]) * f, a[2] + (b[2] - a[2]) * f];
    }
    function dims() { var a = +axis.value; return a === 2 ? [nx, ny, nz] : (a === 1 ? [nx, nz, ny] : [ny, nz, nx]); }
    function cell(h, k) {  // marker position in cell units
        return [(h.c[0] - V.origin[0]) / V.h, (h.c[1] - V.origin[1]) / V.h, (h.c[2] - V.origin[2]) / V.h];
    }

    function drawSlice() {
        if (!field) return;
        var a = +axis.value, d = dims(), W = d[0], H = d[1], D = d[2];
        var k = Math.round(slice.value / 100 * (D - 1));
        cv.width = W; cv.height = H;
        var img = ctx.createImageData(W, H), px = img.data;
        var m = mode.value, tau = timeR.value / 100;
        for (var v = 0; v < H; v++) for (var u = 0; u < W; u++) {
            var x, y, z;
            if (a === 2) { x = u; y = H - 1 - v; z = k; } else if (a === 1) { x = u; z = H - 1 - v; y = k; } else { y = u; z = H - 1 - v; x = k; }
            var val = field[(z * ny + y) * nx + x], o = (v * W + u) * 4;
            if (!val) { px[o + 3] = 0; continue; }
            var c, f = val / 255;
            if (m === 'M') c = ramp(f);
            else if (m === 'T') c = ramp(f * f);
            else c = (f * f > tau) ? [235, 96, 52] : [150, 170, 190];   // still liquid : already solid
            px[o] = c[0]; px[o + 1] = c[1]; px[o + 2] = c[2]; px[o + 3] = 255;
        }
        ctx.putImageData(img, 0, 0);
        var r = Math.max(3, W / 55);
        ctx.lineWidth = Math.max(1, W / 220); ctx.font = Math.max(9, W / 28) + 'px sans-serif';
        V.hotspots.forEach(function (h) {
            var p = cell(h), mx, my, near;
            if (a === 2) { mx = p[0]; my = H - 1 - p[1]; near = Math.abs(p[2] - k) <= 3; }
            else if (a === 1) { mx = p[0]; my = H - 1 - p[2]; near = Math.abs(p[1] - k) <= 3; }
            else { mx = p[1]; my = H - 1 - p[2]; near = Math.abs(p[0] - k) <= 3; }
            ctx.beginPath(); ctx.arc(mx, my, r, 0, 6.283);
            ctx.strokeStyle = near ? '#111' : 'rgba(0,0,0,.25)'; ctx.fillStyle = near ? ROLE[h.role] : 'rgba(255,255,255,.0)';
            if (near) ctx.fill(); ctx.stroke();
            if (near) { ctx.fillStyle = '#111'; ctx.fillText(h.id, mx + r + 2, my - r); }
        });
        if (m === 'L') document.getElementById('caTimeLbl').textContent = '— ' + (tau * tMax).toFixed(1) + ' of ' + tMax.toFixed(1) + ' min';
    }

    function setMode() {
        var m = mode.value;
        timeBox.hidden = playBox.hidden = (m !== 'L');
        legend.innerHTML = m === 'L' ? '<span><span class="ca-dot" style="background:#eb6034"></span>still liquid</span><span><span class="ca-dot" style="background:#96aabe"></span>solid</span>'
            : '<span>' + (m === 'M' ? 'thin section' : 'freezes early') + '</span><div class="ca-bar"></div><span>' + (m === 'M' ? 'heavy section' : 'freezes last') + '</span>';
        drawSlice();
    }
    [mode].forEach(function (e) { e.addEventListener('change', setMode); });
    [axis, slice, timeR].forEach(function (e) { e.addEventListener('input', drawSlice); });
    var timer = null;
    play.addEventListener('click', function () {
        if (timer) { clearInterval(timer); timer = null; play.textContent = '▶ Play'; return; }
        if (+timeR.value >= 99) timeR.value = 0;
        play.textContent = '❚❚ Pause';
        timer = setInterval(function () { timeR.value = Math.min(100, +timeR.value + 1); drawSlice(); if (+timeR.value >= 100) { clearInterval(timer); timer = null; play.textContent = '▶ Play'; } }, 80);
    });

    fetch(URL_FIELD, { credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw 0; return r.arrayBuffer(); })
        .then(function (b) { field = new Uint8Array(b); st.textContent = nx + '×' + ny + '×' + nz + ' cells of ' + V.h.toFixed(1) + ' mm'; setMode(); })
        .catch(function () { st.textContent = 'map unavailable'; });

    // ---- 3-D point view ------------------------------------------------------------------------------------
    var c3 = document.getElementById('ca3d'), g3 = c3.getContext('2d');
    var cx0 = V.origin[0] + nx * V.h / 2, cy0 = V.origin[1] + ny * V.h / 2, cz0 = V.origin[2] + nz * V.h / 2;
    var span = Math.max(nx, ny, nz) * V.h * 0.62;
    var ay = 0.6, ax = 0.45, spin = true, drag = null;
    var roleOf = {}; V.hotspots.forEach(function (h, i) { roleOf[i + 1] = h; });
    function proj(x, y, z) {
        x -= cx0; y -= cy0; z -= cz0;
        var X = x * Math.cos(ay) - y * Math.sin(ay), Y = x * Math.sin(ay) + y * Math.cos(ay);
        var Z = z * Math.cos(ax) - Y * Math.sin(ax), D = z * Math.sin(ax) + Y * Math.cos(ax);
        var s = c3.width / (2 * span) * 0.92;
        return [c3.width / 2 + X * s, c3.height / 2 - Z * s, D];
    }
    function draw3d() {
        g3.clearRect(0, 0, c3.width, c3.height);
        g3.fillStyle = 'rgba(150,165,158,.35)';
        V.ghost.forEach(function (p) { var q = proj(p[0], p[1], p[2]); g3.fillRect(q[0], q[1], 1.6, 1.6); });
        V.hot.forEach(function (p) {
            var h = roleOf[p[3]]; var q = proj(p[0], p[1], p[2]);
            g3.fillStyle = h ? ROLE[h.role] : '#e8452c'; g3.globalAlpha = .75; g3.fillRect(q[0] - 1.5, q[1] - 1.5, 3, 3); g3.globalAlpha = 1;
        });
        g3.font = '13px sans-serif'; g3.fillStyle = '#111';
        V.hotspots.forEach(function (h) { var q = proj(h.c[0], h.c[1], h.c[2]); g3.fillText(h.id, q[0] + 6, q[1] - 6); });
    }
    function loop() { if (spin && !drag) { ay += 0.006; } draw3d(); requestAnimationFrame(loop); }
    c3.addEventListener('pointerdown', function (e) { drag = [e.clientX, e.clientY]; c3.setPointerCapture(e.pointerId); c3.style.cursor = 'grabbing'; });
    c3.addEventListener('pointermove', function (e) { if (!drag) return; ay += (e.clientX - drag[0]) * 0.01; ax = Math.max(-1.5, Math.min(1.5, ax + (e.clientY - drag[1]) * 0.01)); drag = [e.clientX, e.clientY]; });
    c3.addEventListener('pointerup', function () { drag = null; c3.style.cursor = 'grab'; });
    document.getElementById('caSpin').addEventListener('click', function () { spin = !spin; this.textContent = spin ? 'Pause' : 'Spin'; });
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) { spin = false; document.getElementById('caSpin').textContent = 'Spin'; }
    loop();
})();
</script>
