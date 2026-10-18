{{-- Telemetry: the densest dashboard. A one-line status bar, a strip of KPIs, every furnace and station on one board, today's
     shift timeline, exceptions, sand against limits and 14-day trends, sized to fit a 1366×768 screen. ?tv=1 hides the menu
     and reloads every minute for a wall screen. --}}
@extends('layouts.app')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')
@section('content')
@php
    $k = $d['kpis'];
    $fmt = fn ($n, $dp = 0) => \App\Support\Formats::number((float) $n, $dp);
    $tv = request()->boolean('tv');
    $daily = $d['daily'];
    $yields = array_values(array_filter(array_column($daily, 'yield'), fn ($v) => $v !== null));
    $tl = $d['timeline'];
    $trends = [
        ['Scrap %', array_map(fn ($r) => $r['scrap'] ?? 0, $daily), fn ($v) => $fmt($v, 2).'%', 'var(--danger)'],
        ['Yield %', $yields, fn ($v) => $fmt($v, 1).'%', null],
        ['Tonnes poured', array_map(fn ($r) => $r['poured_kg'] / 1000, $daily), fn ($v) => $fmt($v, 1).' t', null],
        ['Castings poured', array_column($daily, 'poured'), fn ($v) => $fmt($v), null],
    ];
@endphp
<div class="dx tm">
@include('dashboard._style')
<style>
.dx.tm .tm-bar{display:flex;flex-wrap:wrap;align-items:center;gap:6px 18px;padding:6px 12px;margin:0 0 8px;border:1px solid var(--dx-line);border-radius:10px;background:var(--surface);font-size:12.5px;color:var(--dx-sub)}
.dx.tm .tm-bar .sp{flex:1}
.dx.tm .tm-bar a.lk{color:var(--dx-sub);font-weight:600}
.dx.tm .tm-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(128px,1fr));margin:0 0 8px;border:1px solid var(--dx-line);border-radius:10px;background:var(--surface);overflow:hidden}
.dx.tm .tm-k{display:block;padding:6px 10px;color:inherit;text-decoration:none;border-right:1px solid var(--dx-line);min-width:0}
.dx.tm .tm-k:last-child{border-right:0}
.dx.tm a.tm-k:hover{background:color-mix(in srgb,var(--accent) 6%,transparent);text-decoration:none}
.dx.tm .tm-k .cap{font-size:10px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dx.tm .tm-k .v{display:flex;align-items:baseline;gap:6px;font-size:19px;font-weight:700;line-height:1.25}
.dx.tm .tm-k .v small{font-size:11px;font-weight:500;color:var(--dx-mute)}
.dx.tm .tm-k .n{display:flex;justify-content:space-between;align-items:center;gap:6px;font-size:11px;color:var(--dx-mute);min-height:18px}
.dx.tm .bad{color:var(--danger)!important}
.dx.tm .tm-grid{display:grid;gap:8px;grid-template-columns:minmax(0,1fr) 330px;align-items:start}
.dx.tm .tm-grid>section{min-width:0;border:1px solid var(--dx-line);border-radius:10px;background:var(--surface);padding:8px 12px}
.dx.tm .tm-wide{grid-column:1/-1}
.dx.tm h2{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--dx-mute);margin:0 0 6px;display:flex;justify-content:space-between;align-items:center;gap:8px}
.dx.tm h2 a{font-size:11px;letter-spacing:0;text-transform:none;font-weight:600}
.dx.tm table.tm-t{width:100%;border-collapse:collapse;font-size:12.5px}
.dx.tm table.tm-t td{padding:4px 6px;border-bottom:1px solid var(--dx-line);white-space:nowrap;vertical-align:middle}
.dx.tm table.tm-t tr:last-child td{border-bottom:0}
.dx.tm table.tm-t td.job{white-space:normal;color:var(--dx-sub);width:100%}
.dx.tm .st{display:inline-flex;align-items:center;gap:6px}
.dx.tm .st i{width:8px;height:8px;border-radius:50%;background:var(--dx-mute);flex:none}
.dx.tm .st.amber i{background:var(--dx-amber)}.dx.tm .st.blue i{background:var(--accent)}.dx.tm .st.red i{background:var(--danger)}
.dx.tm .st.grey{color:var(--dx-mute)}
.dx.tm .bar{width:90px;height:5px}
.dx.tm .tm-att a{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:8px;align-items:center;padding:4px 0;border-bottom:1px solid var(--dx-line);color:inherit;font-size:12.5px;text-decoration:none}
.dx.tm .tm-att a:last-child{border-bottom:0}
.dx.tm .tm-att a:hover span.tx{color:var(--accent)}
.dx.tm .tm-att .tx{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dx.tm .tm-att .pill{font-size:10px;padding:1px 7px}
.dx.tm .tl-hours,.dx.tm .tl-lane{display:grid;grid-template-columns:70px minmax(0,1fr);align-items:center}
.dx.tm .tl-hours .tr{position:relative;height:14px;font-size:10px;color:var(--dx-mute)}
.dx.tm .tl-hours .tr span{position:absolute;transform:translateX(-50%)}
.dx.tm .tl-lane{height:20px;font-size:12px}
.dx.tm .tl-lane .tr{position:relative;height:14px;background:color-mix(in srgb,var(--dx-line) 45%,transparent);border-radius:3px}
.dx.tm .tl-lane .tr a{position:absolute;top:0;bottom:0;border-radius:3px;background:var(--accent);color:var(--surface);font-size:9.5px;line-height:14px;padding:0 3px;overflow:hidden;white-space:nowrap;text-decoration:none}
.dx.tm .tl-lane .tr a.amber{background:var(--dx-amber)}
.dx.tm .tl-now{position:absolute;top:-2px;bottom:-2px;width:0;border-left:2px solid var(--danger);z-index:1}
.dx.tm .sd{display:grid;grid-template-columns:minmax(0,1fr) 84px 54px;gap:6px;align-items:center;font-size:12px;padding:2px 0}
.dx.tm .sd .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--dx-sub)}
.dx.tm .sd .gauge{position:relative;height:8px;border-radius:4px;background:color-mix(in srgb,var(--dx-line) 60%,transparent)}
.dx.tm .sd .gauge b{position:absolute;top:0;bottom:0;border-radius:4px;background:color-mix(in srgb,var(--accent) 25%,transparent)}
.dx.tm .sd .gauge i{position:absolute;top:-2px;width:4px;height:12px;margin-left:-2px;border-radius:2px;background:var(--dx-ink)}
.dx.tm .sd .gauge i.bad{background:var(--danger)}
.dx.tm .sd .val{text-align:right}
.dx.tm .sys{display:flex;justify-content:space-between;font-size:12px;margin:6px 0 2px}
.dx.tm .sys:first-of-type{margin-top:0}
.dx.tm .tr4{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px}
.dx.tm .tr4>div{display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:12px;color:var(--dx-sub)}
.dx.tm .tr4 strong{display:block;font-size:14px;color:var(--dx-ink)}
.dx.tm .empty{padding:6px 0;font-size:12.5px}
@media(max-width:1024px){.dx.tm .tm-grid{grid-template-columns:1fr}}
</style>
@if ($tv)<style>.sidebar,.topbar,.sidebar-resizer{display:none!important}.main-area{margin-left:0!important}.page-content{padding:10px 14px!important}</style>@endif

