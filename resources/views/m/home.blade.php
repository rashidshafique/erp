@extends('m.layout')
@section('title', 'Today')
@section('body')
@php
    $roles = $user?->roles->pluck('name')->implode(' · ');
@endphp
<div style="padding:10px 20px 8px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
        <div>
            <div class="muted" style="font-size:12.5px">{{ $roles ?: 'Shop floor' }}{{ $user ? ' · '.$user->name : '' }}</div>
            <div style="font-size:22px;font-weight:600">Today</div>
        </div>
        @if($user)<form method="POST" action="{{ route('logout') }}" onsubmit="if(navigator.serviceWorker&&navigator.serviceWorker.controller){navigator.serviceWorker.controller.postMessage('signed-out')}">@csrf<input type="hidden" name="mobile" value="1"><button class="chip" style="font:inherit;font-size:13px;cursor:pointer">Sign out</button></form>@endif
    </div>
</div>

@if($active)
    @php
        $h = $active['heat']; $last = $active['last']; $dueAt = $active['due_at'];
        $left = $dueAt ? (int) now()->diffInSeconds($dueAt, false) : null;
        $tone = $left === null ? 'idle' : ($left <= 0 ? 'red' : ($left < 120 ? 'amber' : 'ok'));
        $colour = ['idle' => '#55585d', 'ok' => '#1d7048', 'amber' => '#94610a', 'red' => '#b3352c'][$tone];
        $bg = ['idle' => '#fff', 'ok' => '#fff', 'amber' => '#fdf6e8', 'red' => '#fdf1f0'][$tone];
        $edge = ['idle' => '#d9dbde', 'ok' => '#d9dbde', 'amber' => '#e2b25a', 'red' => '#d9544a'][$tone];
        $clock = $left === null ? '' : ($left <= 0 ? '+' : '').sprintf('%d:%02d', intdiv(abs($left), 60), abs($left) % 60);
        $label = $left === null ? 'No thermal test yet' : ($left <= 0 ? 'Reading overdue' : 'Next reading in');
        $pct = $left === null ? 100 : max(0, min(100, 100 - ($left / max(1, $active['window'] * 60)) * 100));
    @endphp
    <div id="active" data-due="{{ $dueAt?->getTimestamp() }}" data-window="{{ $active['window'] * 60 }}" style="margin:2px 20px 8px;border-radius:14px;border:2px solid {{ $edge }};background:{{ $bg }};padding:12px 14px;display:flex;flex-direction:column;gap:8px">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <span class="cap">Active heat</span>
            @if($active['started'])<span style="display:flex;align-items:center;gap:6px;font-size:12.5px;color:#1d7048;font-weight:500"><span style="width:8px;height:8px;border-radius:50%;background:#1d7048"></span>Melting since {{ $active['started']->format('H:i') }}</span>@endif
        </div>
        <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:10px">
            <div style="min-width:0">
                <div style="font-size:19px;font-weight:600">{{ $h->furnace?->code ?? $h->furnace?->name }} · <code>{{ $h->heat_number }}</code></div>
                <div style="font-size:13px;color:#55585d">{{ collect([$h->ironGrade?->code, $last ? 'last test '.$last->taken_at->format('H:i').($last->ce_from_tl ? ' CEL '.number_format($last->ce_from_tl, 2) : '') : null])->filter()->implode(' · ') }}</div>
            </div>
            @if($left !== null)
            <div style="text-align:right;flex:none"><div id="active-label" style="font-size:12px;color:{{ $colour }}">{{ $label }}</div><div id="active-clock" style="font:600 26px ui-monospace,Menlo,monospace;color:{{ $colour }}">{{ $clock }}</div></div>
            @else
            <div style="text-align:right;flex:none;font-size:12px;color:{{ $colour }}">{{ $label }}</div>
            @endif
        </div>
        <div style="height:6px;border-radius:3px;background:#e6e7e9;overflow:hidden"><div id="active-bar" style="height:100%;width:{{ $pct }}%;background:{{ $colour }}"></div></div>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px">
            <span style="color:#3d4045">Next: thermal {{ $last ? 're-test '.$active['number'] : 'test 1' }}{{ $last?->cup_type ? ', '.$last->cup_type.' cup' : '' }}</span>
            <a href="{{ route('m.tests.thermal', $h) }}" class="blue" style="font-weight:600;text-decoration:none">Start test →</a>
        </div>
    </div>
@endif

<div style="margin:0 20px 4px;display:grid;grid-template-columns:{{ ($defects ? '1fr 1fr' : '1fr') }};gap:10px">
    <a class="tile" href="{{ route('m.scan') }}"><b>Scan label</b><span>Lot, heat or sample</span></a>
    @if($defects)<a class="tile" href="{{ route('m.defects.pick') }}"><b>Log defect</b><span>Photos + type</span></a>@endif
</div>

