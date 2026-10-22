@if ($topbarHeats->isNotEmpty())
<div class="heat-lights" aria-label="Heats on the floor">
    @foreach ($topbarHeats->take(4) as $h)
        @php
            // Time left to the estimated finish, from the same estimate as the heat page; kept for 30 seconds so every page does not recompute it.
            $finishAt = \Illuminate\Support\Facades\Cache::remember('heat-eta-'.$h->id.'-'.$h->updated_at?->timestamp, 30, fn () => ($e = app(\App\Services\Production\HeatEta::class)->for($h)) ? $e['finish_at']->toIso8601String() : '');
        @endphp
        <a class="heat-chip" href="{{ $h->light() === 'green' && $h->pours->first() ? route('pours.show', $h->pours->first()) : route('heats.show', $h) }}"
            title="{{ $h->heat_number }} · {{ $h->lightLabel() }}@if ($finishAt) · finishes about {{ \Illuminate\Support\Carbon::parse($finishAt)->format('H:i') }}@endif"><x-heat-light :heat="$h" /><span class="heat-no">{{ $h->shortNumber() }}</span>@if ($finishAt)<span class="heat-left" data-finish-at="{{ $finishAt }}" data-eta-url="{{ route('heats.eta', $h) }}"></span>@endif</a>
    @endforeach
    @if ($topbarHeats->count() > 4)<a class="heat-chip" href="{{ route('heats.index') }}">+{{ $topbarHeats->count() - 4 }} more</a>@endif
</div>
<script>
(function () {
    var timers = document.querySelectorAll('.heat-left[data-finish-at]');
    if (!timers.length) return;
    function clock(s) { var h = Math.floor(s / 3600), m = Math.floor(s / 60) % 60, x = s % 60; return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(x).padStart(2, '0'); }
    // A countdown to the estimated finish, to the second; past the finish it counts up in red.
    function tick() {
        timers.forEach(function (el) {
            var at = Date.parse(el.dataset.finishAt); if (!at) return;
            var s = Math.round((at - Date.now()) / 1000);
            el.textContent = (s < 0 ? '+' : '−') + clock(Math.abs(s));
            el.classList.toggle('over', s < 0);
        });
        setTimeout(tick, 1000 - (Date.now() % 1000));
    }
    tick();
    // The estimate itself moves as metal is tapped and poured, so it is asked for again every minute.
    setInterval(function () {
        timers.forEach(function (el) {
            fetch(el.dataset.etaUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) { if (d && d.eta) el.dataset.finishAt = d.eta.finish_at; })
                .catch(function () {});
        });
    }, 60000);
})();
</script>
@endif
