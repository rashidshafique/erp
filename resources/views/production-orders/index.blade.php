@extends('layouts.app')

@section('title', 'Production orders')
@section('breadcrumb', 'Production orders')

@section('content')
    <div class="page-heading">
        <div>
            <h1>Production orders</h1>
            <p>Plan, release and monitor foundry production.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('production-orders.create') }}">+ New order</a>
    </div>

    @if ($errors->has('release'))<div class="alert alert-error">{{ $errors->first('release') }}</div>@endif

    <div class="metrics">
        <div class="metric"><div class="metric-label">Draft</div><div class="metric-value">{{ $counts['draft'] }}</div></div>
        <div class="metric"><div class="metric-label">Released</div><div class="metric-value">{{ $counts['released'] }}</div></div>
        <div class="metric"><div class="metric-label">In progress</div><div class="metric-value">{{ $counts['in_progress'] }}</div></div>
        <div class="metric"><div class="metric-label">Completed</div><div class="metric-value">{{ $counts['completed'] }}</div></div>
    </div>

    <section class="panel">
        <div class="panel-body">
            <form method="GET" action="{{ route('production-orders.index') }}" class="filters">
                <div class="field">
                    <label for="search">Order number or product</label>
                    <input id="search" type="search" name="search" value="{{ request('search') }}">
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach (['DRAFT', 'RELEASED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', $status) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a class="btn" href="{{ route('production-orders.index') }}">Clear</a>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2>Orders</h2><span class="muted small">{{ $orders->total() }} total</span></div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Product</th>
                    <th>Planned</th>
                    <th>Good</th>
                    <th>Rejected</th>
                    <th>Required</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>
                            <a href="{{ route('production-orders.show', $order) }}"><strong class="code">{{ $order->production_order_number }}</strong></a>
                            <div class="muted small">{{ $order->order_type }}</div>
                        </td>
                        <td>
                            <x-item :name="$order->product->name ?? 'Product unavailable'" :code="$order->product->code ?? null" :revision="$order->productRevision?->revision ? 'rev '.$order->productRevision->revision : null" />
                        </td>
                        <td>{{ \App\Support\Formats::number((float) $order->planned_qty, 0) }} {{ $order->uom }}</td>
                        <td>{{ \App\Support\Formats::number((float) $order->completed_qty, 0) }}</td>
                        <td>{{ \App\Support\Formats::number((float) $order->rejected_qty, 0) }}</td>
                        <td>{{ $order->required_date?->displayDate() ?? '—' }}</td>
                        <td>{{ $order->priority }}</td>
                        <td><span class="status">{{ str_replace('_', ' ', $order->status) }}</span></td>
                        <td>
                            <div class="actions">
                                @if ($order->status === 'DRAFT')
                                    <form method="POST" action="{{ route('production-orders.release', $order) }}" onsubmit="return confirm('Release this order to production?')">
                                        @csrf<button class="btn" type="submit">Release</button>
                                    </form>
                                @endif
                                @if (! in_array($order->status, ['DRAFT', 'COMPLETED', 'CANCELLED'], true))
                                    <a class="btn" href="{{ route('moulding-runs.create', ['production_order_id' => $order->id]) }}">+ Run</a>
                                @endif
                                <a class="btn" href="{{ route('production-orders.show', $order) }}">Open</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9">No production orders found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-body">{{ $orders->links() }}</div>
    </section>
@endsection
