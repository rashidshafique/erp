<?php

namespace App\Services\Production;

use App\Models\PatternRevision;
use App\Models\ProcessControlPlan;
use App\Models\ProcessControlPlanItem;
use App\Models\ProductRevision;
use App\Support\ControlPlanRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Control plans per product revision. A revision follows the foundry's default plan ("Our process") until someone gives it its
 * own rows; a new revision copies the plan of the one before it. Approving a pattern revision marks the plans of the products
 * that pattern makes as needing a review, because a changed mould can need a changed process.
 */
class ControlPlans
{
    public function defaultPlan(): ProcessControlPlan
    {
        return ProcessControlPlan::where('is_default', true)->first()
            ?? ProcessControlPlan::create([
                'is_default' => true, 'plan_number' => 'CP-DEFAULT', 'title' => 'Our process', 'status' => 'APPROVED',
                'approved_at' => now(), 'approved_by' => 'Foundry default',
            ]);
    }

    /** The revision's plan, made on first use: a copy of the previous revision's plan, or a plan that follows the default. */
    public function forRevision(ProductRevision $revision): ProcessControlPlan
    {
        return ProcessControlPlan::where('product_revision_id', $revision->id)->first()
            ?? DB::transaction(function () use ($revision) {
                $previous = ProcessControlPlan::query()
                    ->whereIn('product_revision_id', ProductRevision::where('product_id', $revision->product_id)->where('id', '!=', $revision->id)->select('id'))
                    ->latest('created_at')->first();
                $code = $revision->product?->code ?? substr($revision->id, 0, 8);
                $plan = ProcessControlPlan::create([
                    'product_revision_id' => $revision->id,
                    'plan_number' => 'CP-'.$code.'-'.$revision->revision,
                    'title' => 'Control plan '.$code.' rev '.$revision->revision,
                    'follows_default' => ! $previous || $previous->follows_default,
                    'based_on_plan_id' => $previous?->id,
                    'status' => $this->inheritedStatus($previous),
                    'approved_at' => $previous && ! $previous->follows_default ? $previous->approved_at : now(),
                    'approved_by' => $previous && ! $previous->follows_default ? $previous->approved_by : 'Follows Our process',
                ]);
                if ($previous && ! $previous->follows_default) {
                    $this->copyItems($previous, $plan);
                }

                return $plan;
            });
    }

    /** @return Collection<int, ProcessControlPlanItem> the rows that apply: the default plan's while the plan follows it */
    public function effectiveItems(ProcessControlPlan $plan): Collection
    {
        return ($plan->follows_default ? $this->defaultPlan() : $plan)->items()->with('stageDefinition')->get();
    }

    /** Gives the plan its own rows, starting from a copy of the default plan's. */
    public function customise(ProcessControlPlan $plan): void
    {
        if (! $plan->follows_default) {
            return;
        }
        DB::transaction(function () use ($plan) {
            $this->copyItems($this->defaultPlan(), $plan);
            $plan->update(['follows_default' => false, 'status' => 'DRAFT', 'approved_at' => null, 'approved_by' => null]);
        });
    }

    /**
     * Replaces the plan's own rows.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function saveItems(ProcessControlPlan $plan, array $rows): void
    {
        DB::transaction(function () use ($plan, $rows) {
            $plan->items()->delete();
            foreach (array_values($rows) as $i => $row) {
                $name = trim((string) ($row['parameter_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $plan->items()->create([
                    'sequence' => $i + 1,
                    'parameter_code' => mb_substr(strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $name)), 0, 100),
                    'parameter_name' => $name,
                    'stage_definition_id' => $row['stage_definition_id'] ?? null,
                    'minimum_value' => $row['minimum_value'] ?? null,
                    'target_value' => $row['target_value'] ?? null,
                    'maximum_value' => $row['maximum_value'] ?? null,
                    'unit' => $row['unit'] ?? null,
                    'control_method' => $row['control_method'] ?? null,
                    'monitoring_frequency' => $row['monitoring_frequency'] ?? null,
                    'reaction_plan' => $row['reaction_plan'] ?? null,
                ]);
            }
            if (! $plan->is_default && $plan->status !== 'DRAFT') {
                $plan->update(['status' => 'DRAFT', 'approved_at' => null, 'approved_by' => null]);
            }
        });
    }

    public function approve(ProcessControlPlan $plan, string $name): void
    {
        $plan->update(['status' => 'APPROVED', 'approved_at' => now(), 'approved_by' => $name, 'needs_review_at' => null, 'review_reason' => null]);
    }

    /** Someone has looked at the plan after a pattern change and it still holds. */
    public function confirmReview(ProcessControlPlan $plan, string $name): void
    {
        $plan->update(['needs_review_at' => null, 'review_reason' => null, 'reviewed_at' => now(), 'reviewed_by' => $name]);
    }

    /** Marks the plan of every product the pattern revision makes. Returns how many plans were marked. */
    public function flagForPattern(PatternRevision $patternRevision): int
    {
        if (ControlPlanRules::get('pattern_change') === 'OFF') {
            return 0;
        }
        $reason = 'Pattern '.($patternRevision->pattern?->code ?? '').' revision '.$patternRevision->shortCode().' approved '.now()->toDateString();
        $count = 0;
        $ids = $patternRevision->cavityMap()->whereNotNull('product_revision_id')->pluck('product_revision_id')->unique();
        foreach (ProductRevision::with('product')->whereIn('id', $ids)->get() as $revision) {
            $this->forRevision($revision)->update(['needs_review_at' => now(), 'review_reason' => mb_substr($reason, 0, 300)]);
            $count++;
        }

        return $count;
    }

    /** @return array{0: string, 1: string}|null severity and message for the contract review; null when the check is off */
    public function status(ProductRevision $revision): ?array
    {
        $check = ControlPlanRules::get('control_plan_check');
        if ($check === 'OFF') {
            return null;
        }
        $plan = $this->forRevision($revision);
        if ($plan->needs_review_at) {
            $severity = ControlPlanRules::get('pattern_change') === 'PPAP' ? 'BLOCK' : 'WARN';

            return ['CONTROL_PLAN', $severity, "The control plan needs a review: {$plan->review_reason}. Confirm it still holds, or change it."];
        }
        if (! $plan->isApproved()) {
            return ['CONTROL_PLAN', $check, 'The control plan has its own rows and is not approved yet.'];
        }

        return ['CONTROL_PLAN', 'PASS', $plan->follows_default ? 'Follows Our process.' : 'An approved process control plan exists.'];
    }

    private function inheritedStatus(?ProcessControlPlan $previous): string
    {
        if (! $previous || $previous->follows_default) {
            return 'APPROVED';
        }
        // Full PPAP: a plan copied from one flagged for review waits for approval.
        if ($previous->needs_review_at && ControlPlanRules::get('pattern_change') === 'PPAP') {
            return 'DRAFT';
        }

        return $previous->status;
    }

    private function copyItems(ProcessControlPlan $from, ProcessControlPlan $to): void
    {
        foreach ($from->items as $item) {
            $to->items()->create(collect($item->getAttributes())->except(['id', 'process_control_plan_id', 'created_at', 'updated_at'])->all());
        }
    }
}
