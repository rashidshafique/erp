@extends('layouts.app')
@php $received = $direction === 'RECEIVED'; @endphp
@section('title', $received ? 'Cheques received' : 'Cheques issued')
@section('content')
@php
    $num = fn ($v) => \App\Support\Formats::number((float) $v, 0);
    $pill = fn (\App\Domains\Finance\Models\Cheque $c) => match ($c->status) {
        'IN_HAND' => $c->cheque_date->lte(today()) ? ['cl-warn', 'Due'] : ['cl-grey', 'In hand'],
        'DEPOSITED' => ['cl-info', 'Deposited'], 'CLEARED' => ['cl-ok', 'Cleared'], default => ['cl-bad', 'Bounced'],
    };
@endphp
<style>
.cl-card { border: 1px solid #e6e7e9; border-radius: 8px; background: #fff; overflow: hidden; font-size: 13px; }
.cl-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid #e6e7e9; }
.cl-tabs { display: flex; gap: 4px; flex-wrap: wrap; } .cl-tabs a { padding: 4px 10px; border-radius: 6px; color: #55585d; text-decoration: none !important; } .cl-tabs a.on { background: #f0f1f3; color: #1c1d1f; }
.cl-btn { padding: 7px 14px; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; font-size: 13px; color: #1c1d1f; cursor: pointer; text-decoration: none !important; font-family: inherit; }
.cl-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; } .cl-btn.small { padding: 3px 10px; font-size: 12px; }
.cl-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 16px; }
.cl-strip { display: flex; gap: 32px; flex-wrap: wrap; padding: 14px 16px; border-radius: 8px; background: #f8f8f9; } .cl-strip > div { display: flex; flex-direction: column; gap: 2px; } .cl-strip b { font-size: 15px; font-weight: 600; }
.cl-lab { font-size: 12px; color: #55585d; }
.cl-filters input[type=search] { width: 100%; max-width: 320px; padding: 7px 10px; border: 1px solid #d9dbde; border-radius: 6px; font-size: 13px; }
.cl-table { margin: 0 -12px; overflow-x: auto; } .cl-table td, .cl-table th { text-align: left; } .cl-table .r { text-align: right; }
.cl-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; display: inline-block; }
.cl-ok { background: #e7f4ec; color: #1d7048; } .cl-warn { background: #fbf0dc; color: #94610a; } .cl-bad { background: #fbe7e5; color: #b3352c; } .cl-info { background: #eaf0fd; color: #2f5fd0; } .cl-grey { background: #f0f1f3; color: #55585d; }
</style>
<div class="cl-card">
    <div class="cl-bar">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <strong style="font-weight:600">{{ $received ? 'Cheques received' : 'Cheques issued' }}</strong>
            <nav class="cl-tabs">
                @foreach (\App\Domains\Finance\Http\Controllers\ChequeController::TABS as $key => $label)
                    @if ($key !== 'deposited' || $steps === 'DEPOSIT' || $counts[$key] > 0)
                    <a href="{{ route($received ? 'cheques.received' : 'cheques.issued', ['tab' => $key, 'q' => $search ?: null]) }}" @class(['on' => $tab === $key])>{{ $label }} {{ $counts[$key] }}</a>
                    @endif
                @endforeach
            </nav>
        </div>
        <a class="cl-btn primary" href="{{ route($received ? 'receipts.create' : 'supplier-payments.create') }}">{{ $received ? 'Receive a cheque' : 'Give a cheque' }}</a>
    </div>
    <div class="cl-main">
        @if ($errors->has('cheque'))<div class="alert alert-error">{{ $errors->first('cheque') }}</div>@endif
        <div class="cl-strip">
            <div><span class="cl-lab">{{ $received ? 'In hand' : 'Not yet cleared' }} · {{ $strip['openCount'] }}</span><b>{{ $num($strip['open']) }}</b></div>
            <div><span class="cl-lab">Due today or earlier</span><b @if ($strip['due'] > 0) style="color:#94610a" @endif>{{ $num($strip['due']) }}</b></div>
            <div><span class="cl-lab">Due in the next 7 days</span><b>{{ $num($strip['week']) }}</b></div>
        </div>
        <form class="cl-filters" method="GET"><input type="hidden" name="tab" value="{{ $tab }}"><input type="search" name="q" value="{{ $search }}" placeholder="Search cheque no., {{ $received ? 'customer' : 'supplier' }}, bank" aria-label="Search"></form>
        <div class="cl-table"><table>
            <thead><tr><th>Cheque</th><th>{{ $received ? 'Customer' : 'Supplier' }}</th><th>Cheque date</th><th>{{ $received ? 'Into' : 'From' }}</th><th class="r">Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($cheques as $c)
                @php [$cls, $label] = $pill($c); $days = (int) today()->diffInDays($c->cheque_date, false); @endphp
                <tr>
                    <td><a href="{{ route('cheques.show', $c) }}"><code style="font-weight:600">{{ $c->cheque_number ?: '—' }}</code></a>@if ($c->drawn_on)<div class="cl-lab">{{ $c->drawn_on }}</div>@endif</td>
                    <td>{{ $c->partyName() }}<div class="cl-lab">{{ $c->mode === 'SETTLE' ? 'Settled when '.($received ? 'received' : 'given') : 'Held until it clears' }}</div></td>
                    <td>{{ $c->cheque_date->displayShortDate() }}@if ($c->isOpen())<div class="cl-lab" @if ($days < 0) style="color:#94610a" @endif>{{ $days === 0 ? 'today' : ($days > 0 ? 'in '.$days.' '.($days === 1 ? 'day' : 'days') : abs($days).' '.(abs($days) === 1 ? 'day' : 'days').' ago') }}</div>@endif</td>
                    <td>{{ $names[$c->account_id] ?? '' }}</td>
                    <td class="r">{{ $num($c->amount) }}</td>
                    <td><span class="cl-pill {{ $cls }}">{{ $label }}</span>@if ($c->status === 'BOUNCED' && $c->bounce_reason)<div class="cl-lab">{{ $c->bounce_reason }}</div>@endif</td>
                    <td class="r" style="white-space:nowrap">
                        @if ($c->isOpen() && $days <= 0)
                            @if ($steps === 'DEPOSIT' && $c->status === 'IN_HAND')
                            <form method="POST" action="{{ route('cheques.deposit', $c) }}" style="display:inline">@csrf<input type="hidden" name="date" value="{{ today()->toDateString() }}"><button class="cl-btn small">Deposit</button></form>
                            @else
                            <form method="POST" action="{{ route('cheques.clear', $c) }}" style="display:inline">@csrf<input type="hidden" name="date" value="{{ today()->toDateString() }}"><button class="cl-btn small">Cleared today</button></form>
                            @endif
                        @endif
                        <a class="cl-btn small" href="{{ route('cheques.show', $c) }}">Open</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="cl-lab">No {{ mb_strtolower(\App\Domains\Finance\Http\Controllers\ChequeController::TABS[$tab]) }} cheques. A {{ $received ? 'receipt' : 'payment' }} by cheque dated after the day it is {{ $received ? 'received' : 'given' }} lands here.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $cheques->links() }}
    </div>
</div>
@endsection
