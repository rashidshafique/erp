@extends('layouts.app')
@section('title', $q->code)
@section('breadcrumb')<a href="{{ route('enquiries.index') }}">Enquiries</a> / <a href="{{ route('enquiries.show', $q->enquiry) }}">{{ $q->enquiry->code }}</a> / {{ $q->code }} @endsection
@section('content')
<style>.ca-wrap td, .ca-wrap th { white-space: normal; vertical-align: top; } .num { text-align: right; } .q-in { width: 84px; } .bd td { padding: 2px 8px; font-size: 12.5px; }</style>
@php
    $qcls = ['DRAFT' => 'status-on-hold', 'ISSUED' => 'status-in-progress', 'ACCEPTED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'EXPIRED' => 'status-cancelled', 'SUPERSEDED' => 'status-cancelled'];
    $draft = $q->status === 'DRAFT';
    $n = fn ($v, $d = 2) => number_format((float) $v, $d);
    $total = $q->lines->sum(fn ($l) => $l->unit_price * $l->qty);
    $min = \App\Services\Quotation\Rates::get()['min_margin_pct'];
    $low = $q->lines->min('effective_margin_pct');
@endphp
<div class="page-heading">
    <div><h1>{{ $q->code }} <span class="muted" style="font-size:.6em">version {{ $q->version }}</span></h1>
    <p>{{ $q->enquiry->title }} · {{ $q->enquiry->customer?->name }} · <span class="status {{ $qcls[$q->status] ?? '' }}">{{ $q->status }}</span></p></div>
    <div class="actions">
        <a class="btn" href="{{ route('enquiries.show', $q->enquiry) }}">Back to enquiry</a>
        @if (! $draft)<a class="btn" href="{{ route('quotations.print', $q) }}" target="_blank">Customer copy</a>@endif
    </div>
</div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@if ($q->status === 'ACCEPTED' && $q->salesOrder)<div class="alert alert-success">Accepted. Sales order <a href="{{ route('sales-orders.show', $q->salesOrder) }}">{{ $q->salesOrder->order_number }}</a> was created.</div>@endif
@if ($q->status === 'EXPIRED')<div class="alert alert-error">This quotation passed its valid-until date. Create a new version from the enquiry and issue it again.</div>@endif

<div class="tab-marker" data-tab-start="Prices"></div>
<form method="POST" action="{{ route('quotations.update', $q) }}">@csrf @method('PUT')
<div class="panel ca-wrap"><div class="panel-header"><h2>Cost build-up and price</h2><span class="muted small">{{ $q->currency }} per casting</span></div>
<div class="table-wrap"><table>
<thead><tr><th>Part</th><th class="num">Qty</th><th class="num">Weight kg</th><th class="num">Poured kg</th><th class="num">Scrap %</th><th class="num">Cost</th><th class="num">Margin %</th><th class="num">Price override</th><th class="num">Price</th><th class="num">Effective margin</th></tr></thead>
<tbody>
@foreach ($q->lines as $l)
<tr>
    <td><strong>{{ $l->line_no }}. {{ $l->item->description }}</strong><div class="muted small">{{ $l->basis }}</div>@if ($toolingNotes[$l->id]['note'] ?? null)<div class="small {{ $toolingNotes[$l->id]['warning'] ? 'text-danger' : 'muted' }}">{{ $toolingNotes[$l->id]['note'] }}</div>@endif</td>
    <td class="num">@if ($draft)<input class="q-in" type="number" min="1" name="lines[{{ $l->id }}][qty]" value="{{ $l->qty }}">@else{{ $l->qty }}@endif</td>
    <td class="num">{{ $n($l->weight_kg, 2) }}</td><td class="num">{{ $n($l->poured_kg, 2) }}</td>
    <td class="num">@if ($draft)<input class="q-in" type="number" step="0.1" min="0" name="lines[{{ $l->id }}][scrap_pct]" value="{{ $l->scrap_pct }}">@else{{ $n($l->scrap_pct, 1) }}@endif</td>
    <td class="num">{{ $n($l->unit_cost) }}</td>
    <td class="num">@if ($draft)<input class="q-in" type="number" step="0.1" min="0" name="lines[{{ $l->id }}][margin_pct]" value="{{ $l->margin_pct }}">@else{{ $n($l->margin_pct, 1) }}@endif</td>
    <td class="num">@if ($draft)<input class="q-in" type="number" step="0.01" min="0" name="lines[{{ $l->id }}][price_override]" value="{{ $l->price_override }}" placeholder="{{ $n($l->breakdown['calculated_price'] ?? 0) }}">@else{{ $l->price_override ? $n($l->price_override) : '—' }}@endif</td>
    <td class="num"><strong>{{ $n($l->unit_price) }}</strong></td>
    <td class="num"><span class="{{ $l->effective_margin_pct < $min ? 'text-danger' : '' }}">{{ $n($l->effective_margin_pct, 1) }}%</span></td>
</tr>
<tr><td colspan="10"><details><summary class="small">Where the cost comes from</summary>
@php $b = $l->breakdown ?? []; $parts = ['metal' => 'Molten metal ('.$n($b['melt_rate'] ?? 0, 0).'/kg)', 'moulding' => 'Moulding', 'cores' => 'Cores', 'fettling' => 'Fettling', 'heat_treatment' => 'Heat treatment', 'testing' => 'Testing and NDT', 'packing' => 'Packing', 'overhead' => 'Overhead', 'machining' => 'Machining', 'other' => 'Other', 'scrap' => 'Scrap allowance', 'tooling' => 'Tooling spread']; @endphp
<table class="bd"><tbody>@foreach ($parts as $k => $lab)@if (($b[$k] ?? 0) != 0)<tr><td>{{ $lab }}</td><td class="num">{{ $n($b[$k]) }}</td></tr>@endif @endforeach
<tr><td><strong>Cost</strong></td><td class="num"><strong>{{ $n($l->unit_cost) }}</strong></td></tr>
<tr><td>Price from margin</td><td class="num">{{ $n($b['calculated_price'] ?? 0) }}</td></tr></tbody></table>
</details></td></tr>
@endforeach
</tbody>
<tfoot><tr><th colspan="8" class="num">Total for the quantities above</th><th class="num">{{ $n($total) }}</th><th></th></tr></tfoot>
</table></div></div>

<div class="panel"><div class="panel-header"><h2>Terms</h2></div><div class="panel-body">
@if ($draft)
<div class="form-grid">
    <div class="field"><label>Valid until</label><input type="date" name="valid_until" value="{{ old('valid_until', $q->valid_until?->toDateString()) }}"><div class="help">Blank: {{ config('quotation.validity_days') }} days from issue.</div></div>
    <div class="field"><label>Delivery, weeks from order</label><input type="number" min="1" name="delivery_weeks" value="{{ old('delivery_weeks', $q->delivery_weeks) }}"></div>
    <div class="field"><label>Payment terms</label><input name="payment_terms" value="{{ old('payment_terms', $q->payment_terms) }}"></div>
    <div class="field"><label>Tooling</label><select name="tooling_mode"><option value="NONE" @selected($q->tooling_mode === 'NONE')>Not charged (customer supplies, or absorbed)</option><option value="UPFRONT" @selected($q->tooling_mode === 'UPFRONT')>Charged up front, separate invoice</option><option value="AMORTISED" @selected($q->tooling_mode === 'AMORTISED')>Spread over the castings</option></select></div>
    <div class="field"><label>Castings to spread tooling over</label><input type="number" min="1" name="tooling_amortise_qty" value="{{ old('tooling_amortise_qty', $q->tooling_amortise_qty) }}"></div>
    <div class="field field-full"><label>Notes for the customer</label><textarea name="notes" rows="3">{{ old('notes', $q->notes) }}</textarea></div>
</div>
<p><button class="btn btn-primary" type="submit">Save and recalculate</button></p>
@else
<p>Valid until {{ $q->valid_until?->format('d M Y') }} · delivery {{ $q->delivery_weeks ? $q->delivery_weeks.' weeks' : 'to be agreed' }} · {{ $q->payment_terms ?: 'payment terms not stated' }} · tooling {{ ['NONE' => 'not charged', 'UPFRONT' => 'charged up front ('.$n($q->upfrontToolingPrice()).')', 'AMORTISED' => 'spread over '.$q->tooling_amortise_qty.' castings'][$q->tooling_mode] }}</p>
@if ($q->notes)<p>{{ $q->notes }}</p>@endif
@endif
</div></div>
</form>

<div class="tab-marker" data-tab-start="Decision"></div>
@if ($draft)
<div class="panel"><div class="panel-header"><h2>Before it can go out</h2></div><div class="panel-body">
@if ($blockers)<ul>@foreach ($blockers as $b)<li>{{ $b }}</li>@endforeach</ul>@else<p>Every part is assessed and priced.</p>@endif
@if ($low < $min)<p class="muted">The lowest margin is {{ $n($low, 1) }}%, below the {{ $n($min, 1) }}% minimum. Issuing will ask for approval if that approval policy is switched on.</p>@endif
<div style="display:flex;gap:8px;flex-wrap:wrap">
<form method="POST" action="{{ route('quotations.refresh', $q) }}">@csrf<button class="btn" type="submit">Re-read the enquiry</button></form>
<form method="POST" action="{{ route('quotations.issue', $q) }}" onsubmit="return confirm('Issue this quotation to the customer?')">@csrf<button class="btn btn-primary" type="submit" @disabled($blockers)>Issue quotation</button></form>
</div></div></div>
@elseif ($q->status === 'ISSUED')
<div class="panel"><div class="panel-header"><h2>Customer's answer</h2></div><div class="panel-body">
<p>Issued {{ $q->issued_at?->format('d M Y') }} by {{ $q->issued_by_name }}. Accepting creates the product and a draft revision if they do not exist, and a draft sales order priced from this quotation.</p>
<div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
<form method="POST" action="{{ route('quotations.accept', $q) }}" onsubmit="return confirm('Customer accepted? This creates a draft sales order.')">@csrf<button class="btn btn-primary" type="submit">Customer accepted</button></form>
<form method="POST" action="{{ route('quotations.reject', $q) }}" style="display:flex;gap:6px">@csrf<input name="decision_note" placeholder="Why (optional)"><button class="btn btn-danger" type="submit">Customer declined</button></form>
</div></div></div>
@else
<div class="panel"><div class="panel-body"><p>{{ $q->status }}{{ $q->decided_at ? ' on '.$q->decided_at->format('d M Y') : '' }}{{ $q->decision_note ? ' — '.$q->decision_note : '' }}</p></div></div>
@endif

<div class="tab-marker" data-tab-start="Versions & history"></div>
<div class="panel"><div class="panel-header"><h2>Versions</h2></div><div class="table-wrap"><table><thead><tr><th>Quotation</th><th>Version</th><th>Status</th></tr></thead><tbody>
@foreach ($versions as $v)<tr><td><a href="{{ route('quotations.show', $v->id) }}">{{ $v->code }}</a></td><td>{{ $v->version }}</td><td>{{ $v->status }}</td></tr>@endforeach
</tbody></table></div></div>
@include('audit._history', ['type' => 'Quotation', 'id' => $q->id])
@endsection
