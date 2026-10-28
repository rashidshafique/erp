<?php

namespace App\Services\Stock;

use App\Models\StockLocation;
use Illuminate\Support\Collection;

/** The stock locations laid out as a tree, each with what it holds itself and what it holds with everything below it. */
class StockLocationTree
{
    /**
     * Rows in tree order, a parent before its children, each level sorted by code. A location whose parent is not in the given
     * set (filtered out or switched off) is listed at the top.
     *
     * @param  Collection<int, StockLocation>  $locations
     * @param  array<string, float>  $held  Location id => kg held there.
     * @return list<array{location: StockLocation, depth: int, own: float, total: float, children: int}>
     */
    public function rows(Collection $locations, array $held): array
    {
        $byParent = $locations->sortBy('code')->groupBy(fn (StockLocation $l) => $locations->contains('id', $l->parent_location_id) ? $l->parent_location_id : '');
        $totals = [];
        $total = function (string $id) use (&$total, &$totals, $byParent, $held): float {
            return $totals[$id] ??= round(($held[$id] ?? 0.0) + $byParent->get($id, collect())->sum(fn (StockLocation $child) => $total($child->id)), 3);
        };

        $rows = [];
        $walk = function (string $parent, int $depth) use (&$walk, &$rows, $byParent, $held, $total): void {
            foreach ($byParent->get($parent, collect()) as $location) {
                $rows[] = ['location' => $location, 'depth' => $depth, 'own' => $held[$location->id] ?? 0.0,
                    'total' => $total($location->id), 'children' => $byParent->get($location->id, collect())->count()];
                $walk($location->id, $depth + 1);
            }
        };
        $walk('', 0);

        return $rows;
    }
}
