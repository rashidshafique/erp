@extends('layouts.app')

@section('title', $order->order_number)
@section('breadcrumb', 'Sales orders / '.$order->order_number)

@section('content')
@php
    use App\Domains\Sales\Models\SalesOrder;
    $reviewLight = null;
    if ($review) {
        $reviewBlocks = $review->items->where('severity', 'BLOCK')->count();
        $reviewWarns = $review->items->where('severity', 'WARN')->whereNull('acknowledgement')->count();
        $reviewLight = $reviewBlocks > 0
            ? ['#d93025', '217,48,37', $reviewBlocks.' blocked in the contract review']
            : ($reviewWarns > 0
                ? ['#f29900', '242,153,0', $reviewWarns.' '.($reviewWarns === 1 ? 'warning' : 'warnings').' in the contract review']
                : ($review->status === 'APPROVED'
                    ? ['#1e8e3e', '30,142,62', \App\Support\OrderRules::reviewsAutomatically() ? 'Contract review approved' : 'Contract review approved by sales and quality']
                    : ['#1e8e3e', '30,142,62', 'Contract review is clear, waiting for sign-off by '.collect(['sales' => $review->sales_signed_at, 'quality' => $review->quality_signed_at])->filter(fn ($signed) => ! $signed)->keys()->implode(' and '), true]));
    }
    $tones = ['DRAFT' => 'el-grey', 'UNDER_REVIEW' => 'el-warn', 'CONFIRMED' => 'el-blue', 'IN_PRODUCTION' => 'el-blue', 'ON_HOLD' => 'el-bad', 'DISPATCHED' => 'el-ok', 'CANCELLED' => 'el-grey'];
    $orderValue = $order->lines->sum(fn ($l) => $l->netValue((float) $l->ordered_qty));
