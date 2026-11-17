{{-- The quotations list (design 30a): every quotation and its newest revision, with the price, what it is worth a year and when it runs out.
     Tabs carry status; the panel shows the selected quotation with its revisions and what it is linked to. A row opens the record (30b). --}}
@extends('layouts.app')
@section('title', 'Quotations')
@section('breadcrumb', 'Quotations')
@section('content')
@php
    use App\Domains\Enquiry\Http\Controllers\QuotationListController as L;
    use App\Domains\Enquiry\Models\Quotation;
    $tones = ['DRAFT' => 'el-grey', 'AWAITING_APPROVAL' => 'el-warn', 'ISSUED' => 'el-blue', 'ACCEPTED' => 'el-ok', 'REJECTED' => 'el-bad', 'EXPIRED' => 'el-grey', 'SUPERSEDED' => 'el-grey'];
    $keep = fn (array $over) => request()->fullUrlWithQuery($over + ['page' => null]);
    $sortUrl = fn (string $col) => request()->fullUrlWithQuery(['sort' => $col, 'dir' => request('sort', 'valid') === $col && request('dir', 'desc') === 'desc' ? 'asc' : 'desc', 'page' => null]);
    $arrow = fn (string $col) => request('sort', 'valid') === $col ? (request('dir', 'desc') === 'asc' ? '↑' : '↓') : '↕';
    $filtered = request()->hasAny(['search', 'customer', 'valid']);
    $details = \App\Support\ListLayout::details();
