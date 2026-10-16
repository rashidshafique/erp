<?php

namespace App\Http\Controllers;

use App\Models\ToolingMaintenanceRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ToolingMaintenanceDashboardController extends Controller
{
    public function __invoke(): View
    {
        $activeStatuses = ['OPEN', 'IN_PROGRESS', 'ON_HOLD'];

        $active = ToolingMaintenanceRecord::query()
            ->whereIn('status', $activeStatuses);

        $urgent = (clone $active)
            ->where('priority', 'URGENT')
            ->count();

        $breakdowns = (clone $active)
            ->where('activity_type', 'BREAKDOWN')
            ->count();

        $overdue = ToolingMaintenanceRecord::query()
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->whereNotNull('next_inspection_due_on')
            ->whereDate('next_inspection_due_on', '<', today())
            ->where('status', '!=', 'CANCELLED')
            ->count();

        $openCount = (clone $active)->count();

        $totals = ToolingMaintenanceRecord::query()
            ->selectRaw('COALESCE(SUM(downtime_hours), 0) AS downtime_hours')
            ->selectRaw('COALESCE(SUM(parts_cost), 0) AS parts_cost')
            ->selectRaw('COALESCE(SUM(other_cost), 0) AS other_cost')
            ->first();

        $recentActive = ToolingMaintenanceRecord::query()
            ->with([
                'pattern',
                'patternRevision',
                'coreDefinition',
                'coreBox',
                'mouldConfiguration',
                'mouldAssembly',
            ])
            ->whereIn('status', $activeStatuses)
            ->orderByRaw("CASE priority
                WHEN 'URGENT' THEN 1
                WHEN 'HIGH' THEN 2
                WHEN 'NORMAL' THEN 3
                ELSE 4 END")
            ->latest('reported_at')
            ->limit(15)
            ->get();

        return view('tooling-maintenance.dashboard', [
            'openCount' => $openCount,
            'urgentCount' => $urgent,
            'breakdownCount' => $breakdowns,
            'overdueCount' => $overdue,
            'downtimeHours' => (float) $totals->downtime_hours,
            'maintenanceSpend' => (float) $totals->parts_cost
                + (float) $totals->other_cost,
            'recentActive' => $recentActive,
        ]);
    }
}