@extends('layouts.app')

@section('title', "Production Orders")
@section('breadcrumb', "Production Orders")

@push('styles')
<style>
.legacy-page .top,
.legacy-page .toolbar,
.legacy-page .metrics { display: flex; gap: 14px; justify-content: space-between; align-items: center; flex-wrap: wrap; }
.legacy-page .metrics { margin: 24px 0; }
.legacy-page .metric,
.legacy-page .panel { background: white; border: 1px solid #dce2e8; border-radius: 10px; padding: 18px; }
.legacy-page .metric { flex: 1; min-width: 130px; }
.legacy-page .number { display: block; font-size: 28px; font-weight: 700; margin-top: 8px; }
.legacy-page .muted { color: #647181; font-size: 13px; }
.legacy-page a.button,
.legacy-page button { background: var(--accent); color: white; border: 0; padding: 10px 14px; border-radius: 6px; text-decoration: none; cursor: pointer; font: inherit; }
.legacy-page input,
.legacy-page select { padding: 10px; border: 1px solid #ccd4dd; border-radius: 6px; background: white; }
.legacy-page .table-wrap { overflow-x: auto; }
.legacy-page table { border-collapse: collapse; width: 100%; min-width: 850px; }
.legacy-page th,
.legacy-page td { text-align: left; padding: 13px 10px; border-bottom: 1px solid #e5e9ee; }
.legacy-page th { font-size: 12px; text-transform: uppercase; color: #647181; }
.legacy-page .status { font-size: 12px; font-weight: 700; padding: 5px 8px; border-radius: 5px; background: #edf0f4; }
.legacy-page .success { background: #e3f6e9; color: #176b36; padding: 12px; border-radius: 6px; margin-bottom: 15px; }
.legacy-page .error { color: #a12626; margin-bottom: 12px; }
.legacy-page form.inline { display: inline; }
@media(max-width: 768px) {
.legacy-page .metric { min-width: 40%; }
}
</style>
@endpush

@section('content')
<div class="legacy-page">
<div class="legacy-main">
    <div class="top">
        <div>
            <h1>Production orders</h1>
            <p class="muted">Plan, release and monitor foundry production.</p>
        </div>
        <a class="button" href="{{ route('production-orders.create') }}">+ New order</a>
    </div>

    @if(session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="metrics">
        <div class="metric"><span class="muted">Draft</span><span class="number">{{ $counts['draft'] }}</span></div>
        <div class="metric"><span class="muted">Released</span><span class="number">{{ $counts['released'] }}</span></div>
        <div class="metric"><span class="muted">In progress</span><span class="number">{{ $counts['in_progress'] }}</span></div>
        <div class="metric"><span class="muted">Completed</span><span class="number">{{ $counts['completed'] }}</span></div>
    </section>

    <section class="panel">
        <form method="GET" action="{{ route('production-orders.index') }}" class="toolbar">
            <input type="search" name="search" value="{{ request('search') }}"
                   placeholder="Order number or product">
            <select name="status">
                <option value="">All statuses</option>
                @foreach(['DRAFT', 'RELEASED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>
                        {{ str_replace('_', ' ', $status) }}
                    </option>
                @endforeach
            </select>
            <button type="submit">Filter</button>
            <a href="{{ route('production-orders.index') }}">Clear</a>
        </form>

        <div class="table-wrap" style="margin-top:20px">
            <table>
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Product</th>
                    <th>Planned</th>
                    <th>Completed</th>
                    <th>Rejected</th>
                    <th>Required date</th>
                    <th>Priority</th>
                    <th>Status / action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>
                            <strong>{{ $order->production_order_number }}</strong>
                            <div class="muted">{{ $order->order_type }}</div>
                        </td>
                        <td>
                            {{ $order->product->code ?? '—' }}<br>
                            <span class="muted">{{ $order->product->name ?? 'Product unavailable' }}</span><br>
                            <span class="muted">Revision: {{ $order->productRevision->revision ?? '—' }}</span>
                        </td>
                        <td>{{ \App\Support\Formats::number((float)$order->planned_qty, 3) }} {{ $order->uom }}</td>
                        <td>{{ \App\Support\Formats::number((float)$order->completed_qty, 3) }}</td>
                        <td>{{ \App\Support\Formats::number((float)$order->rejected_qty, 3) }}</td>
                        <td>{{ $order->required_date?->displayDate() ?? '—' }}</td>
                        <td>{{ $order->priority }}</td>
                        <td>
                            <span class="status">{{ str_replace('_', ' ', $order->status) }}</span>

                            @if(!in_array($order->status, ['COMPLETED', 'CANCELLED'], true))
                                <a class="button"
                                   href="{{ route('moulding-runs.create', ['production_order_id' => $order->id]) }}"
                                   style="display:inline-block; margin-top:8px">
                                    + Create moulding run
                                </a>
                            @endif

                            @if($order->status === 'DRAFT')
                                <form class="inline" method="POST"
                                      action="{{ route('production-orders.release', $order) }}"
                                      onsubmit="return confirm('Release this order to production?')">
                                    @csrf
                                    <button type="submit">Release</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">No production orders found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:18px">{{ $orders->links() }}</div>
    </section>
</div>
</div>
@endsection
