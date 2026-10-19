@extends('layouts.app')

@section('content')
@php
    $hasPercentItems = $chargeBatch->items->contains(fn ($item) => $item->target_percent !== null);
    $totalChargeKg = $hasPercentItems
        ? ($chargeBatch->target_total_charge_weight_kg !== null ? (float) $chargeBatch->target_total_charge_weight_kg : null)
        : (float) $chargeBatch->items->sum(fn ($item) => (float) ($item->target_qty_kg ?? 0));
@endphp
<div class="page-header">
    <div>
        <h1>{{ $chargeBatch->name }}</h1>
        <p>{{ $chargeBatch->code }} · Revision {{ $chargeBatch->revision }} · {{ $chargeBatch->process_type }}</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('charge-batches.index') }}">All recipes</a>
</div>


<div class="card" style="padding:18px;margin-bottom:20px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px">
        <div><small>Status</small><h3>{{ $chargeBatch->status }}</h3></div>
        <div><small>Total charge weight</small><h3>{{ $totalChargeKg !== null && $totalChargeKg > 0 ? \App\Support\Formats::number($totalChargeKg, 3).' kg' : 'Basis required' }}</h3></div>
        <div><small>Target tapped metal</small><h3>{{ $chargeBatch->target_metal_weight_kg !== null ? \App\Support\Formats::number((float)$chargeBatch->target_metal_weight_kg, 3).' kg' : 'Not set' }}</h3></div>
        <div><small>Effective dates</small><h3>{{ $chargeBatch->effective_from?->format('Y-m-d') ?? '—' }} to {{ $chargeBatch->effective_to?->format('Y-m-d') ?? '—' }}</h3></div>
    </div>
    @if($hasPercentItems && $totalChargeKg !== null)
        <p class="muted">Material quantities are calculated from the target total charge weight of {{ \App\Support\Formats::number($totalChargeKg, 3) }} kg.</p>
    @elseif(!$hasPercentItems)
        <p class="muted">Total charge weight is the sum of all fixed material quantities.</p>
    @endif
    @if($chargeBatch->instructions)<p><strong>Instructions:</strong> {{ $chargeBatch->instructions }}</p>@endif
    @if($chargeBatch->notes)<p><strong>Notes:</strong> {{ $chargeBatch->notes }}</p>@endif
</div>

<h2>Recipe components</h2>
<div class="table-responsive">
<table class="table">
    <thead><tr><th>#</th><th>Material</th><th>Role</th><th>Planned kg</th><th>% of total</th><th>Required</th><th>Notes</th></tr></thead>
    <tbody>
    @forelse($chargeBatch->items as $item)
        @php
            $itemQtyKg = $item->target_qty_kg !== null
                ? (float) $item->target_qty_kg
                : ($item->target_percent !== null && $totalChargeKg !== null && $totalChargeKg > 0
                    ? $totalChargeKg * (float) $item->target_percent / 100
                    : null);
            $itemPercent = $item->target_percent !== null
                ? (float) $item->target_percent
                : ($itemQtyKg !== null && $totalChargeKg !== null && $totalChargeKg > 0
                    ? $itemQtyKg / $totalChargeKg * 100
                    : null);
        @endphp
        <tr>
            <td>{{ $item->sequence }}</td>
            <td>{{ $item->material->code }} — {{ $item->material->name }}</td>
            <td>{{ $item->component_role }}</td>
            <td>{{ $itemQtyKg !== null ? \App\Support\Formats::number($itemQtyKg, 3) : '—' }}</td>
            <td>{{ $itemPercent !== null ? \App\Support\Formats::number($itemPercent, 4).'%' : '—' }}</td>
            <td>{{ $item->is_required ? 'Yes' : 'No' }}</td>
            <td>{{ $item->notes ?: '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="7">No components recorded.</td></tr>
    @endforelse
    </tbody>
    @if($chargeBatch->items->isNotEmpty())
        <tfoot><tr><th colspan="3" style="text-align:right">Total charge</th><th>{{ $totalChargeKg !== null && $totalChargeKg > 0 ? \App\Support\Formats::number($totalChargeKg, 3).' kg' : '—' }}</th><th>{{ $chargeBatch->items->sum(fn ($item) => $item->target_percent !== null ? (float) $item->target_percent : 0) > 0 ? \App\Support\Formats::number((float) $chargeBatch->items->sum(fn ($item) => $item->target_percent !== null ? (float) $item->target_percent : 0), 4).'%' : ($totalChargeKg !== null && $totalChargeKg > 0 ? \App\Support\Formats::number(100, 4).'%' : '—') }}</th><th colspan="2"></th></tr></tfoot>
    @endif
</table>
</div>
@endsection
