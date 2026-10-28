<?php

namespace App\Domains\Enquiry\Services;

use App\Domains\Enquiry\Models\EnquiryItem;
use App\Models\IronGrade;
use App\Models\Product;
use App\Services\Planning\HeatPlanner;
use Illuminate\Support\Collection;

/**
 * Parts we already make that look like the one asked for (design 1b): the same grade, or failing that the same iron family, and a
 * weight within 30% when the weight is known. A grade match ranks above a family match, then the closest weight first.
 */
class SimilarParts
{
    /** How far the weight may be from the asked one, as a share of it. */
    private const WEIGHT_BAND = 0.3;

    public function __construct(private HeatPlanner $planner) {}

    /** @return Collection<int, array{product: Product, grade: ?string, weight: ?float, pattern: ?string}> */
    public function for(?EnquiryItem $item, int $limit = 3): Collection
    {
        if (! $item) {
            return collect();
        }
        $askedGrade = IronGrade::resolve($item->material_text)['grade'] ?? null;
        $family = $askedGrade?->family ?? $item->family;
        $weight = $item->weight_kg ? (float) $item->weight_kg : null;
        if (! $askedGrade && ! $family) {
            return collect();
        }

        $candidates = Product::query()->where('active', true)
            ->when($item->product_id, fn ($q) => $q->whereKeyNot($item->product_id))
            ->when($weight, fn ($q) => $q->whereBetween('nominal_weight_kg', [$weight * (1 - self::WEIGHT_BAND), $weight * (1 + self::WEIGHT_BAND)]))
            ->with('revisions.patternCavities.patternRevision.pattern')
            ->limit(200)->get();

        return $candidates->map(function (Product $product) {
            $revisions = $product->revisions->sortByDesc('created_at');
            $revision = $revisions->firstWhere('status', 'APPROVED') ?? $revisions->firstWhere('status', '!=', 'OBSOLETE') ?? $revisions->first();
            $grade = $revision ? $this->planner->gradeFor($revision) : null;
            $pattern = $revision?->patternCavities->map(fn ($c) => $c->patternRevision?->pattern)->filter()->first();

            return ['product' => $product, 'grade' => $grade, 'weight' => $product->nominal_weight_kg !== null ? (float) $product->nominal_weight_kg : null, 'pattern' => $pattern?->code];
        })
            ->filter(fn ($row) => $row['grade'] && (($askedGrade && $row['grade']->id === $askedGrade->id) || $row['grade']->family === $family))
            ->sortBy([
                fn ($a, $b) => (int) ($askedGrade && $b['grade']->id === $askedGrade->id) <=> (int) ($askedGrade && $a['grade']->id === $askedGrade->id),
                fn ($a, $b) => ($weight === null ? 0 : abs(($a['weight'] ?? INF) - $weight)) <=> ($weight === null ? 0 : abs(($b['weight'] ?? INF) - $weight)),
            ])
            ->take($limit)
            ->map(fn ($row) => [...$row, 'grade' => $row['grade']->code])
            ->values();
    }
}
