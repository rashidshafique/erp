@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-semibold">Stock Reconciliation</h1>
        <p class="mt-1 text-sm text-gray-600">
            Read-only comparison of stock movement activity and remaining cost layers.
            Quantities are shown in each material's base unit.
        </p>
    </div>

    <div class="overflow-x-auto rounded-lg border bg-white">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">Material</th>
                <th class="px-4 py-3 text-right">Lots</th>
                <th class="px-4 py-3 text-right">Movements</th>
                <th class="px-4 py-3 text-right">Receipts</th>
                <th class="px-4 py-3 text-right">Issues</th>
                <th class="px-4 py-3 text-right">Movement Balance</th>
                <th class="px-4 py-3 text-right">Layer Qty</th>
                <th class="px-4 py-3 text-right">Difference</th>
                <th class="px-4 py-3 text-right">Layer Value{{ \App\Support\Formats::currencySuffix() }}</th>
                <th class="px-4 py-3 text-left">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $row)
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-medium">
                            {{ $row['material']->name }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $row['material']->code }}
                        </div>
                    </td>

                    <td class="px-4 py-3 text-right">
                        {{ $row['lot_count'] }}
                    </td>

                    <td class="px-4 py-3 text-right">
                        {{ $row['movement_count'] }}
                    </td>

                    <td class="px-4 py-3 text-right">
                        {{ \App\Support\Formats::number($row['receipts'], 3) }}
                    </td>

                    <td class="px-4 py-3 text-right">
                        {{ \App\Support\Formats::number($row['issues'], 3) }}
                    </td>

                    <td class="px-4 py-3 text-right">
                        {{ \App\Support\Formats::number($row['movement_qty'], 3) }}
                        {{ $row['material']->base_uom }}
                    </td>

                    <td class="px-4 py-3 text-right">
                        {{ \App\Support\Formats::number($row['layer_qty'], 3) }}
                        {{ $row['material']->base_uom }}
                    </td>

                    <td class="px-4 py-3 text-right">
                        {{ \App\Support\Formats::number($row['difference'], 3) }}
                        {{ $row['material']->base_uom }}
                    </td>

                    <td class="px-4 py-3 text-right">
                        {{ \App\Support\Formats::number($row['layer_value'], 2) }}
                    </td>

                    <td class="px-4 py-3">
                        @if ($row['status'] === 'Reconciled')
                            <span class="rounded bg-green-100 px-2 py-1 text-xs">
                                Reconciled
                            </span>
                        @elseif ($row['status'] === 'No Activity')
                            <span class="rounded bg-gray-100 px-2 py-1 text-xs">
                                No activity
                            </span>
                        @else
                            <span class="rounded bg-amber-100 px-2 py-1 text-xs">
                                {{ ucfirst(strtolower($row['status'])) }}
                            </span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-4 py-8 text-center text-gray-500">
                        No stock-tracked materials found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="space-y-3">
    <div>
        <h2 class="text-xl font-semibold">Lot-level reconciliation</h2>
        <p class="text-sm text-gray-600">
            Movement balances are compared with remaining cost-layer quantities.
        </p>
    </div>

    <div class="overflow-x-auto rounded-lg border bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Material / Lot</th>
                    <th class="px-4 py-3 text-right">Receipts</th>
                    <th class="px-4 py-3 text-right">Issues</th>
                    <th class="px-4 py-3 text-right">Movement Balance</th>
                    <th class="px-4 py-3 text-right">Layer Qty</th>
                    <th class="px-4 py-3 text-right">Difference</th>
                    <th class="px-4 py-3 text-right">Layer Value{{ \App\Support\Formats::currencySuffix() }}</th>
                    <th class="px-4 py-3 text-left">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($lotRows as $row)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium">
                                {{ $row['lot']->material->name }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $row['lot']->lot_number }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ \App\Support\Formats::number($row['receipts'], 3) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ \App\Support\Formats::number($row['issues'], 3) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ \App\Support\Formats::number($row['movement_qty'], 3) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ \App\Support\Formats::number($row['layer_qty'], 3) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ \App\Support\Formats::number($row['difference'], 3) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ \App\Support\Formats::number($row['layer_value'], 2) }}
                        </td>
                        <td class="px-4 py-3">{{ ucwords(strtolower($row['status'])) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            No stock-tracked lots found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<p class="text-sm text-gray-500">
    Movement balance = receipts − issues. This report is read-only.
    Transfers and other movement types are flagged for review until their
    quantity conventions are implemented. Reconciliation compares total
    movement quantities with remaining cost-layer quantities.
</p>

    
</div>
@endsection