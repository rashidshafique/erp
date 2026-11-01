@extends('layouts.app')
@section('title', $journal->number)
@section('breadcrumb', 'Journal')
@section('content')
@include('ledger::_styles')
@php
    $money = fn (float $v) => $v ? \App\Support\Formats::number($v, fmod($v, 1.0) == 0.0 ? 0 : 2) : '';
    $type = \App\Domains\Ledger\Services\Vouchers::TYPES[$journal->voucher_type] ?? null;
    $grid = 'display:grid;grid-template-columns:28px 300px minmax(0,1fr) 150px 150px;gap:10px;align-items:center';
@endphp
<div class="ac-card">
    <div class="ac-bar">
        <div class="ac-crumb"><span>Accounts</span><span class="sep">/</span><a href="{{ route('ledger.journals.index') }}">Journal</a><span class="sep">/</span><span>{{ $journal->number }}</span>
            @if ($journal->reversal)<span class="ac-pill">Reversed</span>@else<span class="ac-pill ok">Posted</span>@endif</div>
        <div class="ac-actions"><a class="ac-btn" href="{{ route('ledger.journals.print', $journal) }}" target="_blank">Print</a>@if ($url = $journal->sourceUrl())<a class="ac-btn" href="{{ $url }}">Open {{ strtolower($journal->sourceLabel()) }}</a>@endif</div>
    </div>
    <div style="padding:20px 28px;display:flex;flex-direction:column;gap:18px">
        @if (session('success'))<div class="ac-toast" role="status"><span>{{ session('success') }}</span></div>@endif
        @error('journal')<div class="ac-toast bad" role="alert"><span>{{ $message }}</span></div>@enderror
        @if ($journal->reverses)<div class="ac-toast warn"><span>Reverses <a href="{{ route('ledger.journals.show', $journal->reverses) }}">{{ $journal->reverses->number }}</a>.</span></div>@endif
        @if ($journal->reversal)<div class="ac-toast warn"><span>Reversed by <a href="{{ route('ledger.journals.show', $journal->reversal) }}">{{ $journal->reversal->number }}</a> on {{ $journal->reversal->journal_date->displayDate() }}.</span></div>@endif
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px">
            <div class="ac-field"><span class="lab">Voucher type</span><span>{{ $journal->voucher_type ? $journal->voucher_type.' '.$type['name'] : $journal->typeLabel() }}</span></div>
            <div class="ac-field"><span class="lab">Date</span><span>{{ \App\Domains\Ledger\Services\Vouchers::periodLabel($journal->journal_date) }}</span></div>
            @if ($journal->counterAccount)<div class="ac-field"><span class="lab">{{ $type['counter'] ?? 'Through' }}</span><span><code>{{ $journal->counterAccount->code }}</code> {{ $journal->counterAccount->name }}</span></div>@endif
            <div class="ac-field"><span class="lab">{{ $type['ref'] ?? 'Reference' }}</span><span>{{ $journal->reference ?: '—' }}</span></div>
            <div class="ac-field"><span class="lab">By</span><span>{{ $journal->user?->name ?? 'System' }} · {{ $journal->sourceLabel() }}</span></div>
        </div>
        @if ($journal->memo)<div class="ac-muted">{{ $journal->memo }}</div>@endif
        <div style="overflow-x:auto"><div style="min-width:760px">
            <div style="{{ $grid }};padding:8px 0;border-bottom:1px solid #e6e7e9;font-size:11.5px;color:#6b6e73;font-weight:500"><span>#</span><span>Account</span><span>Narration</span><span style="text-align:right">Debit</span><span style="text-align:right">Credit</span></div>
            @foreach ($journal->lines as $i => $l)
            <div style="{{ $grid }};padding:9px 0;border-bottom:1px solid #eff0f2"><span class="ac-muted">{{ $i + 1 }}</span><span><a href="{{ route('ledger.accounts.show', $l->account) }}"><code style="color:#6b6e73">{{ $l->account->code }}</code> {{ $l->account->name }}</a>@isset($parties[$l->party_type.':'.$l->party_id]) · {{ $parties[$l->party_type.':'.$l->party_id] }}@endisset</span><span>{{ $l->description }}</span><span style="text-align:right">{{ $money($l->debit) }}</span><span style="text-align:right">{{ $money($l->credit) }}</span></div>
            @endforeach
            <div style="{{ $grid }};padding:10px 0;font-weight:600"><span></span><span>Total</span><span></span><span style="text-align:right">{{ $money($journal->lines->sum('debit')) }}</span><span style="text-align:right">{{ $money($journal->lines->sum('credit')) }}</span></div>
        </div></div>
        @include('attachments._panel', ['type' => 'ledger_voucher', 'id' => $journal->id])
        @if ($journal->source_type === null && $journal->kind === 'POST' && ! $journal->reversal)
        <form method="POST" action="{{ route('ledger.journals.reverse', $journal) }}" class="ac-filters">@csrf
            <label class="ac-muted" style="display:flex;gap:8px;align-items:center;margin:0">Reverse on <input type="date" name="reverse_on" value="{{ today()->toDateString() }}" required style="width:auto;padding:6px 10px"></label>
            <button class="ac-btn" onclick="return confirm('Post the reversal of this voucher?')">Reverse</button>
            <span class="ac-note">A voucher is never edited: reverse it and post a corrected one.</span>
        </form>
        @endif
    </div>
</div>
@endsection
