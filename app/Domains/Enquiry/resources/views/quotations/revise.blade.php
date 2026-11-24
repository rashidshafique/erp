{{-- New revision (design 30c): why it is changing, what changes, the lines with the old figure beside the new, and what it does to the
     margin before anything is sent. Creates the next revision with the same number and supersedes the last one. --}}
@extends('layouts.app')
@section('title', 'New revision '.$q->code)
@section('breadcrumb')<a href="{{ route('quotations.index') }}">Quotations</a> / <a href="{{ route('quotations.show', $q) }}">{{ $q->code }}</a> / new revision @endsection
@section('content')
@php
    use App\Domains\Enquiry\Models\Quotation;
    use App\Support\Formats;
    $next = $q->version + 1;
    $first = $q->lines->first();
    $money = fn ($v, $d = 2) => Formats::money((float) $v, $d);
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $tpl = Formats::withCurrency('{n}');
    $changes = ['price' => 'Piece price', 'qty' => 'Annual quantity', 'tooling' => 'Tooling price', 'lead' => 'Lead time', 'scope' => 'Alloy or scope', 'terms' => 'Terms'];
    $suggest = ['PRICE_ASK' => ['price'], 'VOLUME' => ['qty', 'price'], 'DRAWING' => ['scope', 'price'], 'COST' => ['price'], 'LEAD_TIME' => ['lead'], 'CORRECTION' => ['price']];
    $reason = old('reason', 'PRICE_ASK');
    $ticked = old('changes', $suggest[$reason]);
