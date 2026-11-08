@extends('layouts.app')
@section('title', $invoice->invoice_number ?? 'Draft invoice')
@section('breadcrumb', $invoice->invoice_number ?? 'Draft invoice')
@section('content')
@php
    $num = fn ($v, $d = 0) => \App\Support\Formats::number((float) $v, $d);
    $money = fn ($v) => \App\Support\Formats::money((float) $v, 0);
    $credit = $invoice->isCreditNote();
    $label = $credit ? 'Credit note' : 'Invoice';
    $balance = $invoice->balance();
    $paid = $invoice->paid();
    $settled = $paid + $invoice->withheldSettled();
    $draft = $invoice->status === 'DRAFT';
    $late = $invoice->isOverdue();
    [$stage, $pill] = match (true) {
        $draft => ['Draft', 'iv-grey'],
        $invoice->status === 'VOID' => ['Voided', 'iv-grey'],
        $credit => ['Credit note', 'iv-info'],
        $balance <= 0 => ['Paid', 'iv-ok'],
        $late => ['Overdue', 'iv-bad'],
        $settled > 0 => ['Part paid', 'iv-info'],
        default => ['Issued', 'iv-info'],
    };
    $templates = app(\App\Domains\Finance\Services\InvoiceTemplates::class);
    $sum = fn ($rows, $k) => collect($rows)->sum($k);
