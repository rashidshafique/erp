<?php

namespace App\Http\Controllers;

use App\Services\ToolingLifeStatusService;
use Illuminate\View\View;

class ToolingLifeDashboardController extends Controller
{
    public function __invoke(
        ToolingLifeStatusService $service
    ): View {
        $items = $service->dashboard();

        return view('tooling-life.dashboard', [
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