@extends('layouts.app')
@section('title', 'Day book')
@section('breadcrumb', 'Day book')
@section('content')
@include('ledger::_styles')
@php
    $money = fn (float $v) => \App\Support\Formats::number($v, fmod($v, 1.0) == 0.0 ? 0 : 2);
    $pill = ['posted' => 'ok', 'draft' => 'warn', 'reversed' => ''];
@endphp
<style>
.db-row { cursor: pointer; }
.db-row.on, .db-lines { background: #f5f8fe; }
.db-lines td { padding-top: 0 !important; }
.db-lines .box { border-left: 2px solid #c9d6f5; padding-left: 12px; font-size: 12.5px; }
.db-lines .ln { display: grid; grid-template-columns: 72px minmax(0,1fr) 120px 120px; gap: 12px; padding: 4px 0; }
.db-filters select, .db-filters input[type=date] { width: auto; padding: 6px 10px; }
</style>
<div class="ac-card" x-data="{ open: @js($open) }">
    <div class="ac-bar">
        <div class="ac-crumb"><span>Accounts</span><span class="sep">/</span><span>Day book</span></div>
        <div class="ac-actions"><a class="ac-btn" href="{{ route('ledger.journals.export', request()->query()) }}">Export</a><a class="ac-btn primary" href="{{ route('ledger.journals.create') }}">New voucher</a></div>
    </div>
    <div class="ac-body" style="gap:14px">
        @if (session('success'))<div class="ac-toast" role="status"><span>{{ session('success') }}</span></div>@endif
        <form method="GET" class="ac-filters db-filters">
            <input class="ac-input" type="search" name="q" value="{{ $f['q'] }}" placeholder="Search voucher, document or narration" style="flex:1;max-width:300px" aria-label="Search">
            <span class="ac-chip" style="display:inline-flex;gap:6px;align-items:center;padding:2px 6px"><input type="date" name="from" value="{{ $f['from'] }}" aria-label="From" style="border:0;padding:4px"> – <input type="date" name="to" value="{{ $f['to'] }}" aria-label="To" style="border:0;padding:4px"></span>
            <select name="type" class="ac-chip" aria-label="Type" onchange="this.form.submit()"><option value="">Type: all</option>@foreach (\App\Domains\Ledger\Services\DayBook::types() as $k => $l)<option value="{{ $k }}" @selected($f['type'] === $k)>Type: {{ $l }}</option>@endforeach</select>
            <select name="source" class="ac-chip" aria-label="Source" onchange="this.form.submit()"><option value="">Source: all</option><option value="manual" @selected($f['source'] === 'manual')>Source: Manual</option>@foreach (\App\Domains\Ledger\Models\Journal::SOURCES as $k => [$l])@continue($k === 'journal')<option value="{{ $k }}" @selected($f['source'] === $k)>Source: {{ $l }}</option>@endforeach</select>
            <select name="status" class="ac-chip" aria-label="Status" onchange="this.form.submit()"><option value="">Status: all</option>@foreach (\App\Domains\Ledger\Services\DayBook::STATUSES as $k => $l)<option value="{{ $k }}" @selected($f['status'] === $k)>Status: {{ $l }}</option>@endforeach</select>
            <button class="ac-btn">Show</button>@if (array_filter($f))<a class="ac-link" href="{{ route('ledger.journals.index') }}">Reset</a>@endif
            <span style="flex:1"></span><span class="ac-muted">{{ $count }} {{ \Illuminate\Support\Str::plural('voucher', $count) }}@if ($drafts) · {{ $drafts }} {{ \Illuminate\Support\Str::plural('draft', $drafts) }}@endif</span>
        </form>
        <div class="ac-table"><table><thead><tr><th>Date</th><th>Voucher</th><th>Type</th><th>Source</th><th>Narration</th><th class="r">Amount ({{ trim(\App\Support\Formats::withCurrency('')) ?: 'amount' }})</th><th>Status</th><th>By</th></tr></thead><tbody>
        @forelse ($rows as $r)
            <tr class="db-row" :class="open === '{{ $r['id'] }}' && 'on'" @click="open = open === '{{ $r['id'] }}' ? null : '{{ $r['id'] }}'">
                <td style="white-space:nowrap">{{ $r['date']->format('d M') }}</td>
                <td><a href="{{ $r['url'] }}" @click.stop><code>{{ $r['number'] }}</code></a></td>
                <td>{{ $r['type'] }}</td>
                <td>@if ($r['sourceUrl'])<a href="{{ $r['sourceUrl'] }}" @click.stop>{{ $r['source'] }}</a>@else{{ $r['source'] }}@endif</td>
                <td style="max-width:360px">{{ $r['narration'] }}</td>
                <td class="r">{{ $money($r['amount']) }}</td>
                <td><span class="ac-pill {{ $pill[$r['status']] }}">{{ \App\Domains\Ledger\Services\DayBook::STATUSES[$r['status']] }}</span></td>
                <td class="ac-muted">{{ $r['by'] }}</td>
            </tr>
            <tr class="db-lines" x-show="open === '{{ $r['id'] }}'" x-cloak><td></td><td colspan="7"><div class="box">
                @foreach ($r['lines'] as $l)<div class="ln"><code style="color:#6b6e73">{{ $l['code'] }}</code><span>{{ $l['name'] }}@if ($l['detail'])<span class="ac-muted"> · {{ $l['detail'] }}</span>@endif</span><span class="r">{{ $l['debit'] ? $money($l['debit']) : '' }}</span><span class="r">{{ $l['credit'] ? $money($l['credit']) : '' }}</span></div>@endforeach
            </div></td></tr>
        @empty
            <tr><td colspan="8" class="ac-muted">No voucher matches. Issuing an invoice or posting a voucher writes the first one.</td></tr>
        @endforelse
        </tbody></table></div>
        @if ($rows->hasPages())<div class="pagination"><div class="pagination-links">{{ $rows->links() }}</div></div>@endif
        <div class="ac-note">Documents post their own vouchers under their document code. Manual vouchers use JV, CPV, BPV, CRV and BRV numbers per financial year.</div>
    </div>
</div>
@endsection
