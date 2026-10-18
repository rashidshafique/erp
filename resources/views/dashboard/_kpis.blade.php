@php $k = $d['kpis']; $fmt = fn ($n, $dp = 0) => \App\Support\Formats::number((float) $n, $dp); @endphp
<div class="kpis">
    <a class="kpi" href="{{ route('enquiries.index') }}">
        <div class="top"><span class="cap">Open enquiries</span><span class="pill blue">Active</span></div>
        <div class="val mono">{{ $fmt($k['enquiries']['open']) }}</div>
        <div class="note">{{ $k['enquiries']['quotes'] }} quotation{{ $k['enquiries']['quotes'] === 1 ? '' : 's' }} issued</div>
        <div class="foot"><span>+{{ $k['enquiries']['today'] }} today</span>@include('dashboard._spark', ['vals' => $k['enquiries']['spark']])</div>
    </a>
    <a class="kpi" href="{{ route('production-orders.index') }}">
        <div class="top"><span class="cap">Orders due (7 days)</span>@if ($k['due']['overdue'])<span class="pill red">{{ $k['due']['overdue'] }} late</span>@else<span class="pill green">On track</span>@endif</div>
        <div class="val mono">{{ $fmt($k['due']['count']) }} <small>orders</small></div>
        <div class="note">{{ $fmt($k['due']['qty']) }} pieces planned</div>
    </a>
    <a class="kpi" href="{{ route('heats.index') }}">
        <div class="top"><span class="cap">Heats today</span><span class="pill blue">{{ $k['heats']['running'] }} running</span></div>
        <div class="val mono">{{ $k['heats']['done'] }} <small>/ {{ $k['heats']['total'] }}</small></div>
        <div class="note">{{ $fmt($k['heats']['tonnes'], 1) }} t tapped</div>
        <div class="bar" style="margin-top:8px"><i style="width:{{ $k['heats']['total'] > 0 ? min(100, round(100 * $k['heats']['done'] / $k['heats']['total'])) : 0 }}%"></i></div>
    </a>
    <a class="kpi" href="{{ route('reports.yield') }}">
        <div class="top"><span class="cap">Shop scrap (7 days)</span>@if ($k['scrap']['rate'] !== null)<span class="pill {{ $k['scrap']['rate'] <= 3 ? 'green' : 'amber' }}">{{ $k['scrap']['rate'] <= 3 ? '≤ 3% ok' : 'Above 3%' }}</span>@endif</div>
        <div class="val mono" style="color:{{ ($k['scrap']['rate'] ?? 0) > 3 ? '#b91c1c' : 'var(--accent)' }}">{{ $k['scrap']['rate'] !== null ? $fmt($k['scrap']['rate'], 2).'%' : '—' }}</div>
        <div class="foot"><span>@if ($k['scrap']['delta'] !== null){{ $k['scrap']['delta'] <= 0 ? '↓' : '↑' }} {{ $fmt(abs($k['scrap']['delta']), 2) }}% vs last week @else No pours recorded @endif</span>@include('dashboard._spark', ['vals' => $k['scrap']['spark'], 'color' => '#ef4444'])</div>
    </a>
    <a class="kpi" href="{{ route('ncrs.index') }}">
        <div class="top"><span class="cap">Open NCRs</span>@if ($k['ncrs']['major'])<span class="pill red">{{ $k['ncrs']['major'] }} major</span>@endif</div>
        <div class="val mono">{{ $k['ncrs']['open'] }} <small>reports</small></div>
        <div class="note">{{ $k['ncrs']['major'] }} major · {{ $k['ncrs']['minor'] }} minor</div>
    </a>
</div>
