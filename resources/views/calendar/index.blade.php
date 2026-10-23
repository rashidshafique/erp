@extends('layouts.app')

@section('title', 'Calendar')
@section('breadcrumb', 'Calendar')

@php
    $url = fn ($v, $d) => route('calendar.index', ['view' => $v, 'date' => $d->toDateString()]);
    $todayKey = today()->toDateString();
    $chip = function ($e) {
        $tag = $e['url'] ? 'a href="'.e($e['url']).'"' : 'span';
        $close = $e['url'] ? 'a' : 'span';

        return '<'.$tag.' class="cal-chip cal-'.strtolower($e['kind']).'" title="'.e($e['label'].' — '.$e['detail']).'">'.e($e['label']).'</'.$close.'>';
    };
    $weekStart = $view === 'month' ? $start->copy()->startOfWeek() : $start;
    $weekEnd = $view === 'month' ? $end->copy()->endOfWeek() : $end;
@endphp

@section('content')
<div class="page-heading">
    <div><h1>Calendar</h1><p>Planned and past heats, pours, moulding runs and order due dates. Shaded days are campaign days under the melting calendar.</p></div>
    <div class="actions" style="gap:8px">
        <div class="cal-switch">@foreach (['week' => 'Week', 'month' => 'Month', 'year' => 'Year', 'gantt' => 'Gantt'] as $v => $name)<a class="btn btn-small {{ $view === $v ? 'btn-primary' : '' }}" href="{{ $url($v, $anchor) }}">{{ $name }}</a>@endforeach</div>
        <a class="btn btn-small" href="{{ $url($view, $previous) }}" aria-label="Previous">‹</a>
        <a class="btn btn-small" href="{{ $url($view, today()) }}">Today</a>
        <a class="btn btn-small" href="{{ $url($view, $next) }}" aria-label="Next">›</a>
    </div>
</div>

<div class="panel"><div class="panel-header"><h2>{{ $title }}</h2>
    <span class="cal-legend">@foreach ($kinds as $k => $name)<span><i class="cal-dot cal-{{ strtolower($k) }}"></i>{{ $name }}</span>@endforeach</span></div>
<div class="panel-body">
@if ($view === 'gantt')
    @php
        $days = (int) $start->diffInDays($end->copy()->startOfDay()) + 1;
        $pct = fn ($d) => max(0, min(100, $start->diffInDays($d->copy()->startOfDay(), false) / $days * 100));
        $todayPct = today()->between($start, $end) ? $pct(today()) + 50 / $days : null;
    @endphp
    <p class="muted small">One bar per open production order, from when it started to its due date. Dark is poured, light is made, the rest is still to do. Red is late, orange means no campaign day is left to pour in time.</p>
    @if ($bars->isEmpty())
        <div class="empty-state">No open production orders fall in these weeks.</div>
    @else
    <div class="gt">
        <div class="gt-row gt-head"><div class="gt-label"></div><div class="gt-track">
            @for ($d = $start->copy(); $d <= $end; $d->addDay())
                <i class="gt-cell @if ($calendar->isCampaignDay($d)) campaign @endif" style="left:{{ $pct($d) }}%;width:{{ 100 / $days }}%" title="{{ $d->format('D j M') }}{{ $calendar->isCampaignDay($d) ? ' · campaign day' : '' }}"></i>
                @if ($d->isMonday())<span class="gt-week" style="left:{{ $pct($d) }}%">{{ $d->format('j M') }}</span>@endif
            @endfor
            @if ($todayPct !== null)<b class="gt-today" style="left:{{ $todayPct }}%"></b>@endif
        </div></div>
        @foreach ($bars as $b)
            @php
                $o = $b['order'];
                $endDay = $b['due'] ?? $b['start'];
                $left = $pct($b['start']);
                $right = $endDay->gt($end) ? 100 : min(100, $pct($endDay) + 100 / $days);
                $width = max($right - $left, 100 / $days);
                $ordered = max(1, $b['ordered']);
                $pouredPct = min(100, $b['poured'] / $ordered * 100);
                $madePct = min(100, $b['made'] / $ordered * 100);
                $state = $b['late'] ? 'late' : ($b['at_risk'] ? 'risk' : '');
            @endphp
            <div class="gt-row">
                <div class="gt-label"><a href="{{ route('production-orders.show', $o) }}"><strong>{{ $o->production_order_number }}</strong></a>
                    <small class="muted">{{ $o->product?->code }} · {{ $b['customer'] }}</small></div>
                <div class="gt-track">
                    @if ($todayPct !== null)<b class="gt-today" style="left:{{ $todayPct }}%"></b>@endif
                    <a href="{{ route('production-orders.show', $o) }}" class="gt-bar {{ $state }}" style="left:{{ $left }}%;width:{{ $width }}%"
                       title="{{ $o->production_order_number }}: {{ $b['start']->format('j M') }} → {{ $b['due']?->format('j M') ?? 'no due date' }} · ordered {{ $b['ordered'] }}, made {{ $b['made'] }}, poured {{ $b['poured'] }}{{ $b['late'] ? ' · LATE' : ($b['at_risk'] ? ' · no campaign day left in time' : '') }}">
                        <span class="gt-made" style="width:{{ $madePct }}%"></span><span class="gt-poured" style="width:{{ $pouredPct }}%"></span>
                        <em>{{ $b['poured'] }}/{{ $b['made'] }}/{{ $b['ordered'] }}</em>
                    </a>
                </div>
            </div>
        @endforeach
    </div>
    <p class="muted small">Numbers on a bar: poured / made / ordered pieces.</p>
    @endif
