<?php

namespace App\Domains\Development\Services;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Development\Models\PatternDevelopmentTool;
use App\Domains\Tooling\Models\ToolingLifeLimit;
use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\CoreRequirement;
use App\Models\Pattern;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Turns the tools of a development into register records and keeps them in step: a planned pattern or core box becomes a real
 * Pattern or CoreBox, a core box goes into service once it is built, and at the end the old tools a replacement stood in for are
 * retired. A gauge, fixture or other tool has no register.
 */
class ToolRegister
{
    /** Statuses at which a tool is made, so a core box may be used for trial cores. */
    private const MADE = ['BUILT', 'TRIAL_PASSED'];

    /** Whether the row can be given a register record now: a pattern or core box (when Tooling is on) that has none yet. */
    public function canCreate(PatternDevelopmentTool $tool): bool
    {
        if ($tool->pattern_id || $tool->core_box_id || $tool->isShared()) {
            return false;
        }

        return match ($tool->kind) {
            'PATTERN' => true,
            'CORE_BOX' => $this->toolingOn(),
            default => false,
        };
    }

    public function toolingOn(): bool
    {
        return app(ModuleRegistry::class)->isEnabled('tooling');
    }

    /**
     * Creates the Pattern or CoreBox for the row and links it. A core box starts out of service unless the row is already built.
     * The owner follows who pays unless the dialog says otherwise (design 18c).
     *
     * @param  array{owner_customer_id?: string|null, custody_location?: string|null, rated_life?: int|float|null, cavities?: int|null}  $details
     */
    public function create(PatternDevelopmentTool $tool, array $details = []): void
    {
        abort_unless($this->canCreate($tool), 422);
        $development = $tool->development;
        $ownerId = array_key_exists('owner_customer_id', $details) ? $details['owner_customer_id'] : ($tool->who_pays === 'CUSTOMER' ? $development->customer_id : null);
        $owner = ['owner_type' => $ownerId ? 'CUSTOMER' : 'FOUNDRY', 'owner_customer_id' => $ownerId, 'custody_location' => $details['custody_location'] ?? null];
        $life = $details['rated_life'] ?? null;
        DB::transaction(function () use ($tool, $development, $owner, $life, $details) {
            if ($tool->kind === 'PATTERN') {
                $pattern = new Pattern($owner + [
                    'name' => $tool->label, 'pattern_type' => 'OTHER', 'status' => 'ACTIVE',
                    'supplied_by_customer' => $tool->made_by === 'CUSTOMER_SUPPLIED',
                ]);
                $pattern->save();
                $tool->update(['pattern_id' => $pattern->id]);
                if (! $development->pattern_id) {
                    $development->update(['pattern_id' => $pattern->id]);
                }
                $this->ratedLife('pattern_id', $pattern->id, 'MOULDS', $life);
                $this->log($development, "Pattern {$pattern->code} added to the register for {$tool->label}.");

                return;
            }
            $core = CoreDefinition::create(['name' => $tool->label, 'core_type' => 'STANDARD', 'status' => 'ACTIVE']);
            $from = $tool->replacesCoreBox;
            $box = CoreBox::create($owner + [
                'core_definition_id' => $core->id, 'name' => $tool->label, 'revision' => 'A', 'cavities' => max(1, (int) ($details['cavities'] ?? 1)),
                'status' => in_array($tool->status, self::MADE, true) ? 'ACTIVE' : 'INACTIVE',
                'origin' => $from ? 'MODIFIED_BOX' : 'NEW', 'modified_from_core_box_id' => $from?->id,
                'commissioned_on' => in_array($tool->status, self::MADE, true) ? today() : null,
            ]);
            $tool->update(['core_box_id' => $box->id, 'core_definition_id' => $core->id]);
            $this->ratedLife('core_box_id', $box->id, 'CORES', $life);
            $this->log($development, "Core box {$box->code} added to the register for {$tool->label}, out of service until it is built.");
            $this->linkRequirements($development);
        });
    }

