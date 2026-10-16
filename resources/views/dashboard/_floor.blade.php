<div class="card">
    <div class="chead"><div><h2>Live floor status</h2><p class="sub">Moulding runs in progress, furnaces and heats.</p></div><a class="btnx" href="{{ route('moulding-runs.index') }}">Moulding runs</a></div>
    @if (count($d['floor']))
        <div class="floor">
            @foreach ($d['floor'] as $f)
                <a class="fl" href="{{ $f['url'] }}">
                    <div class="k"><span class="cap">{{ $f['kind'] }}</span><span class="pill {{ $f['tone'] === 'grey' ? '' : $f['tone'] }}">{{ $f['state'] }}</span></div>
                    <div class="n">{{ $f['name'] }}</div><div class="s">{{ $f['sub'] }}</div>
                    @if ($f['progress'] !== null)<div class="bar" style="margin-top:10px"><i style="width:{{ $f['progress'] }}%"></i></div>@endif
                    <div class="m mono">{{ $f['meta'] }}@if ($f['progress'] !== null) · {{ $f['progress'] }}%@endif</div>
                </a>
            @endforeach
        </div>
    @else
        <div class="empty">No moulding run or heat is in progress.</div>
    @endif
</div>
