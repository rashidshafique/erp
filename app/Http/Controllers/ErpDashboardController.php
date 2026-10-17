<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Services\Dashboard\DashboardData;
use App\Support\UiSettings;
use Illuminate\Http\Request;

class ErpDashboardController extends Controller
{
    public function __invoke(Request $request, DashboardData $data)
    {
        $layout = UiSettings::dashboardLayout($request->query('layout'), $request->cookie('dash_layout'));

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
            'classic' => [],
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

        return $request->filled('layout') && in_array($request->query('layout'), UiSettings::DASHBOARD_LAYOUTS, true) ? $response->cookie('dash_layout', $request->query('layout'), 60 * 24 * 365) : $response;
    }
}