@elseif ($view === 'year')
    <div class="cal-year">
    @for ($m = 1; $m <= 12; $m++)
        @php $first = $anchor->copy()->month($m)->startOfMonth(); $from = $first->copy()->startOfWeek(); $to = $first->copy()->endOfMonth()->endOfWeek(); @endphp
        <div class="cal-mini"><a class="cal-mini-title" href="{{ $url('month', $first) }}">{{ $first->format('F') }}</a>
            <div class="cal-mini-grid">
                @for ($d = $from->copy(); $d <= $to; $d->addDay())
                    @php $key = $d->toDateString(); $n = $d->month === $m ? ($events[$key] ?? collect())->count() : 0; @endphp
                    <a href="{{ $url('week', $d) }}" class="cal-mini-day @if ($d->month !== $m) other @endif @if ($key === $todayKey) today @endif @if ($d->month === $m && $calendar->isCampaignDay($d)) campaign @endif @if ($n) has @endif" title="{{ $d->format('j M') }}{{ $n ? ': '.$n.' '.($n === 1 ? 'item' : 'items') : '' }}">{{ $d->day }}</a>
                @endfor
            </div></div>
    @endfor
    </div>
@elseif ($view === 'month')
    <div class="cal-grid">
        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $n)<div class="cal-dow">{{ $n }}</div>@endforeach
        @for ($d = $weekStart->copy(); $d <= $weekEnd; $d->addDay())
            @php $key = $d->toDateString(); $day = $events[$key] ?? collect(); @endphp
            <div class="cal-day @if ($d->month !== $start->month) other @endif @if ($key === $todayKey) today @endif @if ($calendar->isCampaignDay($d)) campaign @endif">
                <a class="cal-num" href="{{ $url('week', $d) }}">{{ $d->day }}</a>
                @foreach ($day->take(4) as $e){!! $chip($e) !!}@endforeach
                @if ($day->count() > 4)<a class="cal-more" href="{{ $url('week', $d) }}">+{{ $day->count() - 4 }} more</a>@endif
            </div>
        @endfor
    </div>
@else
    <div class="cal-week">
        @for ($d = $start->copy(); $d <= $end; $d->addDay())
            @php $key = $d->toDateString(); $day = $events[$key] ?? collect(); @endphp
            <div class="cal-day tall @if ($key === $todayKey) today @endif @if ($calendar->isCampaignDay($d)) campaign @endif">
                <div class="cal-num">{{ $d->format('D j') }}</div>
                @forelse ($day as $e)
                    <div class="cal-row">{!! $chip($e) !!}<small class="muted">{{ $kinds[$e['kind']] }}@if ($e['detail']) · {{ $e['detail'] }}@endif</small></div>
                @empty<small class="muted">Nothing</small>@endforelse
            </div>
        @endfor
    </div>
@endif
</div></div>

