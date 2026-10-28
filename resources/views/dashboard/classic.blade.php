{{-- design: 1a. A greeting, four headline numbers, the pipeline from enquiry to dispatch, what needs attention and today's melt. --}}
@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
@php
    $first = strtok(auth()->user()?->name ?? '', ' ');
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $tone = ['red' => 'dp-bad', 'amber' => 'dp-warn', 'blue' => 'dp-accent', 'ok' => 'dp-ok', 'accent' => 'dp-accent', '' => ''];
    $fmt = fn (float $t) => number_format($t, 1);
    $meltSummary = collect([
        ($melt['planned'] > 0 || $melt['charged'] == 0) ? $fmt($melt['poured']).' of '.$fmt($melt['planned']).' t poured' : null,
        $melt['charged'] > 0 ? $fmt($melt['charged']).' t charged' : null,
    ])->filter()->implode(' · ');
@endphp
<style>
.dash { display: flex; flex-direction: column; gap: 24px; }
.dash-head { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; }
.dash-head h1 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -.01em; }
.dash-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.dash-kpi { border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; background: var(--surface); }
.dash-kpi .l { font-size: 12px; color: var(--muted); }
.dash-kpi .v { margin-top: 4px; font-size: 24px; font-weight: 600; }
.dash-kpi .n { margin-top: 2px; font-size: 12px; color: var(--muted); }
.dash-kpi .n.ok { color: #1d7048; } .dash-kpi .n.bad { color: #b3352c; }
.dash-title { margin-bottom: 10px; font-size: 13px; font-weight: 600; }
.dash-pipe { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; }
.dash-col { display: flex; flex-direction: column; gap: 8px; padding: 10px; border-radius: 8px; background: var(--surface-sub); min-width: 0; }
.dash-col .h { display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); }
.dash-card { display: block; padding: 8px; border: 1px solid var(--border); border-radius: 6px; background: #fff; color: var(--text); font-size: 12px; text-decoration: none !important; min-width: 0; }
.dash-card:hover { border-color: var(--border-input); }
.dash-card code { color: var(--muted); font-family: ui-monospace, "SF Mono", Menlo, monospace; font-size: .92em; }
.dash-card .t { margin-top: 2px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dash-card .s { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted); }
.dash-card .s.bad { color: #b3352c; } .dash-card .s.accent { color: var(--accent); } .dash-card .s.warn { color: #94610a; }
.dash-two { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 20px; }
.dash-box { border: 1px solid var(--border); border-radius: 8px; background: var(--surface); overflow: hidden; }
.dash-box .bh { display: flex; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600; }
.dash-box .bh span + span { font-weight: 400; color: var(--muted); }
.dash-box td code { font-family: ui-monospace, "SF Mono", Menlo, monospace; font-size: .92em; }
.dash-box tr:last-child td { border-bottom: 0; }
.dash-box .r { color: var(--muted); text-align: right; }
.dash-empty { padding: 20px 16px; color: var(--muted); font-size: 13px; }
.dp { display: inline-block; padding: 2px 8px; border-radius: 999px; background: var(--surface-pill); color: #55585d; font-size: 11.5px; font-weight: 500; white-space: nowrap; }
.dp-bad { background: #fbe7e5; color: #b3352c; } .dp-warn { background: #fbf0dc; color: #94610a; }
.dp-accent { background: color-mix(in srgb, var(--accent) 12%, #fff); color: var(--accent); } .dp-ok { background: #e7f4ec; color: #1d7048; }
@media (min-width: 769px) { .topbar .breadcrumb { display: none; } .topbar .scan-box { margin: 0 auto 0 0 !important; } .page-content { max-width: none; } }
@media (max-width: 1100px) { .dash-pipe { grid-template-columns: repeat(4, minmax(0, 1fr)); } .dash-two { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 768px) { .dash-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dash-pipe { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dash-head { flex-direction: column; align-items: flex-start; } }
</style>

<div class="dash">
    <div class="dash-head">
        <h1>{{ $greeting }}{{ $first ? ', '.$first : '' }}</h1>
        @if (Route::has('enquiries.create'))<a href="{{ route('enquiries.create') }}" class="btn btn-primary">New enquiry</a>@endif
    </div>

    <div class="dash-kpis">
        @foreach ($headline as $k)
            <div class="dash-kpi"><div class="l">{{ $k['label'] }}</div><div class="v">{{ $k['value'] }}</div><div class="n {{ $k['tone'] }}">{{ $k['note'] }}</div></div>
        @endforeach
    </div>

    <div>
        <div class="dash-title">Pipeline</div>
        <div class="dash-pipe">
            @foreach ($pipeline as $stage)
                <div class="dash-col">
                    <div class="h"><span>{{ $stage['label'] }}</span><span>{{ $stage['count'] }}</span></div>
                    @foreach ($stage['cards'] as $c)
                        <a class="dash-card" href="{{ $c['url'] }}"><code>{{ $c['code'] }}</code><div class="t">{{ $c['name'] }}</div><div class="s {{ $c['tone'] }}">{{ $c['sub'] }}</div></a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <div class="dash-two">
        <div class="dash-box">
            <div class="bh"><span>Needs attention</span></div>
            @if (count($d['attention']))
                <table><tbody>
                    @foreach (array_slice($d['attention'], 0, 6) as $a)
                        <tr>
                            <td><span class="dp {{ $tone[$a['tone']] ?? '' }}">{{ $a['tag'] }}</span></td>
                            <td>@if ($a['ref'])<code>{{ $a['ref'] }}</code> @endif{{ $a['text'] }}</td>
                            <td class="r">@if (! empty($a['actions']))<a href="{{ $a['actions'][0][1] }}">{{ $a['actions'][0][0] }}</a>@else{{ \Illuminate\Support\Carbon::parse($a['at'])->diffForHumans(null, true, true) }}@endif</td>
                        </tr>
                    @endforeach
                </tbody></table>
            @else
                <div class="dash-empty">Nothing needs attention right now.</div>
            @endif
        </div>
        <div class="dash-box">
            <div class="bh"><span>Today's melt</span><span>{{ $meltSummary }}</span></div>
            @if (count($melt['rows']))
                <table><tbody>
                    @foreach ($melt['rows'] as $m)
                        <tr><td><code>{{ $m['code'] }}</code></td><td>{{ $m['grade'] }}</td><td class="r" style="text-align:left">{{ $m['heats'] }} heat{{ $m['heats'] === 1 ? '' : 's' }}</td><td class="r" style="text-align:left">{{ $m['metal'] }}</td><td><span class="dp {{ $tone[$m['tone']] ?? '' }}">{{ $m['state'] }}</span></td></tr>
                    @endforeach
                </tbody></table>
            @else
                <div class="dash-empty">No heats planned for today.</div>
            @endif
            <div class="bh" style="border-top:1px solid var(--border);border-bottom:0"><span style="font-weight:400;color:var(--muted)">Next heat</span>@if ($melt['next'])<a href="{{ $melt['next']['url'] }}" style="font-weight:500">{{ $melt['next']['at']->displayShortDateTime() }} · {{ $melt['next']['number'] }}@if ($melt['next']['furnace']) · {{ $melt['next']['furnace'] }}@endif</a>@else<span>None scheduled</span>@endif</div>
        </div>
    </div>
</div>
@endsection
