{{-- iCast owner home: the same month-to-date figures as 4a, in the iCast visual language (near-black, signal orange, mono labels). --}}
@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $first = strtok(auth()->user()?->name ?? '', ' ');
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $money = fn (float $n) => \App\Support\Formats::number($n, 0);
    $one = fn (float $n) => number_format($n, 1);
    $current = max(0, $o['owed'] - $o['overdue']);
    $overdueShare = $o['owed'] > 0 ? round(100 * $o['overdue'] / $o['owed']) : 0;
    $shades = ['#F99B31', '#0A0A0A', '#CC7F28', '#8F8C84', '#D6D4CD'];
    $peak = max(1, collect($o['tonnage'])->max('total'));
    $tone = ['red' => '#C0472F', 'amber' => '#CC7F28', 'blue' => '#F99B31'];
    $pending = $o['decisions']->count();
    $lacs = fn (float $n) => \App\Support\Formats::withCurrency(number_format($n, 1).' L');
    $upDown = fn (?float $change, bool $upIsGood = true) => $change === null ? null : ((($change >= 0) === $upIsGood) ? 'up' : 'down');
    $soldTone = $upDown($t['sold']['change']);
    $turnoverTone = $upDown($t['turnover']['change']);
    $returnsTone = $upDown($t['returns']['change'], false);
@endphp
<style>
.ic { --ic-brand: #F99B31; --ic-brand-deep: #CC7F28; --ic-ink: #0A0A0A; --ic-text: #33322F; --ic-muted: #726F68; --ic-line: #E7E6E2; --ic-soft: #F7F7F5; --ic-paper: #FFFFFF; --ic-green: #1C7C4F; --ic-red: #C0472F; display: flex; flex-direction: column; gap: 20px; font-family: 'Inter', system-ui, sans-serif; color: var(--ic-text); }
.ic *, .ic *::before, .ic *::after { box-sizing: border-box; }
.ic a { color: inherit; text-decoration: none; }
.ic-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }

