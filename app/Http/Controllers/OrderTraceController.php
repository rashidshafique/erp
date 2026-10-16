<?php

namespace App\Http\Controllers;

use App\Models\CastingLot;
use App\Models\SalesOrder;
use App\Services\QualityGates;
use Illuminate\View\View;

class OrderTraceController extends Controller
{
    public function show(SalesOrder $salesOrder, QualityGates $gates): View
    {
        $salesOrder->load(['customer', 'lines.product', 'lines.productionOrders']);
        $currentReview = $salesOrder->currentReview();

        $rows = [];
        foreach ($salesOrder->lines as $line) {
            $orders = [];
            foreach ($line->productionOrders as $po) {
                $lots = CastingLot::with(['pourMouldAssembly.pour.heat.ironGrade'])->where('production_order_id', $po->id)->get()
                    ->map(fn ($lot) => ['lot' => $lot, 'heat' => $lot->pourMouldAssembly?->pour?->heat, 'readiness' => $gates->lotReadiness($lot)]);
                $dispatched = (float) \DB::table('dispatch_items')->where('sales_order_line_id', $line->id)->sum('quantity_dispatched');
                $orders[] = ['po' => $po, 'lots' => $lots, 'moulding_runs' => \DB::table('moulding_runs')->where('production_order_id', $po->id)->count(), 'dispatched' => $dispatched];
            }
            $rows[] = ['line' => $line, 'orders' => $orders];
        }

        return view('sales-orders.trace', ['order' => $salesOrder, 'review' => $currentReview, 'rows' => $rows]);
    }
}