@endphp
@include('partials.record-ui')
<style>
    .rv-reasons { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; } .rv-reasons label { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid var(--border); border-radius: 7px; cursor: pointer; font-size: 13px; }
    .rv-reasons label:has(input:checked) { border-color: var(--accent); background: var(--accent-soft, #eaf0fd); } .rv-reasons .d { font-size: 12px; color: var(--muted); }
    .rv-chips { display: flex; gap: 8px; flex-wrap: wrap; } .rv-chips label { display: inline-flex; gap: 6px; align-items: center; padding: 6px 12px; border: 1px solid var(--border); border-radius: 999px; font-size: 13px; cursor: pointer; } .rv-chips label:has(input:checked) { border-color: var(--accent); background: var(--accent-soft, #eaf0fd); }
    .rv-sec { margin-bottom: 24px; } .rv-sec table td, .rv-sec table th { white-space: normal; vertical-align: middle; } .rv-sec .el-r { text-align: right; }
    .rv-old { color: var(--muted); } .rv-in { width: 96px; text-align: right; } .rv-in:disabled { background: transparent; border-color: transparent; color: var(--muted); }
    .rv-row { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 13px; } .rv-row .k { color: var(--muted); } .rv-arrow { color: var(--muted); padding: 0 4px; }
    .rv-verdict { padding: 10px 12px; border-radius: 7px; font-size: 12.5px; line-height: 1.5; } .rv-verdict.ok { background: #e7f4ec; color: #1d5a3a; } .rv-verdict.under { background: #fbe7e5; color: #8a2a22; }
    .rv-will { margin: 6px 0 0; padding-left: 18px; font-size: 13px; } .rv-extra { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 12px; }
    .rv-extra label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--muted); }
    @media (max-width: 1100px) { .rv-reasons { grid-template-columns: 1fr 1fr; } }
</style>
@if ($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('quotations.revise.store', $q) }}" id="rv-form" data-tpl="{{ $tpl }}" data-floor="{{ $floor }}">@csrf
<div class="el-card">
    <div class="el-bar">
        <div><div style="font-size:12.5px;color:var(--muted)"><a href="{{ route('quotations.index') }}">Quotations</a> / <a href="{{ route('quotations.show', $q) }}"><code>{{ $q->code }}</code></a> / rev {{ $next }}</div>
            <div style="font-size:16px;font-weight:600;margin-top:2px">{{ $first?->item?->description ?? $q->enquiry->title }} <span class="el-sub" style="font-weight:400">· {{ $q->enquiry->customer?->name }}</span></div></div>
        <span class="el-pill el-blue">New revision</span>
    </div>
    <div class="el-grid" style="grid-template-columns: minmax(0, 1fr) 340px">
        <div class="el-main">
            <div class="rv-sec"><div class="el-h" style="margin-bottom:8px">Why is it changing</div>
                <div class="rv-reasons" role="radiogroup" aria-label="Why is it changing">
                    @foreach (Quotation::REVISION_REASONS as $k => [$label, $hint])
                        <label><input type="radio" name="reason" value="{{ $k }}" @checked($reason === $k) data-suggest="{{ implode(',', $suggest[$k]) }}"><span>{{ $label }}<div class="d">{{ $hint }}</div></span></label>
                    @endforeach
                </div>
            </div>
            <div class="rv-sec"><div class="el-h" style="margin-bottom:8px">What changes on rev {{ $next }}</div>
                <div class="rv-chips">@foreach ($changes as $k => $label)<label><input type="checkbox" name="changes[]" value="{{ $k }}" @checked(in_array($k, $ticked, true))> {{ $label }}</label>@endforeach</div>
            </div>
            <div class="rv-sec"><div class="el-h" style="margin-bottom:8px">Lines</div>
                <div class="el-table"><table><thead><tr><th></th><th>Part</th><th class="el-r">Annual qty</th><th class="el-r">Batch</th><th class="el-r">Piece price</th><th class="el-r">Tooling</th><th>Alloy</th></tr></thead><tbody>
                @foreach ($q->lines as $l)
                    <tr data-line data-old-price="{{ $l->unit_price }}" data-old-qty="{{ $l->annualQty() ?: $l->qty }}" data-cost="{{ $l->unit_cost }}" data-cost-now="{{ $costToday[$l->id] }}">
                        <td><input type="checkbox" name="lines[{{ $l->id }}][apply]" value="1" checked aria-label="Change line {{ $l->line_no }}"></td>
                        <td><div style="font-weight:500">{{ $l->item?->description }}</div><div class="el-sub">{{ collect([$l->item?->material_text, Formats::number((float) $l->weight_kg, 1).' kg'])->filter()->implode(' · ') }}</div></td>
                        <td class="el-r"><span class="rv-old">{{ Formats::number($l->annualQty() ?: $l->qty) }}</span> <span class="rv-arrow">→</span> <input class="rv-in" type="number" min="1" name="lines[{{ $l->id }}][annual_qty]" value="{{ old("lines.{$l->id}.annual_qty", $l->annualQty() ?: $l->qty) }}" data-f="qty" aria-label="New annual quantity"></td>
                        <td class="el-r">{{ Formats::number($l->qty) }}</td>
                        <td class="el-r"><span class="rv-old">{{ $money($l->unit_price) }}</span> <span class="rv-arrow">→</span> <input class="rv-in" type="number" step="0.01" min="0.01" name="lines[{{ $l->id }}][price]" value="{{ old("lines.{$l->id}.price", $l->unit_price) }}" data-f="price" aria-label="New piece price"></td>
                        <td class="el-r"><input class="rv-in" type="number" step="0.01" min="0" name="lines[{{ $l->id }}][tooling]" value="{{ old("lines.{$l->id}.tooling", $l->tooling_cost) }}" data-f="tooling" aria-label="Tooling cost"></td>
                        <td><select name="lines[{{ $l->id }}][family]" data-f="scope" aria-label="Alloy" class="ls-select" style="width:auto"><option value="GREY" @selected($l->family === 'GREY')>Grey iron</option><option value="DUCTILE" @selected($l->family === 'DUCTILE')>Ductile iron</option></select></td>
                    </tr>
                @endforeach
                </tbody></table></div>
                <div class="el-note" style="margin-top:8px">Untick a line to leave it exactly as it was. A figure only changes when its box above is ticked.</div>
            </div>
            <div class="rv-sec"><div class="el-h" style="margin-bottom:8px">Validity and note</div>
                <div class="rv-extra">
                    <label>Valid until<input type="date" name="valid_until" value="{{ old('valid_until', today()->addDays($validityDays)->toDateString()) }}"><span>Default is {{ $validityDays }} days from today</span></label>
                    <label data-for="lead">Lead time, weeks<input type="number" min="1" max="104" name="delivery_weeks" value="{{ old('delivery_weeks', $q->delivery_weeks) }}" data-f="lead"></label>
                    <label data-for="terms">Payment terms<input type="text" name="payment_terms" value="{{ old('payment_terms', $q->payment_terms) }}" data-f="terms"></label>
                </div>
                <label class="field" style="display:block;margin-top:12px"><span class="el-sub">Note to the customer</span><textarea name="notes" rows="3" style="width:100%">{{ old('notes') }}</textarea></label>
            </div>
        </div>
        <aside class="el-side">
            <div><div class="el-h">What it does to the deal</div><div style="margin-top:8px">
                <div class="rv-row" data-single><span class="k">Piece price</span><span><span class="rv-old" data-o="price"></span><span class="rv-arrow">→</span><strong data-n="price"></strong></span></div>
                <div class="rv-row"><span class="k">Annual quantity</span><span><span class="rv-old" data-o="qty"></span><span class="rv-arrow">→</span><strong data-n="qty"></strong></span></div>
                <div class="rv-row"><span class="k">Annual value</span><span><span class="rv-old" data-o="value"></span><span class="rv-arrow">→</span><strong data-n="value"></strong></span></div>
                <div class="rv-row"><span class="k">Margin</span><span><span class="rv-old" data-o="margin"></span><span class="rv-arrow">→</span><strong data-n="margin"></strong></span></div>
                <div class="rv-row"><span class="k">Margin at today's costs</span><span><span class="rv-old" data-o="marginNow"></span><span class="rv-arrow">→</span><strong data-n="marginNow"></strong></span></div>
            </div></div>
            <div class="rv-verdict ok" data-verdict aria-live="polite"></div>
            <div><div class="el-h">Revision {{ $next }} will</div>
                <ul class="rv-will"><li>be created as a draft</li><li>mark rev {{ $q->version }} superseded, keeping it on the record</li><li>keep the same quotation number, {{ $q->code }}</li><li>leave the enquiry at Quoting until it is sent</li></ul></div>
            <div style="display:flex;flex-direction:column;gap:8px">
                <button class="el-btn primary" type="submit" name="action" value="send">Create rev {{ $next }} and send</button>
                <button class="el-btn" type="submit" name="action" value="draft">Save as draft</button>
                <a class="el-btn" href="{{ route('quotations.show', $q) }}" style="text-align:center">Cancel</a>
            </div>
        </aside>
    </div>
</div>
<div class="el-note" style="margin-top:10px">The reason is picked first because it is what everyone asks six months later. The margin is worked out as you type, so a price is never sent that quietly goes under the floor.</div>
</form>
<script>
(function () {
    var form = document.getElementById('rv-form'), tpl = form.dataset.tpl, floor = parseFloat(form.dataset.floor);
    var rows = [].slice.call(form.querySelectorAll('[data-line]'));
    var fmt = function (v, d) { return tpl.replace('{n}', Number(v).toLocaleString('en', { minimumFractionDigits: d, maximumFractionDigits: d })); };
    var num = function (el, fallback) { var v = parseFloat(el && el.value); return isNaN(v) ? fallback : v; };
    var on = function (key) { var c = form.querySelector('input[name="changes[]"][value="' + key + '"]'); return !!(c && c.checked); };
    function sync() {
        form.querySelectorAll('[data-f]').forEach(function (el) { el.disabled = !on(el.dataset.f); });
        form.querySelectorAll('[data-for]').forEach(function (l) { l.style.display = on(l.dataset.for) ? '' : 'none'; });
        var oldV = 0, newV = 0, oldQ = 0, newQ = 0, oldM = 100, newM = 100, nowM = 100, oldNowM = 100, floorPrice = 0;
        rows.forEach(function (r) {
            var apply = r.querySelector('input[type=checkbox]').checked;
            var op = parseFloat(r.dataset.oldPrice), oq = parseFloat(r.dataset.oldQty), cost = parseFloat(r.dataset.cost), now = parseFloat(r.dataset.costNow);
            var np = apply && on('price') ? num(r.querySelector('[data-f=price]'), op) : op, nq = apply && on('qty') ? num(r.querySelector('[data-f=qty]'), oq) : oq;
            r.querySelectorAll('[data-f]').forEach(function (el) { if (!apply) { el.disabled = true; } });
            oldV += op * oq; newV += np * nq; oldQ += oq; newQ += nq;
            oldM = Math.min(oldM, (op - cost) / op * 100); newM = Math.min(newM, (np - cost) / np * 100);
            oldNowM = Math.min(oldNowM, (op - now) / op * 100); nowM = Math.min(nowM, (np - now) / np * 100);
            floorPrice = Math.max(floorPrice, now / (1 - floor / 100));
            r.dataset.np = np;
        });
        var set = function (k, o, n) { form.querySelector('[data-o="' + k + '"]').textContent = o; form.querySelector('[data-n="' + k + '"]').textContent = n; };
        if (rows.length === 1) { set('price', fmt(rows[0].dataset.oldPrice, 2), fmt(rows[0].dataset.np, 2)); } else { form.querySelector('[data-single]').style.display = 'none'; }
        set('qty', Number(oldQ).toLocaleString('en'), Number(newQ).toLocaleString('en')); set('value', fmt(oldV, 0), fmt(newV, 0));
        set('margin', oldM.toFixed(1) + '%', newM.toFixed(1) + '%'); set('marginNow', oldNowM.toFixed(1) + '%', nowM.toFixed(1) + '%');
        var v = form.querySelector('[data-verdict]'), under = nowM < floor || newM < floor;
        v.className = 'rv-verdict ' + (under ? 'under' : 'ok');
        v.textContent = under ? 'Under the floor. ' + Math.min(newM, nowM).toFixed(1) + '% is below ' + floor + '%, so this one needs approval before it can be sent. The price that clears the floor is ' + fmt(floorPrice, 2) + '.'
            : 'Above the floor. ' + Math.min(newM, nowM).toFixed(1) + '% clears ' + floor + '%, so this one does not need approval. If the price went below ' + fmt(floorPrice, 2) + ' it would need approval before it could be sent.';
    }
    form.querySelectorAll('input[name=reason]').forEach(function (r) { r.addEventListener('change', function () {
        var want = r.dataset.suggest.split(','); form.querySelectorAll('input[name="changes[]"]').forEach(function (c) { c.checked = want.indexOf(c.value) !== -1; }); sync();
    }); });
    form.addEventListener('input', sync); form.addEventListener('change', sync); sync();
})();
</script>
@endsection
