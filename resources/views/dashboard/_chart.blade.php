@php
    $rows = $d['daily']; $n = count($rows);
    $W = 640; $H = 190; $pl = 34; $pr = 8; $pt = 10; $pb = 24; $iw = $W - $pl - $pr; $ih = $H - $pt - $pb;
    $x = fn ($i) => $pl + ($n > 1 ? $i * $iw / ($n - 1) : 0);
    $ys = array_filter(array_column($rows, 'yield'), fn ($v) => $v !== null);
    $lo = $ys ? max(0, floor(min($ys) / 10) * 10 - 10) : 0; $hi = 100;
    $yy = fn ($v) => $pt + $ih - ($v - $lo) / (($hi - $lo) ?: 1) * $ih;
    $line = []; foreach ($rows as $i => $r) { if ($r['yield'] !== null) { $line[] = round($x($i), 1).','.round($yy($r['yield']), 1); } }
    $smax = max(array_merge(array_filter(array_column($rows, 'scrap'), fn ($v) => $v !== null), [5]));
    $tmax = max(array_merge(array_column($rows, 'poured_kg'), [1]));
    $bw = max(4, $iw / max($n, 1) * 0.5);
    $tot = array_sum(array_column($rows, 'poured_kg'));
    $pieces = array_sum(array_column($rows, 'poured')); $rej = array_sum(array_column($rows, 'rejected'));
@endphp
<div class="card" data-chart>
    <div class="chead"><div><h2>Yield &amp; scrap</h2><p class="sub">Last {{ $n }} days: good metal against metal poured, and pieces rejected.</p></div>
        <div class="tabs"><button type="button" class="on" data-t="y">Yield &amp; scrap</button><button type="button" data-t="t">Tonnes poured</button></div></div>
    <div data-pane="y">
        <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%;height:auto;display:block" role="img" aria-label="Pouring yield and scrap rate by day">
            @foreach ([0, .5, 1] as $g)@php $gy = $pt + $ih * $g; @endphp<line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ $gy }}" y2="{{ $gy }}" stroke="#e2e8f0"/><text x="{{ $pl - 6 }}" y="{{ $gy + 4 }}" text-anchor="end" font-size="10" fill="#64748b">{{ round($hi - ($hi - $lo) * $g) }}%</text>@endforeach
            @foreach ($rows as $i => $r)@if ($r['scrap'] !== null && $r['scrap'] > 0)@php $bh = max(2, $r['scrap'] / $smax * ($ih * .45)); @endphp<rect x="{{ round($x($i) - $bw / 2, 1) }}" y="{{ round($pt + $ih - $bh, 1) }}" width="{{ round($bw, 1) }}" height="{{ round($bh, 1) }}" rx="2" fill="#ef4444" opacity=".28"><title>{{ $r['date']->format('d M') }}: scrap {{ $r['scrap'] }}%</title></rect>@endif @endforeach
            @if (count($line) > 1)<polyline points="{{ implode(' ', $line) }}" fill="none" stroke="var(--accent)" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>@endif
            @foreach ($rows as $i => $r)@if ($r['yield'] !== null)<circle cx="{{ round($x($i), 1) }}" cy="{{ round($yy($r['yield']), 1) }}" r="3" fill="var(--accent)"><title>{{ $r['date']->format('d M') }}: yield {{ $r['yield'] }}%</title></circle>@endif @endforeach
            @foreach ([0, intdiv($n, 2), $n - 1] as $i)@if (isset($rows[$i]))<text x="{{ round($x($i), 1) }}" y="{{ $H - 6 }}" text-anchor="{{ $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle') }}" font-size="10" fill="#64748b">{{ $rows[$i]['date']->format('d M') }}</text>@endif @endforeach
        </svg>
        <div style="font-size:12px;color:var(--dx-sub);display:flex;gap:18px;margin-top:6px"><span><i class="dot"></i>Pouring yield</span><span><i class="dot" style="background:#ef4444;opacity:.5"></i>Rejected pieces (%)</span></div>
        @if (! count($line))<div class="empty">No finished pours in this window yet, so there is nothing to plot.</div>@endif
    </div>
    <div data-pane="t" hidden>
        <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%;height:auto;display:block" role="img" aria-label="Metal poured per day in tonnes">
            @foreach ([0, .5, 1] as $g)@php $gy = $pt + $ih * $g; @endphp<line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ $gy }}" y2="{{ $gy }}" stroke="#e2e8f0"/><text x="{{ $pl - 6 }}" y="{{ $gy + 4 }}" text-anchor="end" font-size="10" fill="#64748b">{{ number_format($tmax / 1000 * (1 - $g), 1) }}</text>@endforeach
            @foreach ($rows as $i => $r)@if ($r['poured_kg'] > 0)@php $bh = $r['poured_kg'] / $tmax * $ih; @endphp<rect x="{{ round($x($i) - $bw / 2, 1) }}" y="{{ round($pt + $ih - $bh, 1) }}" width="{{ round($bw, 1) }}" height="{{ round($bh, 1) }}" rx="2" fill="var(--accent)"><title>{{ $r['date']->format('d M') }}: {{ number_format($r['poured_kg'] / 1000, 2) }} t</title></rect>@endif @endforeach
            @foreach ([0, intdiv($n, 2), $n - 1] as $i)@if (isset($rows[$i]))<text x="{{ round($x($i), 1) }}" y="{{ $H - 6 }}" text-anchor="{{ $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle') }}" font-size="10" fill="#64748b">{{ $rows[$i]['date']->format('d M') }}</text>@endif @endforeach
        </svg>
        <div style="font-size:12px;color:var(--dx-sub);margin-top:6px">Tonnes of liquid metal poured per day.</div>
    </div>
    <div class="mini">
        <div><div class="l">Overall pour yield</div><div class="v mono">{{ $d['yieldTotal'] !== null ? $d['yieldTotal'].'%' : '—' }}</div></div>
        <div><div class="l">Metal poured</div><div class="v mono">{{ number_format($tot / 1000, 1) }} t</div></div>
        <div><div class="l">Pieces rejected</div><div class="v mono">{{ $pieces > 0 ? number_format(100 * $rej / $pieces, 2).'%' : '—' }}</div></div>
    </div>
</div>
<script>
document.querySelectorAll('[data-chart]').forEach(function (c) {
    c.querySelectorAll('.tabs button').forEach(function (b) {
        b.addEventListener('click', function () {
            c.querySelectorAll('.tabs button').forEach(function (o) { o.classList.toggle('on', o === b); });
            c.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== b.getAttribute('data-t'); });
        });
    });
});
</script>