@endphp
<style>
.iv-card { border: 1px solid #e6e7e9; border-radius: 8px; background: #fff; overflow: hidden; font-size: 13px; }
.iv-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid #e6e7e9; }
.iv-crumb { display: flex; align-items: center; gap: 16px; }
.iv-crumb a { font-weight: 600; color: #1c1d1f; text-decoration: none; } .iv-crumb .sl { color: #55585d; }
.iv-btns { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.iv-btn { padding: 7px 14px; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; font-size: 13px; color: #1c1d1f; cursor: pointer; text-decoration: none !important; font-family: inherit; }
.iv-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
.iv-btns form { display: inline; margin: 0; } .iv-btns select { width: auto; padding: 6px 10px; border: 1px solid #d9dbde; border-radius: 6px; background: #fff; font-size: 13px; }
.iv-pill { font-size: 12px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; display: inline-block; }
.iv-ok { background: #e7f4ec; color: #1d7048; } .iv-bad { background: #fbe7e5; color: #b3352c; } .iv-info { background: #eaf0fd; color: #2f5fd0; } .iv-grey { background: #f0f1f3; color: #55585d; }
.iv-body { padding: 20px 28px; display: flex; flex-direction: column; gap: 20px; }
.iv-head { display: grid; grid-template-columns: minmax(0, 1.6fr) repeat(5, minmax(0, 1fr)); gap: 14px; padding-bottom: 20px; border-bottom: 1px solid #e6e7e9; }
.iv-head > div { display: flex; flex-direction: column; gap: 4px; }
.iv-lab { font-size: 12px; color: #55585d; }
.iv-sec { font-size: 12px; font-weight: 600; color: #55585d; text-transform: uppercase; letter-spacing: .06em; }
.iv-tbl { margin: 0 -12px; overflow-x: auto; } .iv-tbl th, .iv-tbl td { text-align: left; } .iv-tbl .r { text-align: right; }
.iv-tot { display: flex; justify-content: flex-end; } .iv-tot > div { width: 320px; display: flex; flex-direction: column; gap: 6px; }
.iv-tot .l { display: flex; justify-content: space-between; } .iv-tot .l span:first-child { color: #55585d; }
.iv-tot .g { border-top: 1px solid #e6e7e9; padding-top: 8px; font-weight: 600; font-size: 15px; }
.iv-three { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr); gap: 28px; align-items: start; padding-top: 20px; border-top: 1px solid #e6e7e9; }
.iv-three > div, .iv-split > div { display: flex; flex-direction: column; gap: 10px; }
.iv-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid #e6e7e9; }
.iv-row:last-child { border-bottom: 0; }
.iv-split { display: grid; grid-template-columns: minmax(0, 1fr) 400px; }
.iv-page { background: #f0f1f3; padding: 24px 28px; } .iv-page iframe { width: 100%; height: 760px; border: 1px solid #e6e7e9; background: #fff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.iv-rail { border-left: 1px solid #e6e7e9; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 22px; }
.iv-chk { display: grid; grid-template-columns: 22px minmax(0, 1fr); gap: 10px; align-items: start; padding: 6px 0; }
.iv-chk > span { width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center; font-size: 12px; font-weight: 600; }
.iv-chk small { display: block; color: #55585d; font-size: 12.5px; line-height: 1.45; }
.iv-gl { display: grid; grid-template-columns: 70px minmax(0, 1fr) 84px 84px; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e6e7e9; font-size: 12.5px; }
.iv-gl.h { font-size: 11.5px; color: #55585d; } .iv-gl .n { text-align: right; } .iv-gl code { color: #55585d; }
@media (max-width: 1000px) { .iv-split { grid-template-columns: minmax(0, 1fr); } .iv-rail { border-left: 0; border-top: 1px solid #e6e7e9; } .iv-head, .iv-three { grid-template-columns: minmax(0, 1fr); } }
</style>

@foreach ($taxChecks['blocks'] as $problem)<div class="alert alert-error">{{ $problem }}</div>@endforeach

<div class="iv-card">
    <div class="iv-bar">
        <div class="iv-crumb">
            <a href="{{ route('invoices.index') }}">Invoices</a><span class="sl">/</span>
            <code>{{ $invoice->invoice_number ?? 'Draft' }}</code>
            <span class="iv-pill {{ $pill }}">{{ $stage }}</span>
            @if ($invoice->original)<span style="color:#55585d">against <a href="{{ route('invoices.show', $invoice->original) }}">{{ $invoice->original->invoice_number }}</a></span>@endif
        </div>
        <div class="iv-btns">
            @if (in_array($invoice->status, ['DRAFT', 'ISSUED'], true) && count($templates->options($credit)) > 1)
                <form method="POST" action="{{ route('invoices.template', $invoice) }}">@csrf @method('PUT')<select name="print_template" onchange="this.form.submit()" aria-label="Print layout" title="Print layout">@foreach ($templates->options($credit) as $k => $l)<option value="{{ $k }}" @selected($templates->keyFor($invoice) === $k)>{{ $l }}</option>@endforeach</select></form>
            @endif
            @if ($draft)
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}">@csrf @method('DELETE')<button class="iv-btn" onclick="return confirm('Delete this draft?')">Delete</button></form>
                <a class="iv-btn" href="{{ route('invoices.edit', $invoice) }}">Back to edit</a>
                <form method="POST" action="{{ route('invoices.issue', $invoice) }}"><input type="hidden" name="print" value="1">@csrf<button class="iv-btn" onclick="return confirm('Issue this {{ strtolower($label) }} and open it to print? It gets its number and can no longer be edited.')">Post and print</button></form>
                <form method="POST" action="{{ route('invoices.issue', $invoice) }}">@csrf<button class="iv-btn primary" onclick="return confirm('Issue this {{ strtolower($label) }}? It gets its number and can no longer be edited.')">Post only</button></form>
            @elseif ($invoice->status === 'ISSUED')
                <a class="iv-btn" href="{{ route('invoices.print', $invoice) }}" target="_blank">Print</a>
                @if ($invoice->customer?->email)<form method="POST" action="{{ route('invoices.email', $invoice) }}">@csrf<button class="iv-btn" title="Email this {{ strtolower($label) }} to {{ $invoice->customer->email }}">Email</button></form>@else<span class="iv-btn" style="opacity:.55;cursor:default" title="Add an email address on the customer to send this">Email: no address</span>@endif
                @if (! $credit)
                    @if ($invoice->cashDue() > 0 && ($onAccount = app(\App\Domains\Finance\Services\Advances::class)->forInvoice($invoice)->sum(fn ($r) => $r->unallocated())) > 0)<form method="POST" action="{{ route('invoices.apply-credit', $invoice) }}">@csrf<button class="iv-btn" title="Money this customer paid that is not on an invoice yet">Use money on account ({{ $num($onAccount, 2) }})</button></form>@endif
                    @if ($invoice->withheldOpen() > 0)<a class="iv-btn" href="{{ route('withholding-certificates.create', ['customer' => $invoice->customer_id]) }}">Tax certificate</a>@endif
                    <a class="iv-btn" href="{{ route('invoices.credit', $invoice) }}">Credit note</a>
                    @if ($balance > 0)<a class="iv-btn primary" href="{{ route('receipts.create', ['customer' => $invoice->customer_id, 'invoice' => $invoice->id]) }}">Receive payment</a>@endif
                @endif
            @endif
        </div>
    </div>

    @if ($draft)
    {{-- 24b: the page the customer gets on the left, what posting does on the right --}}
    <div class="iv-split">
        <div class="iv-page"><iframe src="{{ route('invoices.print', $invoice) }}" title="The {{ strtolower($label) }} as the customer gets it"></iframe></div>
        <aside class="iv-rail">
            <div><div class="iv-sec">Checks</div><div style="margin-top:8px">
                @foreach ($checkRows as $k)
                    <div class="iv-chk"><span style="{{ $k['ok'] ? 'background:#e7f4ec;color:#1d7048' : 'background:#fbf0dc;color:#94610a' }}">{{ $k['ok'] ? '✓' : '!' }}</span><div><div style="font-weight:500">{{ $k['t'] }}</div><small>{{ $k['d'] }}</small></div></div>
                @endforeach
            </div></div>
            @if ($posting)
            <div><div class="iv-sec">Posting</div>
                <div style="font-size:12px;color:#55585d;margin:4px 0 8px">{{ $invoice->invoice_date->format('j M') }}@if (Route::has('ledger.settings.posting-rules')) · rules <a href="{{ route('ledger.settings.posting-rules') }}">19g</a>@endif</div>
                <div class="iv-gl h"><span>Account</span><span></span><span class="n">Debit</span><span class="n">Credit</span></div>
                @foreach ($posting as $g)
                    <div class="iv-gl"><code>{{ $g['code'] }}</code><span>{{ $g['name'] }}</span><span class="n">{{ $g['debit'] ? $num($g['debit']) : '' }}</span><span class="n">{{ $g['credit'] ? $num($g['credit']) : '' }}</span></div>
                @endforeach
                @php $dr = $sum($posting, 'debit'); $cr = $sum($posting, 'credit'); @endphp
                <div class="iv-gl" style="font-weight:600;border-bottom:0"><span></span><span>{{ abs($dr - $cr) < 0.005 ? 'Balanced' : 'Out of balance' }}</span><span class="n">{{ $num($dr) }}</span><span class="n">{{ $num($cr) }}</span></div>
            </div>
            @endif
            <div style="font-size:12.5px;color:#55585d;line-height:1.5">Posting gives the {{ strtolower($label) }} its number and locks it; changes after that go through a credit note.</div>
        </aside>
    </div>
    @else
    {{-- 24c: the record --}}
    <div class="iv-body">
        <div class="iv-head">
            <div><div class="iv-lab">Customer</div><div style="font-weight:500">{{ $invoice->customer?->name }}</div><div class="iv-lab">{{ collect([$invoice->customer?->tax_number ? 'NTN '.$invoice->customer->tax_number : null, $invoice->payment_terms ?: $invoice->customer?->payment_terms])->filter()->implode(' · ') }}</div></div>
            <div><div class="iv-lab">{{ $label }} date</div><div>{{ $invoice->invoice_date->format('j M Y') }}</div></div>
            <div><div class="iv-lab">Due</div><div>{{ $invoice->due_date?->format('j M Y') ?? '—' }}</div>@if ($invoice->due_date && ! $credit && $invoice->status === 'ISSUED' && $balance > 0)<div class="iv-lab" @if ($late) style="color:#b3352c" @endif>{{ $late ? (int) $invoice->due_date->diffInDays(today()).' days overdue' : 'in '.(int) today()->diffInDays($invoice->due_date).' days' }}</div>@endif</div>
            <div><div class="iv-lab">Total</div><div>{{ $money($invoice->total) }}</div>@if ($invoice->tax_withheld > 0)<div class="iv-lab">customer pays {{ $money($invoice->payableToUs()) }}</div>@endif</div>
            <div><div class="iv-lab">Settled</div><div>{{ $money($settled) }}</div>@if ($invoice->withheldSettled() > 0)<div class="iv-lab">incl. {{ $money($invoice->withheldSettled()) }} withheld</div>@endif</div>
            <div><div class="iv-lab">Balance</div><div style="font-weight:600;font-size:15px">{{ $money($balance) }}</div>@if ($invoice->credited() > 0)<div class="iv-lab">{{ $money($invoice->credited()) }} credited</div>@endif</div>
        </div>
        @if ($invoice->status === 'VOID')<div class="alert alert-error">Voided {{ $invoice->voided_at?->displayShortDateTime() }}: {{ $invoice->void_reason }}</div>@endif
        @foreach ($taxChecks['warnings'] as $problem)<div class="alert alert-warning">{{ $problem }}</div>@endforeach
    @endif

    @if ($draft)<div class="iv-body" style="border-top:1px solid #e6e7e9">@endif
        <div style="display:flex;flex-direction:column;gap:10px">
            <div class="iv-sec">Lines</div>
            <div class="iv-tbl"><table><thead><tr><th style="width:36%">Product</th><th>Trace</th><th class="r">Qty</th><th>UoM</th><th class="r">Unit price</th><th class="r">Disc %</th><th class="r">Amount</th></tr></thead><tbody>
            @foreach ($invoice->lines as $l)
                <tr><td>{{ $l->description }}</td><td style="color:#55585d">{{ $trace[$l->id] ?? '' }}</td><td class="r">{{ $num($l->quantity, fmod((float) $l->quantity, 1) == 0 ? 0 : 3) }}</td><td style="color:#55585d">{{ $l->uom }}</td><td class="r">{{ $num($l->unit_price, fmod((float) $l->unit_price, 1) == 0 ? 0 : 2) }}</td><td class="r" style="color:#55585d">{{ $num($l->discount_percent, fmod((float) $l->discount_percent, 1) == 0 ? 0 : 2) }}</td><td class="r">{{ $num($l->line_net, 2) }}</td></tr>
            @endforeach
            </tbody></table></div>
            <div class="iv-tot"><div>
                <div class="l"><span>Value excl. tax</span><span>{{ $num($invoice->subtotal, 2) }}</span></div>
                <div class="l"><span>Tax</span><span>{{ $num($invoice->tax_total, 2) }}</span></div>
                @if ($invoice->adjustmentLines())
                    <div class="l"><span>Total</span><span>{{ $num($invoice->grossTotal(), 2) }}</span></div>
                    @foreach ($invoice->adjustmentLines() as $adj)<div class="l"><span><code>{{ $adj['code'] }}</code> {{ $adj['name'] }}</span><span>{{ $adj['kind'] === 'deduction' ? '−'.$num($adj['amount'], 2) : $num($adj['amount'], 2) }}</span></div>@endforeach
                    <div class="l g"><span>Net</span><span>{{ $num($invoice->total, 2) }}</span></div>
                @else
                    <div class="l g"><span>Total {{ $invoice->currency }}</span><span>{{ $num($invoice->total, 2) }}</span></div>
                @endif
            </div></div>
            @if ($invoice->notes)<div style="font-size:12.5px;color:#55585d">{{ $invoice->notes }}</div>@endif
        </div>

        @php $breakdown = $invoice->taxBreakdown(); @endphp
        @if ($breakdown->count() > 1 || $breakdown->contains(fn ($t) => $t['authority'] || $t['withheld']))
        <div style="display:flex;flex-direction:column;gap:10px"><div class="iv-sec">Taxes</div>
            <div class="iv-tbl"><table><thead><tr><th>Tax</th><th>Authority</th><th class="r">Rate</th><th class="r">On</th><th class="r">Amount</th></tr></thead><tbody>
            @foreach ($breakdown as $t)<tr><td>{{ $t['label'] }}@if ($t['withheld']) <span style="color:#55585d;font-size:12px">withheld by the customer</span>@endif</td><td>{{ $t['authority'] ?? '—' }}</td><td class="r">{{ $num($t['rate'], 2) }}%</td><td class="r">{{ $num($t['base'], 2) }}</td><td class="r">{{ $num($t['amount'], 2) }}</td></tr>@endforeach
            </tbody></table></div></div>
        @endif
    @if ($draft)</div>@endif

    @if (! $draft)
        <div class="iv-three">
            <div><div class="iv-sec">Payments</div>
                @if ($invoice->allocations->isNotEmpty() || $certificates->isNotEmpty() || $invoice->creditNotes->where('status', 'ISSUED')->isNotEmpty())
                <div class="iv-tbl"><table><thead><tr><th>Document</th><th>Date</th><th class="r">Cash</th><th class="r">Withheld</th><th class="r">Settled</th></tr></thead><tbody>
                    @foreach ($invoice->allocations as $a)<tr><td><a href="{{ route('receipts.show', $a->receipt) }}"><code style="font-weight:600">{{ $a->receipt->receipt_number }}</code></a>@if ($a->receipt->method || $a->receipt->reference)<div class="iv-lab">{{ collect([$a->receipt->method, $a->receipt->reference])->filter()->implode(' · ') }}</div>@endif</td><td style="color:#55585d">{{ $a->receipt->received_on->format('j M') }}</td><td class="r">{{ $num($a->amount) }}</td><td class="r">—</td><td class="r">{{ $num($a->amount) }}</td></tr>@endforeach
                    @foreach ($certificates as $c)<tr><td><code style="font-weight:600">{{ $c->certificate->certificate_number }}</code><div class="iv-lab">tax certificate</div></td><td style="color:#55585d">{{ $c->certificate->certificate_date?->format('j M') }}</td><td class="r">—</td><td class="r">{{ $num($c->amount) }}</td><td class="r">{{ $num($c->amount) }}</td></tr>@endforeach
                    @foreach ($invoice->creditNotes->where('status', 'ISSUED') as $c)<tr><td><a href="{{ route('invoices.show', $c) }}"><code style="font-weight:600">{{ $c->invoice_number }}</code></a><div class="iv-lab">credit note</div></td><td style="color:#55585d">{{ $c->invoice_date->format('j M') }}</td><td class="r">—</td><td class="r">—</td><td class="r">{{ $num($c->total) }}</td></tr>@endforeach
                    <tr><td colspan="4" style="font-weight:600;border-bottom:0">Balance</td><td class="r" style="font-weight:600;border-bottom:0">{{ $num($balance) }}</td></tr>
                </tbody></table></div>
                @else
                    <div style="color:#55585d">Nothing received yet.</div>
                @endif
                @if (! $credit && $invoice->tax_withheld > 0)
                    <div class="iv-lab">Withholding certificate: @if ($invoice->withheldOpen() > 0)<span style="color:#94610a">not received yet</span> · <a href="{{ route('withholding-certificates.create', ['customer' => $invoice->customer_id]) }}">attach</a>@else <span style="color:#1d7048">received</span>@endif</div>
                @endif
            </div>
            <div><div class="iv-sec">Linked</div>
                <div>
                    @if ($linked['orders'])<div class="iv-row"><span>{{ count($linked['orders']) === 1 ? 'Sales order' : 'Sales orders' }}</span><code>{{ implode(', ', $linked['orders']) }}</code></div>@endif
                    @if ($linked['notes'])<div class="iv-row"><span>{{ count($linked['notes']) === 1 ? 'Dispatch note' : 'Dispatch notes' }}</span><code>{{ implode(', ', $linked['notes']) }}</code></div>@endif
                    @if ($invoice->customer_reference)<div class="iv-row"><span>Customer PO</span><code style="color:#55585d">{{ $invoice->customer_reference }}</code></div>@endif
                    @if ($journal)<div class="iv-row"><span>Journal</span><a href="{{ route('ledger.journals.show', $journal) }}"><code>{{ $journal->number }}</code></a></div>@endif
                    <div class="iv-row"><span>Credit notes</span>@if ($invoice->creditNotes->isEmpty())<span style="color:#55585d">None</span>@else<span>@foreach ($invoice->creditNotes as $c)<a href="{{ route('invoices.show', $c) }}"><code>{{ $c->invoice_number ?? 'Draft' }}</code></a>@if (! $loop->last), @endif @endforeach</span>@endif</div>
                </div>
            </div>
            <div><div class="iv-sec">History</div>
                @php $hist = \App\Models\AuditLog::where('entity_type', 'Invoice')->where('entity_id', (string) $invoice->id)->orderByDesc('id')->limit(8)->get(); @endphp
                <div style="display:grid;grid-template-columns:52px minmax(0,1fr);gap:8px 10px">
                    @forelse ($hist as $e)<span style="color:#55585d">{{ $e->occurred_at->setTimezone(config('app.timezone'))->format('j M') }}</span><span>{{ $e->action }}@if ($e->user_name) · {{ $e->user_name }}@endif</span>
                    @empty<span style="grid-column:1/-1;color:#55585d">No changes recorded.</span>@endforelse
                </div>
                <a class="iv-lab" href="{{ route('audit.index', ['entity_type' => 'Invoice', 'entity_id' => $invoice->id]) }}">Full history</a>
            </div>
        </div>
        </div>
    @endif
</div>

@if ($invoice->status === 'ISSUED' && $paid == 0 && $invoice->credited() == 0 || ($invoice->status === 'ISSUED' && $credit))
<div class="panel" style="margin-top:16px"><div class="panel-body"><form method="POST" action="{{ route('invoices.void', $invoice) }}" style="display:flex;gap:8px;flex-wrap:wrap">@csrf
<input name="reason" placeholder="Why this {{ strtolower($label) }} is void" maxlength="300" required style="min-width:300px;flex:1"><button class="btn" onclick="return confirm('Void this {{ strtolower($label) }}? The number stays used.')">Void</button></form></div></div>
@endif
@foreach (app(\App\Domains\Core\ModuleRegistry::class)->panels('finance.document') as $panel)@include($panel, ['document' => $invoice])@endforeach
@include('attachments._panel', ['type' => 'invoice', 'id' => $invoice->id])
@endsection
