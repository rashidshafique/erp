{{-- Feasibility review (design 1c): a bar with the part and the decision buttons, the departments with their verdicts on the left, and
     on the right the overall verdict, the part's facts and the conditions for the customer. --}}
@extends('layouts.app')
@section('title', 'Feasibility review')
@section('breadcrumb', 'Feasibility')
@section('content')
@php
    $labels = \App\Domains\Enquiry\Models\EnquiryItem::FEASIBILITY;
    $pill = ['PENDING' => ['#f0f1f3', '#55585d', 'Pending'], 'OK' => ['#e7f4ec', '#1d7048', 'Feasible'], 'CONDITIONAL' => ['#fbf0dc', '#94610a', 'With conditions'], 'NOT_FEASIBLE' => ['#fbe9e7', '#b3352c', 'Not feasible']];
    $open = $item->enquiry->isOpen();
    $signed = $item->reviews->keyBy('department');
    $outcome = $item->reviewOutcome();
    $decided = $item->feasibility_status !== 'PENDING';
    $overall = $decided ? $item->feasibility_status : $outcome;
    $conditions = $item->reviews->where('verdict', '!=', 'OK')->filter(fn ($r) => filled($r->notes));
    $fmt = fn ($v, $unit = '') => $v ? \App\Support\Formats::number((float) $v, 2).$unit : '—';
@endphp
<style>
    .fr-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
    .fr-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--muted); }
    .fr-bar code { color: var(--text); } .fr-bar .t { color: var(--text); margin-left: 4px; }
    .fr-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; }
    .fr-main { padding: 20px 28px; min-width: 0; }
    .fr-rail { border-left: 1px solid var(--border); padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; font-size: 13px; }
    .fr-rail .k { font-size: 12px; color: var(--muted); }
    .fr-rail .row { display: flex; justify-content: space-between; gap: 12px; }
    .fr-pill { font-size: 12px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; }
    .fr-main td, .fr-main th { vertical-align: top; }
    .fr-sign { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .fr-sign select, .fr-sign input { padding: 6px 8px; font-size: 13px; }
    .fr-sign input { flex: 1; min-width: 180px; }
    @media (max-width: 1000px) { .fr-grid { grid-template-columns: 1fr; } .fr-rail { border-left: 0; border-top: 1px solid var(--border); } .fr-bar, .fr-main { padding-left: 16px; padding-right: 16px; } }
</style>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
<div class="fr-card">
    <div class="fr-bar">
        <div><a href="{{ route('feasibility.index') }}">Feasibility</a> / <code>{{ $item->enquiry->code }}.{{ $item->line_no }}</code><span class="t">{{ $item->description }} · {{ $item->enquiry->customer?->name }}@if ($item->material_text) · {{ $item->material_text }}@endif</span></div>
        <div style="display:flex;gap:8px;align-items:center">
            <a class="btn" href="{{ route('enquiries.show', $item->enquiry) }}">Return to sales</a>
        </div>
    </div>
    <div class="fr-grid">
        <div class="fr-main"><div class="table-wrap"><table>
            <thead><tr><th style="width:150px">Department</th><th>Question</th><th style="width:170px">Verdict</th><th>Notes</th><th style="width:110px">Signed</th></tr></thead>
            <tbody>
            @foreach ($departments as $key => [$name, $question])
                @php $r = $signed->get($key); $p = $pill[$r?->verdict ?? 'PENDING']; @endphp
                <tr>
                    <td style="font-weight:500">{{ $name }}</td><td>{{ $question }}</td>
                    @if ($open)
                    <td colspan="2"><form method="POST" action="{{ route('feasibility.sign', [$item, $key]) }}" class="fr-sign">@csrf
                        <select name="verdict" aria-label="Verdict">@foreach (['OK', 'CONDITIONAL', 'NOT_FEASIBLE'] as $v)<option value="{{ $v }}" @selected($r?->verdict === $v)>{{ $labels[$v] }}</option>@endforeach</select>
                        <input name="notes" value="{{ $r?->notes }}" placeholder="Conditions or reason" aria-label="Notes">
                        <button class="btn" type="submit">{{ $r ? 'Re-sign' : 'Sign' }}</button></form></td>
                    @else
                    <td><span class="fr-pill" style="background:{{ $p[0] }};color:{{ $p[1] }}">{{ $p[2] }}</span></td><td style="color:var(--muted)">{{ $r?->notes }}</td>
                    @endif
                    <td style="color:var(--muted)">@if ($r){{ $r->signed_by_name }}<div class="small">{{ $r->signed_at?->displayDate() }}</div>@else — @endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <p class="muted small" style="margin-top:12px">Prefer one step? Record a single decision on the <a href="{{ route('enquiries.show', $item->enquiry) }}">enquiry</a> instead; departments are optional.</p></div>
        <div class="fr-rail">
            <div><div class="k">Overall{{ $decided ? '' : ' so far' }}</div>
                <div style="font-size:18px;font-weight:600;margin-top:2px;color:{{ $overall ? $pill[$overall][1] : 'var(--muted)' }}">{{ $overall ? $labels[$overall] : 'Not assessed' }}</div>
                <div style="color:var(--muted);margin-top:4px">{{ $signed->count() }} of {{ count($departments) }} departments signed</div></div>
            <div style="display:flex;flex-direction:column;gap:8px">
                <div class="row"><span class="k">Process</span><span>{{ \App\Domains\Enquiry\Models\EnquiryItem::PROCESSES[$item->process] ?? '—' }}{{ $item->cores_count ? ', '.$item->cores_count.' '.\Illuminate\Support\Str::plural('core', $item->cores_count) : '' }}</span></div>
                <div class="row"><span class="k">Weight</span><span>{{ $fmt($item->weight_kg, ' kg') }}</span></div>
                <div class="row"><span class="k">Annual quantity</span><span>{{ $item->annual_qty ? number_format($item->annual_qty) : '—' }}</span></div>
                <div class="row"><span class="k">Tooling cost</span><span>{{ $fmt($item->tooling_cost) }}</span></div>
                @if ($item->analysis)<div class="row"><span class="k">Casting analysis</span><span>{{ $item->analysis->code }}{{ $item->analysis->score_letter ? ' · '.$item->analysis->score_letter : '' }}</span></div>@endif
            </div>
            <div style="padding-top:14px;border-top:1px solid var(--border)"><div class="k" style="font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Conditions for customer</div>
                @if ($conditions->isEmpty())<span class="muted">None so far.</span>
                @else<ol style="margin:0;padding-left:18px;line-height:1.6">@foreach ($conditions as $r)<li>{{ $r->notes }} <span class="muted">({{ $departments[$r->department][0] }})</span></li>@endforeach</ol>@endif</div>
            <div class="k">Records are kept as ISO 9001 clause 8.2.3 evidence.</div>
        </div>
    </div>
</div>
@endsection
