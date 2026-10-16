@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1>{{ $chargeBatch->name }}</h1>
        <p>{{ $chargeBatch->code }} · Revision {{ $chargeBatch->revision }} · {{ $chargeBatch->process_type }}</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('charge-batches.index') }}">All recipes</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card" style="padding:18px;margin-bottom:20px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px">
        <div><small>Status</small><h3>{{ $chargeBatch->status }}</h3></div>
        <div><small>Target tapped metal</small><h3>{{ $chargeBatch->target_metal_weight_kg !== null ? number_format((float)$chargeBatch->target_metal_weight_kg, 3).' kg' : 'Not set' }}</h3></div>
        <div><small>Target total charge</small><h3>{{ $chargeBatch->target_total_charge_weight_kg !== null ? number_format((float)$chargeBatch->target_total_charge_weight_kg, 3).' kg' : 'Not set' }}</h3></div>
        <div><small>Effective dates</small><h3>{{ $chargeBatch->effective_from?->format('Y-m-d') ?? '—' }} to {{ $chargeBatch->effective_to?->format('Y-m-d') ?? '—' }}</h3></div>
    </div>
    @if($chargeBatch->instructions)<p><strong>Instructions:</strong> {{ $chargeBatch->instructions }}</p>@endif
    @if($chargeBatch->notes)<p><strong>Notes:</strong> {{ $chargeBatch->notes }}</p>@endif
</div>

<h2>Recipe components</h2>
<div class="table-responsive">
<table class="table">
    <thead><tr><th>#</th><th>Material</th><th>Role</th><th>Target kg</th><th>Target %</th><th>Required</th><th>Notes</th></tr></thead>
    <tbody>
    @forelse($chargeBatch->items as $item)
        <tr>
            <td>{{ $item->sequence }}</td>
            <td>{{ $item->material->code }} — {{ $item->material->name }}</td>
            <td>{{ $item->component_role }}</td>
            <td>{{ $item->target_qty_kg !== null ? number_format((float)$item->target_qty_kg, 3) : '—' }}</td>
            <td>{{ $item->target_percent !== null ? number_format((float)$item->target_percent, 4).'%' : '—' }}</td>
            <td>{{ $item->is_required ? 'Yes' : 'No' }}</td>
            <td>{{ $item->notes ?: '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="7">No components recorded.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
