@extends('layouts.app')

@section('title', 'Stock on Hand')

@section('content')
<div class="page-heading">
    <div>
        <h1>Stock on Hand</h1>
        <p>Inventory balances and remaining FIFO cost-layer valuation.</p>
    </div>
</div>

@php
    $totalQuantity = 0;
    $totalValue = 0;
    $lowStockCount = 0;

    foreach ($materials as $material) {
        $materialQty = 0;

        foreach ($lots as $lot) {
            if ($lot->material_id !== $material->id) {
                continue;
            }

            foreach ($lot->costLayers as $layer) {
                $materialQty += (float) $layer->remaining_qty;
            }
        }

        if ((float) $material->minimum_stock_qty > 0 && $materialQty <= (float) $material->minimum_stock_qty) {
            $lowStockCount++;
        }

        $totalQuantity += $materialQty;
    }

    foreach ($lots as $lot) {
        foreach ($lot->costLayers as $layer) {
            $qty = (float) $layer->remaining_qty;
            $cost = (float) $layer->unit_cost;

            $totalValue += $qty * $cost;
        }
    }
@endphp

<div class="panel">
    <div class="panel-body">
        <div style="display:flex;gap:32px;flex-wrap:wrap">
            <div>
                <div class="muted small">Stock quantity (kg)</div>
                <strong>{{ \App\Support\Formats::number($totalQuantity, 3) }}</strong>
            </div>
            <div>
                <div class="muted small">Stock value (PKR)</div>
                <strong>{{ \App\Support\Formats::number($totalValue, 2) }}</strong>
            </div>
            <div>
                <div class="muted small">Materials at/below minimum</div>
                <strong>{{ $lowStockCount }}</strong>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Balances by lot and cost layer</h2>
    </div>

    <div class="panel-body" style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Material</th>
                    <th>Lot</th>
                    <th>Status</th>
                    <th>Remaining (kg)</th>
                    <th>Unit cost (PKR/kg)</th>
                    <th>Layer value (PKR)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lots as $lot)
                    @foreach($lot->costLayers as $layer)
                        <tr>
                            <td>{{ $lot->material->name }}</td>
                            <td>{{ $lot->lot_number }}</td>
                            <td>{{ $lot->status }}</td>
                            <td>{{ \App\Support\Formats::number((float) $layer->remaining_qty, 3) }}</td>
                            <td>{{ \App\Support\Formats::number((float) $layer->unit_cost, 4) }}</td>
                            <td>
                                {{ \App\Support\Formats::number(
                                    (float) $layer->remaining_qty
                                    * (float) $layer->unit_cost,
                                    2
                                ) }}
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="6">No stock lots found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection