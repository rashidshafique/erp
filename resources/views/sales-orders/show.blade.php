@extends('layouts.app')

@section('title', $order->order_number)
@section('breadcrumb', 'Sales orders / '.$order->order_number)

@section('content')
<div class="page-heading">
    <div><h1>{{ $order->order_number }}</h1><p>{{ $order->customer?->code }} — {{ $order->customer?->name }}</p></div>
    <div class="actions">
        <a class="btn" href="{{ route('sales-orders.index') }}">All orders</a>
        <a class="btn" href="{{ route('sales-orders.trace', $order) }}">Trace to dispatch</a>
        @php $changes = app(\App\Services\Orders\SalesOrderStatus::class)->allowedFrom($order); @endphp
        @if ($changes)
            <details style="display:inline-block"><summary class="btn">Change status</summary>
                <form method="POST" action="{{ route('sales-orders.status', $order) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:6px">@csrf
                    <select name="status" id="status-change" aria-label="New status">@foreach ($changes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    <label id="release-all" hidden><input type="checkbox" name="release_all" value="1" checked> Create and release all production orders</label>
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

@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="metrics">
    <div class="metric"><div class="metric-label">Status</div><div class="metric-value" style="font-size:21px">{{ str_replace('_', ' ', $order->status) }}</div></div>
    <div class="metric"><div class="metric-label">Order date</div><div class="metric-value" style="font-size:21px">{{ $order->order_date?->displayDate() }}</div></div>
    <div class="metric"><div class="metric-label">Requested delivery</div><div class="metric-value" style="font-size:21px">{{ $order->requested_delivery_date?->displayDate() ?? '—' }}</div></div>
    <div class="metric"><div class="metric-label">Customer reference</div><div class="metric-value" style="font-size:21px">{{ $order->customer_reference ?: '—' }}</div></div>
</div>

<div class="tab-marker" data-tab-start="Lines"></div>
<div class="panel">
    <div class="panel-header"><h2>Lines</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Product</th><th>Rev</th><th>Ordered</th><th>Scheduled</th><th>Shipped</th><th>Production orders</th><th></th></tr></thead>
        <tbody>
        @foreach ($order->lines as $l)
            @php $sched = (float) $l->productionOrders->where('status', '!=', 'CANCELLED')->sum('planned_qty'); @endphp
            <tr>
                <td>{{ $l->line_number }}</td>
                <td><strong>{{ $l->product?->code }}</strong> {{ $l->product?->name }}</td>
                <td>{{ $l->productRevision?->revision }}</td>
                <td>{{ rtrim(rtrim(\App\Support\Formats::number((float) $l->ordered_qty, 3), '0'), '.') }}</td>
                <td>{{ rtrim(rtrim(\App\Support\Formats::number($sched, 3), '0'), '.') }}</td>
                <td>{{ rtrim(rtrim(\App\Support\Formats::number((float) ($shipped[$l->id] ?? 0), 3), '0'), '.') ?: '0' }}</td>
                <td>{{ $l->productionOrders->pluck('production_order_number')->implode(', ') ?: '—' }}</td>
                <td>
                    @if ($sched < (float) $l->ordered_qty - 0.0005)
                        <form method="POST" action="{{ route('sales-orders.lines.production-order', [$order, $l]) }}">@csrf
                            <button class="btn" type="submit" @disabled(! $order->hasApprovedReview())>Create production order</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @unless ($order->hasApprovedReview())<div class="panel-body muted small">Production orders can be created once the contract review is approved by both sales and quality.</div>@endunless
</div>

<div class="tab-marker" data-tab-start="Contract review"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Contract review{{ $review ? ' '.$review->review_number : '' }}</h2>
        @if ($review)<span class="status {{ $review->status === 'APPROVED' ? 'status-completed' : ($review->verdict === 'BLOCK' ? 'status-cancelled' : 'status-on-hold') }}">{{ $review->status }} · {{ $review->verdict }}</span>@endif
    </div>
    @if (! $review)
        <div class="empty-state">No review yet. Run the contract review to check the order against specifications, customer requirements, grade, furnaces, thermal window, pattern and control plan.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Line</th><th>Check</th><th>Result</th><th>Finding</th><th>Acknowledgement</th></tr></thead>
            <tbody>
            @foreach ($review->items->sortBy(fn ($i) => [$i->line?->line_number ?? 0, ['BLOCK' => 0, 'WARN' => 1, 'PASS' => 2][$i->severity]]) as $i)
                <tr>
                    <td>{{ $i->line?->line_number ?? '—' }}</td>
                    <td>{{ str_replace('_', ' ', $i->check_code) }}</td>
                    <td><span class="status {{ $i->severity === 'PASS' ? 'status-completed' : ($i->severity === 'WARN' ? 'status-on-hold' : 'status-cancelled') }}">{{ $i->severity }}</span></td>
                    <td style="white-space:normal;min-width:260px">{{ $i->message }}</td>
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
    @endif
</div>
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('custom-fields._panel', ['entity' => 'sales_order', 'id' => $order->id])
@include('attachments._panel', ['type' => 'sales_order', 'id' => $order->id])
@include('audit._history', ['type' => 'SalesOrder', 'id' => $order->id])
@endsection
