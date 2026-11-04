<?php

namespace App\Services\Quality;

use App\Models\Heat;
use Illuminate\Support\Collection;

/**
 * What a tech has still to test, worked out from the floor rather than a test plan (the app holds none): a heat on the go with no
 * thermal test or no chemistry yet, and a lot that has come through its last stage with no final inspection. Oldest first.
 */
class TestsDue
{
    public function __construct(private PendingInspections $pending) {}

    /** @return Collection<int, array{kind:string, what:string, sub:string, since:\Illuminate\Support\Carbon, type:string, key:string}> */
    public function list(int $limit = 8): Collection
    {
        $heats = Heat::with(['furnace', 'ironGrade'])->withCount(['thermalTests', 'chemistryReadings'])
            ->where(fn ($q) => $q->where('status', 'IN_PROGRESS')->orWhere(fn ($p) => $p->where('status', 'PLANNED')->whereDate('planned_start_at', today())))
            ->get();
        $rows = collect();
        foreach ($heats as $h) {
            $since = $h->actual_start_at ?? $h->planned_start_at ?? $h->created_at;
            $sub = trim(($h->furnace?->code ?? '').' · '.($h->ironGrade?->code ?? ''), ' ·');
            if ($h->thermal_tests_count === 0) {
                $rows->push(['kind' => 'Thermal analysis', 'what' => $h->heat_number, 'sub' => $sub, 'since' => $since, 'type' => 'thermal', 'key' => $h->id]);
            }
            if ($h->chemistry_readings_count === 0 && $h->status === 'IN_PROGRESS') {
                $rows->push(['kind' => 'Spectro', 'what' => $h->heat_number, 'sub' => $sub, 'since' => $since, 'type' => 'spectro', 'key' => $h->id]);
            }
        }
        foreach ($this->pending->awaitingLots()->with('product')->oldest()->limit($limit)->get() as $lot) {
            $rows->push(['kind' => 'Hardness and inspection', 'what' => $lot->lot_number, 'sub' => trim(($lot->product?->code ?? '').' · '.$lot->quantity_good.' pcs', ' ·'), 'since' => $lot->created_at, 'type' => 'inspection', 'key' => $lot->id]);
        }

        return $rows->sortBy('since')->take($limit)->values();
    }
}
