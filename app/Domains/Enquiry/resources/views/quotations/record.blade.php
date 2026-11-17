{{-- The quotation record (design 30b): the lines and how the piece price is built, every revision with what changed and why, what it is
     linked to, and the log. Opens from the list, from the enquiry's Quotes table and from the customer panel. A draft's price is worked
     out on the costing editor (1d); this page is the record around it. --}}
@extends('layouts.app')
@section('title', $q->code)
@section('breadcrumb')<a href="{{ route('quotations.index') }}">Quotations</a> / {{ $q->code }} @endsection
@section('content')
@php
    use App\Domains\Enquiry\Models\Quotation;
    use App\Support\Formats;
    $tones = ['DRAFT' => 'el-grey', 'AWAITING_APPROVAL' => 'el-warn', 'ISSUED' => 'el-blue', 'ACCEPTED' => 'el-ok', 'REJECTED' => 'el-bad', 'EXPIRED' => 'el-grey', 'SUPERSEDED' => 'el-grey'];
    $key = $q->statusKey();
    $first = $q->lines->first();
    $money = fn ($v, $d = 2) => Formats::money((float) $v, $d);
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $low = $q->lowestMargin();
    $days = $q->valid_until ? (int) today()->diffInDays($q->valid_until, false) : null;
    $step = match ($key) { 'DRAFT' => 0, 'AWAITING_APPROVAL' => 1, 'ISSUED', 'EXPIRED' => 2, 'ACCEPTED', 'REJECTED' => 3, default => 2 };
    $steps = ['Draft', 'Approval', 'Sent', ['ACCEPTED' => 'Accepted', 'REJECTED' => 'Lost', 'EXPIRED' => 'No reply'][$key] ?? 'Accepted'];
    $needsApproval = $q->approval_asked_at !== null || $key === 'AWAITING_APPROVAL';
    $product = $first?->item?->product;
    $development = $q->enquiry->developments()->orderByDesc('created_at')->first();
@endphp
@include('enquiry::partials._list_styles')
<style>
    .qr-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; padding: 14px 28px 0; }
    .qr-steps div { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: var(--muted); } .qr-steps i { height: 4px; border-radius: 2px; background: #e6e7e9; }
    .qr-steps .done i, .qr-steps .now i { background: var(--accent); } .qr-steps .now { color: var(--text); font-weight: 600; } .qr-steps .skip { opacity: .55; }
    .qr-sec { margin-bottom: 26px; } .qr-sec table td, .qr-sec table th { white-space: normal; vertical-align: top; } .qr-sec .el-r { text-align: right; }
    .qr-rail .k { font-size: 12px; color: var(--muted); } .qr-rail .row { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; }
    .qr-log { display: flex; flex-direction: column; } .qr-log > div { display: grid; grid-template-columns: 72px minmax(0, 1fr) auto; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--border); font-size: 13px; } .qr-log > div:last-child { border-bottom: 0; }
    .qr-add { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; } .qr-add input[type=text] { flex: 1; min-width: 200px; }
    @media (max-width: 1100px) { .qr-steps { padding-left: 16px; padding-right: 16px; } }
