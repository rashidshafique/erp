<div class="card">
    <div class="chead"><div><h2>Needs attention</h2><p class="sub">Deviations and sign-offs waiting on someone.</p></div>@if (count($d['attention']))<span class="pill red">{{ count($d['attention']) }} item{{ count($d['attention']) === 1 ? '' : 's' }}</span>@endif</div>
    @forelse ($d['attention'] as $a)
        <div class="alert-item {{ $a['tone'] === 'red' ? 'red' : '' }}">
            <div class="meta"><span class="pill {{ $a['tone'] }}">{{ $a['tag'] }}</span><span class="mono">{{ $a['ref'] ? $a['ref'].' · ' : '' }}{{ \Illuminate\Support\Carbon::parse($a['at'])->diffForHumans(null, true, true) }}</span></div>
            <p>{{ $a['text'] }}</p>
            <div class="acts">@foreach ($a['actions'] as [$label, $url, $primary])<a class="btnx {{ $primary ? 'primary' : '' }}" href="{{ $url }}">{{ $label }}</a>@endforeach</div>
        </div>
    @empty
        <div class="empty">Nothing needs attention right now.</div>
    @endforelse
</div>
