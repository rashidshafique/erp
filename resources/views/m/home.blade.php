@extends('m.layout')
@section('title', 'Today')
@section('body')
@php
    $roles = $user?->roles->pluck('name')->implode(' · ');
    $heatWhen = fn ($h) => ($h->actual_start_at ?? $h->planned_start_at ?? $h->created_at)?->format('j M H:i');
@endphp
<div style="padding:16px 20px 8px;display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
    <div>
        <div class="muted" style="font-size:12.5px">{{ $roles ?: 'Shop floor' }}{{ $user ? ' · '.$user->name : '' }}</div>
        <div style="font-size:22px;font-weight:600">Today</div>
    </div>
    @if($user)<form method="POST" action="{{ route('logout') }}" onsubmit="if(navigator.serviceWorker&&navigator.serviceWorker.controller){navigator.serviceWorker.controller.postMessage('signed-out')}">@csrf<input type="hidden" name="mobile" value="1"><button class="chip" style="font:inherit;font-size:13px;cursor:pointer">Sign out</button></form>@endif
</div>

@if($tests)
    @if($due->isNotEmpty())
    <div style="padding:8px 20px 0"><div class="cap" style="padding-bottom:2px">Tests due</div>
        @foreach($due as $r)
            <a class="row" href="{{ $r['url'] }}"><span><b>{{ $r['kind'] }} · <code>{{ $r['what'] }}</code></b><br><span class="muted" style="font-size:13px">{{ $r['sub'] }}</span></span><span class="muted" style="font-size:12.5px;text-align:right">waiting<br>{{ $r['since']->diffForHumans(null, true) }}</span></a>
        @endforeach
    </div>
    @endif
    <div style="padding:8px 20px 0"><div class="cap" style="padding-bottom:6px">Record a test · {{ $todayTests }} thermal today</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @foreach($types as $key => $t)
                <a class="tile" href="{{ route('m.tests.pick', $key) }}"><b>{{ $t['name'] }}</b><span>{{ $t['sub'] }}</span></a>
            @endforeach
        </div>
    </div>
    <div style="padding:16px 20px 0"><div class="cap" style="padding-bottom:2px">Latest heats</div>
        @forelse($recentHeats as $h)
            <a class="row" href="{{ route('m.tests.pick', 'thermal') }}"><span><b>{{ $h->furnace?->code ?? $h->furnace?->name }} · <code>{{ $h->heat_number }}</code></b><br><span class="muted" style="font-size:13px">{{ $h->ironGrade?->code }} · {{ $heatWhen($h) }}</span></span><span class="muted" style="font-size:12.5px">{{ str_replace('_', ' ', strtolower($h->status)) }}</span></a>
        @empty
            <div class="muted" style="font-size:14px;padding:10px 0">No heats yet.</div>
        @endforelse
    </div>
@endif

<div style="padding:12px 20px 0"><a class="tile" href="{{ route('m.scan') }}"><b>Scan label</b><span>Lot, heat or sample code</span></a></div>

@if($defects)
    <div style="padding:16px 20px 0"><div class="cap" style="padding-bottom:6px">Defects · {{ $todayDefects }} logged today</div>
        <a class="tile" href="{{ route('m.defects.pick') }}"><b>Log defect</b><span>Pick the lot, then photos + type</span></a>
    </div>
@endif

<div id="queue" hidden style="margin:auto 20px 0;padding:12px 14px;border-radius:14px;background:#f8f8f9;border:1px solid #d9dbde">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><b id="queue-count" style="font-size:14px"></b><button id="queue-send" class="chip" style="font:inherit;font-size:13px;cursor:pointer;color:#2f5fd0;font-weight:600">Send now</button></div>
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
@endsection
