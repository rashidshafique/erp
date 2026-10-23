<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'movement_type' => ['nullable', 'string', 'max:40'],
            'material_id' => ['nullable', 'uuid', 'exists:materials,id'],
            'source_type' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $movements = StockMovement::query()
            ->with(['material', 'materialLot', 'fromLocation', 'toLocation'])
            ->when($filters['movement_type'] ?? null, fn ($q, $v) => $q->where('movement_type', $v))
            ->when($filters['material_id'] ?? null, fn ($q, $v) => $q->where('material_id', $v))
            ->when($filters['source_type'] ?? null, fn ($q, $v) => $q->where('source_type', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '<=', $v))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $materials = \App\Models\Material::query()
            ->where('is_stock_tracked', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $movementTypes = StockMovement::query()
            ->select('movement_type')
            ->distinct()
            ->orderBy('movement_type')
            ->pluck('movement_type');

        $sourceTypes = StockMovement::query()
            ->whereNotNull('source_type')
            ->select('source_type')
            ->distinct()
            ->orderBy('source_type')
            ->pluck('source_type');

        return view('stock-movements.index', compact(
            'movements',
            'materials',
            'movementTypes',
            'sourceTypes',
            'filters'
        ));
    }
}