/* Hero */
.ic-hero { position: relative; overflow: hidden; display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; padding: 30px 32px; border-radius: 24px; background: var(--ic-ink); color: #fff; }
.ic-hero::before { content: ""; position: absolute; inset: -40% -10% auto auto; width: 460px; height: 460px; border-radius: 50%; background: radial-gradient(circle, rgba(249,155,49,.34), rgba(249,155,49,0) 68%); pointer-events: none; }
.ic-hero > * { position: relative; z-index: 1; }
.ic-eyebrow { display: flex; align-items: center; gap: 10px; font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: #A8A7A1; }
.ic-eyebrow b { padding: 3px 8px; border-radius: 999px; background: var(--ic-brand); color: var(--ic-ink); font-weight: 600; letter-spacing: .08em; }
.ic-hero h1 { margin: 12px 0 6px; font-size: 34px; line-height: 1.1; font-weight: 800; letter-spacing: -.03em; }
.ic-hero p { margin: 0; font-size: 13px; color: #C9C7C0; }
.ic-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.ic-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; border: 1px solid #3A3A37; background: transparent; color: #F2F1EC; font-size: 12.5px; font-weight: 500; white-space: nowrap; }
.ic-btn:hover { border-color: #6A6A64; }
.ic-btn.primary { background: var(--ic-brand); border-color: var(--ic-brand); color: var(--ic-ink); font-weight: 700; }
.ic-btn.primary:hover { background: var(--ic-brand-deep); border-color: var(--ic-brand-deep); }
.ic-btn .n { display: inline-grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: rgba(255,255,255,.14); font-size: 10.5px; }

/* KPI strip */
.ic-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.ic-kpi { position: relative; display: flex; flex-direction: column; gap: 6px; min-height: 148px; padding: 18px; border: 1px solid var(--ic-line); border-radius: 18px; background: var(--ic-paper); }
.ic-kpi .lab { font-size: 10.5px; letter-spacing: .12em; text-transform: uppercase; color: var(--ic-muted); }
.ic-kpi .val { font-size: 38px; line-height: 1; font-weight: 800; letter-spacing: -.04em; color: var(--ic-ink); }
.ic-kpi .note { margin-top: auto; font-size: 11.5px; color: var(--ic-muted); }
.ic-kpi .note.ok { color: var(--ic-green); } .ic-kpi .note.bad { color: var(--ic-red); } .ic-kpi .note.warn { color: var(--ic-brand-deep); }
.ic-kpi .foot { margin-top: auto; display: flex; flex-direction: column; gap: 7px; }
.ic-kpi .foot .note { margin-top: 0; }
.ic-kpi .delta { display: inline-flex; align-items: center; gap: 3px; width: fit-content; padding: 2px 7px; border-radius: 999px; font-size: 10.5px; font-weight: 700; }
.ic-kpi .delta.up { background: #E7F4EC; color: var(--ic-green); }
.ic-kpi .delta.down { background: #FBE9E4; color: var(--ic-red); }
.ic-kpi.dark .delta.up { background: rgba(28,124,79,.28); color: #8FE0B4; }
.ic-kpi.dark .delta.down { background: rgba(192,71,47,.28); color: #FF9D8A; }
.ic-kpi .track { height: 5px; border-radius: 999px; background: #EFEEEA; overflow: hidden; }
.ic-kpi .track i { display: block; height: 100%; border-radius: 999px; background: var(--ic-brand); }
.ic-kpi.dark { background: var(--ic-ink); border-color: var(--ic-ink); }
.ic-kpi.dark .lab { color: #A8A7A1; } .ic-kpi.dark .val { color: var(--ic-brand); } .ic-kpi.dark .note { color: #C9C7C0; }
.ic-kpi.dark .note.bad { color: #FF9D8A; } .ic-kpi.dark .note.ok { color: #8FE0B4; }
.ic-spark { display: flex; align-items: flex-end; gap: 2px; height: 22px; margin-top: 4px; }
.ic-spark i { flex: 1; min-width: 2px; border-radius: 1px 1px 0 0; background: #D6D4CD; }
.ic-spark i.hot { background: var(--ic-brand); }

/* Cards */
.ic-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; }
.ic-grid.wide { grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr); }
.ic-card { display: flex; flex-direction: column; border: 1px solid var(--ic-line); border-radius: 18px; background: var(--ic-paper); overflow: hidden; }
.ic-card-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 15px 18px; border-bottom: 1px solid var(--ic-line); }
.ic-card-head h2 { margin: 0; font-size: 14px; font-weight: 700; letter-spacing: -.01em; color: var(--ic-ink); }
.ic-card-head .sub { font-size: 11.5px; color: var(--ic-muted); }
.ic-badge { display: inline-grid; place-items: center; min-width: 24px; height: 22px; padding: 0 7px; border-radius: 999px; background: var(--ic-brand); color: var(--ic-ink); font-size: 11px; font-weight: 700; }
.ic-row { display: flex; align-items: flex-start; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #F0EFEC; }
.ic-row:last-child { border-bottom: 0; }
.ic-row .grow { flex: 1; min-width: 0; }
.ic-row .t { font-size: 13px; font-weight: 600; color: var(--ic-ink); }
.ic-row .s { margin-top: 3px; font-size: 12px; color: var(--ic-muted); }
.ic-dot { flex: none; width: 9px; height: 9px; margin-top: 5px; border-radius: 50%; }
.ic-empty { padding: 22px 18px; font-size: 13px; color: var(--ic-muted); }
.ic-link { font-size: 12px; font-weight: 600; color: var(--ic-brand-deep); }
.ic-link:hover { color: var(--ic-ink); }

/* Tonnage chart */
.ic-chart { padding: 18px; }
.ic-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 11.5px; color: var(--ic-muted); }
.ic-legend span { display: inline-flex; align-items: center; gap: 5px; }
.ic-legend i { width: 9px; height: 9px; border-radius: 2px; }
.ic-cols { display: flex; align-items: flex-end; gap: 14px; height: 176px; margin-top: 18px; padding-top: 8px; border-top: 1px dashed var(--ic-line); }
.ic-col { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; gap: 2px; height: 100%; }
.ic-col .stack { display: flex; flex-direction: column; justify-content: flex-end; gap: 2px; height: 100%; }
.ic-col .stack i { display: block; border-radius: 3px 3px 0 0; min-height: 2px; }
.ic-col .wk { margin-top: 7px; font-size: 10.5px; text-align: center; color: var(--ic-muted); }

/* Cash */
.ic-cash { padding: 18px; display: flex; flex-direction: column; gap: 16px; }
.ic-cash .big { font-size: 40px; line-height: 1; font-weight: 800; letter-spacing: -.04em; color: var(--ic-ink); }
.ic-cash .big small { font-size: 13px; font-weight: 500; letter-spacing: 0; color: var(--ic-muted); }
.ic-split { display: flex; height: 10px; border-radius: 999px; overflow: hidden; background: #EFEEEA; }
.ic-split i { display: block; height: 100%; }
.ic-keys { display: flex; flex-direction: column; gap: 9px; }
.ic-key { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 12.5px; }
.ic-key span { display: inline-flex; align-items: center; gap: 7px; color: var(--ic-muted); }
.ic-key i { width: 9px; height: 9px; border-radius: 2px; }
.ic-key b { font-weight: 600; color: var(--ic-ink); }

/* Table */
.ic-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ic-table th { padding: 10px 18px; text-align: left; font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--ic-muted); font-weight: 500; border-bottom: 1px solid var(--ic-line); }
.ic-table td { padding: 12px 18px; border-bottom: 1px solid #F0EFEC; }
.ic-table tr:last-child td { border-bottom: 0; }
.ic-table .r { text-align: right; }
.ic-table .muted { color: var(--ic-muted); }
.ic-foot { display: flex; justify-content: space-between; gap: 12px; padding: 12px 18px; border-top: 1px solid var(--ic-line); font-size: 11.5px; color: var(--ic-muted); }

@media (max-width: 1180px) { .ic-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .ic-grid, .ic-grid.wide { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 780px) { .ic-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .ic-hero { flex-direction: column; align-items: flex-start; padding: 24px; } .ic-hero h1 { font-size: 28px; } .ic-kpi .val { font-size: 32px; } }
</style>

<div class="ic">
    <section class="ic-hero">
        <div>
            <div class="ic-eyebrow ic-mono"><b>Owner</b> Month to date · {{ $o['month']->format('M Y') }}</div>
            <h1>{{ $greeting }}{{ $first ? ', '.$first : '' }}</h1>
            <p>{{ now()->format('l j F') }} · day {{ now()->day }} of {{ now()->daysInMonth }}</p>
        </div>
        <div class="ic-actions">
            <a class="ic-btn" href="{{ route('approvals.index') }}">Approvals @if ($pending > 0)<span class="n">{{ $pending }}</span>@endif</a>
            <a class="ic-btn" href="{{ route('invoices.create') }}">New invoice</a>
            <a class="ic-btn primary" href="{{ route('receipts.create') }}">Receive payment</a>
        </div>
    </section>

    <section class="ic-kpis" aria-label="Month at a glance">
        <div class="ic-kpi">
            <div class="lab ic-mono">Castings sold</div>
            <div class="val">{{ $one($t['sold']['tons']) }}<small style="font-size:14px;font-weight:600;color:var(--ic-muted)"> t</small></div>
            <div class="foot">
                <div class="note">Last month to date {{ $one($t['sold']['prev']) }} t</div>
                @if ($soldTone)
                    <span class="delta {{ $soldTone }}">{{ $t['sold']['change'] >= 0 ? '▲' : '▼' }} {{ $one(abs($t['sold']['delta'])) }} t · {{ number_format(abs($t['sold']['change']), 1) }}%</span>
                @else
                    <span class="note">Nothing sold last month to date</span>
                @endif
            </div>
        </div>
        <div class="ic-kpi">
            <div class="lab ic-mono">Turnover{{ \App\Support\Formats::currencySuffix() }}</div>
            <div class="val">{{ number_format($t['turnover']['lacs'], 1) }}<small style="font-size:14px;font-weight:600;color:var(--ic-muted)"> L</small></div>
            <div class="foot">
                <div class="note">Last month {{ $lacs($t['turnover']['prev']) }}</div>
                @if ($turnoverTone)
                    <span class="delta {{ $turnoverTone }}">{{ $t['turnover']['change'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($t['turnover']['change']), 1) }}% vs last month</span>
                @endif
            </div>
        </div>
        <div class="ic-kpi">
            <div class="lab ic-mono">Casting returns</div>
            <div class="val">{{ $one($t['returns']['tons']) }}<small style="font-size:14px;font-weight:600;color:var(--ic-muted)"> t</small></div>
            <div class="foot">
                <div class="note">Projected {{ $one($t['returns']['projected']) }} t · last month {{ $one($t['returns']['prev']) }} t</div>
                @if ($returnsTone)
                    <span class="delta {{ $returnsTone }}">{{ $t['returns']['change'] > 0 ? '▲' : '▼' }} {{ number_format(abs($t['returns']['change']), 1) }}% vs last month</span>
                @else
                    <span class="note">No returns last month</span>
                @endif
            </div>
        </div>
        <div class="ic-kpi dark">
            <div class="lab ic-mono">Orders pending</div>
            <div class="val">{{ $one($t['pending']['tons']) }}<small style="font-size:14px;font-weight:600;color:#A8A7A1"> t</small></div>
            <div class="foot">
                <div class="note">{{ $lacs($t['pending']['value_lacs']) }} · {{ $t['pending']['orders'] }} open order{{ $t['pending']['orders'] === 1 ? '' : 's' }}</div>
                <div class="note" style="color:#A8A7A1">Not yet dispatched</div>
            </div>
        </div>
    </section>

    <div class="ic-grid">
        <section class="ic-card">
            <div class="ic-card-head">
                <h2>Waiting for your decision</h2>
                @if ($pending > 0)<span class="ic-badge">{{ str_pad((string) $pending, 2, '0', STR_PAD_LEFT) }}</span>@endif
            </div>
            @forelse ($o['decisions'] as $a)
                <div class="ic-row">
                    <div class="grow">
                        <div class="t">{{ $a->actionLabel() }}@if ($a->subject_label) · {{ $a->subject_label }}@endif</div>
                        <div class="s">Asked {{ $a->created_at->diffForHumans() }}</div>
                    </div>
                    <a class="ic-btn" style="border-color:var(--ic-line);color:var(--ic-ink);padding:7px 12px" href="{{ route('approvals.index') }}">Review</a>
                </div>
            @empty
                <div class="ic-empty">Nothing is waiting for a decision.</div>
            @endforelse
        </section>

        <section class="ic-card">
            <div class="ic-card-head">
                <h2>Where it's slipping</h2>
                @if ($o['late'])<span class="sub">{{ $o['late'] }} order{{ $o['late'] === 1 ? '' : 's' }} late</span>@endif
            </div>
            @forelse (array_slice($o['slipping'], 0, 4) as $a)
                <div class="ic-row">
                    <span class="ic-dot" style="background:{{ $tone[$a['tone']] ?? '#CC7F28' }}"></span>
                    <div class="grow">
                        <div class="t">{{ $a['ref'] ? $a['ref'].' · ' : '' }}{{ $a['tag'] }}</div>
                        <div class="s">{{ $a['text'] }}@if (! empty($a['actions'])) <a class="ic-link" href="{{ $a['actions'][0][1] }}">{{ $a['actions'][0][0] }} →</a>@endif</div>
                    </div>
                </div>
            @empty
                <div class="ic-empty">Nothing is slipping.</div>
            @endforelse
        </section>
    </div>

    <div class="ic-grid wide">
        <section class="ic-card">
            <div class="ic-card-head">
                <h2>Good tonnage by week</h2>
                <span class="sub">Last six weeks</span>
            </div>
            <div class="ic-chart">
                <div class="ic-legend">
                    @foreach ($o['families'] as $i => $f)
                        <span><i style="background:{{ $shades[$i % count($shades)] }}"></i>{{ $f }}</span>
                    @endforeach
                </div>
                <div class="ic-cols">
                    @foreach ($o['tonnage'] as $w)
                        <div class="ic-col" title="{{ $one($w['total']) }} t in {{ $w['label'] }}">
                            <div class="stack">
                                @foreach (array_reverse($o['families'], true) as $i => $f)
                                    @if (($w['by'][$f] ?? 0) > 0)
                                        <i style="height:{{ round(100 * $w['by'][$f] / $peak) }}%;background:{{ $shades[$i % count($shades)] }}"></i>
                                    @endif
                                @endforeach
                            </div>
                            <div class="wk ic-mono">{{ $w['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                @if (collect($o['tonnage'])->sum('total') <= 0)
                    <div class="ic-empty" style="padding:16px 0 0">Nothing has been poured in the last six weeks.</div>
                @endif
            </div>
        </section>

        <section class="ic-card">
            <div class="ic-card-head">
                <h2>Cash to collect</h2>
                <a class="ic-link" href="{{ route('receivables.index') }}">Open receivables →</a>
            </div>
            <div class="ic-cash">
                <div>
                    <div class="big">{{ $money($o['owed']) }} <small>outstanding</small></div>
                    <div class="s" style="margin-top:6px;font-size:12px;color:var(--ic-muted)">
                        {{ $o['overdue'] > 0 ? $overdueShare.'% is more than 60 days old' : 'Nothing is more than 60 days old' }}
                    </div>
                </div>
                <div class="ic-split" role="img" aria-label="{{ $overdueShare }}% of receivables are over 60 days old">
                    <i style="width:{{ max(0, 100 - $overdueShare) }}%;background:var(--ic-brand)"></i>
                    <i style="width:{{ $overdueShare }}%;background:var(--ic-red)"></i>
                </div>
                <div class="ic-keys">
                    <div class="ic-key"><span><i style="background:var(--ic-brand)"></i>0–60 days</span><b>{{ $money($current) }}</b></div>
                    <div class="ic-key"><span><i style="background:var(--ic-red)"></i>Over 60 days</span><b>{{ $money($o['overdue']) }}</b></div>
                </div>
            </div>
        </section>
    </div>

    <section class="ic-card">
        <div class="ic-card-head">
            <h2>Top customers this month</h2>
            <a class="ic-link" href="{{ route('invoices.index') }}">All invoices →</a>
        </div>
        @if (count($o['customers']))
            <table class="ic-table">
                <thead><tr><th>Customer</th><th class="r">Invoiced</th><th class="r">Still owed</th></tr></thead>
                <tbody>
                    @foreach ($o['customers'] as $c)
                        <tr>
                            <td>{{ $c['name'] }}</td>
                            <td class="r">{{ $money($c['sales']) }}</td>
                            <td class="r {{ $c['owed'] > 0 ? '' : 'muted' }}">{{ $c['owed'] > 0 ? $money($c['owed']) : 'Paid' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="ic-empty">Nothing invoiced this month.</div>
        @endif
        <div class="ic-foot">
            <span>Live from pours, invoices and approvals.</span>
            <a class="ic-link" href="{{ route('dashboard') }}">Classic owner view →</a>
        </div>
    </section>
</div>
@endsection