@endphp
@include('enquiry::partials._list_styles')
@include('partials.list-ui')
<style>
    .qt-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); border-bottom: 1px solid var(--border); font-size: 13px; }
    .qt-kpi { padding: 14px 20px; border-right: 1px solid var(--border); } .qt-kpi:last-child { border-right: 0; }
    .qt-kpi .l { color: var(--muted); } .qt-kpi .v { font-size: 22px; font-weight: 600; letter-spacing: -.01em; margin-top: 2px; } .qt-kpi .s { font-size: 12px; color: var(--muted); }
    .qt-th a { color: inherit; text-decoration: none; white-space: nowrap; } .qt-th .a { color: var(--muted); font-size: 11px; margin-left: 2px; }
    .qt-foot { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 12px; color: var(--muted); }
    .qt-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; } .qt-facts .k { font-size: 12px; color: var(--muted); } .qt-facts .v { font-weight: 500; }
    .qt-wait { padding: 10px 12px; border-radius: 7px; background: #fbf0dc; color: #6b4608; font-size: 12.5px; }
    .qt-new { position: relative; } .qt-new .ls-pop { min-width: 300px; right: 0; left: auto; }
    @media (max-width: 1100px) { .qt-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
<div class="el-card ls-card">
    <div class="qt-kpis">
        <div class="qt-kpi"><div class="l">Quoted, 90 days</div><div class="v">{{ $stats['quoted'] }}</div><div class="s">{{ L::money($stats['quotedValue']) }} of work</div></div>
        <div class="qt-kpi"><div class="l">Won</div><div class="v">{{ $stats['won'] }}</div><div class="s">{{ $stats['wonShare'] !== null ? $stats['wonShare'].'% of quoted' : 'nothing quoted yet' }}</div></div>
        <div class="qt-kpi"><div class="l">Avg. days to quote</div><div class="v">{{ $stats['daysToQuote'] ?? '—' }}</div><div class="s">from receipt to first send</div></div>
        <div class="qt-kpi"><div class="l">Awaiting approval</div><div class="v">{{ $stats['waiting'] }}</div><div class="s">@if ($stats['oldestWaiting']) oldest {{ (int) \Illuminate\Support\Carbon::parse($stats['oldestWaiting'])->diffInDays(now()) }} days @else nothing waiting @endif</div></div>
        <div class="qt-kpi"><div class="l">Expiring in 7 days</div><div class="v">{{ $stats['expiring'] }}</div><div class="s">{{ L::money($stats['expiringValue']) }}</div></div>
    </div>
    <div class="ls-head">
        <h1 class="ls-title">Quotations</h1>
        <div class="ls-actions">
            <details class="ls-menu right qt-new">
                <summary class="ls-btn primary">+ New quotation</summary>
                <div class="ls-pop" role="menu">
                    @forelse ($quotable as $e)
                        <form method="POST" action="{{ route('enquiries.quotations.store', $e) }}" style="margin:0">@csrf<button type="submit" role="menuitem" style="all:unset;display:block;padding:7px 12px;cursor:pointer;width:100%;box-sizing:border-box">{{ $e->code }} · {{ $e->title }} <span class="el-sub">{{ $e->customer?->name }}</span></button></form>
                    @empty
                        <span class="el-note" style="padding:7px 12px;display:block">No open enquiry is ready to quote. <a href="{{ route('enquiries.create') }}">Start one</a>.</span>
                    @endforelse
                </div>
            </details>
        </div>
    </div>
    <nav class="ls-tabs" aria-label="Quotation status">@foreach (L::TABS as $k => $l)<a href="{{ $keep(['tab' => $k === 'all' ? null : $k]) }}" @class(['on' => $tab === $k])>{{ $l }} <span class="n">{{ $counts[$k] }}</span></a>@endforeach</nav>
    <div class="ls-tools">
        <form method="GET" action="{{ route('quotations.index') }}" style="display:contents">
            @if ($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
            <label class="ls-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" name="search" value="{{ request('search') }}" placeholder="Search quote, part, customer or RFQ ref" aria-label="Search quote, part, customer or RFQ ref"></label>
            <select data-search-select name="customer" aria-label="Customer" class="ls-select" onchange="this.form.submit()"><option value="">Customer: all</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(request('customer') === $c->id)>{{ $c->name }}</option>@endforeach</select>
            <select name="valid" aria-label="Valid until" class="ls-select" onchange="this.form.submit()"><option value="">Valid until: any time</option><option value="7" @selected(request('valid') === '7')>Within 7 days</option><option value="30" @selected(request('valid') === '30')>Within 30 days</option><option value="past" @selected(request('valid') === 'past')>Already passed</option></select>
            <noscript><button class="ls-tool" type="submit">Filter</button></noscript>
            @if ($filtered)<a class="ls-tool" href="{{ route('quotations.index', $tab === 'all' ? [] : ['tab' => $tab]) }}">Clear</a>@endif
        </form>
        <span class="ls-right">
            <x-column-picker table="quotations" :columns="['customer' => 'Customer', 'rev' => 'Rev', 'piece' => 'Piece', 'value' => 'Value / yr', 'valid' => 'Valid / outcome']" />
            <a class="ls-tool ls-ico" href="{{ route('quotations.export', request()->query()) }}" title="Export" aria-label="Export"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16"/></svg></a>
        </span>
    </div>
    <div class="el-grid" @style(['grid-template-columns: minmax(0, 1fr)' => ! $details])>
        <div class="el-main">
            @if ($rows->isEmpty())
                <div class="empty-state"><strong>{{ $filtered || $tab !== 'all' ? 'No quotation matches' : 'No quotations yet' }}</strong><p>A quotation is priced from an enquiry's items once they have been checked for feasibility. Start from + New quotation, or open an enquiry.</p></div>
            @else
            <div class="el-table"><table data-table="quotations">
                <thead><tr class="qt-th">
                    <th><a href="{{ $sortUrl('quotation') }}">Quotation<span class="a">{{ $arrow('quotation') }}</span></a></th>
                    <th data-col="customer"><a href="{{ $sortUrl('customer') }}">Customer<span class="a">{{ $arrow('customer') }}</span></a></th>
                    <th data-col="rev"><a href="{{ $sortUrl('rev') }}">Rev<span class="a">{{ $arrow('rev') }}</span></a></th>
                    <th class="el-r" data-col="piece"><a href="{{ $sortUrl('piece') }}">Piece<span class="a">{{ $arrow('piece') }}</span></a></th>
                    <th class="el-r" data-col="value"><a href="{{ $sortUrl('value') }}">Value / yr<span class="a">{{ $arrow('value') }}</span></a></th>
                    <th data-col="valid"><a href="{{ $sortUrl('valid') }}">Valid / outcome<span class="a">{{ $arrow('valid') }}</span></a></th>
                    <th><a href="{{ $sortUrl('status') }}">Status<span class="a">{{ $arrow('status') }}</span></a></th>
                    @unless ($details)<th class="ls-dth">@include('partials.list-details-form')</th>@endunless
                </tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    @php
                        $first = $r->lines->first(); $key = $r->statusKey(); $more = $r->lines->count() - 1;
                        $days = $r->valid_until ? (int) today()->diffInDays($r->valid_until, false) : null;
                    @endphp
                    <tr @class(['is-selected' => $selected?->id === $r->id]) onclick="if (!event.target.closest('a,summary,details')) location.href='{{ $details ? $keep(['q' => $r->id]) : route('quotations.show', $r) }}'">
                        <td><div style="font-weight:500">{{ $first?->item?->description ?? $r->enquiry->title }}@if ($more > 0) <span class="el-sub">+{{ $more }} more</span>@endif</div><div class="el-sub"><code>{{ $r->code }}</code></div></td>
                        <td data-col="customer">{{ $r->enquiry->customer?->name }}</td>
                        <td data-col="rev">v{{ $r->version }}</td>
                        <td class="el-r" data-col="piece">@if ($first){{ \App\Support\Formats::money((float) $first->unit_price, 2) }}@else<span class="el-sub">—</span>@endif</td>
                        <td class="el-r" data-col="value">{{ L::money($r->annualValue()) }}</td>
                        <td data-col="valid">
                            @if ($key === 'ACCEPTED')@if ($r->salesOrder)<a href="{{ route('sales-orders.show', $r->salesOrder) }}">{{ $r->salesOrder->order_number }}</a>@else<span class="el-sub">—</span>@endif
                            @elseif ($key === 'REJECTED'){{ Quotation::LOST_REASONS[$r->lost_reason] ?? '—' }}
                            @elseif ($r->valid_until && in_array($key, ['ISSUED', 'EXPIRED'], true)){{ $r->valid_until->displayShortDate() }} · {{ $days < 0 ? 'gone' : $days.' d' }}
                            @else<span class="el-sub">—</span>@endif
                        </td>
                        <td><span class="el-pill {{ $tones[$key] ?? '' }}">{{ $r->statusLabel() }}</span></td>
                        @unless ($details)<td class="act"><details class="ls-menu right"><summary class="ls-more" aria-label="Actions for {{ $r->code }}">⋯</summary><div class="ls-pop" role="menu"><a role="menuitem" href="{{ route('quotations.show', $r) }}">Open</a><a role="menuitem" href="{{ route('enquiries.show', $r->enquiry) }}">Open enquiry</a></div></details></td>@endunless
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="pagination"><div class="pagination-links">{{ $rows->links() }}</div></div>
            @endif
            <div class="qt-foot"><span>{{ $rows->count() }} of {{ $counts['all'] }} · Open value {{ L::money($openValue) }} · {{ $stats['expiring'] }} expire within 7 days</span></div>
            <div class="el-note">One row per quotation, not per revision: the Rev column says which one is live. Earlier revisions stay on the record. Accepted rows show their sales order and lost rows show the reason, so the list answers "what happened" without opening anything.</div>
        </div>
        @if ($details && $selected)
        @php $s = $selected; $sKey = $s->statusKey(); $sFirst = $s->lines->first(); $low = $s->lowestMargin(); @endphp
        <aside class="el-side">
            <div>
                <div class="ls-ph"><div><div style="font-weight:600;font-size:14px">{{ $sFirst?->item?->description ?? $s->enquiry->title }}</div><div class="el-sub"><code>{{ $s->code }}</code> · rev {{ $s->version }} · {{ $s->enquiry->customer?->name }}</div></div>@include('partials.list-hide-form')</div>
                <div style="display:flex;gap:8px;margin-top:10px;align-items:center"><a class="el-btn primary" href="{{ route('quotations.show', $s) }}">Open quotation</a><span class="el-pill {{ $tones[$sKey] ?? '' }}">{{ $s->statusLabel() }}</span></div>
            </div>
            @if ($sKey === 'AWAITING_APPROVAL')
                <div class="qt-wait">Waiting for approval since {{ $s->approval_asked_at?->displayShortDate() ?? 'it was asked' }}: the piece price is under the margin floor of {{ rtrim(rtrim(number_format($floor, 1), '0'), '.') }}%.</div>
            @endif
            <div class="qt-facts">
                <div><div class="k">Margin</div><div class="v">{{ number_format($low, 1) }}%</div><div class="el-sub">Floor {{ rtrim(rtrim(number_format($floor, 1), '0'), '.') }}%{{ $low < $floor ? ' · '.number_format($floor - $low, 1).' points under, needs approval' : '' }}</div></div>
                <div><div class="k">Piece price</div><div class="v">{{ $sFirst ? \App\Support\Formats::money((float) $sFirst->unit_price, 2) : '—' }}</div></div>
                <div><div class="k">Tooling</div><div class="v">{{ \App\Support\Formats::money((float) $s->lines->sum('tooling_cost'), 0) }}</div></div>
                <div><div class="k">Annual volume</div><div class="v">{{ $sFirst ? \App\Support\Formats::number($sFirst->item?->annual_qty ?: $sFirst->qty).' / yr' : '—' }}</div></div>
                <div><div class="k">Annual value</div><div class="v">{{ \App\Support\Formats::money($s->annualValue(), 0) }}</div></div>
                <div><div class="k">Terms</div><div class="v">{{ $s->payment_terms ?: '—' }}</div></div>
            </div>
            <div><div class="el-h">Revisions</div><div style="margin-top:8px">
                @foreach ($s->revisions as $rev)
                    @php $rl = $rev->lines->first(); @endphp
                    <div class="el-row"><span>v{{ $rev->version }} {{ $rl ? \App\Support\Formats::money((float) $rl->unit_price, 2) : '' }} · {{ $rev->change_note ? \Illuminate\Support\Str::limit($rev->change_note, 28) : ($rev->version === 1 ? 'first quote' : 'revised') }}</span><span>{{ $rev->created_at->displayShortDate() }}</span></div>
                @endforeach
            </div></div>
            <div><div class="el-h">Linked</div><div style="margin-top:8px">
                <div class="el-row"><span><a href="{{ route('enquiries.show', $s->enquiry) }}">Enquiry {{ $s->enquiry->code }}</a></span><span>{{ \App\Domains\Enquiry\Models\Enquiry::STAGES[$s->enquiry->stage()] ?? '' }}</span></div>
                @foreach ($s->enquiry->developments as $d)<div class="el-row"><span>Development {{ $d->code }}</span><span>{{ config('pattern_developments.pattern_by.'.$d->pattern_by, '') }}</span></div>@endforeach
                <div class="el-row"><span>Customer {{ $s->enquiry->customer?->name }}</span><span>{{ $s->enquiry->customer?->payment_terms }}</span></div>
            </div></div>
        </aside>
        @endif
    </div>
</div>
@endsection
