@php $L = $layout; @endphp
<div class="card hero-card">
    <div class="hero">
        <div>
            <span class="live"><i class="dot"></i>Live</span>
            <h1>Foundry operations overview</h1>
            <p>Melt shop, moulding, quality and the order book on one page. Everything here is read from the live records.</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;align-items:flex-end">
            <div class="switch" title="Choose a dashboard layout">
                @foreach (['classic' => 'Classic', 'command' => 'Command', 'columns' => 'Columns', 'telemetry' => 'Telemetry'] as $k => $name)<a href="{{ route('dashboard', ['layout' => $k]) }}" class="{{ $L === $k ? 'on' : '' }}">{{ $name }}</a>@endforeach
            </div>
            <div class="acts">
                <a class="btnx" href="{{ route('scan') }}">Scan code</a>
                <a class="btnx" href="{{ route('heats.create') }}">Plan a heat</a>
                @if (Route::has('enquiries.create'))<a class="btnx primary" href="{{ route('enquiries.create') }}">+ New enquiry</a>@endif
            </div>
        </div>
    </div>
    @if (! empty($strip))
        <div class="strip">
            <span><i class="dot"></i>{{ $d['kpis']['heats']['running'] }} heat{{ $d['kpis']['heats']['running'] === 1 ? '' : 's' }} running</span>
            <span class="mono">{{ now()->format('D d M Y') }}</span>
            <span>{{ $d['queueTotal'] }} open production order{{ $d['queueTotal'] === 1 ? '' : 's' }}</span>
        </div>
    @endif
</div>