<style>
    .cal-legend { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 12px; color: var(--muted); }
    .cal-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 4px; }
    .cal-planned { --c: #7c5cc4; } .cal-heat { --c: #d9622b; } .cal-pour { --c: #c0392b; } .cal-moulding { --c: #2a7fb8; } .cal-due { --c: #6b7280; }
    .cal-dot { background: var(--c); }
    .cal-grid, .cal-week { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; }
    .cal-dow { font-size: 12px; color: var(--muted); text-align: center; }
    .cal-day { border: 1px solid var(--border); border-radius: 6px; min-height: 96px; padding: 4px; display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .cal-day.tall { min-height: 220px; }
    .cal-day.campaign { background: color-mix(in srgb, var(--primary, #2a7fb8) 8%, transparent); }
    .cal-day.other { opacity: .45; }
    .cal-day.today { outline: 2px solid var(--primary, #2a7fb8); }
    .cal-num { font-size: 12px; font-weight: 600; color: var(--muted); }
    .cal-chip { display: block; font-size: 11px; line-height: 1.3; padding: 1px 5px; border-radius: 4px; border-left: 3px solid var(--c); background: color-mix(in srgb, var(--c) 14%, transparent); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cal-more { font-size: 11px; color: var(--muted); }
    .cal-row { display: flex; flex-direction: column; margin-bottom: 4px; }
    .cal-year { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
    .cal-mini-title { font-weight: 600; display: block; margin-bottom: 4px; }
    .cal-mini-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
    .cal-mini-day { font-size: 11px; text-align: center; padding: 3px 0; border-radius: 4px; color: inherit; text-decoration: none; }
    .cal-mini-day.other { visibility: hidden; }
    .cal-mini-day.campaign { background: color-mix(in srgb, var(--primary, #2a7fb8) 10%, transparent); }
    .cal-mini-day.has { background: color-mix(in srgb, #d9622b 35%, transparent); font-weight: 600; }
    .cal-mini-day.today { outline: 2px solid var(--primary, #2a7fb8); }
    .gt { overflow-x: auto; }
    .gt-row { display: grid; grid-template-columns: 190px minmax(640px, 1fr); border-bottom: 1px solid var(--border); }
    .gt-label { padding: 4px 8px 4px 0; display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
    .gt-label small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gt-track { position: relative; height: 34px; }
    .gt-head .gt-track { height: 24px; }
    .gt-cell { position: absolute; top: 0; bottom: 0; border-left: 1px solid color-mix(in srgb, var(--border) 60%, transparent); }
    .gt-cell.campaign { background: color-mix(in srgb, var(--primary, #2a7fb8) 8%, transparent); }
    .gt-week { position: absolute; top: 3px; font-size: 11px; color: var(--muted); padding-left: 3px; }
    .gt-today { position: absolute; top: 0; bottom: 0; width: 2px; background: var(--primary, #2a7fb8); z-index: 2; }
    .gt-bar { position: absolute; top: 6px; height: 22px; border-radius: 5px; background: color-mix(in srgb, #2a7fb8 18%, transparent); border: 1px solid #2a7fb8; overflow: hidden; z-index: 1; color: inherit; text-decoration: none; }
    .gt-bar.risk { border-color: #d98a1c; background: color-mix(in srgb, #d98a1c 18%, transparent); }
    .gt-bar.late { border-color: #c0392b; background: color-mix(in srgb, #c0392b 18%, transparent); }
    .gt-made, .gt-poured { position: absolute; left: 0; top: 0; bottom: 0; }
    .gt-made { background: color-mix(in srgb, #2a7fb8 30%, transparent); }
    .gt-poured { background: color-mix(in srgb, #2a7fb8 65%, transparent); }
    .gt-bar.risk .gt-made { background: color-mix(in srgb, #d98a1c 30%, transparent); } .gt-bar.risk .gt-poured { background: color-mix(in srgb, #d98a1c 65%, transparent); }
    .gt-bar.late .gt-made { background: color-mix(in srgb, #c0392b 30%, transparent); } .gt-bar.late .gt-poured { background: color-mix(in srgb, #c0392b 65%, transparent); }
    .gt-bar em { position: relative; font-size: 11px; font-style: normal; padding: 0 6px; line-height: 20px; white-space: nowrap; }
    @media (max-width: 760px) { .cal-grid, .cal-week { grid-template-columns: 1fr; } .cal-dow { display: none; } .cal-day.other { display: none; } .cal-day, .cal-day.tall { min-height: 0; } }
</style>
@endsection
