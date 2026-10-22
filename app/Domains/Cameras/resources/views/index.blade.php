@extends('layouts.app')

@section('title', 'Cameras')
@section('breadcrumb', 'Cameras / Live view')

@section('content')
<div class="page-heading">
    <div><h1>Cameras</h1><p>Live pictures from the recorder. Click a camera to see it larger and switch it to the live stream.</p></div>
    <div class="actions cam-controls">
        <label class="cam-ctl">Refresh
            <select id="cam-interval">
                <option value="1000" selected>every 1 s</option><option value="2000">every 2 s</option><option value="5000">every 5 s</option><option value="10000">every 10 s</option><option value="0">paused</option>
            </select></label>
        <label class="cam-ctl">Columns
            <select id="cam-cols"><option value="1">1</option><option value="2">2</option><option value="3" selected>3</option><option value="4">4</option></select></label>
        @if ($areas->isNotEmpty())
            <label class="cam-ctl">Area
                <select id="cam-area"><option value="">All areas</option>@foreach ($areas as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach</select></label>
        @endif
        <a class="btn" href="{{ route('cameras.setup') }}">Camera setup</a>
    </div>
</div>

@if ($cameras->isEmpty())
    <div class="panel"><div class="panel-body">
        <p class="muted" style="margin:0">No cameras are set up yet. Add the recorder and its channels on the <a href="{{ route('cameras.setup') }}">Camera setup</a> page.</p>
    </div></div>
@else
    <div class="cam-grid" id="cam-grid" style="--cols:3">
        @foreach ($cameras as $c)
            <figure class="cam-card" data-area="{{ $c->area }}" data-camera="{{ $c->id }}" data-name="{{ $c->name }}">
                <div class="cam-shot"><img alt="{{ $c->name }}" data-snapshot="{{ route('cameras.snapshot', $c) }}" data-stream="{{ route('cameras.stream', $c) }}"></div>
                <figcaption><strong>{{ $c->name }}</strong><span class="muted small">{{ $c->area ?: $c->dvr->name }}</span><span class="cam-dot" aria-hidden="true"></span></figcaption>
            </figure>
        @endforeach
    </div>

    <dialog id="cam-big" class="qd cam-big" aria-labelledby="cam-big-title">
        <div class="qd-head"><h2 id="cam-big-title"></h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
        <div class="cam-big-body"><img id="cam-big-img" alt=""></div>
        <div class="qd-foot">
            <span class="muted small" id="cam-big-mode"></span>
            <button type="button" class="btn" id="cam-big-live">Switch to live stream</button>
            <button type="button" class="btn" data-close>Close</button>
        </div>
    </dialog>
@endif

<style>
    .cam-controls { display: flex; flex-wrap: wrap; gap: 8px 14px; align-items: end; }
    .cam-ctl { display: flex; flex-direction: column; font-size: 12px; font-weight: 600; color: var(--muted); gap: 3px; }
    .cam-ctl select { min-height: 32px; font-size: 13px; }
    .cam-grid { display: grid; grid-template-columns: repeat(var(--cols), minmax(0, 1fr)); gap: 14px; }
    .cam-card { margin: 0; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; background: var(--surface); cursor: pointer; }
    .cam-card[hidden] { display: none; }
    .cam-shot { aspect-ratio: 16 / 9; background: #11161d; }
    .cam-shot img { display: block; width: 100%; height: 100%; object-fit: contain; padding: 0; min-height: 0; border: 0; }
    .cam-card figcaption { display: flex; gap: 8px; align-items: baseline; padding: 8px 12px; }
    .cam-card figcaption .small { margin-left: auto; }
    .cam-dot { width: 9px; height: 9px; border-radius: 50%; background: #bbb; align-self: center; flex: none; }
    .cam-card.is-ok .cam-dot { background: #1e8e3e; }
    .cam-card.is-bad .cam-dot { background: #d93025; }
    dialog.cam-big { width: min(1180px, calc(100vw - 24px)); }
    .cam-big-body { background: #11161d; }
    .cam-big-body img { display: block; width: 100%; max-height: calc(100vh - 190px); object-fit: contain; padding: 0; min-height: 0; border: 0; }
    @media (max-width: 900px) { .cam-grid { grid-template-columns: repeat(min(var(--cols), 2), minmax(0, 1fr)); } }
    @media (max-width: 560px) { .cam-grid { grid-template-columns: 1fr; } }
</style>
@include('partials.quick-dialog-assets')
<script>
(function () {
    var grid = document.getElementById('cam-grid'); if (!grid) return;
    var interval = 1000, cards = [].slice.call(grid.querySelectorAll('.cam-card'));
    // Each picture is asked for again only after the last one arrived, so a slow recorder is never piled up with requests.
    function run(card) {
        var img = card.querySelector('img'), timer = null;
        function next() { clearTimeout(timer); if (interval > 0 && !document.hidden && !card.hidden) timer = setTimeout(load, interval); }
        function load() {
            if (card.hidden || document.hidden) { next(); return; }
            img.src = img.dataset.snapshot + '?t=' + Date.now();
        }
        img.addEventListener('load', function () { card.classList.add('is-ok'); card.classList.remove('is-bad'); next(); });
        img.addEventListener('error', function () { card.classList.add('is-bad'); card.classList.remove('is-ok'); clearTimeout(timer); timer = setTimeout(load, Math.max(interval, 5000)); });
        card._restart = function () { clearTimeout(timer); if (interval > 0) load(); };
        load();
    }
    cards.forEach(run);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) cards.forEach(function (c) { c._restart(); }); });
    document.getElementById('cam-interval').addEventListener('change', function () { interval = parseInt(this.value, 10) || 0; cards.forEach(function (c) { c._restart(); }); });
    document.getElementById('cam-cols').addEventListener('change', function () { grid.style.setProperty('--cols', this.value); });
    var area = document.getElementById('cam-area');
    if (area) area.addEventListener('change', function () { cards.forEach(function (c) { c.hidden = !!area.value && c.dataset.area !== area.value; c._restart(); }); });

    // One camera large: stills at a fast rate, or the live stream, which holds one connection open until it is closed.
    var dlg = document.getElementById('cam-big'), big = document.getElementById('cam-big-img'), live = document.getElementById('cam-big-live'), mode = document.getElementById('cam-big-mode');
    var current = null, bigTimer = null, streaming = false;
    function stills() {
        clearTimeout(bigTimer); streaming = false; live.textContent = 'Switch to live stream'; mode.textContent = 'A new picture about every second.';
        (function tick() { if (!dlg.open || streaming) return; big.onload = function () { bigTimer = setTimeout(tick, 700); }; big.onerror = function () { bigTimer = setTimeout(tick, 3000); }; big.src = current.dataset.snapshot + '?t=' + Date.now(); })();
    }
    function stream() { clearTimeout(bigTimer); streaming = true; live.textContent = 'Back to pictures'; mode.textContent = 'Live stream from the recorder.'; big.onload = big.onerror = null; big.src = current.dataset.stream + '?main=1&t=' + Date.now(); }
    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            current = card.querySelector('img'); document.getElementById('cam-big-title').textContent = card.dataset.name; big.alt = card.dataset.name;
            dlg.showModal ? dlg.showModal() : dlg.setAttribute('open', ''); stills();
        });
    });
    live.addEventListener('click', function () { streaming ? stills() : stream(); });
    dlg.addEventListener('close', function () { clearTimeout(bigTimer); streaming = false; big.removeAttribute('src'); });
})();
</script>
@endsection
