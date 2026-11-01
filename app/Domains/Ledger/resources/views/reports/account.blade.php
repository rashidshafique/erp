@extends('layouts.app')
@section('title', $account->code.' '.$account->name)
@section('breadcrumb', 'Ledger')
@section('content')
@include('ledger::_styles')
@php
    $n = fn (float $v) => $v ? \App\Support\Formats::number(abs($v), fmod($v, 1.0) == 0.0 ? 0 : 2) : '';
    $bal = fn (float $v) => $v == 0 ? '0' : $n($v).($v > 0 ? ' Dr' : ' Cr');
    $money = fn (float $v) => \App\Support\Formats::money(abs($v), fmod($v, 1.0) == 0.0 ? 0 : 2).($v > 0 ? ' Dr' : ($v < 0 ? ' Cr' : ''));
    $debits = round($lines->sum('debit'), 2);
    $credits = round($lines->sum('credit'), 2);
    $closing = round($opening + $debits - $credits, 2);
    $running = $opening;
    $d = fn (string $x) => \Illuminate\Support\Carbon::parse($x);
    $range = $d($from)->format('d').' – '.$d($to)->format('d M Y');
    $mail = $customer && $customer->email ? 'mailto:'.rawurlencode($customer->email).'?subject='.rawurlencode('Statement of account, '.$d($from)->format('d M Y').' to '.$d($to)->format('d M Y'))
        .'&body='.rawurlencode("Dear {$customer->name},\n\nYour balance with us on ".$d($to)->format('d M Y').' is '.$money($closing).".\n\n".\App\Support\UiSettings::brand()['name']) : null;
@endphp
<style>
.ls-filters select, .ls-filters input[type=date] { width: auto; padding: 6px 10px; }
.ls-sum { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; padding: 12px 16px; border: 1px solid #e6e7e9; border-radius: 8px; }
.ls-sum .lab { font-size: 12px; color: #6b6e73; }
.ls tr.open td { background: #f8f8f9; }
.ls tr.vl td { padding-top: 0; font-size: 12.5px; color: #6b6e73; border-bottom: 1px solid #eff0f2; }
@media (max-width: 760px) { .ls-sum { grid-template-columns: 1fr 1fr; } }
</style>
<div class="ac-card">
    <div class="ac-bar">
        <div class="ac-crumb"><span>Accounts</span><span class="sep">/</span><a href="{{ route('ledger.accounts.index') }}">Ledger</a><span class="sep">/</span><span><code>{{ $account->code }}</code> {{ $account->name }}</span></div>
        <div class="ac-actions"><button type="button" class="ac-btn" onclick="window.print()">Print statement</button>@if ($mail)<a class="ac-btn" href="{{ $mail }}">Email to customer</a>@endif</div>
    </div>
    <div class="ac-body" style="gap:14px">
        <form method="GET" class="ac-filters ls-filters" x-data>
            <select class="ac-chip" aria-label="Account" @change="location.href = $event.target.value">@foreach ($accounts as $a)<option value="{{ route('ledger.accounts.show', [$a, 'from' => $from, 'to' => $to]) }}" @selected($a->id === $account->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select>
            @if ($parties)<select name="party" class="ac-chip" aria-label="Party" onchange="this.form.submit()"><option value="">Party: all</option>@foreach ($parties as $k => $name)<option value="{{ $k }}" @selected($party && $k === $party['type'].':'.$party['id'])>Party: {{ $name }}</option>@endforeach</select>@endif
            <span class="ac-chip" style="display:inline-flex;gap:6px;align-items:center;padding:2px 6px"><input type="date" name="from" value="{{ $from }}" aria-label="From" style="border:0;padding:4px"> – <input type="date" name="to" value="{{ $to }}" aria-label="To" style="border:0;padding:4px"></span>
            <label class="ac-check" style="margin-left:8px"><input type="checkbox" name="lines" value="1" @checked($showLines) onchange="this.form.submit()" style="width:14px;height:14px;margin:0">Show voucher lines</label>
            <button class="ac-btn">Show</button>
        </form>
        <div class="ls-sum">
            <div><div class="lab">Opening</div><div>{{ $money($opening) }}</div></div>
            <div><div class="lab">Debits</div><div>{{ \App\Support\Formats::money($debits, fmod($debits, 1.0) == 0.0 ? 0 : 2) }}</div></div>
            <div><div class="lab">Credits</div><div>{{ \App\Support\Formats::money($credits, fmod($credits, 1.0) == 0.0 ? 0 : 2) }}</div></div>
            <div><div class="lab">Closing</div><div><strong style="font-weight:600">{{ $money($closing) }}</strong></div></div>
        </div>
        <div class="ac-table"><table class="ls"><thead><tr><th>Date</th><th>Voucher</th><th>Source</th><th>Narration</th><th class="r">Debit</th><th class="r">Credit</th><th class="r">Balance</th></tr></thead><tbody>
            <tr class="open"><td>{{ $d($from)->format('d M') }}</td><td></td><td></td><td style="font-weight:500">Opening balance</td><td></td><td></td><td class="r" style="font-weight:500">{{ $bal($opening) }}</td></tr>
            @foreach ($lines as $l)
                @php $running = round($running + $l->debit - $l->credit, 2); $j = $l->journal; @endphp
                <tr><td>{{ $j->journal_date->format('d M') }}</td><td><a href="{{ route('ledger.journals.show', $j) }}"><code>{{ $j->number }}</code></a></td><td>@if ($url = $j->sourceUrl())<a href="{{ $url }}">{{ $j->sourceKind() }}</a>@else{{ $j->sourceKind() }}@endif</td>
                    <td>{{ $l->description ?: $j->narration() }}@if (! $party && $l->party_id && isset($lineParties[$l->party_type.':'.$l->party_id]))<span class="ac-muted"> · {{ $lineParties[$l->party_type.':'.$l->party_id] }}</span>@endif @if ($account->isGroup())<span class="ac-muted"> · {{ $l->account->code }}</span>@endif</td>
                    <td class="r">{{ $n($l->debit) }}</td><td class="r">{{ $n($l->credit) }}</td><td class="r">{{ $bal($running) }}</td></tr>
                @if ($showLines)
                    @foreach ($j->lines->reject(fn ($o) => $o->id === $l->id) as $o)
                    <tr class="vl"><td></td><td></td><td><code>{{ $o->account->code }}</code></td><td>{{ $o->account->name }}@isset($lineParties[$o->party_type.':'.$o->party_id]) · {{ $lineParties[$o->party_type.':'.$o->party_id] }}@endisset</td><td class="r">{{ $n($o->debit) }}</td><td class="r">{{ $n($o->credit) }}</td><td></td></tr>
                    @endforeach
                @endif
            @endforeach
            <tr><td style="border:0"></td><td style="border:0"></td><td style="border:0"></td><td style="border:0;font-weight:600">Closing balance</td><td class="r" style="border:0;font-weight:600">{{ $n($debits) ?: '0' }}</td><td class="r" style="border:0;font-weight:600">{{ $n($credits) ?: '0' }}</td><td class="r" style="border:0;font-weight:600">{{ $bal($closing) }}</td></tr>
        </tbody></table></div>
        <div class="ac-note">Without a party the same screen shows the whole account.@if ($customer) Ageing for this customer is on <a href="{{ route('receivables.statement', $customer) }}">their statement</a>.@endif</div>
    </div>
</div>
@endsection
