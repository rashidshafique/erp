<?php

namespace App\Services;

use App\Models\ToolingLifeLimit;
use App\Models\ToolingUsageRecord;
use Illuminate\Support\Collection;

class ToolingLifeStatusService
{
    private const TOOLING_COLUMNS = [
        'PATTERN' => 'pattern_id',
        'PATTERN_REVISION' => 'pattern_revision_id',
        'CORE_DEFINITION' => 'core_definition_id',
        'CORE_BOX' => 'core_box_id',
        'MOULD_CONFIGURATION' => 'mould_configuration_id',
        'MOULD_ASSEMBLY' => 'mould_assembly_id',
        'FLASK' => 'flask_id',
    ];

    public function dashboard(): Collection
    {
        return ToolingLifeLimit::query()
            ->where('is_active', true)
            ->orderBy('tooling_type')
            ->get()
            ->map(fn (ToolingLifeLimit $limit) => $this->calculate($limit));
    }

    public function calculate(ToolingLifeLimit $limit): array
    {
        $column = self::TOOLING_COLUMNS[$limit->tooling_type] ?? null;

        if (! $column) {
            return [
                'limit' => $limit,
                'current_usage' => 0.0,
                'usage_since_inspection' => 0.0,
                'life_percent' => null,
                'inspection_percent' => null,
                'status' => 'CONFIGURATION_ERROR',
                'status_label' => 'Configuration error',
            ];
        }

        $usageQuery = ToolingUsageRecord::query()
            ->where($column, $limit->{$column});

        $currentUsage = (float) (clone $usageQuery)
            ->sum('usage_quantity');

        $usageSinceInspection = max(
            0,
            $currentUsage - (float) $limit->usage_at_last_inspection
        );

        $lifeLimit = $limit->service_life_quantity !== null
            ? (float) $limit->service_life_quantity
            : null;

        $inspectionLimit = $limit->inspection_interval_quantity !== null
            ? (float) $limit->inspection_interval_quantity
            : null;

        $lifePercent = $lifeLimit && $lifeLimit > 0
            ? ($currentUsage / $lifeLimit) * 100
            : null;

        $inspectionPercent = $inspectionLimit && $inspectionLimit > 0
            ? ($usageSinceInspection / $inspectionLimit) * 100
            : null;

        $threshold = (int) $limit->warning_threshold_percent;

        // Inspection takes precedence because it requires an action.
        if (
            $inspectionPercent !== null
            && $inspectionPercent >= 100
        ) {
            $status = 'INSPECTION_DUE';
            $label = 'Inspection due';
        } elseif (
            $lifePercent !== null
            && $lifePercent >= 100
        ) {
            $status = 'LIFE_EXCEEDED';
            $label = 'Past service-life limit';
        } elseif (
            ($inspectionPercent !== null && $inspectionPercent >= $threshold)
            || ($lifePercent !== null && $lifePercent >= $threshold)
        ) {
            $status = 'APPROACHING_LIMIT';
            $label = 'Approaching limit';
        } else {
            $status = 'OK';
            $label = 'Within limits';
        }

        return [
            'limit' => $limit,
            'current_usage' => $currentUsage,
            'usage_since_inspection' => $usageSinceInspection,
            'life_percent' => $lifePercent,
            'inspection_percent' => $inspectionPercent,
            'status' => $status,
            'status_label' => $label,
        ];
    }
}
