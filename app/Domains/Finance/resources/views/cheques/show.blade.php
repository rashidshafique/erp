@extends('layouts.app')
@php $received = $cheque->isReceived(); $doc = $cheque->document(); @endphp
@section('title', 'Cheque '.$cheque->cheque_number)
@section('content')
@php
    $num = fn ($v) => \App\Support\Formats::number((float) $v, 0);
    [$cls, $label] = match ($cheque->status) {
        'IN_HAND' => $cheque->cheque_date->lte(today()) ? ['cl-warn', 'Due'] : ['cl-grey', 'In hand'],
        'DEPOSITED' => ['cl-info', 'Deposited'], 'CLEARED' => ['cl-ok', 'Cleared'], default => ['cl-bad', 'Bounced'],
    };
    $canDeposit = $steps === 'DEPOSIT' && $cheque->status === 'IN_HAND';
    $canClear = $steps === 'DEPOSIT' ? $cheque->status === 'DEPOSITED' : $cheque->isOpen();
    $minDate = $cheque->cheque_date->toDateString();
    $today = max(today()->toDateString(), $minDate);
@endphp
<style>
.cq-card { border: 1px solid #e6e7e9; border-radius: 8px; background: #fff; overflow: hidden; font-size: 13px; }
.cq-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid #e6e7e9; }
.cq-bar a.crumb { font-weight: 600; color: #1c1d1f; text-decoration: none; }
.cq-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; }
.cq-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 20px; min-width: 0; }
.cq-side { border-left: 1px solid #e6e7e9; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; }
.cq-kv { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; } .cq-kv > div { display: flex; flex-direction: column; gap: 4px; }
.cq-lab { font-size: 12px; color: #55585d; }
.cq-sec { font-size: 12px; font-weight: 600; color: #55585d; text-transform: uppercase; letter-spacing: .06em; }
.cq-side form { display: flex; flex-direction: column; gap: 8px; } .cq-side input, .cq-side select { padding: 7px 10px; border: 1px solid #d9dbde; border-radius: 6px; font-size: 13px; background: #fff; width: 100%; }
.cq-btn { padding: 7px 14px; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; font-size: 13px; color: #1c1d1f; cursor: pointer; font-family: inherit; }
.cq-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; } .cq-btn.danger { color: #b3352c; }
.cl-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; display: inline-block; }
.cl-ok { background: #e7f4ec; color: #1d7048; } .cl-warn { background: #fbf0dc; color: #94610a; } .cl-bad { background: #fbe7e5; color: #b3352c; } .cl-info { background: #eaf0fd; color: #2f5fd0; } .cl-grey { background: #f0f1f3; color: #55585d; }
@media (max-width: 1000px) { .cq-grid { grid-template-columns: minmax(0, 1fr); } .cq-side { border-left: 0; border-top: 1px solid #e6e7e9; } .cq-kv { grid-template-columns: 1fr 1fr; } }
</style>
<div class="cq-card">
    <div class="cq-bar">
        <div style="display:flex;align-items:center;gap:16px"><a class="crumb" href="{{ route($received ? 'cheques.received' : 'cheques.issued') }}">{{ $received ? 'Cheques received' : 'Cheques issued' }}</a><span class="cq-lab">/</span><code>{{ $cheque->cheque_number ?: '—' }}</code><span class="cl-pill {{ $cls }}">{{ $label }}</span></div>
    </div>
    <div class="cq-grid">
        <div class="cq-main">
            @if ($errors->has('cheque'))<div class="alert alert-error">{{ $errors->first('cheque') }}</div>@endif
            <div class="cq-kv">
                <div><span class="cq-lab">{{ $received ? 'Customer' : 'Supplier' }}</span><strong>{{ $cheque->partyName() }}</strong></div>
                <div><span class="cq-lab">Amount</span><strong style="font-size:15px">{{ $num($cheque->amount) }}</strong></div>
                <div><span class="cq-lab">Cheque date</span>{{ $cheque->cheque_date->displayDate() }}</div>
                <div><span class="cq-lab">{{ $received ? 'Received on' : 'Given on' }}</span>{{ $cheque->handed_on->displayDate() }}</div>
                <div><span class="cq-lab">{{ $received ? 'Into' : 'Drawn on' }}</span>{{ $names[$cheque->account_id] ?? '—' }}</div>
                @if ($cheque->drawn_on)<div><span class="cq-lab">Customer's bank</span>{{ $cheque->drawn_on }}</div>@endif
                <div><span class="cq-lab">Until it clears</span>{{ $cheque->mode === 'SETTLE' ? 'Settled when '.($received ? 'received' : 'given') : 'Held; '.($received ? 'invoices' : 'bills').' stay unpaid' }}</div>
                @if ($cheque->deposited_on)<div><span class="cq-lab">Deposited</span>{{ $cheque->deposited_on->displayDate() }}</div>@endif
                @if ($cheque->cleared_on)<div><span class="cq-lab">Cleared</span>{{ $cheque->cleared_on->displayDate() }}</div>@endif
                @if ($cheque->bounced_on)<div><span class="cq-lab">Bounced</span>{{ $cheque->bounced_on->displayDate() }}@if ($cheque->bounce_reason)<span class="cq-lab">{{ $cheque->bounce_reason }}</span>@endif</div>@endif
            </div>
            @if ($doc)
            <div style="display:flex;flex-direction:column;gap:10px">
                <div class="cq-sec">{{ $received ? 'Receipt' : 'Payment' }}</div>
                <div><a href="{{ route($received ? 'receipts.show' : 'supplier-payments.show', $doc) }}"><code style="font-weight:600">{{ $received ? $doc->receipt_number : $doc->payment_number }}</code></a>
                    · {{ ($received ? $doc->received_on : $doc->paid_on)->displayShortDate() }}@if ($doc->isVoided()) · <span class="cl-pill cl-bad">Voided</span>@endif</div>
                @if (! $doc->isVoided())
                <table><thead><tr><th>{{ $received ? 'Invoice' : 'Bill' }}</th><th class="num">Settled</th></tr></thead><tbody>
                @forelse ($doc->allocations as $a)
                    @php $d = $received ? $a->invoice : $a->bill; @endphp
                    <tr><td><a href="{{ route($received ? 'invoices.show' : 'bills.show', $d) }}">{{ $received ? $d->invoice_number : $d->bill_number }}</a></td><td class="num">{{ $num($a->amount) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="cq-lab">All of it on account.</td></tr>
                @endforelse
                </tbody></table>
                @endif
            </div>
            @elseif ($planned->isNotEmpty())
            <div style="display:flex;flex-direction:column;gap:10px">
                <div class="cq-sec">For {{ $received ? 'invoices' : 'bills' }}</div>
                <table><thead><tr><th>{{ $received ? 'Invoice' : 'Bill' }}</th><th class="num">Owing now</th><th class="num">From this cheque</th></tr></thead><tbody>
                @foreach ($planned as $p)
                    <tr><td><a href="{{ route($received ? 'invoices.show' : 'bills.show', $p['doc']) }}">{{ $received ? $p['doc']->invoice_number : $p['doc']->bill_number }}</a></td><td class="num">{{ $num($p['doc']->balance()) }}</td><td class="num">{{ $num($p['amount']) }}</td></tr>
                @endforeach
                </tbody></table>
                <span class="cq-lab">Put on them when the cheque clears, up to what they still owe then; anything left stays on account.</span>
            </div>
            @endif
            @if ($cheque->notes)<div><div class="cq-sec">Note</div>{{ $cheque->notes }}</div>@endif
        </div>
        <div class="cq-side">
            @if ($cheque->isOpen() && today()->lt($cheque->cheque_date))
                <div class="cq-lab">Dated {{ $cheque->cheque_date->displayDate() }}: it can be {{ $canDeposit ? 'deposited' : 'cleared' }} from then.</div>
            @endif
            @if ($canDeposit)
            <form method="POST" action="{{ route('cheques.deposit', $cheque) }}">@csrf
                <div class="cq-sec">Deposit</div>
                <label class="cq-lab">Deposited on<input type="date" name="date" value="{{ $today }}" min="{{ $minDate }}" required></label>
                @if ($accounts->isNotEmpty())<label class="cq-lab">Into<select name="account_id">@foreach ($accounts as $a)<option value="{{ $a['id'] }}" @selected($cheque->account_id === $a['id'])>{{ $a['label'] }}</option>@endforeach</select></label>@endif
                <button class="cq-btn primary">Deposit</button>
            </form>
            @endif
            @if ($canClear)
            <form method="POST" action="{{ route('cheques.clear', $cheque) }}">@csrf
                <div class="cq-sec">Cleared</div>
                <label class="cq-lab">Cleared on<input type="date" name="date" value="{{ $today }}" min="{{ $minDate }}" required></label>
                @if ($accounts->isNotEmpty())<label class="cq-lab">{{ $received ? 'Into' : 'From' }}<select name="account_id">@foreach ($accounts as $a)<option value="{{ $a['id'] }}" @selected($cheque->account_id === $a['id'])>{{ $a['label'] }}</option>@endforeach</select></label>@endif
                <span class="cq-lab">{{ $cheque->mode === 'HOLD' ? 'Makes the '.($received ? 'receipt' : 'payment').' on this date and settles the '.($received ? 'invoices' : 'bills').'.' : 'Moves the money from the cheques account into the bank.' }}</span>
                <button class="cq-btn primary">Mark cleared</button>
            </form>
            @endif
            @if (in_array($cheque->status, ['IN_HAND', 'DEPOSITED', 'CLEARED'], true))
            <form method="POST" action="{{ route('cheques.bounce', $cheque) }}">@csrf
                <div class="cq-sec">Bounced or returned</div>
                <label class="cq-lab">On<input type="date" name="date" value="{{ today()->toDateString() }}" required></label>
                <label class="cq-lab">Reason<input name="reason" maxlength="300" placeholder="Insufficient funds, signature, stopped…"></label>
                <span class="cq-lab">{{ $doc ? 'Voids '.($received ? 'receipt '.$doc->receipt_number : 'payment '.$doc->payment_number).'; the '.($received ? 'invoices' : 'bills').' are owed again.' : 'Nothing was settled by it yet.' }}</span>
                <button class="cq-btn danger">Mark bounced</button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
