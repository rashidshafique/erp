<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Services\Dashboard\DashboardData;
use Illuminate\Http\Request;

class ErpDashboardController extends Controller
{
    public function __invoke(Request $request, DashboardData $data)
    {
        $layout = match ($request->query('layout')) {   // the owner's home (4a) is the default; the pipeline view (1a) and the wall screen have their own links
            'telemetry' => 'telemetry',
            'classic' => 'classic',
            default => 'owner',
        };

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
            'owner' => ['o' => $data->owner()],
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
