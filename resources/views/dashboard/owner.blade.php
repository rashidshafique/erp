{{-- design: 4a. The owner's home: month to date, what needs a decision, where money and quality are leaking. --}}
@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
@php
    $first = strtok(auth()->user()?->name ?? '', ' ');
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $tone = ['red' => '#b3352c', 'amber' => '#c58a1a', 'blue' => 'var(--accent)'];
    $money = fn (float $n) => \App\Support\Formats::number($n, 0);
    $one = fn (float $n) => number_format($n, 1);
    $pctPlan = $o['plan'] > 0 ? min(100, round(100 * $o['good'] / $o['plan'])) : null;
    $change = $o['salesPrev'] > 0 ? round(100 * ($o['sales'] - $o['salesPrev']) / $o['salesPrev']) : null;
    $shades = ['#1f3f8f', '#5b80dd', '#b9c9f1', '#8aa2d8', '#d7def2'];
    $peak = max(1, collect($o['tonnage'])->max('total'));
@endphp
<style>
.own { display: flex; flex-direction: column; gap: 20px; }
.own-head { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; }
.own-head h1 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -.01em; }
.own-head span { font-size: 13px; color: var(--muted); }
.own-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
.own-kpi { border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; background: var(--surface); }
.own-kpi .l { font-size: 12px; color: var(--muted); }
.own-kpi .v { margin-top: 4px; font-size: 24px; font-weight: 600; }
.own-kpi .n { margin-top: 8px; font-size: 12px; color: var(--muted); }
.own-kpi .n.ok { color: #1d7048; } .own-kpi .n.bad { color: #b3352c; } .own-kpi .n.warn { color: #94610a; }
.own-bar { height: 4px; margin-top: 8px; border-radius: 2px; background: #eff0f2; } .own-bar i { display: block; height: 4px; border-radius: 2px; background: var(--accent); }
.own-two { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 20px; }
.own-two.wide { grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); }
.own-box { border: 1px solid var(--border); border-radius: 8px; background: var(--surface); overflow: hidden; }
.own-box .bh { display: flex; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600; }
.own-box .bh span + span { font-weight: 400; color: var(--muted); }
.own-row { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-bottom: 1px solid var(--surface-pill); font-size: 13px; }
.own-row:last-child { border-bottom: 0; }
.own-row .g { flex: 1; min-width: 0; } .own-row .g b { font-weight: 500; } .own-row .g div + div { color: var(--muted); }
.own-dot { flex: none; width: 8px; height: 8px; margin-top: 5px; align-self: flex-start; border-radius: 50%; }
.own-empty { padding: 20px 16px; color: var(--muted); font-size: 13px; }
.own-chart { padding: 14px 16px; }
.own-legend { display: flex; gap: 12px; font-size: 12px; color: var(--muted); } .own-legend i { display: inline-block; width: 9px; height: 9px; margin-right: 5px; border-radius: 2px; }
.own-cols { display: flex; align-items: flex-end; gap: 14px; height: 140px; margin-top: 12px; }
.own-col { flex: 1; display: flex; flex-direction: column; gap: 2px; justify-content: flex-end; height: 100%; font-size: 11px; color: var(--muted); text-align: center; }
.own-col span.s { display: block; }
.own-box td.r { text-align: right; } .own-box td.m { color: var(--muted); } .own-box tr:last-child td { border-bottom: 0; }
@media (min-width: 769px) { .topbar .breadcrumb { display: none; } .page-content { max-width: none; } }
@media (max-width: 1100px) { .own-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } .own-two, .own-two.wide { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 768px) { .own-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .own-head { flex-direction: column; align-items: flex-start; } }
</style>

<div class="own">
    <div class="own-head">
        <h1>{{ $greeting }}{{ $first ? ', '.$first : '' }}</h1>
        <span>{{ now()->format('D j M') }} · {{ now()->format('F') }}, day {{ now()->day }} of {{ now()->daysInMonth }}</span>
    </div>

    <div class="own-kpis">
        <div class="own-kpi"><div class="l">Good castings</div><div class="v">{{ $one($o['good']) }} t</div>
            @if ($pctPlan !== null)<div class="own-bar"><i style="width:{{ $pctPlan }}%"></i></div><div class="n" style="margin-top:4px">{{ $pctPlan }}% of {{ $one($o['plan']) }} t plan</div>@else<div class="n">No plan for the month</div>@endif</div>
        <div class="own-kpi"><div class="l">Sales invoiced</div><div class="v">{{ $money($o['sales']) }}</div>
            <div class="n {{ $change === null ? '' : ($change >= 0 ? 'ok' : 'bad') }}">{{ $change === null ? 'Nothing invoiced last month' : ($change >= 0 ? '+' : '').$change.'% vs last month' }}</div></div>
        <div class="own-kpi"><div class="l">Internal rejection</div><div class="v">{{ $o['scrap'] === null ? '—' : $one($o['scrap']).'%' }}</div>
            <div class="n {{ $o['scrap'] === null ? '' : ($o['scrap'] > 3 ? 'bad' : 'ok') }}">{{ $o['scrap'] === null ? 'Nothing poured this month' : 'Target 3.0%' }}</div></div>
        <div class="own-kpi"><div class="l">Cash to collect</div><div class="v">{{ $money($o['owed']) }}</div>
            <div class="n {{ $o['overdue'] > 0 ? 'bad' : '' }}">{{ $o['overdue'] > 0 ? $money($o['overdue']).' over 60 days' : 'Nothing over 60 days' }}</div></div>
        <div class="own-kpi"><div class="l">Open NCRs</div><div class="v">{{ $o['ncrs'] }}</div>
            <div class="n {{ $o['ncrMajor'] ? 'bad' : '' }}">{{ $o['ncrMajor'] }} major or critical</div></div>
    </div>

    <div class="own-two">
        <div class="own-box">
            <div class="bh"><span>Waiting for your decision</span><span>{{ $o['decisions']->count() }}</span></div>
            @forelse ($o['decisions'] as $a)
                <div class="own-row"><div class="g"><div><b>{{ $a->actionLabel() }}</b>@if ($a->subject_label) · {{ $a->subject_label }}@endif</div><div>Asked {{ $a->created_at->diffForHumans() }}</div></div>
                    <a class="btn btn-primary" href="{{ route('approvals.index') }}">Review</a></div>
            @empty
                <div class="own-empty">Nothing is waiting for a decision.</div>
            @endforelse
        </div>
        <div class="own-box">
            <div class="bh"><span>Where it's slipping</span>@if ($o['late'])<span>{{ $o['late'] }} order{{ $o['late'] === 1 ? '' : 's' }} late</span>@endif</div>
            @forelse (array_slice($o['slipping'], 0, 4) as $a)
                <div class="own-row"><span class="own-dot" style="background:{{ $tone[$a['tone']] ?? $tone['amber'] }}"></span>
                    <div class="g"><div><b>{{ $a['ref'] ? $a['ref'].' · ' : '' }}{{ $a['tag'] }}</b></div><div>{{ $a['text'] }}@if (! empty($a['actions'])) <a href="{{ $a['actions'][0][1] }}">{{ $a['actions'][0][0] }}</a>@endif</div></div></div>
            @empty
                <div class="own-empty">Nothing is slipping.</div>
            @endforelse
        </div>
    </div>

    <div class="own-two wide">
        <div class="own-box own-chart">
            <div style="display:flex;justify-content:space-between;font-size:13px"><b style="font-weight:600">Good tonnage by week</b>
                <span class="own-legend">@foreach ($o['families'] as $i => $f)<span><i style="background:{{ $shades[$i % count($shades)] }}"></i>{{ $f }}</span>@endforeach</span></div>
            <div class="own-cols">
                @foreach ($o['tonnage'] as $w)
                    <div class="own-col" title="{{ $one($w['total']) }} t">
                        @foreach (array_reverse($o['families'], true) as $i => $f)
                            @if (($w['by'][$f] ?? 0) > 0)<span class="s" style="height:{{ round(100 * $w['by'][$f] / $peak) }}%;background:{{ $shades[$i % count($shades)] }}"></span>@endif
                        @endforeach
                        <span style="margin-top:4px">{{ $w['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="own-box">
            <div class="bh"><span>Top customers this month</span></div>
            @if (count($o['customers']))
                <table><tbody>
                    @foreach ($o['customers'] as $c)
                        <tr><td>{{ $c['name'] }}</td><td class="r">{{ $money($c['sales']) }}</td><td class="r m">{{ $c['owed'] > 0 ? $money($c['owed']).' owed' : 'Paid' }}</td></tr>
                    @endforeach
                </tbody></table>
            @else
                <div class="own-empty">Nothing invoiced this month.</div>
            @endif
        </div>
    </div>
</div>
@endsection
