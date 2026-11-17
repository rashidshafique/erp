{{-- Margin approval (design 30d): the approver sees the gap in money, what changed since the last revision, what the part has fetched
     before and the methods note, then approves, attaches a condition, or sends it back. --}}
@extends('layouts.app')
@section('title', 'Approve '.$q->code)
@section('breadcrumb')<a href="{{ route('approvals.index') }}">Approvals</a> / {{ $q->code }} @endsection
@section('content')
@php
    use App\Domains\Enquiry\Models\Quotation;
    use App\Support\Formats;
    $money = fn ($v, $d = 2) => Formats::money((float) $v, $d);
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $low = $q->lowestMargin();
    $waiting = $q->approval_asked_at ? (int) $q->approval_asked_at->diffInDays(now()) : 0;
    $waitingNow = $q->status === 'AWAITING_APPROVAL';
    $annual = $line?->annualQty() ?: $line?->qty;
@endphp
@include('enquiry::partials._list_styles')
<style>
    .ap-sum { padding: 14px 28px; border-bottom: 1px solid var(--border); font-size: 14px; background: #fbf0dc; color: #5a3d07; }
    .ap-sec { margin-bottom: 24px; } .ap-sec table td, .ap-sec table th { white-space: normal; vertical-align: top; } .ap-sec .el-r { text-align: right; }
    .ap-big { font-size: 28px; font-weight: 600; letter-spacing: -.02em; } .ap-row { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 13px; } .ap-row .k { color: var(--muted); }
    .ap-decide { display: flex; flex-direction: column; gap: 8px; } .ap-decide details { border: 1px solid var(--border); border-radius: 7px; padding: 0; } .ap-decide summary { padding: 8px 12px; cursor: pointer; font-size: 13px; }
    .ap-decide details form { padding: 0 12px 12px; display: flex; flex-direction: column; gap: 8px; } .ap-decide textarea { width: 100%; }
</style>
@if ($errors->has('approval'))<div class="alert alert-error">{{ $errors->first('approval') }}</div>@endif
<div class="el-card">
    <div class="el-bar">
        <div><div style="font-size:12.5px;color:var(--muted)"><a href="{{ route('approvals.index') }}">Approvals</a> / <a href="{{ route('quotations.show', [$q, 'record' => 1]) }}"><code>{{ $q->code }}</code></a> rev {{ $q->version }}</div>
            <div style="font-size:16px;font-weight:600;margin-top:2px">{{ $line?->item?->description ?? $q->enquiry->title }} <span class="el-sub" style="font-weight:400">· {{ $q->enquiry->customer?->name }}</span></div></div>
        <span class="el-pill {{ $waitingNow ? 'el-warn' : 'el-grey' }}">{{ $waitingNow ? 'Awaiting approval' : $q->statusLabel() }}</span>
    </div>
    @if ($waitingNow)
        <div class="ap-sum">{{ $q->approval_asked_by ?? 'Sales' }} is asking for {{ $pct($low) }}% on {{ Formats::number((int) $annual) }} pieces a year. The floor is {{ $pct($floor) }}%. The gap is {{ $money($gap) }} a piece, {{ $money($gapYear, 0) }} a year.</div>
    @endif
    <div class="el-grid" style="grid-template-columns: minmax(0, 1fr) 320px">
        <div class="el-main">
            <div class="ap-sec"><div class="el-h">The quotation</div>
                <div class="el-table"><table><thead><tr><th>Part</th><th class="el-r">Annual qty</th><th class="el-r">Piece price</th><th class="el-r">Tooling</th><th class="el-r">Annual value</th><th class="el-r">Margin</th></tr></thead><tbody>
                @foreach ($q->lines as $l)<tr><td><div style="font-weight:500">{{ $l->item?->description }}</div><div class="el-sub">{{ collect([$l->item?->material_text, Formats::number((float) $l->weight_kg, 1).' kg', $q->enquiry->customer?->name])->filter()->implode(' · ') }}</div></td>
                    <td class="el-r">{{ Formats::number($l->annualQty() ?: $l->qty) }} / yr</td><td class="el-r">{{ $money($l->unit_price) }}</td><td class="el-r">{{ (float) $l->tooling_cost > 0 ? $money($l->tooling_cost, 0) : '—' }}</td>
                    <td class="el-r">{{ $money(($l->annualQty() ?: $l->qty) * (float) $l->unit_price, 0) }}</td><td class="el-r">{{ $pct($l->effective_margin_pct) }}%</td></tr>@endforeach
                </tbody></table></div></div>

            @if ($previous)
            @php $pl = $previous->lines->first(); @endphp
            <div class="ap-sec"><div class="el-h">What changed since rev {{ $previous->version }}</div>
                <div class="el-table"><table><thead><tr><th></th><th class="el-r">Rev {{ $previous->version }}</th><th class="el-r">Rev {{ $q->version }}</th><th>Why</th></tr></thead><tbody>
                    <tr><td>Piece price</td><td class="el-r">{{ $pl ? $money($pl->unit_price) : '—' }}</td><td class="el-r">{{ $line ? $money($line->unit_price) : '—' }}</td><td>{{ $q->change_note ?: (Quotation::REVISION_REASONS[$q->revision_reason][0] ?? '—') }}</td></tr>
                    <tr><td>Margin</td><td class="el-r">{{ $pl ? $pct($pl->effective_margin_pct).'%' : '—' }}</td><td class="el-r">{{ $line ? $pct($line->effective_margin_pct).'%' : '—' }}</td><td class="el-sub">follows the price unless the cost moved</td></tr>
                    <tr><td>Tooling</td><td class="el-r">{{ $pl ? $money($pl->tooling_cost, 0) : '—' }}</td><td class="el-r">{{ $line ? $money($line->tooling_cost, 0) : '—' }}</td><td class="el-sub">{{ $pl && $line && round((float) $pl->tooling_cost, 2) === round((float) $line->tooling_cost, 2) ? 'unchanged' : 'changed' }}</td></tr>
                </tbody></table></div></div>
            @endif

            <div class="ap-sec"><div class="el-h">What this part has fetched before</div>
                @if ($earlier->isEmpty() && $orders->isEmpty())
                    <div class="el-note" style="margin-top:8px">No earlier revision or order for this part.</div>
                @else
                <div class="el-table"><table><thead><tr><th>Quotation or order</th><th>When</th><th class="el-r">Piece</th><th class="el-r">Margin</th><th>What happened</th></tr></thead><tbody>
                    @foreach ($earlier as $e)@php $el = $e->lines->first(); @endphp<tr><td>{{ $e->code }} rev {{ $e->version }}</td><td>{{ $e->created_at->displayShortDate() }}</td><td class="el-r">{{ $el ? $money($el->unit_price) : '—' }}</td><td class="el-r">{{ $el ? $pct($el->effective_margin_pct).'%' : '—' }}</td><td>{{ $e->change_note ?: $e->statusLabel() }}</td></tr>@endforeach
                    @foreach ($orders as $o)<tr><td>{{ $o->order_number }}</td><td>{{ \Illuminate\Support\Carbon::parse($o->order_date)->displayShortDate() }}</td><td class="el-r">{{ $money($o->unit_price) }}</td><td class="el-r">—</td><td>{{ Formats::number((float) $o->ordered_qty) }} pieces ordered</td></tr>@endforeach
                </tbody></table></div>
                @endif
            </div>

            @if ($note)<div class="ap-sec"><div class="el-h">Latest note on the quotation</div><div style="margin-top:8px;font-size:13px">{{ $note->text }} <span class="el-sub">{{ $note->user_name }} · {{ $note->happened_on->displayShortDate() }}</span></div></div>@endif
        </div>
        <aside class="el-side">
            <div>
                <div class="ap-row"><span class="k">Asked by</span><span>{{ $q->approval_asked_by ?? '—' }}</span></div>
                <div class="ap-row"><span class="k">Waiting</span><span>{{ $q->approval_asked_at ? ($waiting === 0 ? 'since today' : $waiting.' '.\Illuminate\Support\Str::plural('day', $waiting)) : '—' }}</span></div>
                <div class="ap-row"><span class="k">Rule</span><span>Margin under {{ $pct($floor) }}%</span></div>
                <div class="ap-row"><span class="k">Approver</span><span>{{ $request ? 'Anyone with the '.$request->permission.' permission' : '—' }}</span></div>
                <div class="ap-row"><span class="k">Customer</span><span>{{ $q->enquiry->customer?->name }} · {{ $q->payment_terms ?: 'no terms' }}</span></div>
            </div>
            <div><div class="ap-big">{{ $pct($low) }}%</div><div class="el-sub">Floor {{ $pct($floor) }}%{{ $low < $floor ? ' · '.$pct($floor - $low).' points under, needs approval' : '' }}</div></div>
            @if ($waitingNow && $request)
                <div class="ap-decide"><div class="el-h">Decide</div>
                    @if ($canDecide)
                    <form method="POST" action="{{ route('quotations.approval.decide', $q) }}">@csrf<input type="hidden" name="choice" value="approve"><button class="el-btn primary" type="submit" style="width:100%">Approve at {{ $pct($low) }}%</button></form>
                    <details><summary>Approve with a condition</summary><form method="POST" action="{{ route('quotations.approval.decide', $q) }}">@csrf<input type="hidden" name="choice" value="condition">
                        <textarea name="condition" rows="2" required maxlength="500" placeholder="Tooling paid up front, a firm schedule, or a price review at six months" aria-label="Condition"></textarea><button class="el-btn" type="submit">Approve with this condition</button></form></details>
                    <details><summary>Send back with a note</summary><form method="POST" action="{{ route('quotations.approval.decide', $q) }}">@csrf<input type="hidden" name="choice" value="back">
                        <textarea name="note" rows="2" required maxlength="1000" placeholder="What needs to change" aria-label="Note to the quote owner"></textarea><button class="el-btn" type="submit">Send back</button></form></details>
                    <div class="el-note">A condition is written on the quotation and has to be met before it can be sent.</div>
                    @else
                    <div class="el-note">Your role cannot decide this kind of request.</div>
                    @endif
                </div>
            @elseif ($waitingNow)
                <div class="el-note">No approval request is open for this quotation, so there is nothing to decide here.</div>
            @else
                <div class="el-note">This quotation is {{ strtolower($q->statusLabel()) }}; nothing is waiting on an approver.</div>
            @endif
            <div class="el-note">This sits in the <a href="{{ route('approvals.index') }}">approvals queue</a> with everything else waiting for a decision.</div>
        </aside>
    </div>
</div>
<div class="el-note" style="margin-top:10px">The decision needs a comparison, not a form. Past prices for the same family and one methods note are usually enough to say yes or no without a meeting.</div>
@endsection