@if($tests)
<div style="padding:10px 20px 0;flex:1"><div class="cap" style="padding-bottom:2px">Tests due</div>
    @forelse($due as $r)
        @php
            $late = $r['late'] > 0;
            $pill = $late ? 'background:#fbe7e5;color:#b3352c' : 'background:#fbf0dc;color:#94610a';
            $name = $r['type'] === 'inspection' ? 'Hardness' : $r['kind'];
        @endphp
        <a href="{{ $r['url'] }}" style="display:grid;grid-template-columns:52px minmax(0,1fr) auto;gap:12px;align-items:center;padding:13px 0;border-bottom:1px solid #eff0f2;color:inherit;text-decoration:none">
            <span style="font:500 13px ui-monospace,Menlo,monospace;color:#55585d">{{ $late ? 'Late' : $r['since']->format('H:i') }}</span>
            <div style="min-width:0"><div style="font-size:15px;font-weight:600">{{ $name }} · {{ $r['what'] }}</div><div style="font-size:13px;color:#55585d">{{ $r['sub'] }}</div></div>
            <span style="font-size:12px;padding:3px 9px;border-radius:999px;font-weight:500;white-space:nowrap;{{ $pill }}">{{ $late ? 'Overdue '.$r['late'].' min' : 'Due' }}</span>
        </a>
    @empty
        <div class="muted" style="font-size:14px;padding:13px 0">Nothing is waiting for a test.</div>
    @endforelse
    <div class="cap" style="padding:16px 0 6px">Record a test</div>
    <div style="display:flex;flex-direction:column;gap:10px;padding-bottom:12px">
        @foreach($types as $key => $t)
            <a class="tile" href="{{ route('m.tests.pick', $key) }}"><b>{{ $t['name'] }}</b><span>{{ $t['sub'] }}</span></a>
        @endforeach
    </div>
</div>
@else
<div style="flex:1"></div>
@endif

<div id="queue" hidden style="margin:0 20px 10px;padding:10px 14px;border-radius:12px;background:#f8f8f9">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><b id="queue-count" style="font-size:13.5px;font-weight:400"></b><button id="queue-send" class="chip" style="font:inherit;font-size:13px;cursor:pointer;color:#2f5fd0;font-weight:600">Send now</button></div>
    <div id="queue-list" style="margin-top:6px"></div>
</div>
<script>
(function () {
    var box = document.getElementById('queue'), list = document.getElementById('queue-list'), esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
    function draw(rows) {
        box.hidden = rows.length === 0;
        var waiting = rows.filter(function (r) { return r.state !== 'refused'; }).length;
        document.getElementById('queue-count').textContent = rows.length + (rows.length === 1 ? ' record' : ' records') + ' saved on phone' + (waiting < rows.length ? ' · ' + (rows.length - waiting) + ' need attention' : '');
        list.innerHTML = rows.map(function (r) {
            var bad = r.state === 'refused';
            return '<div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-top:1px solid #eff0f2;font-size:13px"><span>' + esc(r.label) + '<br><span style="color:' + (bad ? '#b00020' : '#55585d') + '">' + esc(bad ? r.note : 'Waiting for signal · ' + new Date(r.saved).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })) + '</span></span><span style="white-space:nowrap">' + (bad ? '<a href="#" data-retry="' + r.id + '" class="blue">Retry</a> · ' : '') + '<a href="#" data-drop="' + r.id + '" style="color:#55585d">Discard</a></span></div>';
        }).join('') + (window.phoneQueueNeedsSignIn ? '<div style="padding-top:6px;font-size:13px"><a class="blue" href="{{ route('m.login') }}">Sign in again to send</a></div>' : '');
    }
    list.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-drop],a[data-retry]'); if (!a) { return; } e.preventDefault();
        if (a.dataset.drop) { if (confirm('Discard this record? It will not be saved.')) { phoneQueue.discard(a.dataset.drop); } } else { phoneQueue.retry(a.dataset.retry); }
    });
    document.getElementById('queue-send').addEventListener('click', function () { phoneQueue.flush(); });
    window.addEventListener('load', function () { phoneQueue.onChange(draw); });
})();
</script>
<script>
(function () {
    var box = document.getElementById('active'); if (!box || !box.dataset.due) { return; }
    var due = +box.dataset.due * 1000, win = +box.dataset.window * 1000, lab = document.getElementById('active-label'), clk = document.getElementById('active-clock'), bar = document.getElementById('active-bar');
    function tick() {
        var ms = due - Date.now(), s = Math.floor(Math.abs(ms) / 1000), tone = ms <= 0 ? 'red' : (ms < 120000 ? 'amber' : 'ok');
        var c = { ok: '#1d7048', amber: '#94610a', red: '#b3352c' }[tone], bg = { ok: '#fff', amber: '#fdf6e8', red: '#fdf1f0' }[tone], edge = { ok: '#d9dbde', amber: '#e2b25a', red: '#d9544a' }[tone];
        clk.textContent = (ms <= 0 ? '+' : '') + Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
        lab.textContent = ms <= 0 ? 'Reading overdue' : 'Next reading in';
        lab.style.color = clk.style.color = bar.style.background = c; box.style.background = bg; box.style.borderColor = edge;
        bar.style.width = Math.max(0, Math.min(100, 100 - (ms / win) * 100)) + '%';
    }
    tick(); setInterval(tick, 1000);
})();
</script>
<div style="display:grid;grid-template-columns:repeat(3,1fr);border-top:1px solid #e6e7e9;padding:10px 0 14px;font-size:12.5px;text-align:center">
    <a href="{{ route('m.home') }}" style="color:#2f5fd0;font-weight:600;text-decoration:none">Today</a>
    @if($tests)<a href="{{ route('m.tests.pick', 'thermal') }}" style="color:#55585d;text-decoration:none">Tests</a>@else<span></span>@endif
    @if($defects)<a href="{{ route('m.defects.pick') }}" style="color:#55585d;text-decoration:none">Defects</a>@else<span></span>@endif
</div>
@endsection
