{{-- The enquiries list (design 14a): every RFQ from receipt to won or lost, with reply-by dates, stage, pattern route and value. A draft
     opens the intake page (1b), a sent one its record (14b). Beside the list: the last 90 days, and why enquiries were lost or declined. --}}
@extends('layouts.app')
@section('title', 'Enquiries')
@section('breadcrumb', 'Enquiries')
@section('content')
@php
    use App\Domains\Enquiry\Http\Controllers\EnquiryController;
    use App\Domains\Enquiry\Models\Enquiry;
    $tones = ['DRAFT' => 'el-grey', 'RECEIVED' => 'el-grey', 'FEASIBILITY' => 'el-blue', 'QUOTING' => 'el-blue', 'QUOTED' => 'el-ok', 'ON_HOLD' => 'el-bad', 'WON' => 'el-ok', 'LOST' => 'el-bad', 'DECLINED' => 'el-grey', 'CANCELLED' => 'el-grey'];
    $money = fn ($v) => $v >= 1000000 ? \App\Support\Formats::withCurrency(rtrim(rtrim(number_format($v / 1000000, 2), '0'), '.').'M') : \App\Support\Formats::money($v, 0);
    $keep = fn (array $over) => request()->fullUrlWithQuery($over + ['page' => null]);
@endphp
<style>
    .el-card { background: var(--surface, #fff); border: 1px solid var(--border); border-radius: 10px; }
    .el-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid var(--border); }
    .el-tabs { display: flex; gap: 4px; flex-wrap: wrap; font-size: 13px; } .el-tabs a { padding: 4px 10px; border-radius: 6px; color: var(--muted); text-decoration: none; } .el-tabs a.on { background: #f0f1f3; color: var(--text); }
    .el-btn { padding: 7px 14px; border-radius: 6px; background: var(--surface, #fff); border: 1px solid var(--border); font-size: 13px; color: var(--text); text-decoration: none !important; cursor: pointer; white-space: nowrap; }
    .el-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
    .el-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; }
    .el-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .el-side { border-left: 1px solid var(--border); padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; font-size: 13px; }
    .el-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; font-size: 13px; } .el-filters input[type=search] { flex: 1; max-width: 320px; } .el-filters select { width: auto; }
    .el-table { margin: 0 -12px; overflow-x: auto; } .el-table table { width: 100%; } .el-table td { vertical-align: top; }
    .el-table tbody tr { cursor: pointer; } .el-table tbody tr:hover { background: #f5f8fe; }
    .el-sub { font-size: 12px; color: var(--muted); }
    .el-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #6b6e73; }
    .el-pill.el-blue { background: #eaf0fd; color: var(--accent); } .el-pill.el-ok { background: #e7f4ec; color: #1d7048; } .el-pill.el-warn { background: #fbf0dc; color: #94610a; } .el-pill.el-bad { background: #fbe7e5; color: #b3352c; }
    .el-h { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
    .el-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid var(--border); } .el-row:last-child { border-bottom: 0; } .el-row span:last-child { color: var(--muted); text-align: right; }
    .el-note { font-size: 12px; color: var(--muted); }
    .el-r { text-align: right; }
    @media (max-width: 1100px) { .el-grid { grid-template-columns: 1fr; } .el-side { border-left: 0; border-top: 1px solid var(--border); } }
    @media (max-width: 700px) { .el-bar, .el-main { padding-left: 16px; padding-right: 16px; } }
</style>
<div class="el-card">
    <div class="el-bar">
        <div style="display:flex;align-items:center;gap:16px;font-size:13px;flex-wrap:wrap">
            <span style="font-weight:600">Enquiries</span>
            <nav class="el-tabs">@foreach (EnquiryController::TABS as $k => $l)<a href="{{ $keep(['tab' => $k === 'open' ? null : $k]) }}" @class(['on' => $tab === $k])>{{ $l }} {{ $counts[$k] }}</a>@endforeach</nav>
        </div>
        <div style="display:flex;gap:8px">
            <x-column-picker table="enquiries" :columns="['customer' => 'Customer', 'stage' => 'Stage', 'pattern' => 'Pattern', 'value' => 'Annual value', 'reply' => 'Reply by', 'owner' => 'Owner']" />
            <a class="el-btn" href="{{ route('enquiries.export', request()->query()) }}">Export</a>
            <a class="el-btn primary" href="{{ route('enquiries.create') }}">New enquiry</a>
        </div>
    </div>
    <div class="el-grid">
        <div class="el-main">
            <form method="GET" action="{{ route('enquiries.index') }}" class="el-filters">
                @if ($tab !== 'open')<input type="hidden" name="tab" value="{{ $tab }}">@endif
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search code, part, customer or RFQ ref" aria-label="Search code, part, customer or RFQ ref">
                <select name="customer" aria-label="Customer" onchange="this.form.submit()"><option value="">Customer: all</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(request('customer') === $c->id)>{{ $c->name }}</option>@endforeach</select>
                <select name="owner" aria-label="Owner" onchange="this.form.submit()"><option value="">Owner: all</option>@foreach ($owners as $u)<option value="{{ $u->id }}" @selected((string) request('owner') === (string) $u->id)>{{ $u->name }}</option>@endforeach</select>
                <noscript><button class="el-btn" type="submit">Filter</button></noscript>
                @if (request()->hasAny(['search', 'customer', 'owner']))<a class="el-note" href="{{ route('enquiries.index', $tab === 'open' ? [] : ['tab' => $tab]) }}">Clear</a>@endif
            </form>
            @if ($rows->isEmpty())
                <div class="empty-state"><strong>{{ $tab === 'open' && ! request()->hasAny(['search', 'customer', 'owner']) ? 'No open enquiries' : 'No enquiry matches' }}</strong><p>Record each request as it comes in, whether it is a repeat, a drawing, a sample or a concept.</p><a class="btn btn-primary" href="{{ route('enquiries.create') }}">Start a request</a></div>
            @else
            <div class="el-table"><table data-table="enquiries">
                <thead><tr><th>Code</th><th>Part</th><th data-col="customer">Customer</th><th data-col="stage">Stage</th><th data-col="pattern">Pattern</th><th class="el-r" data-col="value">Annual value</th><th data-col="reply">Reply by</th><th data-col="owner">Owner</th></tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    @php
                        $stage = $r->stage(); $first = $r->items->first(); $value = $r->annualValue(); $days = $r->daysToReply();
                        $issued = $r->quotations->whereNotNull('issued_at')->sortByDesc('issued_at')->first();
                        $pattern = EnquiryController::patternText($r);
                    @endphp
                    <tr onclick="if (!event.target.closest('a')) location.href='{{ route('enquiries.show', $r) }}'">
                        <td><a href="{{ route('enquiries.show', $r) }}" style="color:inherit"><code>{{ $r->code }}</code></a></td>
                        <td><div style="font-weight:500">{{ $first?->description ?? $r->title }}</div><div class="el-sub">{{ collect([$first?->material_text, $first?->annual_qty ? \App\Support\Formats::number($first->annual_qty).' / yr' : null, $r->items->count() > 1 ? '+'.($r->items->count() - 1).' more' : null])->filter()->implode(' · ') ?: Enquiry::SOURCES[$r->source_type] }}</div></td>
                        <td data-col="customer">{{ $r->customer?->name }}</td>
                        <td data-col="stage"><span class="el-pill {{ $tones[$stage] }}">{{ Enquiry::STAGES[$stage] }}</span></td>
                        <td data-col="pattern">@if ($pattern){{ $pattern }}@else<span class="el-sub">Not set</span>@endif</td>
                        <td class="el-r" data-col="value">@if ($value){{ $money($value) }}@else<span class="el-sub">—</span>@endif</td>
                        <td data-col="reply">
                            @if ($stage === 'ON_HOLD')Paused
                            @elseif ($stage === 'QUOTED' && $issued)Sent {{ $issued->issued_at->displayShortDate() }}
                            @elseif (! $r->isOpen()){{ $r->closed_at?->displayShortDate() ?? '—' }}
                            @elseif ($days !== null)<span class="el-pill {{ $days <= 1 ? 'el-bad' : ($days <= 3 ? 'el-warn' : '') }}">{{ $r->due_on->displayShortDate() }} · {{ $days < 0 ? abs($days).' d late' : $days.' d' }}</span>
                            @else<span class="el-sub">—</span>@endif
                        </td>
                        <td data-col="owner">{{ $r->owner?->name ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="pagination"><div class="pagination-links">{{ $rows->links() }}</div></div>
            @endif
            <div class="el-note">On hold rows follow the customer's credit hold. Won enquiries link to their sales order and development.</div>
        </div>
        <aside class="el-side">
            <div><div class="el-h">Last 90 days</div><div style="margin-top:8px">
                <div class="el-row"><span>Received</span><span>{{ $stats['received'] }}</span></div>
                <div class="el-row"><span>Quoted</span><span>{{ $stats['quoted'] }}</span></div>
                <div class="el-row"><span>Won</span><span>{{ $stats['won'] }}{{ $stats['wonShare'] !== null ? ' · '.$stats['wonShare'].'% of quoted' : '' }}</span></div>
                <div class="el-row"><span>Avg. days to quote</span><span>{{ $stats['daysToQuote'] ?? '—' }}</span></div>
            </div></div>
            <div><div class="el-h">Lost and declined, why</div><div style="margin-top:8px">
                @forelse ($reasons as $code => $n)
                    <div class="el-row"><span>{{ Enquiry::CLOSE_REASONS[$code] ?? $code }}</span><span>{{ collect([$n['lost'] ? $n['lost'] : null, $n['declined'] ? $n['declined'].' declined' : null])->filter()->implode(' · ') }}</span></div>
                @empty
                    <div class="el-note">Nothing lost or declined in the last 90 days.</div>
                @endforelse
            </div></div>
        </aside>
    </div>
</div>
@endsection
