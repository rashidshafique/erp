<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\MaterialLot;
use Illuminate\View\View;

class StockReportController extends Controller
{
    public function index(): View
    {
        $materials = Material::query()
            ->where('is_stock_tracked', true)
            ->orderBy('name')
            ->get();

        $lots = MaterialLot::query()
            ->whereHas('material', function ($query) {
                $query->where('is_stock_tracked', true);
            })
            ->with('material')
            ->with('costLayers')
            ->orderBy('material_id')
            ->orderBy('lot_number')
            ->get();

        return view('stock-report.index', compact('materials', 'lots'));
    }
}