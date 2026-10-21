@extends('layouts.app')

@section('title', $order->production_order_number)
@section('breadcrumb')
    <a href="{{ route('production-orders.index') }}">Production orders</a> / {{ $order->production_order_number }}
@endsection

@section('content')
    @php($f = fn ($n) => \App\Support\Formats::number((float) $n, 0))
    <div class="page-heading">
        <div>
            <h1>{{ $order->production_order_number }}
                <span class="status status-{{ strtolower(str_replace('_', '-', $order->status)) }}">{{ str_replace('_', ' ', $order->status) }}</span>
            </h1>
            <p><strong>{{ $order->product?->code }} — {{ $order->product?->name }}</strong>, revision {{ $order->productRevision?->revision ?? '—' }} · {{ $order->customerLabel() }} · {{ $order->order_type }} · {{ $order->priority }} priority</p>
        </div>
        <div class="actions">
            <a href="{{ route('production-orders.index') }}" class="btn">Back to orders</a>
            @if (! in_array($order->status, ['COMPLETED', 'CANCELLED'], true))
                <a class="btn btn-primary" href="{{ route('moulding-runs.create', ['production_order_id' => $order->id]) }}">+ Moulding run</a>
            @endif
        </div>
    </div>

    <div class="metrics">
        <div class="metric"><div class="metric-label">Planned</div><div class="metric-value">{{ $f($order->planned_qty) }}</div><div class="metric-note">{{ $order->uom }}</div></div>
        <div class="metric"><div class="metric-label">Moulds accepted</div><div class="metric-value">{{ $f($mouldsAccepted) }}</div><div class="metric-note">From {{ $runs->count() }} run(s)</div></div>
        <div class="metric"><div class="metric-label">Castings poured</div><div class="metric-value">{{ $f($poured) }}</div><div class="metric-note">In {{ $lots->count() }} lot(s)</div></div>
        <div class="metric"><div class="metric-label">Good / rejected</div><div class="metric-value">{{ $f($order->completed_qty) }} / {{ $f($order->rejected_qty) }}</div><div class="metric-note">After final inspection</div></div>
    </div>

    @if ($errors->has('order'))<div class="alert alert-error">{{ $errors->first('order') }}</div>@endif

    <div class="actions" style="margin-bottom:16px">
        @if ($order->status === 'DRAFT')
            <a class="btn" href="{{ route('production-orders.edit', $order) }}">Edit</a>
            <form method="POST" action="{{ route('production-orders.release', $order) }}" onsubmit="return confirm('Release this order to production?')">@csrf<button class="btn btn-primary" type="submit">Release</button></form>
        @endif
        @if ($order->status === 'IN_PROGRESS')
            <form method="POST" action="{{ route('production-orders.close', $order) }}"
                  onsubmit="const short = {{ (float) $order->completed_qty < (float) $order->planned_qty ? 'true' : 'false' }}; const reason = prompt(short ? 'Good castings are below the planned quantity. Why is the rest not being made?' : 'Close this order?'); if (reason === null || (short && !reason.trim())) return false; this.reason.value = reason; return true;">
                @csrf<input type="hidden" name="reason"><button class="btn" type="submit">Close order</button>
            </form>
        @endif
        @if (in_array($order->status, ['DRAFT', 'RELEASED', 'IN_PROGRESS'], true))
            <form method="POST" action="{{ route('production-orders.cancel', $order) }}"
                  onsubmit="const reason = prompt('Cancel this order? Say why:'); if (reason === null || !reason.trim()) return false; this.reason.value = reason; return true;">
                @csrf<input type="hidden" name="reason"><button class="btn" type="submit">Cancel order</button>
            </form>
        @endif
    </div>

    <section class="panel">
        <div class="panel-header"><h2>Moulding runs</h2><span class="muted small">{{ $runs->count() }}</span></div>
        @if ($runs->isEmpty())
            <div class="panel-body"><p class="muted">No runs yet.</p></div>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Run</th><th>Date</th><th>Pattern</th><th>Made</th><th>Accepted</th><th>Damaged</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($runs as $run)
                    <tr>
                        <td><a href="{{ route('moulding-runs.show', $run) }}">{{ $run->run_number }}</a></td>
                        <td>{{ $run->run_date?->displayDate() ?? '—' }}</td>
                        <td>{{ $run->patternRevision?->pattern?->name ?? '—' }}</td>
                        <td>{{ $f($run->moulds_made_qty) }}</td>
                        <td>{{ $f($run->accepted_mould_qty) }}</td>
                        <td>{{ $f($run->damaged_mould_qty) }}</td>
                        <td><span class="status">{{ str_replace('_', ' ', $run->status) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-header"><h2>Casting lots</h2><span class="muted small">{{ $lots->count() }}</span></div>
        @if ($lots->isEmpty())
            <div class="panel-body"><p class="muted">Nothing poured for this order yet.</p></div>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Lot</th><th>Pour</th><th>Poured</th><th>Good</th><th>Rejected</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($lots as $lot)
                    <tr>
                        <td><a href="{{ route('lots.show', $lot) }}">{{ $lot->label }}</a></td>
                        <td>@if ($lot->pourMouldAssembly?->pour)<a href="{{ route('pours.show', $lot->pourMouldAssembly->pour) }}">{{ $lot->pourMouldAssembly->pour->pour_number }}</a>@else — @endif</td>
                        <td>{{ $f($lot->quantity_poured) }}</td>
                        <td>{{ $f($lot->quantity_good) }}</td>
                        <td>{{ $f($lot->quantity_rejected) }}</td>
                        <td><span class="status">{{ str_replace('_', ' ', $lot->status) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-header"><h2>Details</h2></div>
        <div class="panel-body detail-grid">
            <div><div class="detail-label">Required date</div>{{ $order->required_date?->displayDate() ?? '—' }}</div>
            <div><div class="detail-label">Planned start</div>{{ $order->planned_start_date?->displayDate() ?? '—' }}</div>
            <div><div class="detail-label">Planned end</div>{{ $order->planned_finish_date?->displayDate() ?? '—' }}</div>
            <div><div class="detail-label">Started</div>{{ $order->actual_started_at?->displayDateTime() ?? '—' }}</div>
            <div><div class="detail-label">Completed</div>{{ $order->actual_completed_at?->displayDateTime() ?? '—' }}</div>
            <div><div class="detail-label">Sales order</div>@if ($order->salesOrderLine?->salesOrder)<a href="{{ route('sales-orders.show', $order->salesOrderLine->salesOrder) }}">{{ $order->salesOrderLine->salesOrder->order_number }}</a>@else — @endif</div>
        </div>
        @if ($order->instructions)<div class="panel-body"><div class="detail-label">Instructions</div>{!! nl2br(e($order->instructions)) !!}</div>@endif
        @if ($order->notes)<div class="panel-body"><div class="detail-label">Notes</div>{!! nl2br(e($order->notes)) !!}</div>@endif
    </section>
@endsection