@endphp
@include('sales::orders._record-styles')
<div class="el-card" style="margin-bottom:16px">
    <div class="el-bar">
        <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);flex-wrap:wrap">
            <a href="{{ route('sales-orders.index') }}" style="color:inherit">Sales orders</a><span>/</span><code style="color:var(--text)">{{ $order->order_number }}</code>
            <span class="el-pill {{ $tones[$order->status] ?? '' }}" style="margin-left:6px">{{ SalesOrder::STATUSES[$order->status] ?? str_replace('_', ' ', $order->status) }}</span>
            @if ($reviewLight)<span class="heat-light" title="{{ $reviewLight[2] }}" aria-label="{{ $reviewLight[2] }}" style="--hl:{{ $reviewLight[0] }};--hl-rgb:{{ $reviewLight[1] }};margin-left:4px;{{ ($reviewLight[3] ?? false) ? 'opacity:.4;box-shadow:none' : '' }}"></span>@endif
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            @if (in_array($order->status, SalesOrder::OPEN, true))<a class="el-btn" href="{{ route('sales-orders.edit', $order) }}">Edit</a>@endif
            <a class="el-btn" href="{{ route('sales-orders.trace', $order) }}">Trace to dispatch</a>
            @if (Route::has('invoices.create'))<a class="el-btn" href="{{ route('invoices.create', ['order' => $order->id]) }}">Raise invoice</a>@endif
            @php $changes = app(\App\Domains\Sales\Services\SalesOrderStatus::class)->allowedFrom($order); @endphp
            @if ($changes)
                <details style="display:inline-block"><summary class="el-btn">Change status</summary>
                    <form method="POST" action="{{ route('sales-orders.status', $order) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:6px">@csrf
                        <select name="status" id="status-change" aria-label="New status">@foreach ($changes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                        <label id="release-all" hidden><input type="hidden" name="release_all" value="0"><input type="checkbox" name="release_all" value="1" checked> Create and release all production orders</label>
                        <input name="reason" id="cancel-reason" maxlength="300" placeholder="Reason for cancelling" aria-label="Reason for cancelling" hidden>
                        <button class="el-btn primary" type="submit">Apply</button>
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
            @php $canRelease = in_array($order->status, ['DRAFT', 'UNDER_REVIEW', 'CONFIRMED'], true) && $order->lines->contains(fn ($l) => $l->fromPieces((float) $l->productionOrders->where('status', '!=', 'CANCELLED')->sum('planned_qty')) < (float) $l->ordered_qty - 0.0005); @endphp
            @if ($canRelease)
                <form method="POST" action="{{ route('sales-orders.approve-release', $order) }}" style="display:inline">@csrf
                    @unless (auth()->user())<input name="name" placeholder="Your name" required maxlength="100" aria-label="Your name">@endunless
                    <button class="el-btn primary" type="submit" title="{{ \App\Support\OrderRules::reviewRequired() ? 'Runs the review if needed, signs for you, makes the order live and releases a production order per line' : 'Makes the order live and releases a production order per line' }}">{{ \App\Support\OrderRules::reviewRequired() ? 'Approve & release' : 'Release' }}</button></form>
            @endif
            @unless ($canRelease)
            <form method="POST" action="{{ route('sales-orders.review', $order) }}" style="display:inline">@csrf
                <button class="el-btn primary" type="submit">{{ $review ? 'Re-run contract review' : 'Run contract review' }}</button></form>
            @endunless
        </div>
    </div>
    <div class="el-facts">
        <div><div class="el-sub">Customer</div><div style="font-weight:500">{{ $order->customer?->name }}</div></div>
        <div><div class="el-sub">Customer PO</div>@if ($order->customer_reference)<code>{{ $order->customer_reference }}</code>@else<div>—</div>@endif</div>
        <div><div class="el-sub">Order date</div><div>{{ $order->order_date?->displayDate() }}</div></div>
        <div><div class="el-sub">Requested delivery</div><div>{{ $order->requested_delivery_date?->displayDate() ?? '—' }}</div></div>
        <div><div class="el-sub">Order value</div><div>{{ \App\Support\Formats::withCurrency(\App\Support\Formats::money($orderValue)) }}</div></div>
        <div><div class="el-sub">Lines</div><div>{{ $order->lines->count() }}</div></div>
    </div>
</div>

@if ($review && $order->changedSince($review))
    <form method="POST" action="{{ route('sales-orders.review', $order) }}">@csrf
        <button type="submit" class="alert alert-warning" style="display:block;width:100%;text-align:left;font:inherit;cursor:pointer" title="Re-run the contract review">This order was changed after the last contract review ({{ $review->evaluated_at->displayDateTime() }}). <strong>Click here to re-run the review.</strong></button></form>
@endif

@php $reviewBlocksProduction = \App\Support\OrderRules::reviewRequired() && ! $order->hasApprovedReview(); @endphp
<div class="tab-marker" data-tab-start="Lines"></div>
<div class="el-card">
    <div class="el-bar"><span style="font-weight:600;font-size:13px">Lines and delivery schedule</span></div>
    <div class="el-table" style="margin:0;padding:8px 16px"><table>
        <thead><tr><th>#</th><th>Product</th><th>Rev</th><th>Ordered</th><th>Scheduled</th><th>Shipped</th><th>Production orders</th><th style="text-align:right"><button type="button" id="lines-toggle-all" data-state="open" title="Collapse all" aria-label="Collapse or expand all lines" style="border:0;background:none;padding:0 4px;cursor:pointer;color:inherit;font-size:20px;line-height:1">&#9662;</button></th></tr></thead>
        <tbody>
        @php \App\Models\Attachment::primeCovers('product', $order->lines->pluck('product_id')->all()); @endphp
        @foreach ($order->lines as $l)
            @php $isOpen = in_array($order->status, \App\Domains\Sales\Models\SalesOrder::OPEN, true); @endphp
            @php $sched = (float) $l->productionOrders->where('status', '!=', 'CANCELLED')->sum('planned_qty'); $kgUnit = $l->isSoldByKg() ? ' kg' : ''; @endphp
            <tr style="cursor:pointer">
                <td>{{ $l->line_number }}</td>
                <td><x-product-cell :product="$l->product" /></td>
                <td>{{ $l->productRevision?->revision }}</td>
                <td>{{ rtrim(rtrim(\App\Support\Formats::number((float) $l->ordered_qty, 3), '0'), '.') }}{{ $kgUnit }}
                    @if ($l->repeats())<br><span class="muted small">{{ rtrim(rtrim(\App\Support\Formats::number((float) $l->repeat_qty, 3), '0'), '.') }}/{{ $l->repeat_every === 'MONTH' ? 'month' : 'week' }}@if ($l->repeat_until) till {{ $l->repeat_until->displayDate() }}@endif · {{ ($next = $l->nextRepeatDate()) ? 'next '.$next->displayDate() : 'ended' }}</span>@endif</td>
                <td>{{ rtrim(rtrim(\App\Support\Formats::number($sched, 3), '0'), '.') }}@if ($kgUnit) pcs<br><span class="muted small">≈ {{ rtrim(rtrim(\App\Support\Formats::number($l->fromPieces($sched), 3), '0'), '.') ?: '0' }} kg</span>@endif</td>
                <td>{{ rtrim(rtrim(\App\Support\Formats::number((float) ($shipped[$l->id] ?? 0), 3), '0'), '.') ?: '0' }}{{ $kgUnit }}</td>
                <td>@forelse ($l->productionOrders as $po)<a href="{{ route('production-orders.show', $po) }}" class="mono">{{ $po->production_order_number }}</a> <span class="muted small">{{ strtolower(str_replace('_', ' ', $po->status)) }}</span>@if (! $loop->last)<br>@endif @empty — @endforelse</td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;justify-content:flex-end">
                    @if ($l->fromPieces($sched) < (float) $l->ordered_qty - 0.0005)
                        <form method="POST" action="{{ route('sales-orders.lines.production-order', [$order, $l]) }}">@csrf
                            <button class="btn @if ($reviewBlocksProduction) btn-review-blocked @endif" type="submit" @disabled($reviewBlocksProduction) @if ($reviewBlocksProduction) title="Needs an approved contract review: sales and quality must both sign it off" @endif>+ Production Order</button></form>
                    @endif
                    <button type="button" class="btn" data-quick="qd-shipping-{{ $l->id }}" title="Partial shipments and the fewest pieces to send">Shipping rule {!! ($l->min_ship_qty || $l->partial_shipments !== null) ? '<span class="muted small">· set</span>' : '' !!}</button>
                    <dialog id="qd-shipping-{{ $l->id }}" class="qd" aria-labelledby="qd-shipping-{{ $l->id }}-title">
                        <form method="POST" action="{{ route('sales-orders.lines.shipping', [$order, $l]) }}">@csrf @method('PUT')
                            <div class="qd-head"><h2 id="qd-shipping-{{ $l->id }}-title">Shipping rule · line {{ $l->line_number }}, {{ $l->product?->name }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
                            <div class="qd-body"><div class="qd-fields">
                                <div class="field"><label>Partial shipments</label><select name="partial_shipments">
                                    <option value="" @selected($l->partial_shipments === null)>Customer's setting ({{ $order->customer?->allows_partial_shipments ? 'allowed' : 'not allowed' }})</option>
                                    <option value="yes" @selected($l->partial_shipments === true)>Allowed on this line</option>
                                    <option value="no" @selected($l->partial_shipments === false)>Not allowed on this line</option>
                                </select><div class="help">Allowed: an open NCR holds only its own pieces and the accepted ones can ship. Not allowed: it holds the whole lot.</div></div>
                                <div class="field"><label>Fewest pieces to ship</label><input name="min_ship_qty" type="number" min="1" step="1" value="{{ $l->min_ship_qty }}" placeholder="none"><div class="help">A lot waits until this many pieces can ship. Never more than what the line still has to receive.</div></div>
                            </div></div>
                            <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
                        </form>
                    </dialog>
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
                        @php $upcoming = $l->repeats() ? $l->deliveries->filter(fn ($d) => $d->due_date->gte(today())) : collect(); @endphp
                        @if ($upcoming->isNotEmpty())
                            @php $fid = 'qd-forecast-'.$l->id; $fail = old('_quick') === $fid && $errors->any(); @endphp
                            <button type="button" class="btn" data-quick="{{ $fid }}">Change forecast</button>
                            <dialog id="{{ $fid }}" class="qd" aria-labelledby="{{ $fid }}-title" data-forecast @if ($fail) data-reopen="{{ json_encode($errors->getMessages()) }}" @endif>
                                <form method="POST" action="{{ route('sales-orders.lines.forecast', [$order, $l]) }}">@csrf @method('PUT')<input type="hidden" name="_quick" value="{{ $fid }}">
                                    <div class="qd-head"><h2 id="{{ $fid }}-title">Forecast · line {{ $l->line_number }}, {{ $l->product?->name }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
                                    <div class="qd-body">
                                        <div class="alert alert-error qd-errors" role="alert" @unless ($fail) hidden @endunless>@if ($fail)@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach @endif</div>
                                        <p class="help" style="margin-top:0">Set each future period's quantity. 0 removes it. Past periods stay.</p>
                                        <table>
                                            <thead><tr><th>Due</th><th style="text-align:right">Quantity</th></tr></thead>
                                            <tbody>
                                            @foreach ($upcoming as $d)
                                                <tr><td>{{ $d->due_date->displayDate() }}</td>
                                                    <td style="text-align:right"><input name="quantities[{{ $d->id }}]" type="number" step="any" min="0" value="{{ old('quantities.'.$d->id, rtrim(rtrim(number_format((float) $d->quantity, 3, '.', ''), '0'), '.')) }}" aria-label="Quantity due {{ $d->due_date->displayDate() }}" style="width:120px;text-align:right" required></td></tr>
                                            @endforeach
                                            </tbody>
                                            <tfoot><tr><th>Line total</th><th style="text-align:right" data-forecast-total data-fixed="{{ (float) $l->ordered_qty - (float) $upcoming->sum('quantity') }}">{{ rtrim(rtrim(\App\Support\Formats::number((float) $l->ordered_qty, 3), '0'), '.') }}</th></tr></tfoot>
                                        </table>
                                    </div>
                                    <div class="qd-foot">@if ($fail && $errors->has('confirm_jump'))<label style="margin:0 auto 0 0;align-self:center"><input type="checkbox" name="confirm_jump" value="1"> Yes, these quantities are right</label>@endif<button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save forecast</button></div>
                                </form>
                            </dialog>
                        @endif
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
        document.addEventListener('input', function (e) {
            var dlg = e.target.closest('dialog[data-forecast]');
            if (! dlg) { return; }
            var cell = dlg.querySelector('[data-forecast-total]'), total = parseFloat(cell.dataset.fixed) || 0;
            dlg.querySelectorAll('input[name^="quantities"]').forEach(function (i) { total += parseFloat(i.value) || 0; });
            cell.textContent = total.toLocaleString(undefined, { maximumFractionDigits: 3 });
        });
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
    @if ($reviewBlocksProduction)<div class="el-note" style="padding:0 28px 16px">Production orders can be created once the contract review is approved{{ \App\Support\OrderRules::reviewsAutomatically() ? '' : ' by both sales and quality' }}.</div>@endif
</div>

<div class="tab-marker" data-tab-start="Contract review" data-tab-warn="{{ $review ? $review->items->where('severity', 'WARN')->whereNull('acknowledgement')->count() : 0 }}" data-tab-block="{{ $review ? $review->items->where('severity', 'BLOCK')->count() : 0 }}"></div>
<div class="el-card" style="margin-top:16px">
    <div class="el-bar">
        <span style="font-weight:600;font-size:13px">Contract review{{ $review ? ' '.$review->review_number : '' }}</span>
        @if ($canRelease)<form method="POST" action="{{ route('sales-orders.review', $order) }}" style="margin-left:auto">@csrf<button class="el-btn" type="submit">{{ $review ? 'Re-run review' : 'Run review' }}</button></form>@endif
        @if ($review)@php $openWarns = $review->items->where('severity', 'WARN')->whereNull('acknowledgement')->count(); $nBlocks = $review->items->where('severity', 'BLOCK')->count(); @endphp
            <span style="display:flex;gap:6px;align-items:center">@if ($nBlocks > 0)<span class="el-pill el-bad">{{ $nBlocks }} to fix</span>@endif @if ($openWarns > 0)<span class="el-pill el-warn">{{ $openWarns }} {{ $openWarns === 1 ? 'note' : 'notes' }} needed</span>@endif<span class="el-pill {{ $review->status === 'APPROVED' ? 'el-ok' : ($review->verdict === 'BLOCK' ? 'el-bad' : 'el-warn') }}">{{ $review->status }} · {{ $review->verdict }}</span></span>
        @endif
    </div>
    @if (! $review)
        <div class="empty-state">No review yet. Run it to check specs, requirements, grade, furnaces, thermal window, pattern and control plan.</div>
    @else
        @php
            $needs = $review->items->where('severity', '!=', 'PASS')->sortBy(fn ($i) => [['BLOCK' => 0, 'WARN' => 1][$i->severity], $i->line?->line_number ?? 0]);
            $passed = $review->items->where('severity', 'PASS')->count();
        @endphp
        <div class="el-grid" style="grid-template-columns:minmax(0,1fr) 340px">
            <div class="el-main">
                <div class="el-h">Only what needs you</div>
                @forelse ($needs as $i)
                    <div class="el-todo {{ $i->severity === 'BLOCK' ? 'bad' : '' }}">
                        <div>
                            <div class="el-todo-t">{{ $i->line ? 'Line '.$i->line->line_number.' · ' : '' }}{{ ucfirst(strtolower(str_replace('_', ' ', $i->check_code))) }}</div>
                            <div class="el-todo-m">{{ $i->message }}</div>
                            @foreach ($i->fixLinks() as $fix)<div><a href="{{ $fix['url'] }}">{{ $fix['label'] }} →</a></div>@endforeach
                        </div>
                        <div>
                            @if ($i->severity === 'WARN')
                                @if ($i->acknowledgement)<span class="el-note">Accepted: {{ $i->acknowledgement }}</span>
                                @elseif ($review->status === 'IN_REVIEW')
                                    <form method="POST" action="{{ route('sales-orders.review.acknowledge', $order) }}" style="display:flex;gap:6px">@csrf
                                        <input type="hidden" name="item_id" value="{{ $i->id }}">
                                        <input name="reason" placeholder="Why this is acceptable" required minlength="5" maxlength="500" style="width:240px">
                                        <button class="el-btn" type="submit">Acknowledge</button></form>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="el-note">Nothing needs you.</div>
                @endforelse
                <details>
                    <summary class="el-note" style="cursor:pointer">All {{ $review->items->count() }} checks ({{ $passed }} passed)</summary>
                    <div class="el-table"><table>
                        <thead><tr><th>Line</th><th>Check</th><th>Result</th><th>Finding</th></tr></thead>
                        <tbody>
                        @foreach ($review->items->sortBy(fn ($i) => [$i->line?->line_number ?? 0, ['BLOCK' => 0, 'WARN' => 1, 'PASS' => 2][$i->severity]]) as $i)
                            <tr style="cursor:default">
                                <td>@if($i->line)<x-product-cell :product="$i->line->product" /><div class="el-sub">Line {{ $i->line->line_number }}</div>@else—@endif</td>
                                <td>{{ str_replace('_', ' ', $i->check_code) }}</td>
                                <td><span class="el-pill {{ $i->severity === 'PASS' ? 'el-ok' : ($i->severity === 'WARN' ? 'el-warn' : 'el-bad') }}">{{ $i->severity }}</span></td>
                                <td style="white-space:normal;min-width:260px">{{ $i->message }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </details>
            </div>
            <aside class="el-side">
                <div class="el-h">Sign-off</div>
                @foreach (\App\Support\OrderRules::reviewsAutomatically() && ! $review->quality_signed_at ? ['sales' => 'Approval'] : ['sales' => 'Sales', 'quality' => 'Quality'] as $role => $label)
                    <div class="el-row" style="align-items:center">
                        <span>{{ $label }}</span>
                        <span>
                        @if ($review->{$role.'_signed_at'})
                            <span style="color:#1d7048">✓ {{ $review->{$role.'_signed_by'} }}</span><br><span class="el-note">{{ $review->{$role.'_signed_at'}->displayDateTime() }}</span>
                        @elseif ($review->status === 'IN_REVIEW' && auth()->user() && (int) $review->{($role === 'sales' ? 'quality' : 'sales').'_signed_by_user_id'} === (int) auth()->id())
                            <span class="el-note">Needs someone other than {{ auth()->user()->name }}, who signed as {{ $role === 'sales' ? 'quality' : 'sales' }}. Working alone? Under <a href="{{ route('settings.rules').'#orders' }}">Settings &gt; Process rules</a>, set contract review to "Only when something is off".</span>
                        @elseif ($review->status === 'IN_REVIEW')
                            <form method="POST" action="{{ route('sales-orders.review.sign', $order) }}">@csrf
                                <input type="hidden" name="role" value="{{ $role }}">
                                @if (auth()->user())<input type="hidden" name="name" value="{{ auth()->user()->name }}">@else<input name="name" placeholder="Your name" required maxlength="100">@endif
                                <button class="el-btn primary" type="submit">{{ $label === 'Approval' ? 'Approve' : 'Sign as '.strtolower($label) }}{{ auth()->user() ? ' ('.auth()->user()->name.')' : '' }}</button></form>
                        @else
                            <span class="el-note">Not signed</span>
                        @endif
                        </span>
                    </div>
                @endforeach
                <div style="padding-top:14px;border-top:1px solid var(--border)"><div class="el-h">After approval</div><div style="line-height:1.6;margin-top:8px">1. The order can go live<br>2. Each line gets a production order<br>3. They reach the planner</div></div>
            </aside>
        </div>
    @endif
</div>
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('partials.search-select')
@include('custom-fields._panel', ['entity' => 'sales_order', 'id' => $order->id])
@include('attachments._panel', ['type' => 'sales_order', 'id' => $order->id])
@include('audit._history', ['type' => 'SalesOrder', 'id' => $order->id])
@endsection