</style>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@if ($q->status === 'AWAITING_APPROVAL')<div style="padding:10px 14px;margin-bottom:12px;border-radius:7px;background:#fbf0dc;color:#5a3d07;font-size:13px">Waiting for approval since {{ $q->approval_asked_at?->displayDate() ?? 'it was asked' }}: the piece price is under the margin floor of {{ $pct($floor) }}%. <a href="{{ route('quotations.approval', $q) }}">Review the approval</a>.</div>@endif
@if ($q->status === 'DRAFT' && $q->approved_by)<div style="padding:10px 14px;margin-bottom:12px;border-radius:7px;background:#e7f4ec;color:#1d5a3a;font-size:13px">Approved by {{ $q->approved_by }}{{ $q->approved_at ? ' on '.$q->approved_at->displayDate() : '' }}.@if (filled($q->approval_condition)) Condition: {{ $q->approval_condition }}@endif</div>@endif
@if ($key === 'EXPIRED')<div class="alert alert-error">This quotation has expired. Make a new revision from the enquiry and send it.</div>@endif
@if ($q->status === 'ACCEPTED' && $q->salesOrder)<div class="alert alert-success">Accepted. Sales order <a href="{{ route('sales-orders.show', $q->salesOrder) }}">{{ $q->salesOrder->order_number }}</a> was created from it.</div>@endif
<div class="el-card">
    <div class="el-bar">
        <div><div style="font-size:12.5px;color:var(--muted)"><a href="{{ route('quotations.index') }}">Quotations</a> / <code style="color:var(--text)">{{ $q->code }}</code></div>
            <div style="font-size:16px;font-weight:600;margin-top:2px">{{ $first?->item?->description ?? $q->enquiry->title }} <span class="el-sub" style="font-weight:400">· {{ $q->enquiry->customer?->name }}</span></div></div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span class="el-pill {{ $tones[$key] ?? '' }}">{{ $q->statusLabel() }}</span><span class="el-sub">rev {{ $q->version }}@unless ($isLatest) · superseded by a later revision @endunless</span>
            @if ($q->status === 'DRAFT')<a class="el-btn primary" href="{{ route('quotations.show', $q) }}">Open costing editor</a>@endif
            @if (app(\App\Domains\Enquiry\Services\QuotationService::class)->canRevise($q))<a class="el-btn primary" href="{{ route('quotations.revise', $q) }}">New revision</a>@endif
            @if (app(\App\Domains\Enquiry\Services\QuotationService::class)->canClose($q))<a class="el-btn" href="{{ route('quotations.close', $q) }}">Close quotation</a>@endif
            @if (in_array($q->status, ['ISSUED', 'ACCEPTED', 'EXPIRED', 'SUPERSEDED', 'REJECTED'], true))<a class="el-btn" href="{{ route('quotations.print', $q) }}" target="_blank">Preview PDF</a>@endif
        </div>
    </div>
    <div class="qr-steps" aria-label="Where the quotation stands">
        @foreach ($steps as $i => $label)<div @class(['done' => $i < $step && ! ($i === 1 && ! $needsApproval), 'now' => $i === $step, 'skip' => $i === 1 && ! $needsApproval && $i < $step])><i></i><span>{{ $label }}</span></div>@endforeach
    </div>
    <div class="el-grid" style="grid-template-columns: minmax(0, 1fr) 320px">
        <div class="el-main">
            <div class="qr-sec">
                <div class="el-h">Lines</div>
                <div class="el-table"><table><thead><tr><th>Part</th><th class="el-r">Annual qty</th><th class="el-r">Batch</th><th class="el-r">Piece price</th><th class="el-r">Tooling</th><th>Lead time</th></tr></thead><tbody>
                @foreach ($q->lines as $l)
                    <tr><td><div style="font-weight:500">{{ $l->item?->description }}</div><div class="el-sub">{{ collect([$l->item?->material_text, Formats::number((float) $l->weight_kg, 1).' kg', $l->item?->drawing_rev ? 'drawing rev '.$l->item->drawing_rev : null])->filter()->implode(' · ') }}</div></td>
                        <td class="el-r">{{ $l->annualQty() ? Formats::number($l->annualQty()).' / yr' : '—' }}</td><td class="el-r">{{ Formats::number($l->qty) }}</td>
                        <td class="el-r">{{ $money($l->unit_price) }}</td><td class="el-r">{{ (float) $l->tooling_cost > 0 ? $money($l->tooling_cost, 0) : '—' }}</td><td>{{ $q->delivery_weeks ? $q->delivery_weeks.' weeks' : '—' }}</td></tr>
                @endforeach
                </tbody></table></div>
            </div>

            @foreach ($q->lines as $l)
                @php
                    $b = $l->breakdown ?? []; $price = (float) $l->unit_price; $share = fn ($v) => $price > 0 ? $pct(100 * (float) $v / $price).'%' : '—';
                    $rates = \App\Domains\Enquiry\Services\Rates::get(); $yield = (float) $l->poured_kg > 0 ? 100 * (float) $l->weight_kg / (float) $l->poured_kg : 0;
                    $parts = [
                        ['Metal', $b['metal'] ?? 0, Formats::number((float) $l->poured_kg, 1).' kg poured at '.$money($b['melt_rate'] ?? 0).', yield '.$pct($yield).'%'],
                        ['Moulding', $b['moulding'] ?? 0, Formats::number((float) $l->poured_kg, 1).' kg at '.$money($rates['moulding_per_kg']).'/kg'],
                        ['Cores', $b['cores'] ?? 0, $l->cores_count.' '.\Illuminate\Support\Str::plural('core', $l->cores_count)],
                        ['Fettling, testing and packing', ($b['fettling'] ?? 0) + ($b['heat_treatment'] ?? 0) + ($b['testing'] ?? 0) + ($b['packing'] ?? 0), Formats::number((float) $l->weight_kg, 1).' kg at '.$money($rates['fettling_per_kg']).'/kg'.($l->heat_treatment ? ', heat treated' : '').($l->ndt ? ', NDT' : '')],
                        ['Machining and other', ($b['machining'] ?? 0) + ($b['other'] ?? 0), 'as entered on the enquiry'],
                        ['Scrap allowance', $b['scrap'] ?? 0, $pct($l->scrap_pct).'% on good pieces'],
                        ['Overhead', $b['overhead'] ?? 0, $pct($l->overhead_pct).'% on conversion'],
                        ['Tooling', $b['tooling_price'] ?? 0, 'spread over '.Formats::number((int) $q->tooling_amortise_qty).' castings'],
                    ];
                    $costed = collect($parts)->sum(fn ($p) => (float) $p[1]); $marginAmount = $price - (float) $l->unit_cost;
                @endphp
                <div class="qr-sec">
                    <div class="el-h">How the piece price is built{{ $q->lines->count() > 1 ? ': '.$l->item?->description : '' }}</div>
                    @fieldcan('costs')
                    <div class="el-table"><table><thead><tr><th></th><th class="el-r">Per piece</th><th class="el-r">Share</th><th>From</th></tr></thead><tbody>
                        @foreach ($parts as [$label, $value, $from])@if ((float) $value != 0)<tr><td>{{ $label }}</td><td class="el-r">{{ $money($value) }}</td><td class="el-r">{{ $share($value) }}</td><td class="el-sub">{{ $from }}</td></tr>@endif @endforeach
                        <tr><td>Margin</td><td class="el-r">{{ $money($marginAmount) }}</td><td class="el-r">{{ $share($marginAmount) }}</td><td class="el-sub">floor {{ $pct($floor) }}%</td></tr>
                        <tr style="font-weight:600"><td>Piece price</td><td class="el-r">{{ $money($price) }}</td><td class="el-r">100%</td><td class="el-sub">at {{ Formats::number($l->qty) }} off · <a href="{{ route('quotations.show', $q) }}">costing editor</a></td></tr>
                    </tbody></table></div>
                    @else
                    <div class="el-note">The cost build-up is hidden for your role.</div>
                    @endfieldcan
                </div>
            @endforeach

            <div class="qr-sec">
                <div class="el-h">Revisions</div>
                <div class="el-table"><table><thead><tr><th>Rev</th><th>Date</th><th class="el-r">Piece</th><th class="el-r">Tooling</th><th>What changed</th><th>By</th><th>Status</th></tr></thead><tbody>
                @foreach ($revisions as $rev)
                    @php $rl = $rev->lines->first(); @endphp
                    <tr @style(['background: #f8f8f9' => $rev->id === $q->id])><td>v{{ $rev->version }}</td><td>{{ $rev->created_at->displayShortDate() }}</td><td class="el-r">{{ $rl ? $money($rl->unit_price) : '—' }}</td><td class="el-r">{{ $rl && (float) $rl->tooling_cost > 0 ? $money($rl->tooling_cost, 0) : '—' }}</td>
                        <td>{{ $rev->change_note ?: ($rev->version === 1 ? 'First quote' : (Quotation::REVISION_REASONS[$rev->revision_reason][0] ?? 'Revised')) }}</td><td>{{ $rev->created_by_name ?? '—' }}</td>
                        <td>{{ $rev->id === $q->id && $rev->status !== 'SUPERSEDED' ? 'Current · ' : '' }}{{ $rev->statusLabel() }}</td></tr>
                @endforeach
                </tbody></table></div>
                <div class="el-note" style="margin-top:8px">A revision never overwrites the one before it. The customer keeps whatever they were sent.</div>
            </div>

            <div class="qr-sec">
                <div class="el-h">Log</div>
                <div class="qr-log">
                    @forelse ($events as $e)
                        <div><span class="el-pill">{{ \App\Domains\Enquiry\Models\QuotationEvent::KINDS[$e->kind] ?? $e->kind }}</span><span>{{ $e->text }}<div class="el-sub">{{ $e->direction === 'OUT' ? 'Out · ' : ($e->direction === 'IN' ? 'In · ' : '') }}{{ $e->user_name }}</div></span><span class="el-sub">{{ $e->happened_on->displayShortDate() }}</span></div>
                    @empty
                        <div class="el-note" style="display:block">Nothing logged yet. Emails, approvals and notes about this quotation go here.</div>
                    @endforelse
                </div>
                <form class="qr-add" method="POST" action="{{ route('quotations.log', $q) }}">@csrf
                    <select name="kind" aria-label="What to log" class="ls-select" style="width:auto"><option value="NOTE">Note</option><option value="EMAIL_OUT">Email out</option><option value="EMAIL_IN">Email in</option></select>
                    <input type="text" name="text" maxlength="1000" required placeholder="What was said or decided" aria-label="Log text"><button class="el-btn" type="submit">Add to log</button>
                </form>
            </div>
        </div>
        <aside class="el-side qr-rail">
            <div>
                <div class="row"><span class="k">Status</span><span class="el-pill {{ $tones[$key] ?? '' }}">{{ $q->statusLabel() }}</span></div>
                <div class="row"><span class="k">Sent</span><span>{{ $q->issued_at?->displayDate() ?? '—' }}</span></div>
                <div class="row"><span class="k">Valid until</span><span>{{ $q->valid_until ? $q->valid_until->displayDate().($key === 'ISSUED' ? ' · '.$days.' d' : '') : '—' }}</span></div>
                <div class="row"><span class="k">Terms</span><span>{{ $q->payment_terms ?: '—' }}</span></div>
                <div class="row"><span class="k">Delivery</span><span>{{ $q->delivery_weeks ? $q->delivery_weeks.' weeks from approval' : '—' }}</span></div>
                <div class="row"><span class="k">Margin</span><span>{{ $pct($low) }}%</span></div>
                <div class="el-sub">Floor {{ $pct($floor) }}%{{ $low < $floor ? ' · '.$pct($floor - $low).' points under, needs approval' : ' · clear by '.$pct($low - $floor).' points' }}</div>
            </div>
            <div><div class="el-h">Linked</div><div style="margin-top:8px">
                <div class="el-row"><span><a href="{{ route('enquiries.show', $q->enquiry) }}">Enquiry {{ $q->enquiry->code }}</a></span><span>{{ \App\Domains\Enquiry\Models\Enquiry::STAGES[$q->enquiry->stage()] ?? '' }}</span></div>
                <div class="el-row"><span>Customer {{ $q->enquiry->customer?->name }}</span><span>{{ $q->enquiry->customer?->payment_terms }}</span></div>
                @if ($product)<div class="el-row"><span><a href="{{ route('products.show', $product) }}">Product {{ $product->code }}</a></span><span>{{ $first?->item?->drawing_rev ? 'rev '.$first->item->drawing_rev : '' }}</span></div>@endif
                @if ($development)<div class="el-row"><span>Development {{ $development->code }}</span><span>{{ config('pattern_developments.pattern_by.'.$development->pattern_by, '') }}</span></div>@endif
                @if ($first?->item)<div class="el-row"><span><a href="{{ route('feasibility.show', $first->item) }}">Feasibility</a></span><span>{{ ucfirst(strtolower(str_replace('_', ' ', $first->item->feasibility_status))) }}</span></div>@endif
                @if ($q->salesOrder)<div class="el-row"><span><a href="{{ route('sales-orders.show', $q->salesOrder) }}">Sales order {{ $q->salesOrder->order_number }}</a></span><span></span></div>@endif
            </div></div>
            @if ($q->status === 'ISSUED' || $key === 'EXPIRED')
                <div><div class="el-h">If it is won</div><p class="el-note" style="margin:8px 0 0">Marking it accepted opens a new sales order with these lines and prices already in, and closes the enquiry as won.</p></div>
            @endif
        </aside>
    </div>
</div>
<div class="el-note" style="margin-top:10px">This page is the record: what was sent, when, at what price, and on whose approval. The costing editor stays where the piece price is worked out.</div>
@endsection
