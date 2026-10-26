@extends('layouts.app')

@section('title', $order->order_number)
@section('breadcrumb', 'Sales orders / '.$order->order_number)

@section('content')
<div class="page-heading">
    @php
    $reviewLight = null;
    if ($review) {
        $reviewBlocks = $review->items->where('severity', 'BLOCK')->count();
        $reviewWarns = $review->items->where('severity', 'WARN')->whereNull('acknowledgement')->count();
        $reviewLight = $reviewBlocks > 0
            ? ['#d93025', '217,48,37', $reviewBlocks.' blocked in the contract review']
            : ($reviewWarns > 0
                ? ['#f29900', '242,153,0', $reviewWarns.' '.($reviewWarns === 1 ? 'warning' : 'warnings').' in the contract review']
                : ($review->status === 'APPROVED'
                    ? ['#1e8e3e', '30,142,62', 'Contract review approved by sales and quality']
                    : ['#1e8e3e', '30,142,62', 'Contract review is clear, waiting for sign-off by '.collect(['sales' => $review->sales_signed_at, 'quality' => $review->quality_signed_at])->filter(fn ($signed) => ! $signed)->keys()->implode(' and '), true]));
    }
@endphp
<div><h1>{{ $order->order_number }}@if ($reviewLight)<span class="heat-light" title="{{ $reviewLight[2] }}" aria-label="{{ $reviewLight[2] }}" style="--hl:{{ $reviewLight[0] }};--hl-rgb:{{ $reviewLight[1] }};margin-left:8px;{{ ($reviewLight[3] ?? false) ? 'opacity:.4;box-shadow:none' : '' }}"></span>@endif</h1><p>{{ $order->customer?->code }} — {{ $order->customer?->name }}</p></div>
    <div class="actions">
        @if (in_array($order->status, \App\Domains\Sales\Models\SalesOrder::OPEN, true))<a class="btn" href="{{ route('sales-orders.edit', $order) }}">Edit</a>@endif
        <a class="btn" href="{{ route('sales-orders.trace', $order) }}">Trace to dispatch</a>
        @if (Route::has('invoices.create'))<a class="btn" href="{{ route('invoices.create', ['order' => $order->id]) }}">Raise invoice</a>@endif
        @php $changes = app(\App\Domains\Sales\Services\SalesOrderStatus::class)->allowedFrom($order); @endphp
        @if ($changes)
            <details style="display:inline-block"><summary class="btn">Change status</summary>
                <form method="POST" action="{{ route('sales-orders.status', $order) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:6px">@csrf
                    <select name="status" id="status-change" aria-label="New status">@foreach ($changes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    <label id="release-all" hidden><input type="hidden" name="release_all" value="0"><input type="checkbox" name="release_all" value="1" checked> Create and release all production orders</label>
                    <input name="reason" id="cancel-reason" maxlength="300" placeholder="Reason for cancelling" aria-label="Reason for cancelling" hidden>
                    <button class="btn btn-primary" type="submit">Apply</button>
                </form></details>
            <script>
                (function () {
                    var select = document.getElementById('status-change');
                    function sync() {
                        document.getElementById('release-all').hidden = select.value !== 'IN_PRODUCTION';
                        document.getElementById('cancel-reason').hidden = select.value !== 'CANCELLED';
                    }
                    select.addEventListener('change', sync); sync();
                })();
            </script>
        @endif
        <form method="POST" action="{{ route('sales-orders.review', $order) }}" style="display:inline">@csrf
            <button class="btn btn-primary" type="submit">{{ $review ? 'Re-run contract review' : 'Run contract review' }}</button></form>
    </div>
</div>


<div class="metrics">
    <div class="metric"><div class="metric-label">Status</div><div class="metric-value" style="font-size:21px">{{ str_replace('_', ' ', $order->status) }}</div></div>
    <div class="metric"><div class="metric-label">Order date</div><div class="metric-value" style="font-size:21px">{{ $order->order_date?->displayDate() }}</div></div>
    <div class="metric"><div class="metric-label">Requested delivery</div><div class="metric-value" style="font-size:21px">{{ $order->requested_delivery_date?->displayDate() ?? '—' }}</div></div>
    <div class="metric"><div class="metric-label">Customer reference</div><div class="metric-value" style="font-size:21px">{{ $order->customer_reference ?: '—' }}</div></div>
</div>

@if ($review && $order->changedSince($review))
    <form method="POST" action="{{ route('sales-orders.review', $order) }}">@csrf
        <button type="submit" class="alert alert-warning" style="display:block;width:100%;text-align:left;font:inherit;cursor:pointer" title="Re-run the contract review">This order was changed after the last contract review ({{ $review->evaluated_at->displayDateTime() }}). <strong>Click here to re-run the review.</strong></button></form>
@endif

@php $reviewBlocksProduction = \App\Support\OrderRules::reviewRequired() && ! $order->hasApprovedReview(); @endphp
<div class="tab-marker" data-tab-start="Lines"></div>
<div class="panel">
    <div class="panel-header"><h2>Lines and delivery schedule</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Product</th><th>Rev</th><th>Ordered</th><th>Scheduled</th><th>Shipped</th><th>Production orders</th><th style="text-align:right"><button type="button" id="lines-toggle-all" data-state="open" title="Collapse all" aria-label="Collapse or expand all lines" style="border:0;background:none;padding:0 4px;cursor:pointer;color:inherit;font-size:20px;line-height:1">&#9662;</button></th></tr></thead>
        <tbody>
        @foreach ($order->lines as $l)
            @php $isOpen = in_array($order->status, \App\Domains\Sales\Models\SalesOrder::OPEN, true); @endphp
            @php $sched = (float) $l->productionOrders->where('status', '!=', 'CANCELLED')->sum('planned_qty'); @endphp
            <tr style="cursor:pointer">
                <td>{{ $l->line_number }}</td>
                <td><x-product-cell :product="$l->product" /></td>
                <td>{{ $l->productRevision?->revision }}</td>
                <td>{{ rtrim(rtrim(\App\Support\Formats::number((float) $l->ordered_qty, 3), '0'), '.') }}
                    @if ($l->repeats())<br><span class="muted small">{{ rtrim(rtrim(\App\Support\Formats::number((float) $l->repeat_qty, 3), '0'), '.') }}/{{ $l->repeat_every === 'MONTH' ? 'month' : 'week' }}@if ($l->repeat_until) till {{ $l->repeat_until->displayDate() }}@endif · {{ ($next = $l->nextRepeatDate()) ? 'next '.$next->displayDate() : 'ended' }}</span>@endif</td>
                <td>{{ rtrim(rtrim(\App\Support\Formats::number($sched, 3), '0'), '.') }}</td>
                <td>{{ rtrim(rtrim(\App\Support\Formats::number((float) ($shipped[$l->id] ?? 0), 3), '0'), '.') ?: '0' }}</td>
                <td>@forelse ($l->productionOrders as $po)<a href="{{ route('production-orders.show', $po) }}" class="mono">{{ $po->production_order_number }}</a> <span class="muted small">{{ strtolower(str_replace('_', ' ', $po->status)) }}</span>@if (! $loop->last)<br>@endif @empty — @endforelse</td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;justify-content:flex-end">
                    @if ($sched < (float) $l->ordered_qty - 0.0005)
                        <form method="POST" action="{{ route('sales-orders.lines.production-order', [$order, $l]) }}">@csrf
                            <button class="btn" type="submit" @disabled($reviewBlocksProduction) @if ($reviewBlocksProduction) title="Needs an approved contract review: sales and quality must both sign it off" @endif>+ Production Order</button></form>
                    @endif
                    @if ($isOpen)
                        <button type="button" class="btn" data-quick="qd-delivery-{{ $l->id }}">+ Add delivery</button>
                        <dialog id="qd-delivery-{{ $l->id }}" class="qd" aria-labelledby="qd-delivery-{{ $l->id }}-title" @if (old('_quick') === 'qd-delivery-'.$l->id && $errors->has('quantity')) data-reopen="{{ json_encode($errors->getMessages()) }}" @endif>
                            <form method="POST" action="{{ route('sales-orders.lines.deliveries.store', [$order, $l]) }}">@csrf<input type="hidden" name="_quick" value="qd-delivery-{{ $l->id }}">
                                <div class="qd-head"><h2 id="qd-delivery-{{ $l->id }}-title">Add a delivery · line {{ $l->line_number }}, {{ $l->product?->name }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
                                <div class="qd-body">
                                    <div class="alert alert-error qd-errors" role="alert" @unless (old('_quick') === 'qd-delivery-'.$l->id && $errors->has('quantity')) hidden @endunless>@if (old('_quick') === 'qd-delivery-'.$l->id)@error('quantity')<div>{{ $message }}</div>@enderror @endif</div>
                                    <div class="qd-fields">
                                        @php $unscheduled = max(0, (float) $l->ordered_qty - (float) $l->deliveries->sum('quantity')); @endphp
                                        <div class="help">Ordered {{ rtrim(rtrim(\App\Support\Formats::number((float) $l->ordered_qty, 3), '0'), '.') }}; still to schedule: <strong>{{ rtrim(rtrim(\App\Support\Formats::number($unscheduled, 3), '0'), '.') ?: '0' }}</strong></div>
                                        <div class="field"><label>Due</label><input name="due_date" type="date" required></div>
                                        <div class="field"><label>Quantity</label><input name="quantity" type="number" step="any" min="0" max="{{ $unscheduled }}" value="{{ $unscheduled ?: '' }}" required></div>
                                        <div class="field"><label>Note</label><input name="notes" maxlength="200"></div>
                                    </div>
                                </div>
                                <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Add delivery</button></div>
                            </form>
                        </dialog>
                    @endif
                    <button type="button" data-toggle-line="{{ $l->id }}" aria-expanded="true" title="Show or hide the deliveries of this line" style="border:0;background:none;padding:0 4px;cursor:pointer;color:inherit;font-size:20px;line-height:1">&#9662;</button>
                    </div>
                </td>
            </tr>
            @foreach ($l->deliveryProgress((float) ($shipped[$l->id] ?? 0)) as $p)
                <tr class="muted" data-line-rows="{{ $l->id }}">
                    <td></td>
                    <td colspan="2">Deliver by {{ $p['delivery']->due_date->displayDate() }} @if ($p['overdue'])<span class="status status-cancelled">overdue</span>@endif</td>
                    <td>{{ rtrim(rtrim(\App\Support\Formats::number((float) $p['delivery']->quantity, 3), '0'), '.') }}</td>
                    <td></td>
                    <td>{{ rtrim(rtrim(\App\Support\Formats::number($p['covered'], 3), '0'), '.') ?: '0' }}</td>
                    <td>{{ $p['delivery']->notes }}</td>
                    <td>@if ($isOpen)<form method="POST" action="{{ route('sales-orders.lines.deliveries.destroy', [$order, $l, $p['delivery']]) }}">@csrf @method('DELETE')<button class="btn btn-small" type="submit">Remove</button></form>@endif</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table></div>
    @include('partials.quick-dialog-assets')
    <script>
    (function () {
        var all = document.getElementById('lines-toggle-all');
        function set(id, open) {
            document.querySelectorAll('[data-line-rows="' + id + '"]').forEach(function (row) { row.hidden = !open; });
            var b = document.querySelector('[data-toggle-line="' + id + '"]');
            if (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); b.innerHTML = open ? '&#9662;' : '&#9656;'; }
        }
        document.addEventListener('click', function (e) {
            var b = e.target.closest('[data-toggle-line]');
            if (b) { set(b.dataset.toggleLine, b.getAttribute('aria-expanded') !== 'true'); return; }
            if (e.target.closest('button, a, input, select, textarea, label, form, dialog')) { return; }
            var row = e.target.closest('tr');
            var rowToggle = row && row.querySelector('[data-toggle-line]');
            if (rowToggle && ! window.getSelection().toString()) { set(rowToggle.dataset.toggleLine, rowToggle.getAttribute('aria-expanded') !== 'true'); return; }
            if (e.target === all) {
                var open = all.dataset.state !== 'open';
                document.querySelectorAll('[data-toggle-line]').forEach(function (t) { set(t.dataset.toggleLine, open); });
                all.dataset.state = open ? 'open' : 'closed';
                all.innerHTML = open ? '&#9662;' : '&#9656;'; all.title = open ? 'Collapse all' : 'Expand all';
            }
        });
    })();
    </script>
    @if ($reviewBlocksProduction)<div class="panel-body muted small">Production orders can be created once the contract review is approved by both sales and quality.</div>@endif
</div>

<div class="tab-marker" data-tab-start="Contract review" data-tab-warn="{{ $review ? $review->items->where('severity', 'WARN')->whereNull('acknowledgement')->count() : 0 }}" data-tab-block="{{ $review ? $review->items->where('severity', 'BLOCK')->count() : 0 }}"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Contract review{{ $review ? ' '.$review->review_number : '' }}</h2>
        @if ($review)@php $openWarns = $review->items->where('severity', 'WARN')->whereNull('acknowledgement')->count(); @endphp<span>@if (($n = $review->items->where('severity', 'BLOCK')->count()) > 0)<span class="nav-count" style="float:none" title="Blocks">{{ $n }} blocked</span> @endif @if ($openWarns > 0)<span class="nav-count" style="float:none" title="Warnings not yet acknowledged">{{ $openWarns }} {{ $openWarns === 1 ? 'warning' : 'warnings' }}</span> @endif<span class="status {{ $review->status === 'APPROVED' ? 'status-completed' : ($review->verdict === 'BLOCK' ? 'status-cancelled' : 'status-on-hold') }}">{{ $review->status }} · {{ $review->verdict }}</span></span>@endif
    </div>
    @if (! $review)
        <div class="empty-state">No review yet. Run the contract review to check the order against specifications, customer requirements, grade, furnaces, thermal window, pattern and control plan.</div>
    @else
        <div class="panel-body">
            <div class="form-grid">
                @foreach (['sales' => 'Sales', 'quality' => 'Quality'] as $role => $label)
                    <div class="field">
                        <strong>{{ $label }} sign-off</strong>
                        @if ($review->{$role.'_signed_at'})
                            <div>{{ $review->{$role.'_signed_by'} }} · {{ $review->{$role.'_signed_at'}->displayDateTime() }}</div>
                        @elseif ($review->status === 'IN_REVIEW')
                            <form method="POST" action="{{ route('sales-orders.review.sign', $order) }}">@csrf
                                <input type="hidden" name="role" value="{{ $role }}">
                                <input name="name" placeholder="Your name" required maxlength="100">
                                <button class="btn btn-primary" type="submit">Sign as {{ strtolower($label) }}</button></form>
                        @else
                            <div class="muted small">Not signed.</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @php $blocks = $review->items->where('severity', 'BLOCK'); @endphp
        @if ($blocks->isNotEmpty())
            <div class="alert alert-error" style="margin:12px"><strong>Blocked. Fix these, then re-run the review:</strong><ul>@foreach ($blocks as $b)<li>{{ $b->message }}@foreach ($b->fixLinks() as $fix) <a href="{{ $fix['url'] }}" class="btn btn-small">{{ $fix['label'] }} →</a>@endforeach</li>@endforeach</ul></div>
        @endif
        <div class="table-wrap"><table>
            <thead><tr><th>Line</th><th>Check</th><th>Result</th><th>Finding</th><th>Acknowledgement</th></tr></thead>
            <tbody>
            @foreach ($review->items->sortBy(fn ($i) => [$i->line?->line_number ?? 0, ['BLOCK' => 0, 'WARN' => 1, 'PASS' => 2][$i->severity]]) as $i)
                <tr>
                    <td>@if($i->line)<x-product-cell :product="$i->line->product" /><div class="muted small">Line {{ $i->line->line_number }}</div>@else—@endif</td>
                    <td>{{ str_replace('_', ' ', $i->check_code) }}</td>
                    <td><span class="status {{ $i->severity === 'PASS' ? 'status-completed' : ($i->severity === 'WARN' ? 'status-on-hold' : 'status-cancelled') }}">{{ $i->severity }}</span></td>
                    <td style="white-space:normal;min-width:260px">{{ $i->message }}@foreach ($i->fixLinks() as $fix)<div><a href="{{ $fix['url'] }}">{{ $fix['label'] }} →</a></div>@endforeach</td>
                    <td style="white-space:normal;min-width:220px">
                        @if ($i->severity === 'WARN')
                            @if ($i->acknowledgement) {{ $i->acknowledgement }}
                            @elseif ($review->status === 'IN_REVIEW')
                                <form method="POST" action="{{ route('sales-orders.review.acknowledge', $order) }}">@csrf
                                    <input type="hidden" name="item_id" value="{{ $i->id }}">
                                    <input name="reason" placeholder="Why this is acceptable" required minlength="5" maxlength="500">
                                    <button class="btn" type="submit">Acknowledge</button></form>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('partials.search-select')
@include('custom-fields._panel', ['entity' => 'sales_order', 'id' => $order->id])
@include('attachments._panel', ['type' => 'sales_order', 'id' => $order->id])
@include('audit._history', ['type' => 'SalesOrder', 'id' => $order->id])
@endsection
