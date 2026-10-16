<?php

namespace App\Services;

use App\Models\MouldCavity;
use App\Models\MouldingRun;
use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use App\Models\ProductionOrder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Rules for the pattern cavity map: which product (or product component)
 * each cavity of a pattern revision makes.
 */
class PatternCavityMap
{
    public function hasMap(string $patternRevisionId): bool
    {
        return PatternRevisionCavity::where('pattern_revision_id', $patternRevisionId)->exists();
    }

    /** Validate a cavity definition before it is saved. Throws ValidationException. */
    public function assertDefinable(PatternRevision $revision, array $data, ?string $ignoreId = null): void
    {
        $errors = [];
        $max = $revision->cavities_per_mould;

        if ($max !== null && (int) $data['cavity_number'] > (int) $max) {
            $errors['cavity_number'] = "This pattern revision has {$max} cavities per mould; cavity {$data['cavity_number']} does not exist.";
        }

        $clash = PatternRevisionCavity::where('pattern_revision_id', $revision->id)
            ->where('cavity_number', $data['cavity_number'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($clash) {
            $errors['cavity_number'] = "Cavity {$data['cavity_number']} already has a product assigned on this revision.";
        }

        $product = \App\Models\ProductRevision::find($data['product_revision_id']);
        if ($product && $product->status === 'OBSOLETE') {
            $errors['product_revision_id'] = 'An obsolete product revision cannot be linked to a pattern cavity.';
        }

        if (($data['cavity_role'] ?? 'WHOLE') === 'PART' && blank($data['component_label'] ?? null)) {
            $errors['component_label'] = 'Name the component this cavity makes.';
        }

        $cw = $data['casting_weight_kg'] ?? null;
        $pw = $data['poured_weight_kg'] ?? null;
        if ($cw !== null && $pw !== null && (float) $pw < (float) $cw) {
            $errors['poured_weight_kg'] = 'Poured weight (casting plus gating and risers) cannot be below the casting weight.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** A mould cavity must match the map once the pattern revision has one. */
    public function assertMouldCavityMatches(MouldCavity $cavity): void
    {
        if (! $cavity->pattern_revision_id || ! $this->hasMap($cavity->pattern_revision_id)) {
            return;
        }

        $entry = PatternRevisionCavity::where('pattern_revision_id', $cavity->pattern_revision_id)
            ->where('cavity_number', $cavity->cavity_number)
            ->first();

        if (! $entry) {
            throw ValidationException::withMessages([
                'cavity_number' => "Cavity {$cavity->cavity_number} is not defined in the cavity map of this pattern revision.",
            ]);
        }

        if ($entry->product_revision_id !== $cavity->product_revision_id) {
            throw ValidationException::withMessages([
                'product_revision_id' => "Cavity {$cavity->cavity_number} of this pattern revision makes a different product revision according to the cavity map.",
            ]);
        }
    }

    /** Products a pattern revision can make, as a list for display and checks. */
    public function productsFor(PatternRevision $revision): Collection
    {
        return $revision->cavityMap()->with('productRevision.product')->orderBy('cavity_number')->get();
    }

    /** Block a moulding run whose pattern cannot make the production order's product. */
    public function assertPatternMakesOrderProduct(?string $patternRevisionId, string $productionOrderId): void
    {
        if (! $patternRevisionId || ! $this->hasMap($patternRevisionId)) {
            return;
        }

        $order = ProductionOrder::find($productionOrderId);
        if (! $order) {
            return;
        }

        $makes = PatternRevisionCavity::where('pattern_revision_id', $patternRevisionId)
            ->whereHas('productRevision', fn ($q) => $q->where('product_id', $order->product_id))
            ->exists();

        if (! $makes) {
            throw ValidationException::withMessages([
                'pattern_revision_id' => 'This pattern revision has no cavity for the product on the selected production order.',
            ]);
        }
    }

    /**
     * Expected castings per mould for the product of an order on a pattern revision.
     * WHOLE cavities count one casting each; PART cavities are converted to complete products.
     */
    public function expectedProductsPerMould(PatternRevision $revision, string $productId): float
    {
        $total = 0.0;
        $partsByLabel = [];

        foreach ($revision->cavityMap()->whereHas('productRevision', fn ($q) => $q->where('product_id', $productId))->get() as $c) {
            if ($c->cavity_role === 'WHOLE') {
                $total += 1 / max(1, $c->pieces_per_product);
            } else {
                $partsByLabel[$c->component_label] = ($partsByLabel[$c->component_label] ?? 0) + 1 / max(1, $c->pieces_per_product);
            }
        }

        if ($partsByLabel) {
            $total += min($partsByLabel);
        }

        return round($total, 3);
    }

    /** Existing mould cavities that disagree with the map. */
    public function mismatches(): Collection
    {
        return MouldCavity::query()
            ->with(['patternRevision.pattern', 'productRevision.product'])
            ->get()
            ->filter(function (MouldCavity $c) {
                if (! $this->hasMap($c->pattern_revision_id)) {
                    return false;
                }
                $entry = PatternRevisionCavity::where('pattern_revision_id', $c->pattern_revision_id)
                    ->where('cavity_number', $c->cavity_number)->first();

                return ! $entry || $entry->product_revision_id !== $c->product_revision_id;
            })->values();
    }
}
