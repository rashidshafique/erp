@extends('layouts.app')
@section('title', 'Receive payment')
@section('breadcrumb', 'New receipt')
@section('content')
@php
    $num = fn ($v, $d = 0) => \App\Support\Formats::number((float) $v, $d);
    $owingTotal = $open->sum(fn ($i) => $i->balance());
@endphp
<style>
.rc-card { border: 1px solid #e6e7e9; border-radius: 8px; background: #fff; overflow: hidden; font-size: 13px; }
.rc-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid #e6e7e9; }
.rc-crumb { display: flex; align-items: center; gap: 16px; } .rc-crumb a { font-weight: 600; color: #1c1d1f; text-decoration: none; } .rc-crumb .sl { color: #55585d; }
.rc-btns { display: flex; gap: 8px; }
.rc-btn { padding: 7px 14px; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; font-size: 13px; color: #1c1d1f; cursor: pointer; text-decoration: none !important; font-family: inherit; }
.rc-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
.rc-body { padding: 20px 28px; display: flex; flex-direction: column; gap: 20px; }
.rc-head { display: grid; grid-template-columns: minmax(0, 1.6fr) repeat({{ $accounts->isNotEmpty() ? 4 : 3 }}, minmax(0, 1fr)); gap: 14px; padding-bottom: 20px; border-bottom: 1px solid #e6e7e9; }
.rc-head > label, .rc-head > div:not(.pay-with), .rc-head .pay-with > label { display: flex; flex-direction: column; gap: 6px; margin: 0; }
.rc-lab { font-size: 12px; color: #55585d; font-weight: 400; }
.rc-head input, .rc-head select { padding: 7px 10px; border: 1px solid #d9dbde; border-radius: 6px; font-size: 13px; background: #fff; width: 100%; }
.rc-sec { font-size: 12px; font-weight: 600; color: #55585d; text-transform: uppercase; letter-spacing: .06em; }
.rc-tbl { margin: 0 -12px; overflow-x: auto; } .rc-tbl th, .rc-tbl td { text-align: left; } .rc-tbl .r { text-align: right; }
.rc-tbl input { width: 110px; padding: 3px 6px; border: 1px solid #e6e7e9; border-radius: 5px; text-align: right; font-size: 13px; }
.rc-tbl tr.hit { background: #f5f8fe; } .rc-tbl tr.hit input { border: 1.5px solid var(--accent); font-weight: 500; }
.rc-sum { display: flex; gap: 32px; flex-wrap: wrap; padding: 14px 16px; border-radius: 8px; background: #f8f8f9; }
.rc-sum > div { display: flex; flex-direction: column; gap: 2px; } .rc-sum b { font-size: 15px; font-weight: 600; }
@media (max-width: 1000px) { .rc-head { grid-template-columns: minmax(0, 1fr); } }
</style>
<form id="pickCustomer" method="GET" action="{{ route('receipts.create') }}"></form>
<form id="receiptForm" method="POST" action="{{ route('receipts.store') }}" x-data="{ amount: {{ (float) old('amount', 0) }}, alloc: {}, get used() { return Object.values(this.alloc).reduce((n, v) => n + (Number(v) || 0), 0); },
    fmt(v) { return (Number(v) || 0).toLocaleString('en-US', { maximumFractionDigits: window.decimalPlaces ?? 0 }); },
    fill() { let left = Number(this.amount) || 0; document.querySelectorAll('[data-owing]').forEach(el => { const take = Math.min(left, Number(el.dataset.owing)); this.alloc[el.dataset.id] = take > 0 ? take.toFixed(2) : ''; left -= take; }); } }">@csrf
<div class="rc-card">
    <div class="rc-bar">
        <div class="rc-crumb"><a href="{{ route('receipts.index') }}">Receipts</a><span class="sl">/</span><span>New receipt</span></div>
        <div class="rc-btns">
            <a class="rc-btn" href="{{ $invoiceId ? route('invoices.show', $invoiceId) : route('receipts.index') }}">Cancel</a>
            @if ($customerId)<button class="rc-btn primary" type="submit">Save receipt</button>@endif
        </div>
    </div>
    <div class="rc-body">
        <div class="rc-head">
            <div><span class="rc-lab">Customer</span>
                <select form="pickCustomer" name="customer" onchange="this.form.submit()" aria-label="Customer"><option value="">Pick a customer</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected($customerId === $c->id)>{{ $c->name }}</option>@endforeach</select>
                @if ($customerId)<span class="rc-lab">{{ $open->count() }} open {{ $open->count() === 1 ? 'invoice' : 'invoices' }} · {{ \App\Support\Formats::money($owingTotal, 0) }}</span>@endif
            </div>
            @if ($customerId)
            <input type="hidden" name="customer_id" value="{{ $customerId }}">
            <label><span class="rc-lab">Received on</span><input type="date" name="received_on" value="{{ old('received_on', today()->toDateString()) }}" required></label>
            @include('finance::partials.pay-with', ['into' => 'Into', 'lab' => 'rc-lab', 'drawnOn' => true, 'pdcMode' => \App\Domains\Finance\Services\FinanceRules::pdcMode()])
            <label><span class="rc-lab">Amount received</span><input type="number" step="any" min="0" name="amount" x-model.number="amount" required style="text-align:right;font-weight:500"></label>
            @endif
        </div>
        @if ($customerId)
        @if ($orders->isNotEmpty())
        <label style="display:flex;flex-direction:column;gap:6px;max-width:420px"><span class="rc-lab">Advance for sales order</span><select name="sales_order_id" style="padding:7px 10px;border:1px solid #d9dbde;border-radius:6px;background:#fff"><option value="">— any invoice —</option>@foreach ($orders as $o)<option value="{{ $o->id }}" @selected(old('sales_order_id') === $o->id)>{{ $o->order_number }} · {{ $o->order_date?->displayShortDate() }}</option>@endforeach</select><span class="rc-lab">Money left on account then only goes on this order's invoices.</span></label>
        @endif
        <div style="display:flex;flex-direction:column;gap:10px">
            <div style="display:flex;justify-content:space-between;align-items:baseline"><div class="rc-sec">Settle invoices</div><span class="rc-lab"><a href="#" @click.prevent="fill()">Fill oldest first</a></span></div>
            <div class="rc-tbl"><table><thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th class="r">Balance</th><th class="r">Settle</th><th class="r">Left</th></tr></thead><tbody>
            @forelse ($open as $i)
                <tr :class="Number(alloc['{{ $i->id }}']) > 0 && 'hit'">
                    <td><a href="{{ route('invoices.show', $i) }}"><code style="font-weight:600">{{ $i->invoice_number }}</code></a></td>
                    <td style="color:#55585d">{{ $i->invoice_date->format('j M') }}</td>
                    <td>@if ($i->due_date)<div @if (! $i->isOverdue()) style="color:#55585d" @endif>{{ $i->due_date->format('j M') }}</div>@if ($i->isOverdue())<div style="font-size:12px;color:#b3352c">{{ (int) $i->due_date->diffInDays(today()) }} days overdue</div>@endif @endif</td>
                    <td class="r" data-owing="{{ $i->cashDue() }}" data-id="{{ $i->id }}">{{ $num($i->balance()) }}@if ($i->withheldOpen() > 0)<div class="rc-lab">{{ $num($i->withheldOpen()) }} withheld, due by certificate</div>@endif</td>
                    <td class="r"><input type="number" step="any" min="0" max="{{ $i->balance() }}" name="alloc[{{ $i->id }}]" x-model="alloc['{{ $i->id }}']" aria-label="Settle {{ $i->invoice_number }}" @if ($invoiceId === $i->id) x-init="alloc['{{ $i->id }}'] = {{ $i->cashDue() }}; if (!amount) amount = {{ $i->cashDue() }}" @endif></td>
                    <td class="r" x-text="fmt({{ $i->balance() }} - (Number(alloc['{{ $i->id }}']) || 0))">{{ $num($i->balance()) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="color:#55585d">This customer owes nothing; the whole receipt will stay on account.</td></tr>
            @endforelse
            </tbody></table></div>
        </div>
        <div class="rc-sum">
            <div><span class="rc-lab">Received</span><b x-text="fmt(amount)">0</b></div>
            <div><span class="rc-lab">Settles</span><b x-text="fmt(used)">0</b></div>
            <div><span class="rc-lab">Unallocated</span><b x-text="fmt((Number(amount) || 0) - used)">0</b></div>
        </div>
        <div style="font-size:12px;color:#55585d">Anything left unallocated stays on the customer as an advance and can be applied later from the statement.</div>
        <label style="display:flex;flex-direction:column;gap:6px"><span class="rc-lab">Notes</span><textarea name="notes" rows="2" maxlength="1000" style="padding:7px 10px;border:1px solid #d9dbde;border-radius:6px">{{ old('notes') }}</textarea></label>
        @else
        <div style="color:#55585d">Pick the customer the money came from. Their open invoices appear here to settle.</div>
        @endif
    </div>
</div>
</form>
@endsection