    /** Links a planned tool to a record already in the register, as "pattern:ID" or "box:ID". The kind must match. */
    public function link(PatternDevelopmentTool $tool, string $pick): void
    {
        abort_unless($this->canCreate($tool), 422);
        [$kind, $id] = array_pad(explode(':', $pick, 2), 2, null);
        $record = match (true) {
            $kind === 'pattern' && $tool->kind === 'PATTERN' => Pattern::findOrFail($id),
            $kind === 'box' && $tool->kind === 'CORE_BOX' => CoreBox::findOrFail($id),
            default => abort(422),
        };
        $tool->update([$kind === 'box' ? 'core_box_id' : 'pattern_id' => $record->id]);
        if ($kind === 'pattern' && ! $tool->development->pattern_id) {
            $tool->development->update(['pattern_id' => $record->id]);
        }
        $this->log($tool->development, "{$tool->label} linked to {$record->code} in the register.");
    }

    /**
     * Carries out the decision on each tool of a cancelled development. Tools nobody started are dropped. Spend stays on the tool
     * for billing. Only a record this development made new is scrapped: a tool it merely used or changed stays in the register.
     *
     * @param  array<string, string>  $decisions  tool id => HOLD, KEEP, SCRAP or DROP
     */
    public function settle(PatternDevelopment $development, array $decisions): void
    {
        foreach ($development->tools()->get() as $tool) {
            $decision = $decisions[$tool->id] ?? 'DROP';
            $name = config('pattern_developments.cancel_decisions.'.$decision);
            if ($decision === 'SCRAP' && $tool->route === 'NEW') {
                $tool->pattern?->update(['status' => 'RETIRED', 'custody' => 'SCRAPPED']);
                $tool->coreBox?->update(['status' => 'RETIRED']);
            }
            $tool->update(in_array($decision, ['SCRAP', 'DROP'], true) ? ['status' => 'NOT_NEEDED', 'status_note' => $name] : ['status_note' => $name]);
            if ($tool->isStarted() || isset($decisions[$tool->id])) {
                $this->log($development, "{$tool->label}: {$name}.");
            }
        }
    }

    /** Puts a core box this development created into service once its tool is built. */
    public function sync(PatternDevelopmentTool $tool): void
    {
        if (! $tool->core_definition_id || ! $tool->core_box_id || ! in_array($tool->status, self::MADE, true)) {
            return;
        }
        $box = $tool->coreBox;
        if ($box && $box->status === 'INACTIVE') {
            $box->update(['status' => 'ACTIVE', 'commissioned_on' => $box->commissioned_on ?? today()]);
            $this->log($tool->development, "Core box {$box->code} is in service: trial cores can be made.");
        }
    }

    /** Records the cores the development's new boxes make against the part's draft revision, once the part has one. */
    public function linkRequirements(PatternDevelopment $development): void
    {
        $revision = $development->product?->revisions()->where('status', 'DRAFT')->latest()->first();
        if (! $revision) {
            return;
        }
        foreach ($development->tools()->whereNotNull('core_definition_id')->get() as $tool) {
            CoreRequirement::firstOrCreate(['core_definition_id' => $tool->core_definition_id, 'product_revision_id' => $revision->id], ['quantity_per_casting' => 1]);
        }
    }

    /** At the end: each old tool a replacement stood in for is retired, if its new tool passed trial; otherwise it is left and said so. */
    public function retire(PatternDevelopment $development): void
    {
        foreach ($development->tools()->get() as $tool) {
            $old = $tool->replacesPattern ?? $tool->replacesCoreBox;
            if (! $old || $tool->retired_at) {
                continue;
            }
            if ($tool->status !== 'TRIAL_PASSED') {
                $this->log($development, "{$old->code} stays in use: {$tool->label} has not passed trial.");

                continue;
            }
            $old->update(['status' => 'RETIRED']);
            $tool->update(['retired_at' => now()]);
            $this->log($development, "{$old->code} retired.");
        }
    }

    /** A life limit for the new record when a rated life was given and Tooling is on. */
    private function ratedLife(string $column, string $id, string $unit, int|float|null $quantity): void
    {
        if (! $quantity || ! $this->toolingOn()) {
            return;
        }
        ToolingLifeLimit::create([
            'tooling_type' => $column === 'core_box_id' ? 'CORE_BOX' : 'PATTERN', $column => $id, 'usage_unit' => $unit,
            'service_life_quantity' => $quantity, 'warning_threshold_percent' => 80, 'usage_at_last_inspection' => 0, 'is_active' => true,
        ]);
    }

    private function log(PatternDevelopment $development, string $text): void
    {
        $development->events()->create(['kind' => 'NOTE', 'text' => $text, 'user_name' => Auth::user()?->name]);
    }
}