<div class="tm-bar">
    <span class="live" style="padding:1px 8px"><i class="dot"></i>Live</span>
    <span class="mono">{{ now()->format('D d M Y · H:i') }}</span>
    <span>{{ $k['heats']['running'] }} heat{{ $k['heats']['running'] === 1 ? '' : 's' }} running</span>
    <span>{{ $d['queueTotal'] }} open production order{{ $d['queueTotal'] === 1 ? '' : 's' }}</span>
    <span class="sp"></span>
    <div class="switch" title="Choose a dashboard layout">@foreach (['classic' => 'Classic', 'command' => 'Command', 'columns' => 'Columns', 'telemetry' => 'Telemetry'] as $key => $name)<a href="{{ route('dashboard', ['layout' => $key]) }}" class="{{ $key === 'telemetry' ? 'on' : '' }}">{{ $name }}</a>@endforeach</div>
    <a class="lk" href="{{ route('dashboard', ['layout' => 'telemetry'] + ($tv ? [] : ['tv' => 1])) }}">{{ $tv ? 'Exit wall screen' : 'Wall screen' }}</a>
    <a class="lk" href="{{ route('scan') }}">Scan</a>
</div>

<div class="tm-kpis">
    <a class="tm-k" href="{{ route('enquiries.index') }}"><span class="cap">Open enquiries</span><span class="v mono">{{ $fmt($k['enquiries']['open']) }}</span><span class="n"><span>+{{ $k['enquiries']['today'] }} today</span>@include('dashboard._spark', ['vals' => $k['enquiries']['spark'], 'width' => 48, 'height' => 16])</span></a>
    <a class="tm-k" href="{{ route('production-orders.index') }}"><span class="cap">Due in 7 days</span><span class="v mono">{{ $fmt($k['due']['count']) }} <small>orders</small></span><span class="n {{ $k['due']['overdue'] ? 'bad' : '' }}">{{ $k['due']['overdue'] ? $k['due']['overdue'].' late' : 'none late' }}</span></a>
    <a class="tm-k" href="{{ route('heats.index') }}"><span class="cap">Heats today</span><span class="v mono">{{ $k['heats']['done'] }} <small>/ {{ $k['heats']['total'] }}</small></span><span class="n"><span class="bar" style="width:100%"><i style="width:{{ $k['heats']['total'] > 0 ? min(100, round(100 * $k['heats']['done'] / $k['heats']['total'])) : 0 }}%"></i></span></span></a>
    <a class="tm-k" href="{{ route('heats.index') }}"><span class="cap">Tapped today</span><span class="v mono">{{ $fmt($k['heats']['tonnes'], 1) }} <small>t</small></span><span class="n">{{ $k['heats']['running'] }} melting</span></a>
    <a class="tm-k" href="{{ route('moulding-runs.index') }}"><span class="cap">Moulds today</span><span class="v mono">{{ $fmt($d['mouldsToday']) }}</span><span class="n">made</span></a>
    <a class="tm-k" href="{{ route('reports.yield') }}"><span class="cap">Scrap, 7 days</span><span class="v mono {{ ($k['scrap']['rate'] ?? 0) > 3 ? 'bad' : '' }}">{{ $k['scrap']['rate'] !== null ? $fmt($k['scrap']['rate'], 2).'%' : '—' }}</span><span class="n"><span>@if ($k['scrap']['delta'] !== null){{ $k['scrap']['delta'] <= 0 ? '↓' : '↑' }} {{ $fmt(abs($k['scrap']['delta']), 2) }}@else no pours @endif</span>@include('dashboard._spark', ['vals' => $k['scrap']['spark'], 'width' => 48, 'height' => 16, 'color' => 'var(--danger)'])</span></a>
    <a class="tm-k" href="{{ route('reports.yield') }}"><span class="cap">Yield, 14 days</span><span class="v mono">{{ $d['yieldTotal'] !== null ? $fmt($d['yieldTotal'], 1).'%' : '—' }}</span><span class="n"><span>good ÷ poured</span>@include('dashboard._spark', ['vals' => $yields, 'width' => 48, 'height' => 16])</span></a>
    <a class="tm-k" href="{{ route('ncrs.index') }}"><span class="cap">Open NCRs</span><span class="v mono">{{ $k['ncrs']['open'] }}</span><span class="n {{ $k['ncrs']['major'] ? 'bad' : '' }}">{{ $k['ncrs']['major'] }} major · {{ $k['ncrs']['minor'] }} minor</span></a>
