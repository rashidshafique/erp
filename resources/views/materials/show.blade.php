@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1>{{ $material->name }}</h1>
        <p>{{ $material->code }} · {{ $material->material_type }} · {{ $material->base_uom }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-secondary" href="{{ route('materials.edit', $material) }}">Edit material</a>
        <a class="btn btn-primary" href="{{ route('materials.lots.create', $material) }}">+ Record lot</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card" style="padding:18px;margin-bottom:20px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px">
        <div><small>Status</small><h3>{{ $material->status }}</h3></div>
        <div><small>Lot records</small><h3>{{ $material->lots->count() }}</h3></div>
        <div><small>Recipe components</small><h3>{{ $material->charge_batch_items_count }}</h3></div>
        <div><small>Standard cost</small><h3>{{ $material->standard_cost_per_uom !== null ? number_format((float)$material->standard_cost_per_uom, 4).' PKR/'.$material->base_uom : 'Not set' }}</h3></div>
    </div>
    @if($material->description)<p>{{ $material->description }}</p>@endif
    @if($material->notes)<p><strong>Notes:</strong> {{ $material->notes }}</p>@endif
</div>

<h2>Material lots</h2>

<div class="table-responsive">
    <table class="table">
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
                <td>{{ $lot->lot_number }}</td>
                <td>{{ $lot->supplier_name_snapshot ?: '—' }}</td>
                <td>{{ number_format((float) $lot->received_qty, 3) }}</td>
                <td>{{ number_format((float) $lot->accepted_qty, 3) }}</td>
                <td>
                    {{ $lot->unit_cost !== null
                        ? number_format((float) $lot->unit_cost, 4).' '.$lot->currency_code
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
                            {{ number_format((float) $receipt->quantity, 3) }} kg
                            · PKR
                            {{ number_format((float) $receipt->total_cost, 2) }}
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
                                    {{ number_format((float) $lot->accepted_qty, 3) }}
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
@endsection
