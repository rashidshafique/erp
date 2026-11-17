{{-- Close the quotation (design 30e) as accepted, lost or no reply. Accepted fills a new sales order from the quote and closes the enquiry
     as won; lost asks for a reason that feeds the win-and-loss figures. The same shape as closing an enquiry (14c). --}}
@extends('layouts.app')
@section('title', 'Close '.$q->code)
@section('breadcrumb')<a href="{{ route('quotations.index') }}">Quotations</a> / <a href="{{ route('quotations.show', $q) }}">{{ $q->code }}</a> / close @endsection
@section('content')
@php
    use App\Domains\Enquiry\Models\Quotation;
    use App\Support\Formats;
    $money = fn ($v, $d = 2) => Formats::money((float) $v, $d);
    $first = $q->lines->first();
    $outcome = old('outcome', 'ACCEPTED');
    $dev = $developments->first();
@endphp
@include('enquiry::partials._list_styles')
<style>
    .cl-out { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; } .cl-out label { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid var(--border); border-radius: 7px; cursor: pointer; font-size: 13px; }
    .cl-out label:has(input:checked) { border-color: var(--accent); background: var(--accent-soft, #eaf0fd); } .cl-out .d { font-size: 12px; color: var(--muted); }
    .cl-sec { margin-bottom: 22px; } .cl-sec table td, .cl-sec table th { white-space: normal; vertical-align: top; } .cl-sec .el-r { text-align: right; }
    .cl-f { display: flex; gap: 14px; flex-wrap: wrap; } .cl-f label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--muted); }
    .cl-will { margin: 6px 0 0; padding-left: 18px; font-size: 13px; line-height: 1.6; }
    @media (max-width: 1100px) { .cl-out { grid-template-columns: 1fr; } }
