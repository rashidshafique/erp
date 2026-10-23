@extends('layouts.app')

@section('title', $material->code)
@section('breadcrumb')<a href="{{ route('materials.index') }}">Materials</a> / {{ $material->code }} @endsection

@section('content')
<div class="page-heading">
    <div>
        <h1>{{ $material->name }}</h1>
        <p>{{ $material->code }} · {{ \App\Models\Material::TYPES[$material->material_type] ?? $material->material_type }} · {{ $material->base_uom }}</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('materials.edit', $material) }}">Edit material</a>
        @if(app(\App\Domains\Core\ModuleRegistry::class)->isEnabled('purchasing'))<a class="btn" href="{{ route('incoming.plan.edit', $material) }}">Inspection plan</a>@endif
        <a class="btn btn-primary" href="{{ route('materials.lots.create', $material) }}">+ Record lot</a>
    </div>
</div>


<div class="metrics">
    <div class="metric"><div class="metric-label">Status</div><div class="metric-value" style="font-size:20px">{{ $material->status }}</div></div>
    <div class="metric"><div class="metric-label">On hand</div><div class="metric-value" style="font-size:20px">{{ $material->is_stock_tracked ? \App\Support\Formats::number($onHand, 3).' '.$material->base_uom : 'Not tracked' }}</div>@if ($material->minimum_stock_qty !== null)<div class="metric-note">minimum {{ \App\Support\Formats::number((float) $material->minimum_stock_qty, 3) }}</div>@endif</div>
    <div class="metric"><div class="metric-label">Lot records</div><div class="metric-value">{{ $material->lots->count() }}</div></div>
    <div class="metric"><div class="metric-label">Recipe components</div><div class="metric-value">{{ $material->charge_batch_items_count }}</div></div>
    <div class="metric"><div class="metric-label">Standard cost</div><div class="metric-value" style="font-size:20px">{{ $material->standard_cost_per_uom !== null ? \App\Support\Formats::number((float)$material->standard_cost_per_uom, 4).' PKR/'.$material->base_uom : 'Not set' }}</div></div>
</div>

@if ($material->description || $material->notes)
<div class="panel"><div class="panel-body" style="display:flow-root">
    @include('attachments._cover', ['type' => 'material', 'id' => $material->id, 'size' => 140])
    @if($material->description)<p>{{ $material->description }}</p>@endif
    @if($material->notes)<p><strong>Notes:</strong> {{ $material->notes }}</p>@endif
</div></div>
@else
<div class="panel"><div class="panel-body" style="display:flow-root">@include('attachments._cover', ['type' => 'material', 'id' => $material->id, 'size' => 140])<span class="muted">No description or notes.</span></div></div>
@endif

<div class="panel">
<div class="panel-header"><h2>Material lots</h2></div>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Lot</th>
                <th>Supplier</th>
                <th>Received</th>
                <th>Accepted</th>
                <th>Unit cost</th>
                <th>Status</th>
                <th>Receipt posting</th>
            </tr>
        </thead>
        <tbody>
        @forelse($material->lots as $lot)
            @php
                $receipt = $receiptMovements->get($lot->id);
                $canPostReceipt =
                    $lot->status === 'ACCEPTED'
                    && (float) $lot->accepted_qty > 0
                    && $lot->unit_cost !== null
                    && $lot->currency_code === 'PKR';
            @endphp

            <tr>
                <td>@if(app(\App\Domains\Core\ModuleRegistry::class)->isEnabled('purchasing'))<a href="{{ route('incoming.lots.show', $lot) }}">{{ $lot->lot_number }}</a>@else{{ $lot->lot_number }}@endif</td>
                <td>{{ $lot->supplier_name_snapshot ?: '—' }}</td>
                <td>{{ \App\Support\Formats::number((float) $lot->received_qty, 3) }}</td>
                <td>{{ \App\Support\Formats::number((float) $lot->accepted_qty, 3) }}</td>
                <td>
                    {{ $lot->unit_cost !== null
                        ? \App\Support\Formats::number((float) $lot->unit_cost, 4).' '.$lot->currency_code
                        : '—' }}
                </td>
                <td>{{ $lot->status }}</td>

                <td>
                    @if($receipt)
                        <strong>Posted</strong>
                        <div>
                            {{ $receipt->movement_number }}
                        </div>
                        <small>
                            {{ \App\Support\Formats::number((float) $receipt->quantity, 3) }} kg
                            · PKR
                            {{ \App\Support\Formats::number((float) $receipt->total_cost, 2) }}
                        </small>
                    @elseif($canPostReceipt)
                        <details>
                            <summary>Post receipt</summary>

                            <form
                                method="POST"
                                action="{{ route('materials.lots.post-receipt', [$material, $lot]) }}"
                                style="min-width:240px;margin-top:10px"
                            >
                                @csrf

                                <p>
                                    <strong>Confirm physical stock</strong>
                                </p>

                                <p>
                                    This will add
                                    {{ \App\Support\Formats::number((float) $lot->accepted_qty, 3) }}
                                    kg to the stock ledger and create its cost layer.
                                </p>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="confirmed_physical_stock"
                                        value="1"
                                        required
                                    >
                                    I confirm this stock is physically available.
                                </label>

                                @if ($stockLocations->isNotEmpty())
                                    <div style="margin-top:10px">
                                        <label for="receipt_location_{{ $lot->id }}">Put into</label>
                                        <select id="receipt_location_{{ $lot->id }}" name="stock_location_id">
                                            <option value="">— no location —</option>
                                            @foreach ($stockLocations as $location)
                                                <option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div style="margin-top:10px">
                                    <label for="recorded_by_{{ $lot->id }}">
                                        Recorded by
                                    </label>
                                    <input
                                        id="recorded_by_{{ $lot->id }}"
                                        type="text"
                                        name="recorded_by"
                                        maxlength="100"
                                        required
                                    >
                                </div>

                                <div style="margin-top:10px">
                                    <label for="receipt_notes_{{ $lot->id }}">
                                        Notes (optional)
                                    </label>
                                    <textarea
                                        id="receipt_notes_{{ $lot->id }}"
                                        name="notes"
                                        rows="2"
                                        maxlength="10000"
                                    ></textarea>
                                </div>

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    style="margin-top:10px"
                                    onclick="return confirm('Post this lot receipt to the stock ledger? This action must not be used for test data or stock that is not physically available.');"
                                >
                                    Confirm receipt
                                </button>
                            </form>
                        </details>
                    @else
                        <span>Not eligible</span>
                        <small>
                            Requires ACCEPTED status, positive accepted
                            quantity, a unit cost, and PKR currency.
                        </small>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">No lots recorded.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
</div>
@include('attachments._panel', ['type' => 'material', 'id' => $material->id])
@endsection
