<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ToolingMaintenanceRecord;
use Illuminate\Contracts\View\View;

class ToolingMaintenanceHistoryController extends Controller
{
    public function __invoke(
        ToolingMaintenanceRecord $toolingMaintenance
    ): View {
        $toolingMaintenance->load([
            'pattern',
            'patternRevision',
            'coreDefinition',
            'coreBox',
            'mouldConfiguration',
            'mouldAssembly',
        ]);

        $type = $toolingMaintenance->tooling_type;

        $foreignKey = match ($type) {
            'PATTERN' => 'pattern_id',
            'PATTERN_REVISION' => 'pattern_revision_id',
            'CORE_DEFINITION' => 'core_definition_id',
            'CORE_BOX' => 'core_box_id',
            'MOULD_CONFIGURATION' => 'mould_configuration_id',
            'MOULD_ASSEMBLY' => 'mould_assembly_id',
            default => abort(422, 'Unsupported tooling type.'),
        };

        $toolingId = $toolingMaintenance->{$foreignKey};

        $records = ToolingMaintenanceRecord::query()
            ->where('tooling_type', $type)
            ->where($foreignKey, $toolingId)
            ->with([
                'pattern',
                'patternRevision',
                'coreDefinition',
                'coreBox',
                'mouldConfiguration',
                'mouldAssembly',
            ])
            ->select('tooling_maintenance_records.*')
            ->addSelect(['attachments_count' => Attachment::selectRaw('count(*)')
                ->where('attachable_type', 'tooling_maintenance')
                ->whereNull('removed_at')
                ->whereRaw('attachments.attachable_id = CAST(tooling_maintenance_records.id AS VARCHAR)')])
            ->orderByDesc('reported_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $summaryQuery = ToolingMaintenanceRecord::query()
            ->where('tooling_type', $type)
            ->where($foreignKey, $toolingId);

        $summary = [
            'total_records' => (clone $summaryQuery)->count(),
            'completed_records' => (clone $summaryQuery)
                ->where('status', 'COMPLETED')
                ->count(),
            'breakdowns' => (clone $summaryQuery)
                ->where('activity_type', 'BREAKDOWN')
                ->count(),
            'downtime_hours' => (clone $summaryQuery)
                ->sum('downtime_hours'),
            'parts_cost' => (clone $summaryQuery)->sum('parts_cost'),
            'other_cost' => (clone $summaryQuery)->sum('other_cost'),
        ];

        return view('tooling-maintenance.history', [
            'toolingMaintenance' => $toolingMaintenance,
            'records' => $records,
            'summary' => $summary,
        ]);
    }
}
