@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
    <div class="page-heading">
        <div>
            <h1>Foundry overview</h1>
            <p>Production status and quick access to shop-floor operations.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <span class="muted small">Layout</span>
            @foreach (['classic' => 'Classic', 'command' => 'Command', 'columns' => 'Columns', 'telemetry' => 'Telemetry'] as $k => $name)<a href="{{ route('dashboard', ['layout' => $k]) }}" class="btn btn-small {{ $k === 'classic' ? 'btn-primary' : '' }}">{{ $name }}</a>@endforeach
            <a href="{{ route('production-orders.create') }}" class="btn btn-primary">+ New production order</a>
        </div>
    </div>

    @php
        $u = auth()->user();
        $waiting = \App\Models\ApprovalRequest::where('status', 'PENDING')->when($u && ! $u->isAdmin(), fn ($q) => $q->where('requested_by_id', '!=', $u->id)->whereIn('permission', $u->permissions()))->count();
        $ready = $u ? \App\Models\ApprovalRequest::where('status', 'APPROVED')->where('requested_by_id', $u->id)->count() : \App\Models\ApprovalRequest::where('status', 'APPROVED')->count();
    @endphp
    @if ($waiting || $ready)
        <div class="alert alert-success" style="border-color:#e6c36a;background:#fff8e1;color:#7a5b00">
            @if ($waiting)<strong>{{ $waiting }}</strong> approval{{ $waiting === 1 ? '' : 's' }} waiting for a decision.@endif
            @if ($ready)<strong>{{ $ready }}</strong> approved — repeat the action to go ahead.@endif
            <a href="{{ route('approvals.index') }}">Open approvals →</a>
        </div>
    @endif

    <div class="metrics">
        <div class="metric">
            <div class="metric-label">Open orders</div>
            <div class="metric-value">{{ \App\Support\Formats::number($openOrders) }}</div>
            <div class="metric-note">Not completed or cancelled</div>
        </div>

        <div class="metric">
            <div class="metric-label">Released orders</div>
            <div class="metric-value">{{ \App\Support\Formats::number($releasedOrders) }}</div>
            <div class="metric-note">Released for production</div>
        </div>

        <div class="metric">
            <div class="metric-label">Orders in progress</div>
            <div class="metric-value">{{ \App\Support\Formats::number($inProgressOrders) }}</div>
            <div class="metric-note">Currently marked in progress</div>
        </div>

        <div class="metric">
            <div class="metric-label">Completed today</div>
            <div class="metric-value">{{ \App\Support\Formats::number($completedToday) }}</div>
            <div class="metric-note">Orders completed today</div>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2>Production pipeline</h2>
            <span class="muted small">Order-to-dispatch workflow</span>
        </div>

        <div class="panel-body">
            <div class="detail-grid">
                @foreach ([
                    ['Production orders', 'Plan and release orders', route('production-orders.index')],
                    ['Moulding runs', 'Record mould production', route('moulding-runs.index')],
                    ['Pouring', 'Track furnace and pour activity', null],
                    ['Shot blasting', 'Track castings through cleaning', null],
                    ['Quality control', 'Record inspection outcomes', null],
                    ['Dispatch', 'Track finished order dispatch', null],
                ] as [$label, $description, $url])
                    <div style="padding:14px;border:1px solid var(--border);border-radius:8px">
                        <div class="detail-value">
                            @if ($url)
                                <a href="{{ $url }}">{{ $label }}</a>
                            @else
                                {{ $label }}
                            @endif
                        </div>
                        <div class="muted small" style="margin-top:5px">{{ $description }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Recent production orders</h2>
            <a href="{{ route('production-orders.index') }}">View all</a>
        </div>

        @if ($recentOrders->isNotEmpty())
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Order</th>
                        <th>Product</th>
                        <th>Planned quantity</th>
                        <th>Required date</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($recentOrders as $order)
                        <tr>
                            <td><strong>{{ $order->production_order_number }}</strong></td>
                            <td>{{ $order->product?->name ?? '—' }}</td>
                            <td>
                                {{ \App\Support\Formats::number((float) $order->planned_qty, 3) }}
                                {{ $order->uom }}
                            </td>
                            <td>{{ $order->required_date?->displayDate() ?? '—' }}</td>
                            <td>
                                <span class="status status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                                    {{ str_replace('_', ' ', $order->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <strong>No production orders yet.</strong>
                <p>Create a production order to begin recording work.</p>
                <a href="{{ route('production-orders.create') }}" class="btn btn-primary">
                    Create production order
                </a>
            </div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Quick access</h2>
        </div>

        <div class="panel-body">
            <div class="detail-grid">
                <div>
                    <div class="detail-value">
                        <a href="{{ route('production-orders.index') }}">Production orders</a>
                    </div>
                    <div class="muted small">Plan and monitor manufacturing orders.</div>
                </div>

                <div>
                    <div class="detail-value">
                        <a href="{{ route('moulding-runs.index') }}">Moulding records</a>
                    </div>
                    <div class="muted small">Record moulds made, damage, operators and downtime.</div>
                </div>

                <div>
                    <div class="detail-value">
                        <a href="{{ route('tooling-maintenance.dashboard') }}">Maintenance</a>
                    </div>
                    <div class="muted small">Review tooling maintenance activity.</div>
                </div>

                <div>
                    <div class="detail-value">
                        <a href="{{ route('tooling-life.dashboard') }}">Tooling life</a>
                    </div>
                    <div class="muted small">Review tooling life limits.</div>
                </div>
            </div>
        </div>
    </section>
@endsection