</style>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
@if ($expired)<div class="alert alert-error">This quotation has passed its valid-until date. It can still be closed as lost or no reply; to accept it, make a new revision first.</div>@endif
<form method="POST" action="{{ route('quotations.close.store', $q) }}" id="cl-form">@csrf
<div class="el-card">
    <div class="el-bar">
        <div><div style="font-size:12.5px;color:var(--muted)"><a href="{{ route('quotations.index') }}">Quotations</a> / <a href="{{ route('quotations.show', $q) }}"><code>{{ $q->code }}</code></a> / rev {{ $q->version }}</div>
            <div style="font-size:16px;font-weight:600;margin-top:2px">{{ $first?->item?->description ?? $q->enquiry->title }} <span class="el-sub" style="font-weight:400">· {{ $q->enquiry->customer?->name }}</span></div></div>
        <span class="el-pill el-blue">Close quotation</span>
    </div>
    <div class="el-grid" style="grid-template-columns: minmax(0, 1fr) 340px">
        <div class="el-main">
            <div class="cl-sec"><div class="cl-out" role="radiogroup" aria-label="How it closes">
                @foreach (Quotation::OUTCOMES as $k => [$label, $hint])
                    <label><input type="radio" name="outcome" value="{{ $k }}" @checked($outcome === $k) @disabled($k === 'ACCEPTED' && $expired)><span>{{ $label }}<div class="d">{{ $hint }}</div></span></label>
                @endforeach
            </div></div>

            <div data-show="ACCEPTED">
                <div class="cl-sec"><div class="el-h" style="margin-bottom:8px">What they sent</div>
                    <div class="cl-f">
                        <label>Their reference<input type="text" name="customer_po" value="{{ old('customer_po') }}" maxlength="100" placeholder="Customer PO number"></label>
                        <label>Date<input type="date" name="customer_po_date" value="{{ old('customer_po_date', today()->toDateString()) }}"></label>
                        <label>Accepted against<span style="font-size:13px;color:var(--text);padding-top:8px">Rev {{ $q->version }} · {{ $first ? $money($first->unit_price) : '—' }} · {{ $first?->annualQty() ? Formats::number($first->annualQty()).' / yr' : Formats::number($first?->qty).' pieces' }}</span></label>
                    </div>
                </div>
                <div class="cl-sec"><div class="el-h" style="margin-bottom:8px">The order this makes</div>
                    <div class="el-table"><table><thead><tr><th>Part</th><th class="el-r">Qty</th><th class="el-r">Price</th><th>Delivery</th><th>First production</th></tr></thead><tbody>
                    @foreach ($q->lines as $l)
                        <tr><td><div style="font-weight:500">{{ $l->item?->description }}</div><div class="el-sub">{{ collect([$l->item?->material_text, $l->item?->drawing_rev ? 'rev '.$l->item->drawing_rev : null])->filter()->implode(' · ') }}</div></td>
                            <td class="el-r">{{ Formats::number($l->qty) }}</td><td class="el-r">{{ $money($l->unit_price) }}</td>
                            <td>{{ $q->delivery_weeks ? $q->delivery_weeks.' weeks from approval' : 'No lead time quoted' }}</td>
                            <td>{{ $l->item?->product_id ? 'Product exists, repeat work' : 'New product, a draft revision is made' }}@if ($dev) · needs the pattern, {{ $dev->code }}@endif</td></tr>
                    @endforeach
                    </tbody></table></div>
                    <div class="el-note" style="margin-top:8px">You can change all of this on the order before it goes live; this only fills it in.</div>
                </div>
                <div class="el-note">Nothing is sent to the customer from here. This records what they decided. The acknowledgement goes out from the order, once contract review passes.</div>
            </div>

            <div data-show="LOST" hidden>
                <div class="cl-sec"><div class="el-h" style="margin-bottom:8px">Why it was lost</div>
                    <div class="cl-f">
                        <label>Reason<select name="lost_reason" class="ls-select" style="width:auto"><option value="">Choose a reason</option>@foreach (Quotation::LOST_REASONS as $k => $label)<option value="{{ $k }}" @selected(old('lost_reason') === $k)>{{ $label }}</option>@endforeach</select></label>
                        <label data-price>How far off were we, % (if they said)<input type="number" step="0.1" min="0" max="100" name="price_gap_pct" value="{{ old('price_gap_pct') }}" style="width:120px"></label>
                    </div>
                    <div class="el-note" style="margin-top:8px">It feeds the lost-and-declined summary on the enquiries list and the quotations list.</div>
                </div>
            </div>
            <div data-show="NO_REPLY" hidden><div class="el-note">The quotation is kept as expired with its history. It is not counted as lost, so the win rate does not lie.</div></div>

            <div class="cl-sec" style="margin-top:18px"><label style="display:block"><span class="el-sub">Note (optional)</span><textarea name="note" rows="2" maxlength="500" style="width:100%">{{ old('note') }}</textarea></label></div>
        </div>
        <aside class="el-side">
            <div data-show="ACCEPTED"><div class="el-h">Marking it accepted will</div>
                <ul class="cl-will"><li>open a new sales order with these lines and prices</li><li>close enquiry {{ $q->enquiry->code }} as won</li>@if ($dev)<li>move development {{ $dev->code }} on to its build stage</li>@endif<li>lock rev {{ $q->version }}; later changes go on the order, not the quote</li></ul></div>
            <div data-show="LOST" hidden><div class="el-h">Marking it lost will</div>
                <ul class="cl-will"><li>close enquiry {{ $q->enquiry->code }} as lost, with the reason</li><li>keep rev {{ $q->version }} and its history on the record</li></ul></div>
            <div data-show="NO_REPLY" hidden><div class="el-h">Closing with no reply will</div>
                <ul class="cl-will"><li>mark the quotation expired</li><li>leave the enquiry as it is</li></ul></div>
            <div style="display:flex;flex-direction:column;gap:8px">
                <button class="el-btn primary" type="submit" data-submit>Mark accepted and open the order</button>
                <a class="el-btn" href="{{ route('quotations.show', $q) }}" style="text-align:center">Cancel</a>
            </div>
        </aside>
    </div>
</div>
<div class="el-note" style="margin-top:10px">The same shape as closing an enquiry, so the two feel like one habit. A quote that simply ran out is "no reply", not "lost", otherwise the win rate lies.</div>
</form>
<script>
(function () {
    var form = document.getElementById('cl-form'), labels = { ACCEPTED: 'Mark accepted and open the order', LOST: 'Mark lost', NO_REPLY: 'Close with no reply' };
    function sync() {
        var v = (form.querySelector('input[name=outcome]:checked') || {}).value || 'ACCEPTED';
        form.querySelectorAll('[data-show]').forEach(function (e) { e.hidden = e.dataset.show !== v; });
        form.querySelector('[data-submit]').textContent = labels[v];
        var reason = form.querySelector('select[name=lost_reason]'); form.querySelector('[data-price]').style.display = reason.value === 'PRICE' ? '' : 'none';
    }
    form.addEventListener('change', sync); sync();
})();
</script>
@endsection
