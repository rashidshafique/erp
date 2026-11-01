@extends('layouts.app')
@section('title', 'Trial balance')
@section('breadcrumb', 'Trial balance')
@section('content')
@include('ledger::_styles')
@php
    $n = fn (float $v) => $v == 0 ? '—' : \App\Support\Formats::number(abs($v), fmod($v, 1.0) == 0.0 ? 0 : 2);
    $open = fn (float $v) => $v == 0 ? '—' : $n($v).($v > 0 ? ' Dr' : ' Cr');
    $dr = fn (float $v) => $v > 0 ? $n($v) : '';
    $cr = fn (float $v) => $v < 0 ? $n($v) : '';
    $hasMoved = fn (array $r) => $r['opening'] != 0 || $r['debit'] != 0 || $r['credit'] != 0;
    $q = request()->except('export');
    $fromLabel = \Illuminate\Support\Carbon::parse($from)->format('j M');
@endphp
<style>
.tb tr.cat td { background: #f8f8f9; font-weight: 600; }
.tb tr.sec { cursor: pointer; }
.tb tr.acc td:first-child { padding-left: 58px; }
.tb tr.acc { cursor: pointer; }
.tb tr.acc:hover td { background: #fafbfc; }
.tb tr.total td { border: 0; border-top: 2px solid #1c1d1f; font-weight: 600; }
.tb .caret { color: #6b6e73; font-size: 11px; margin-right: 4px; display: inline-block; width: 10px; }
.tb-filters select { width: auto; padding: 6px 10px; }
</style>
<div class="ac-card" x-data="{ open: {} }">
    <div class="ac-bar">
        <div class="ac-crumb"><span>Accounts</span><span class="sep">/</span><span>Trial balance</span></div>
        <div class="ac-actions"><a class="ac-btn" href="{{ route('ledger.trial-balance', $q + ['export' => 1]) }}">Export</a><button type="button" class="ac-btn" onclick="window.print()">Print</button></div>
    </div>
    <div class="ac-body" style="gap:14px">
        <form method="GET" class="ac-filters tb-filters">
            <select name="period" class="ac-chip" aria-label="Period" onchange="this.form.submit()">@foreach ($months as $k => $l)<option value="{{ $k }}" @selected((string) $period === (string) $k)>Period: {{ $l }}</option>@endforeach<option value="year" @selected($period === 'year')>Period: whole year</option></select>
            <select name="fy" class="ac-chip" aria-label="Financial year" onchange="this.form.submit()">@foreach ($fys as $k => $l)<option value="{{ $k }}" @selected($fy == $k)>{{ $l }}</option>@endforeach</select>
            <select name="level" class="ac-chip" aria-label="Level" onchange="this.form.submit()">@foreach (['categories', 'sections', 'accounts'] as $l)<option value="{{ $l }}" @selected($level === $l)>Level: {{ $l }}</option>@endforeach</select>
            <label class="ac-check" style="margin-left:8px"><input type="checkbox" name="zero" value="1" @checked($zero) onchange="this.form.submit()" style="width:14px;height:14px;margin:0">Show zero balances</label>
            <span style="flex:1"></span>
            @if ($balanced)<span class="ac-pill ok">Balanced</span>@else<span class="ac-pill bad">Out by {{ $n($totals['debit'] - $totals['credit']) }}</span>@endif
        </form>
        <div class="ac-table"><table class="tb"><thead><tr><th>Account</th><th class="r">Opening {{ $fromLabel }}</th><th class="r">Debit</th><th class="r">Credit</th><th class="r">Closing Dr</th><th class="r">Closing Cr</th></tr></thead><tbody>
        @foreach ($rows as $c)
            @continue(! $zero && ! $hasMoved($c))
            <tr class="cat"><td style="padding-left:12px">@if ($c['account'])<code style="color:#6b6e73">{{ $c['account']->code }}</code> {{ $c['account']->name }}@else{{ $c['name'] }}@endif</td><td class="r">{{ $open($c['opening']) }}</td><td class="r">{{ $n($c['debit']) }}</td><td class="r">{{ $n($c['credit']) }}</td><td class="r">{{ $dr($c['closing']) }}</td><td class="r">{{ $cr($c['closing']) }}</td></tr>
            @if ($level !== 'categories')
            @foreach ($c['sections'] as $s)
                @continue(! $zero && ! $hasMoved($s))
                @php $sid = $s['account']->id; @endphp
                <tr class="sec" @if ($level === 'sections') @click="open['{{ $sid }}'] = ! open['{{ $sid }}']" @endif><td style="padding-left:22px">@if ($level === 'sections')<span class="caret" x-text="open['{{ $sid }}'] ? '▾' : '▸'">▸</span>@endif<code style="color:#6b6e73">{{ $s['account']->code }}</code> {{ $s['account']->name }}</td><td class="r">{{ $open($s['opening']) }}</td><td class="r">{{ $n($s['debit']) }}</td><td class="r">{{ $n($s['credit']) }}</td><td class="r">{{ $dr($s['closing']) }}</td><td class="r">{{ $cr($s['closing']) }}</td></tr>
                @foreach ($s['accounts'] as $a)
                    @continue(! $zero && ! $hasMoved($a))
                    <tr class="acc" @if ($level === 'sections') x-show="open['{{ $sid }}']" x-cloak @endif @if ($a['account']) onclick="location.href='{{ route('ledger.accounts.show', [$a['account'], 'from' => $from, 'to' => $to]) }}'" @endif>
                        <td>@if ($a['account'])<a href="{{ route('ledger.accounts.show', [$a['account'], 'from' => $from, 'to' => $to]) }}" style="color:inherit"><code style="color:#6b6e73">{{ $a['account']->code }}</code> {{ $a['account']->name }}</a>@else<span class="ac-muted">{{ $a['name'] }}</span>@endif</td>
                        <td class="r">{{ $open($a['opening']) }}</td><td class="r">{{ $n($a['debit']) }}</td><td class="r">{{ $n($a['credit']) }}</td><td class="r">{{ $dr($a['closing']) }}</td><td class="r">{{ $cr($a['closing']) }}</td></tr>
                @endforeach
            @endforeach
            @endif
        @endforeach
            <tr class="total"><td>Total</td><td class="r">{{ \App\Support\Formats::number(round($totals['opening'], 2), 0) }}</td><td class="r">{{ $n($totals['debit']) }}</td><td class="r">{{ $n($totals['credit']) }}</td><td class="r">{{ $n($totals['dr']) }}</td><td class="r">{{ $n($totals['cr']) }}</td></tr>
        </tbody></table></div>
        <div class="ac-note">Level can be categories, sections or accounts. Click a section to open its accounts; click an account to open its ledger. Revenue and cost lines are year to date from {{ \Illuminate\Support\Carbon::parse($fyStart)->format('j F') }}.</div>
    </div>
</div>
@endsection
