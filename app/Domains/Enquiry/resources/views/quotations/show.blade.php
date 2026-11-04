@extends('layouts.app')
@section('title', $q->code)
@section('breadcrumb')<a href="{{ route('enquiries.index') }}">Enquiries</a> / <a href="{{ route('enquiries.show', $q->enquiry) }}">{{ $q->enquiry->code }}</a> / {{ $q->code }} @endsection
@section('content')
<style>.ca-wrap td, .ca-wrap th { white-space: normal; vertical-align: top; } .num { text-align: right; } .q-in { width: 84px; } .bd td { padding: 2px 8px; font-size: 12.5px; }</style>
@php
    $qcls = ['DRAFT' => 'status-on-hold', 'ISSUED' => 'status-in-progress', 'ACCEPTED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'EXPIRED' => 'status-cancelled', 'SUPERSEDED' => 'status-cancelled'];
    $draft = $q->status === 'DRAFT';
    $n = fn ($v, $d = 2) => \App\Support\Formats::number((float) $v, $d);
    $total = $q->lines->sum(fn ($l) => $l->unit_price * $l->qty);
    $min = \App\Domains\Enquiry\Services\Rates::get()['min_margin_pct'];
    $low = $q->lines->min('effective_margin_pct');
@endphp
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@if ($q->status === 'ACCEPTED' && $q->salesOrder)<div class="alert alert-success">Accepted. Sales order @if (Route::has('sales-orders.show'))<a href="{{ route('sales-orders.show', $q->salesOrder) }}">{{ $q->salesOrder->order_number }}</a>@else{{ $q->salesOrder->order_number }}@endif was created.</div>@endif
@if ($q->status === 'EXPIRED')<div class="alert alert-error">This quotation has expired. Make a new version from the enquiry and issue it.</div>@endif
@php
    $annual = $q->lines->sum(fn ($l) => $l->unit_price * ($l->item?->annual_qty ?: 0));
    $target = $q->lines->count() === 1 ? $q->lines->first()->item?->target_price : null;
    $one = $q->lines->count() === 1 ? $q->lines->first() : null;
    $margin = $total > 0 ? ($total - $q->lines->sum(fn ($l) => $l->unit_cost * $l->qty)) / $total * 100 : 0;
@endphp
<style>
    .qd-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
    .qd-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--muted); }
    .qd-bar code { color: var(--text); }
    .qd-vers { display: inline-flex; gap: 2px; margin-left: 8px; } .qd-vers a { padding: 2px 8px; border-radius: 5px; font-size: 12px; color: var(--muted); text-decoration: none; } .qd-vers a.on { background: #eaf0fd; color: #2f5fd0; font-weight: 500; }
    .qd-seg { display: inline-flex; padding: 3px; background: #f0f1f3; border-radius: 7px; font-size: 12.5px; } .qd-seg button { padding: 5px 12px; border: 0; border-radius: 5px; background: transparent; cursor: pointer; } .qd-seg button.on { background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.12); }
    .qd-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; }
    .qd-main { padding: 20px 28px; min-width: 0; }
    .qd-rail { border-left: 1px solid var(--border); padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 16px; font-size: 13px; }
    .qd-rail .k { font-size: 12px; color: var(--muted); } .qd-rail .row { display: flex; justify-content: space-between; gap: 12px; }
    .qd-rail .sec { padding-top: 14px; border-top: 1px solid var(--border); }
    .qd-facts { display: flex; gap: 28px; flex-wrap: wrap; font-size: 13px; margin-bottom: 14px; } .qd-facts .k { font-size: 12px; color: var(--muted); }
    .qd-part { margin-bottom: 26px; } .qd-part table td, .qd-part table th { white-space: normal; vertical-align: top; } .qd-part .num { text-align: right; }
    .qd-ins { display: flex; gap: 12px; flex-wrap: wrap; margin: 12px 0 0; } .qd-ins label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--muted); } .qd-ins input { width: 96px; padding: 6px 8px; }
    .qd-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 8px; }
    .qd-box { border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; font-size: 13px; } .qd-box .k { font-size: 12px; color: var(--muted); } .qd-box .v { font-weight: 500; margin-top: 2px; }
    .qd-note { font-size: 12.5px; color: var(--muted); }
    @media (max-width: 1000px) { .qd-grid { grid-template-columns: 1fr; } .qd-rail { border-left: 0; border-top: 1px solid var(--border); } .qd-cards { grid-template-columns: 1fr; } .qd-bar, .qd-main { padding-left: 16px; padding-right: 16px; } }
