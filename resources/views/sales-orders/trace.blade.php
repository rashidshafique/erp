@extends('layouts.app')

@section('title', 'Trace '.$order->order_number)
@section('breadcrumb', 'Sales orders / '.$order->order_number.' / Trace')

@section('content')
<div class="page-heading">
    <div><h1>{{ $order->order_number }} — order to dispatch</h1><p>{{ $order->customer?->name }} · contract review {{ $review ? $review->status : 'not run' }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('sales-orders.show', $order) }}">Back to order</a></div>
</div>

@foreach ($rows as $row)
    <div class="panel">
        <div class="panel-header"><h2>Line {{ $row['line']->line_number }} — {{ $row['line']->product?->code }} × {{ rtrim(rtrim(number_format((float) $row['line']->ordered_qty, 3), '0'), '.') }}</h2></div>
        @if (empty($row['orders']))
            <div class="empty-state">No production order yet.</div>
        @endif
        @foreach ($row['orders'] as $o)
            <div class="panel-body">
                <p><strong>{{ $o['po']->production_order_number }}</strong> — {{ $o['po']->status }}, planned {{ rtrim(rtrim(number_format((float) $o['po']->planned_qty, 3), '0'), '.') }},
                    completed {{ rtrim(rtrim(number_format((float) $o['po']->completed_qty, 3), '0'), '.') }}; {{ $o['moulding_runs'] }} moulding run(s); dispatched {{ rtrim(rtrim(number_format($o['dispatched'], 3), '0'), '.') }}.</p>
                @if ($o['lots']->isEmpty())
                    <p class="muted small">No casting lot has been created from a pour yet.</p>
                @else
                    <div class="table-wrap"><table>
                        <thead><tr><th>Lot</th><th>Heat</th><th>Grade</th><th>Good / poured</th><th>Dispatch evidence</th></tr></thead>
                        <tbody>
                        @foreach ($o['lots'] as $x)
                            <tr>
                                <td><strong>{{ $x['lot']->lot_number }}</strong> ({{ $x['lot']->status }})</td>
                                <td>@if ($x['heat'])<a href="{{ route('heats.show', $x['heat']) }}">{{ $x['heat']->heat_number }}</a>@else — @endif</td>
                                <td>{{ $x['heat']?->ironGrade?->code ?? '—' }}</td>
                                <td>{{ $x['lot']->quantity_good ?? '—' }} / {{ $x['lot']->quantity_poured ?? '—' }}</td>
                                <td style="white-space:normal;min-width:300px">
                                    <span class="status {{ $x['readiness']['ok'] ? 'status-completed' : 'status-cancelled' }}">{{ $x['readiness']['ok'] ? 'READY' : 'NOT READY' }}</span>
                                    @foreach ($x['readiness']['checks'] as $c)
                                        <div class="small">{{ $c['ok'] === true ? '✓' : ($c['ok'] === false ? '✗' : '•') }} {{ $c['label'] }}: {{ $c['detail'] }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        @endforeach
    </div>
@endforeach
@endsection
