<?php

namespace App\Services;

use App\Models\Furnace;
use App\Models\FurnaceGradeCapability;
use App\Models\IronGrade;
use Illuminate\Support\Collection;

class FurnaceEligibility
{
    public function canMelt(Furnace $furnace, IronGrade $grade): bool
    {
        if ($furnace->status !== 'ACTIVE') {
            return false;
        }
        $override = FurnaceGradeCapability::where('furnace_id', $furnace->id)->where('iron_grade_id', $grade->id)->first();
        if ($override) {
            return (bool) $override->allowed;
        }
        $families = match ($furnace->furnace_type) {
            'CUPOLA' => config('foundry.planner.cupola_families'),
            'INDUCTION' => config('foundry.planner.induction_families'),
            default => [],
        };

        return in_array($grade->family, $families, true);
    }

    /** @return Collection<int, Furnace> */
    public function eligible(IronGrade $grade): Collection
    {
        return Furnace::query()->where('status', 'ACTIVE')->orderBy('code')->get()
            ->filter(fn (Furnace $f) => $this->canMelt($f, $grade))->values();
    }

    public function reason(Furnace $furnace, IronGrade $grade): string
    {
        if ($furnace->status !== 'ACTIVE') {
            return "{$furnace->code} is {$furnace->status}.";
        }

        return $this->canMelt($furnace, $grade)
            ? "{$furnace->code} can melt {$grade->code}."
            : "{$furnace->code} ({$furnace->furnace_type}) is not set up to melt {$grade->family} iron.";
    }
}