</style>
<div class="qd-card" data-qd>
    <div class="qd-bar">
        <div><a href="{{ route('enquiries.index') }}">Quotations</a> / <a href="{{ route('enquiries.show', $q->enquiry) }}">{{ $q->enquiry->code }}</a> / <code>{{ $q->code }}</code>
            <span class="qd-vers">@foreach ($versions->sortBy('version') as $v)<a href="{{ route('quotations.show', $v->id) }}" @class(['on' => $v->id === $q->id])>R{{ $v->version - 1 }}</a>@endforeach</span>
            <span class="status {{ $qcls[$q->status] ?? '' }}" style="margin-left:8px">{{ $q->status }}</span></div>
        <div style="display:flex;gap:10px;align-items:center">
            <div class="qd-seg" role="group" aria-label="Detail"><button type="button" class="on" data-mode="detailed">Detailed</button><button type="button" data-mode="simple">Simple</button></div>
            @if (! $draft)<a class="btn" href="{{ route('quotations.print', $q) }}" target="_blank">Customer copy</a>@endif
            <a class="btn" href="{{ route('enquiries.show', $q->enquiry) }}">Back to enquiry</a>
        </div>
    </div>
    <div class="qd-grid">
    <div class="qd-main">
    <form method="POST" action="{{ route('quotations.update', $q) }}" id="q-form">@csrf @method('PUT')
    @foreach ($q->lines as $l)
        @php $b = $l->breakdown ?? []; $parts = ['metal' => 'Molten metal ('.$n($b['melt_rate'] ?? 0, 0).'/kg)', 'moulding' => 'Moulding', 'cores' => 'Cores', 'fettling' => 'Fettling', 'heat_treatment' => 'Heat treatment', 'testing' => 'Testing and NDT', 'packing' => 'Packing', 'overhead' => 'Overhead', 'machining' => 'Machining', 'other' => 'Other', 'scrap' => 'Scrap allowance', 'tooling' => 'Tooling spread']; @endphp
        <div class="qd-part">
            <div class="qd-facts"><div><div class="k">Part</div><strong>{{ $l->line_no }}. {{ $l->item->description }}</strong></div><div><div class="k">Casting / poured</div>{{ $n($l->weight_kg, 2) }} kg / {{ $n($l->poured_kg, 2) }} kg</div><div><div class="k">Annual qty</div>{{ $l->item->annual_qty ? number_format($l->item->annual_qty) : '—' }}</div><div><div class="k">Quantity quoted</div>{{ number_format($l->qty) }}</div></div>
            @if ($l->basis)<div class="muted small" style="margin-bottom:6px">{{ $l->basis }}</div>@endif
            @if ($toolingNotes[$l->id]['note'] ?? null)<div class="small {{ $toolingNotes[$l->id]['warning'] ? 'text-danger' : 'muted' }}" style="margin-bottom:6px">{{ $toolingNotes[$l->id]['note'] }}</div>@endif
            <div data-detailed><div class="muted small" style="margin-bottom:4px">Where the cost comes from</div>
            <div class="table-wrap"><table><thead><tr><th>Cost head</th><th class="num">{{ $q->currency }} / pc</th></tr></thead><tbody>
                @foreach ($parts as $k => $lab)@if (($b[$k] ?? 0) != 0)<tr><td>{{ $lab }}</td><td class="num">{{ $n($b[$k]) }}</td></tr>@endif @endforeach
                <tr><td style="font-weight:600">Total cost</td><td class="num" style="font-weight:600">{{ $n($l->unit_cost) }}</td></tr>
                <tr><td>Price from margin</td><td class="num">{{ $n($b['calculated_price'] ?? 0) }}</td></tr>
            </tbody></table></div></div>
            <div data-simple hidden><div class="table-wrap"><table><thead><tr><th>Item</th><th class="num">{{ $q->currency }} / pc</th></tr></thead><tbody>
                <tr><td>Cost</td><td class="num">{{ $n($l->unit_cost) }}</td></tr><tr><td>Margin</td><td class="num">{{ $n($l->effective_margin_pct, 1) }}%</td></tr><tr><td style="font-weight:600">Price</td><td class="num" style="font-weight:600">{{ $n($l->unit_price) }}</td></tr>
            </tbody></table></div>
            <div class="qd-note" style="margin-top:8px">Simple hides the cost build-up. Switch to Detailed for the full sheet.</div></div>
            @if ($draft)
            <div class="qd-ins">
                <label>Qty<input type="number" min="1" name="lines[{{ $l->id }}][qty]" value="{{ $l->qty }}"></label>
                <label>Scrap %<input type="number" step="any" min="0" name="lines[{{ $l->id }}][scrap_pct]" value="{{ $l->scrap_pct }}"></label>
                <label>Margin %<input type="number" step="any" min="0" name="lines[{{ $l->id }}][margin_pct]" value="{{ $l->margin_pct }}"></label>
                <label>Price override<input type="number" step="any" min="0" name="lines[{{ $l->id }}][price_override]" value="{{ $l->price_override }}" placeholder="{{ $n($b['calculated_price'] ?? 0) }}"></label>
            </div>
            @else
            <div class="qd-ins" style="color:var(--muted);font-size:13px">Scrap {{ $n($l->scrap_pct, 1) }}% · margin {{ $n($l->margin_pct, 1) }}% · price override {{ $l->price_override ? $n($l->price_override) : '—' }} · effective margin <span class="{{ $l->effective_margin_pct < $min ? 'text-danger' : '' }}">{{ $n($l->effective_margin_pct, 1) }}%</span></div>
            @endif
        </div>
    @endforeach
    <div class="qd-cards">
        <div class="qd-box"><div class="k">Tooling</div><div class="v">{{ ['NONE' => 'Not charged', 'UPFRONT' => 'Up front, '.$n($q->upfrontToolingPrice()), 'AMORTISED' => 'Spread over '.$q->tooling_amortise_qty.' castings'][$q->tooling_mode] }}</div></div>
        <div class="qd-box"><div class="k">Terms</div><div class="v">{{ $q->payment_terms ?: 'Payment terms not stated' }}</div><div class="k" style="margin-top:2px">Delivery {{ $q->delivery_weeks ? $q->delivery_weeks.' weeks' : 'to be agreed' }}</div></div>
        <div class="qd-box"><div class="k">Valid until</div><div class="v">{{ $q->valid_until?->displayDate() ?? config('quotation.validity_days').' days from issue' }}</div></div>
    </div>
    @if ($draft)
    <div class="form-grid" style="margin-top:18px">
        <div class="field"><label>Valid until</label><input type="date" name="valid_until" value="{{ old('valid_until', $q->valid_until?->toDateString()) }}"><div class="help">Blank: {{ config('quotation.validity_days') }} days from issue.</div></div>
        <div class="field"><label>Delivery, weeks from order</label><input type="number" min="1" name="delivery_weeks" value="{{ old('delivery_weeks', $q->delivery_weeks) }}"></div>
        <div class="field"><label>Payment terms</label><input name="payment_terms" value="{{ old('payment_terms', $q->payment_terms) }}"></div>
        <div class="field"><label>Tooling</label><select name="tooling_mode"><option value="NONE" @selected($q->tooling_mode === 'NONE')>Not charged (customer supplies, or absorbed)</option><option value="UPFRONT" @selected($q->tooling_mode === 'UPFRONT')>Charged up front, separate invoice</option><option value="AMORTISED" @selected($q->tooling_mode === 'AMORTISED')>Spread over the castings</option></select></div>
        <div class="field"><label>Castings to spread tooling over</label><input type="number" min="1" name="tooling_amortise_qty" value="{{ old('tooling_amortise_qty', $q->tooling_amortise_qty) }}"></div>
        <div class="field field-full"><label>Notes for the customer</label><textarea name="notes" rows="3">{{ old('notes', $q->notes) }}</textarea></div>
    </div>
    <p><button class="btn btn-primary" type="submit">Save and recalculate</button></p>
    @elseif ($q->notes)<p style="margin-top:14px">{{ $q->notes }}</p>@endif
    </form>
    </div>
    <div class="qd-rail">
        <div><div class="k">Quoted price {{ $one ? 'per piece' : 'for the quantities quoted' }}</div>
            <div style="font-size:32px;font-weight:600;letter-spacing:-.02em;margin-top:2px">{{ $n($one ? $one->unit_price : $total) }}</div>
            <div class="k">{{ $q->currency }} · margin {{ $n($margin, 1) }}%@if ($one && $one->poured_kg > 0) · {{ $n($one->unit_price / max((float) $one->weight_kg, 0.0001)) }}/kg @endif</div></div>
        <div style="display:flex;flex-direction:column;gap:8px" class="sec">
            @if ($target)<div class="row"><span class="k">Customer target</span><span>{{ $n($target) }}</span></div>@endif
            @if ($annual > 0)<div class="row"><span class="k">Annual value</span><span>{{ $n($annual, 0) }}</span></div>@endif
            <div class="row"><span class="k">Total for quantities</span><span>{{ $n($total) }}</span></div>
            <div class="row"><span class="k">Lowest margin</span><span class="{{ $low < $min ? 'text-danger' : '' }}">{{ $n($low, 1) }}%</span></div>
        </div>
        @if ($draft)
        <div class="sec"><div class="k" style="font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Before it can go out</div>
            @if ($blockers)<ul style="margin:0;padding-left:18px">@foreach ($blockers as $bl)<li>{{ $bl }}</li>@endforeach</ul>@else<p style="margin:0">Every part is assessed and priced.</p>@endif
            @if ($low < $min)<p class="muted" style="margin:8px 0 0">Lowest margin {{ $n($low, 1) }}%, below the {{ $n($min, 1) }}% minimum. Issuing may need approval.</p>@endif
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                <form method="POST" action="{{ route('quotations.refresh', $q) }}">@csrf<button class="btn" type="submit">Re-read the enquiry</button></form>
                <form method="POST" action="{{ route('quotations.issue', $q) }}" onsubmit="return confirm('Issue this quotation to the customer?')">@csrf<button class="btn btn-primary" type="submit" @disabled($blockers)>Issue quotation</button></form>
            </div></div>
        @elseif ($q->status === 'ISSUED')
        <div class="sec"><div class="k" style="font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Customer's answer</div>
            <p style="margin:0 0 10px">Issued {{ $q->issued_at?->displayDate() }} by {{ $q->issued_by_name }}. Accepting creates the product and a draft revision if they do not exist, and a draft sales order priced from this quotation.</p>
            <form method="POST" action="{{ route('quotations.accept', $q) }}" onsubmit="return confirm('Customer accepted? This creates a draft sales order.')">@csrf<button class="btn btn-primary" type="submit">Customer accepted</button></form>
            <form method="POST" action="{{ route('quotations.reject', $q) }}" style="display:flex;gap:6px;margin-top:8px">@csrf<input name="decision_note" placeholder="Why (optional)"><button class="btn btn-danger" type="submit">Customer declined</button></form></div>
        @else
        <div class="sec">{{ $q->status }}{{ $q->decided_at ? ' on '.$q->decided_at->displayDate() : '' }}{{ $q->decision_note ? ' — '.$q->decision_note : '' }}</div>
        @endif
    </div>
    </div>
</div>
<script>
document.querySelectorAll('[data-qd] [data-mode]').forEach(function (b) { b.addEventListener('click', function () {
    var root = b.closest('[data-qd]'), simple = b.dataset.mode === 'simple';
    root.querySelectorAll('[data-detailed]').forEach(function (e) { e.hidden = simple; }); root.querySelectorAll('[data-simple]').forEach(function (e) { e.hidden = !simple; });
    root.querySelectorAll('[data-mode]').forEach(function (x) { x.classList.toggle('on', x === b); });
}); });
</script>

<div class="tab-marker" data-tab-start="Versions & history"></div>
<div class="panel"><div class="panel-header"><h2>Versions</h2></div><div class="table-wrap"><table><thead><tr><th>Quotation</th><th>Version</th><th>Status</th></tr></thead><tbody>
@foreach ($versions as $v)<tr><td><a href="{{ route('quotations.show', $v->id) }}">{{ $v->code }}</a></td><td>{{ $v->version }}</td><td>{{ $v->status }}</td></tr>@endforeach
</tbody></table></div></div>
@include('audit._history', ['type' => 'Quotation', 'id' => $q->id])
@endsection
