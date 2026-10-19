<?php

namespace App\Domains\Tooling\Http\Controllers;

use App\Domains\Tooling\Services\ToolingLifeStatusService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ToolingLifeDashboardController extends Controller
{
    public function __invoke(
        ToolingLifeStatusService $service
    ): View {
        $items = $service->dashboard();

        return view('tooling::tooling-life.dashboard', [
            'items' => $items,
            'inspectionDueCount' => $items->where(
                'status',
                'INSPECTION_DUE'
            )->count(),
            'lifeExceededCount' => $items->where(
                'status',
                'LIFE_EXCEEDED'
            )->count(),
            'approachingCount' => $items->where(
                'status',
                'APPROACHING_LIMIT'
            )->count(),
            'withinLimitsCount' => $items->where(
                'status',
                'OK'
            )->count(),
        ]);
    }
}
