<?php

namespace App\Domains\Development\Models;

use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\Pattern;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One tool a development makes or changes: a pattern, core box, gauge, fixture or other. Its status feeds the development's gates. */
class PatternDevelopmentTool extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['retired_at' => 'datetime', 'due_on' => 'date', 'quoted_cost' => 'float', 'actual_cost' => 'float'];
    }

    /** @return string The status name from config, e.g. "Trial passed". */
    public function statusName(): string
    {
        return config('pattern_developments.tool_statuses.'.$this->status, $this->status);
    }

    /** Whether the tool has reached the status a gate asks for; a tool marked not needed always has. */
    public function reached(string $required): bool
    {
        if ($this->status === 'NOT_NEEDED') {
            return true;
        }
        $order = array_flip(config('pattern_developments.tool_progress'));

        return isset($order[$this->status]) && $order[$this->status] >= $order[$required];
    }

    /** The register code once it has one, e.g. CB-0146. */
    public function registerCode(): ?string
    {
        return $this->pattern?->code ?? $this->coreBox?->code;
    }

    /** Work has begun on it or money has gone on it, so stopping it needs a decision. */
    public function isStarted(): bool
    {
        return ! in_array($this->status, ['NOT_STARTED', 'NOT_NEEDED'], true) || (float) $this->actual_cost > 0;
    }

    /** How far along the Build and Trial mini bar it is: 0 to 3 steps. */
    public function steps(): int
    {
        return ['BUILDING' => 1, 'REWORK' => 1, 'BUILT' => 2, 'TRIAL_PASSED' => 3][$this->status] ?? 0;
    }

    public function development(): BelongsTo
    {
        return $this->belongsTo(PatternDevelopment::class, 'pattern_development_id');
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function coreBox(): BelongsTo
    {
        return $this->belongsTo(CoreBox::class)->withTrashed();
    }

    public function coreDefinition(): BelongsTo
    {
        return $this->belongsTo(CoreDefinition::class)->withTrashed();
    }

    /** The old pattern this tool replaces, retired when the development ends. */
    public function replacesPattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class, 'replaces_pattern_id')->withTrashed();
    }

    /** The old core box this tool replaces, retired when the development ends. */
    public function replacesCoreBox(): BelongsTo
    {
        return $this->belongsTo(CoreBox::class, 'replaces_core_box_id')->withTrashed();
    }
}