</div>

<div class="tm-grid">
    <section>
        <h2>Floor board <a href="{{ route('moulding-runs.index') }}">Moulding runs</a></h2>
        @if (count($d['board']))
            <table class="tm-t"><tbody>
                @foreach ($d['board'] as $r)
                    <tr>
                        <td><a href="{{ $r['url'] }}"><strong class="mono">{{ $r['code'] }}</strong></a></td>
                        <td><span class="st {{ $r['tone'] }}"><i></i>{{ $r['state'] }}</span></td>
                        <td class="job">{{ $r['job'] }}</td>
                        <td>@if ($r['progress'] !== null)<div class="bar"><i style="width:{{ $r['progress'] }}%"></i></div>@endif</td>
                        <td class="mono" style="color:var(--dx-mute)">{{ $r['meta'] }}</td>
                    </tr>
                @endforeach
            </tbody></table>
        @else
            <div class="empty">No active furnaces or moulding stations are set up.</div>
        @endif
    </section>

    <section class="tm-att">
        <h2>Needs attention @if (count($d['attention']))<span class="pill red">{{ count($d['attention']) }}</span>@endif</h2>
        @forelse ($d['attention'] as $a)
            <a href="{{ $a['actions'][0][1] ?? '#' }}" title="{{ $a['text'] }}"><span class="pill {{ $a['tone'] }}">{{ $a['tag'] }}</span><span class="tx">{{ $a['ref'] ? $a['ref'].' · ' : '' }}{{ $a['text'] }}</span><span class="mono" style="color:var(--dx-mute);font-size:11px">{{ \Illuminate\Support\Carbon::parse($a['at'])->diffForHumans(null, true, true) }}</span></a>
        @empty
            <div class="empty">Nothing needs attention right now.</div>
        @endforelse
    </section>

    <section>
        <h2>Shift timeline <span class="mono" style="font-weight:500;letter-spacing:0;text-transform:none">{{ $tl['from']->displayTime() }}–{{ $tl['to']->displayTime() }}</span></h2>
        @if (count($tl['lanes']))
            <div class="tl-hours"><span></span><div class="tr">@foreach ($tl['hours'] as $h)<span style="left:{{ $h['at'] }}%">{{ $h['label'] }}</span>@endforeach</div></div>
            @foreach ($tl['lanes'] as $lane => $bars)
                <div class="tl-lane"><span class="mono">{{ $lane }}</span><div class="tr">
                    @if ($tl['now'] !== null)<span class="tl-now" style="left:{{ $tl['now'] }}%" title="Now"></span>@endif
                    @foreach ($bars as $b)<a class="{{ $b['tone'] }}" href="{{ $b['url'] }}" style="left:{{ $b['left'] }}%;width:{{ $b['width'] }}%" title="{{ $b['label'] }}">{{ $b['label'] }}</a>@endforeach
                </div></div>
            @endforeach
        @else
            <div class="empty">No heat or moulding run has started today.</div>
        @endif
    </section>

    <section>
        <h2>Sand, last test <a href="{{ route('sand-tests.index') }}">Full log</a></h2>
        @forelse ($d['sand'] as $s)
            <div class="sys"><strong>{{ $s['system'] }}</strong><span class="mono" style="color:var(--dx-mute)">{{ $s['set']->taken_at?->diffForHumans(null, true, true) }}</span></div>
            @foreach ($s['set']->measurements->sortBy('sort_order')->take(4) as $m)
                @php
                    $lo = $m->min_value; $hi = $m->max_value; $v = $m->value;
                    $hasRange = $lo !== null && $hi !== null && (float) $hi > (float) $lo && $v !== null;
                    if ($hasRange) { $pad = ((float) $hi - (float) $lo) / 2; $base = (float) $lo - $pad; $span = ((float) $hi + $pad) - $base; $pos = fn ($x) => max(0, min(100, round(100 * ((float) $x - $base) / $span, 1))); }
                @endphp
                <div class="sd">
                    <span class="nm" title="{{ $m->name }}">{{ $m->name }}</span>
                    <span>@if ($hasRange)<span class="gauge" title="Limits {{ $lo }}–{{ $hi }}"><b style="left:{{ $pos($lo) }}%;width:{{ $pos($hi) - $pos($lo) }}%"></b><i class="{{ $m->passed === false ? 'bad' : '' }}" style="left:{{ $pos($v) }}%"></i></span>@endif</span>
                    <span class="val mono {{ $m->passed === false ? 'bad' : '' }}">{{ $v !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $v, 2), '0'), '.') : ($m->text_value ?? '—') }}{{ $m->unit ? ' '.$m->unit : '' }}</span>
                </div>
            @endforeach
        @empty
            <div class="empty">No sand test has been recorded yet.</div>
        @endforelse
    </section>

    <section class="tm-wide">
        <h2>Last 14 days <a href="{{ route('reports.yield') }}">Yield report</a></h2>
        <div class="tr4">
            @foreach ($trends as [$label, $vals, $show, $colour])
                <div><span>{{ $label }}<strong class="mono">{{ count($vals) ? $show(end($vals)) : '—' }}</strong></span>@if (count($vals) > 1)@include('dashboard._spark', ['vals' => $vals, 'width' => 120, 'height' => 28, 'color' => $colour ?? 'var(--accent)'])@else<span class="muted small">not enough data</span>@endif</div>
            @endforeach
        </div>
    </section>
</div>
</div>
@if ($tv)<script>setTimeout(function () { location.reload(); }, 60000);</script>@endif
@endsection
