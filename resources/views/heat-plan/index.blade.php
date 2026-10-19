@extends('layouts.app')

@section('title', 'Melting plan')
@section('breadcrumb', 'Melting plan')

@section('content')
<div class="page-heading">
    <div><h1>Melting plan</h1><p>Released production orders grouped by iron grade, sequenced to limit changeover, placed on eligible furnaces.</p></div>
    <div class="actions">
        <form method="POST" action="{{ route('heat-plan.rebuild') }}" style="display:flex;gap:8px;align-items:center">@csrf
            <input type="date" name="from" value="{{ old('from', today()->toDateString()) }}" min="{{ today()->toDateString() }}" required>
            <button class="btn btn-primary" type="submit">Rebuild plan</button></form>
    </div>
</div>
@if (! empty($skipped))
    <div class="alert alert-error"><strong>Not planned:</strong><ul>@foreach ($skipped as $s)<li>{{ $s }}</li>@endforeach</ul></div>
@endif
<p class="muted small">Rebuilding replaces heats that are still only planned. Released heats are kept and their metal is not planned twice. Planned weights come from the cavity map (poured weights), else casting weight or net weight divided by the default yield.</p>

<div class="panel">
    @if ($heats->isEmpty())
        <div class="empty-state">No heats planned. Release production orders, then rebuild the plan.</div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Plan</th><th>Grade</th><th>Furnace</th><th>Metal (kg)</th><th>Orders</th><th>Flags</th><th></th></tr></thead>
            <tbody>
            @foreach ($heats as $h)
                <tr>
                    <td>{{ $h->planned_date->displayShortDate() }}</td>
                    <td><strong>{{ $h->plan_number }}</strong><div class="muted small">{{ $h->status }}</div></td>
                    <td>{{ $h->ironGrade?->code }}</td>
                    <td>
                        @if ($h->status === 'PLANNED')
                            <form method="POST" action="{{ route('heat-plan.assign', $h) }}">@csrf
                                <select name="furnace_id" onchange="this.form.submit()">
                                    @foreach ($furnaces as $f)<option value="{{ $f->id }}" @selected($h->furnace_id === $f->id)>{{ $f->code }} ({{ $f->furnace_type }})</option>@endforeach
                                </select></form>
                        @else {{ $h->furnace?->code }} @endif
                    </td>
                    <td>{{ \App\Support\Formats::number((float) $h->planned_metal_kg, 1) }}</td>
                    <td style="white-space:normal">@foreach ($h->orders as $o){{ $o->productionOrder?->production_order_number }} ({{ \App\Support\Formats::number((float) $o->metal_kg, 0) }} kg)<br>@endforeach</td>
                    <td style="white-space:normal">@if ($h->changeover_required)<span class="status status-on-hold">Changeover</span> @endif @if ($h->late_risk)<span class="status status-cancelled">Late risk</span>@endif</td>
                    <td>
                        @if ($h->status === 'PLANNED')
                            <form method="POST" action="{{ route('heat-plan.release', $h) }}">@csrf
                                <input name="operator_name" placeholder="Operator" maxlength="100">
                                <button class="btn btn-primary" type="submit">Release heat</button></form>
                        @elseif ($h->heat)
                            <a href="{{ route('heats.show', $h->heat) }}">{{ $h->heat->heat_number }}</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
@endsection
