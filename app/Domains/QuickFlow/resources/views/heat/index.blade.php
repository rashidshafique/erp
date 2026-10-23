@extends('layouts.app')

@section('title', 'Next heat')
@section('breadcrumb', 'Quick flow / Next heat')

@section('content')
<div class="page-heading">
    <div><h1>Next heat</h1><p>What to melt next, from open orders. Metal for moulds already made comes first, then moulds still to make, by due date. A heat holds no more than its furnace's working capacity.</p></div>
    <div class="actions"><a class="btn" href="{{ route('quick.floor.index') }}">Moulds made / poured</a><a class="btn btn-primary" href="{{ route('quick.orders.create') }}">+ New order</a></div>
</div>
<div class="panel"><div class="panel-body" style="display:flex;gap:12px 24px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div><strong>Next melting day:</strong> {{ $calendar['next']?->isToday() ? 'Today' : ($calendar['next']?->format('D j M') ?? 'none in the next two months') }}
        <span class="muted small" style="display:block">{{ $calendar['description'] }}</span></div>
    <a class="btn btn-small" href="{{ route('settings.melting-days.edit') }}">Melting days</a>
</div></div>
@if ($warnings)<div class="alert alert-warning"><strong>Not planned:</strong><ul>@foreach (array_unique($warnings) as $w)<li>{{ $w }}</li>@endforeach</ul></div>@endif

@forelse ($heats as $i => $heat)
    <div class="panel">
        <div class="panel-header"><h2>{{ $i === 0 ? 'Next heat' : 'Heat '.($i + 1) }} · {{ $heat['grade']->code }} on {{ $heat['furnace']->code }}</h2>
            <span class="muted small">{{ \App\Support\Formats::number($heat['kg'], 0) }} kg of {{ \App\Support\Formats::number($heat['capacity_kg'], 0) }} kg working capacity@if($heat['hours']) · about {{ $heat['hours'] }} h to melt@endif</span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Order</th><th>Customer</th><th>Due</th><th></th><th>Pieces</th><th>Metal (kg)</th></tr></thead>
            <tbody>@foreach ($heat['lines'] as $line)
                <tr><td>{{ $line['order']->production_order_number }} {{ $line['order']->product?->code }}</td><td>{{ $line['customer'] }}</td><td>{{ $line['due']?->displayShortDate() ?? '—' }}</td>
                    <td>{{ $line['ready'] ? 'Moulds ready to pour' : 'Moulds still to make' }}</td><td>{{ $line['pieces'] }}</td><td>{{ \App\Support\Formats::number($line['kg'], 1) }}</td></tr>
            @endforeach</tbody>
        </table></div>
        @if ($i === 0)
        <div class="panel-body">
            <form method="POST" action="{{ route('quick.heat.start') }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">@csrf
                <input type="hidden" name="iron_grade_id" value="{{ $heat['grade']->id }}"><input type="hidden" name="furnace_id" value="{{ $heat['furnace']->id }}">
                <div class="field"><label for="kg">Melt (kg)</label><input id="kg" type="number" step="any" min="1" name="kg" value="{{ $heat['kg'] }}" required></div>
                <div class="field"><label for="operator_name">Operator</label><input id="operator_name" name="operator_name" maxlength="100"></div>
                <button class="btn btn-primary" type="submit">Start this heat</button>
            </form>
        </div>
        @endif
    </div>
@empty
    <div class="panel"><div class="empty-state">Nothing to melt. Enter an order, or fix the items listed above.</div></div>
@endforelse

<div class="panel"><div class="panel-header"><h2>Open orders</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Order</th><th>Customer</th><th>Grade</th><th>Due</th>@if ($calendar['enabled'])<th>Pour in time?</th>@endif<th>Ordered</th><th>Moulded</th><th>Poured</th><th>Moulded, not poured</th><th>Still to mould</th></tr></thead>
        <tbody>@forelse ($orders as $o)
            <tr><td>{{ $o['order']->production_order_number }} {{ $o['order']->product?->code }}</td><td>{{ $o['customer'] }}</td><td>{{ $o['grade']?->code ?? '—' }}</td><td>{{ $o['due']?->displayShortDate() ?? '—' }}</td>
                @if ($calendar['enabled'])@php $c = $dueChecks[$o['order']->id] ?? null; @endphp<td>@if (! $c)<span class="muted">—</span>@elseif ($c['ok'])<span class="status status-completed" title="Pour by {{ $c['latest_pour']->format('D j M') }}">Next pour {{ $c['next']->isToday() ? 'today' : $c['next']->format('D j M') }}</span>@else<span class="status status-cancelled" title="Pour by {{ $c['latest_pour']->format('D j M') }}">No melting day in time{{ $c['next'] ? ' (next '.$c['next']->format('D j M').')' : '' }}</span>@endif</td>@endif
                <td>{{ $o['ordered'] }}</td><td>{{ $o['made'] }}</td><td>{{ $o['poured'] }}</td><td>{{ $o['awaiting'] }}</td><td>{{ $o['to_mould'] }}</td></tr>
        @empty<tr><td colspan="10" class="muted">No open orders.</td></tr>@endforelse</tbody>
    </table></div>
</div>
@endsection
