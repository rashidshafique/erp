<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Services\Dashboard\DashboardData;
use Illuminate\Http\Request;

class ErpDashboardController extends Controller
{
    public function __invoke(Request $request, DashboardData $data)
    {
        $layout = $request->query('layout') === 'telemetry' ? 'telemetry' : 'classic';   // one dashboard, as designed (1a); the wall screen is opened by its own link

        $openOrders = ProductionOrder::open()->count();

        $inProgressOrders = ProductionOrder::where(
            'status',
            'IN_PROGRESS'
        )->count();

        $releasedOrders = ProductionOrder::where(
            'status',
            'RELEASED'
        )->count();

        $completedToday = ProductionOrder::where(
            'status',
            'COMPLETED'
        )
            ->whereDate('actual_completed_at', today())
            ->count();

        $rejectedQuantity = ProductionOrder::open()
            ->sum('rejected_qty');

        $recentOrders = ProductionOrder::query()
            ->with(['product', 'productRevision'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $extra = match ($layout) {
            'classic' => (fn (array $d) => ['d' => $d, 'headline' => $data->headline($d['daily']), 'pipeline' => $data->pipeline(), 'melt' => $data->melt()])($data->all()),
            'telemetry' => ['d' => $data->telemetry()],
            default => ['d' => $data->all()],
        };

        $response = response()->view('dashboard.'.$layout, $extra + ['layout' => $layout,
            'openOrders' => $openOrders,
            'inProgressOrders' => $inProgressOrders,
            'releasedOrders' => $releasedOrders,
            'completedToday' => $completedToday,
            'rejectedQuantity' => $rejectedQuantity,
            'recentOrders' => $recentOrders,
        ]);

        return $response;
    }
}
