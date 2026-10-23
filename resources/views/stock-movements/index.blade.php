@extends('layouts.app')

@section('title', 'Stock Movement Ledger')

@section('content')
<div class="page-heading">
    <div>
        <h1>Stock Movement Ledger</h1>
        <p>Trace stock receipts, issues and transfers to their lots and source records.</p>
    </div>
    @if (app(\App\Domains\Core\ModuleRegistry::class)->isEnabled('inventory'))
        <div class="actions"><a class="btn" href="{{ route('stock-balances.index') }}">Stock by location</a> <a class="btn btn-primary" href="{{ route('stock-movements.record') }}">+ Record movement</a></div>
    @endif
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" action="{{ route('stock-movements.index') }}">
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
                <div>
                    <label for="material_id">Material</label>
                    <select name="material_id" id="material_id">
                        <option value="">All materials</option>
                        @foreach($materials as $material)
                            <option value="{{ $material->id }}"
                                @selected(($filters['material_id'] ?? '') === $material->id)>
                                {{ $material->code }} — {{ $material->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="movement_type">Movement type</label>
                    <select name="movement_type" id="movement_type">
                        <option value="">All types</option>
                        @foreach($movementTypes as $type)
                            <option value="{{ $type }}"
                                @selected(($filters['movement_type'] ?? '') === $type)>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="source_type">Source</label>
                    <select name="source_type" id="source_type">
                        <option value="">All sources</option>
                        @foreach($sourceTypes as $source)
                            <option value="{{ $source }}"
                                @selected(($filters['source_type'] ?? '') === $source)>
                                {{ $source }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="date_from">From</label>
                    <input type="date" name="date_from" id="date_from"
                        value="{{ $filters['date_from'] ?? '' }}">
                </div>

                <div>
                    <label for="date_to">To</label>
                    <input type="date" name="date_to" id="date_to"
                        value="{{ $filters['date_to'] ?? '' }}">
                </div>

                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn" href="{{ route('stock-movements.index') }}">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Movements ({{ $movements->total() }})</h2>
    </div>

    <div class="panel-body" style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Movement no.</th>
                    <th>Type</th>
                    <th>Material</th>
                    <th>Lot</th>
                    <th>From → to</th>
                    <th>Qty (kg)</th>
                    <th>Unit cost (PKR/kg)</th>
                    <th>Total cost (PKR)</th>
                    <th>Source</th>
                    <th>Recorded by</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $movement)
                    <tr>
                        <td>{{ $movement->occurred_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $movement->movement_number }}</td>
                        <td>{{ $movement->movement_type }}</td>
                        <td>{{ $movement->material?->name ?? '—' }}</td>
                        <td>{{ $movement->materialLot?->lot_number ?? '—' }}</td>
                        <td>{{ $movement->fromLocation || $movement->toLocation ? ($movement->fromLocation?->code ?? '—').' → '.($movement->toLocation?->code ?? '—') : '—' }}</td>
                        <td>{{ \App\Support\Formats::number((float) $movement->quantity, 3) }}</td>
                        <td>{{ $movement->unit_cost === null ? '—' : \App\Support\Formats::number((float) $movement->unit_cost, 4) }}</td>
                        <td>{{ $movement->total_cost === null ? '—' : \App\Support\Formats::number((float) $movement->total_cost, 2) }}</td>
                        <td>
                            {{ $movement->source_type ?? '—' }}
                            @if($movement->source_id)
                                <div class="muted small">{{ $movement->source_id }}</div>
                            @endif
                        </td>
                        <td>{{ $movement->recorded_by ?? '—' }}</td>
                        <td>{{ $movement->reason ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12">No stock movements match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="panel-body">
        {{ $movements->links() }}
    </div>
</div>
@endsection