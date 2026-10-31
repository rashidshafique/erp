<?php

namespace App\Domains\Development\Models;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryItem;
use App\Models\Concerns\GeneratesCodes;
use App\Models\CoreBox;
use App\Models\Customer;
use App\Models\Pattern;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** A new or replacement pattern worked through its stages with the customer. The stages are set by the type, in config/pattern_developments.php. */
class PatternDevelopment extends Model
{
    use GeneratesCodes, HasUuids;

    public string $codeKind = 'pattern_development';

    public const STATUSES = ['OPEN' => 'Open', 'DONE' => 'Done', 'CANCELLED' => 'Cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'quoted_tooling_cost' => 'float', 'actual_cost' => 'float'];
    }

    /**
     * Every development starts with its first tool, taken from what it was opened for: a new pattern, or for a replacement the
     * new tool standing in for the old pattern or core box, which is retired at the end, or for a modification the tool itself.
     */
    protected static function booted(): void
    {
        static::created(function (PatternDevelopment $d) {
            if ($d->tools()->exists()) {
                return;
            }
            $old = $d->coreBox ?? $d->pattern;
            $replacing = $d->type === 'REPLACEMENT' && $old !== null;
            $isBox = $d->coreBox !== null;
            $d->tools()->create([
                'kind' => $isBox ? 'CORE_BOX' : 'PATTERN',
                'label' => $replacing ? 'Replacement for '.trim($old->code.' '.$old->name) : ($old ? trim($old->code.' '.$old->name) : 'Pattern'),
                'route' => match (true) { $d->type === 'MODIFICATION' => 'MODIFY', $d->type === 'CUSTOMER_PATTERN' => 'EXISTING', default => 'NEW' },
                'made_by' => $d->pattern_by ?: 'DECIDE_LATER', 'maker' => $d->pattern_maker, 'who_pays' => $d->who_pays,
                'quoted_cost' => $d->quoted_tooling_cost, 'due_on' => $d->due_on,
                'pattern_id' => ! $isBox && ! $replacing ? $d->pattern_id : null, 'core_box_id' => $isBox && ! $replacing ? $d->core_box_id : null,
                'replaces_pattern_id' => $replacing && ! $isBox ? $d->pattern_id : null, 'replaces_core_box_id' => $replacing && $isBox ? $d->core_box_id : null,
            ]);
        });
    }

    /** @return array<string, mixed> The type's name, stages, copies and note. */
    public function typeInfo(): array
    {
        return config('pattern_developments.types.'.$this->type);
    }

    /** @return list<string> */
    public function stages(): array
    {
        return $this->typeInfo()['stages'];
    }

    public function stageName(): string
    {
        return $this->stages()[$this->stage_index] ?? '';
    }

    public function nextStageName(): ?string
    {
        return $this->stages()[$this->stage_index + 1] ?? null;
    }

    /** The part's draft revision that this development's last stage, Approved, also approves; none for a type that ends otherwise. */
    public function draftRevision(): ?ProductRevision
    {
        if (last($this->stages()) !== 'Approved') {
            return null;
        }

        return $this->product?->revisions()->where('status', 'DRAFT')->latest()->first();
    }

    /**
     * The status every tool must have reached before the development leaves its current stage: built before the trial, trial
     * passed before approval. Null when the stage has no gate. A stage is gated by its name.
     */
    public function gateStatus(): ?string
    {
        return self::gateFor($this->stageName());
    }

    /** The status a stage's tools must have reached before leaving it, by the stage's name. */
    public static function gateFor(string $name): ?string
    {
        return match (true) {
            str_contains($name, 'Trial') => 'TRIAL_PASSED',
            (bool) preg_match('/build|modification|rework/i', $name) => 'BUILT',
            default => null,
        };
    }

    /**
     * The tools holding up the move out of the current stage. Empty when the stage has no gate, or when the development is a plain
     * single pattern nobody has started tracking, so a small foundry is never nagged.
     *
     * @return Collection<int, PatternDevelopmentTool>
     */
    public function toolsInTheWay(): Collection
    {
        $required = $this->gateStatus();
        $tools = $this->tools;
        $tracked = $tools->count() > 1 || $tools->contains(fn (PatternDevelopmentTool $t) => $t->status !== 'NOT_STARTED');
        if ($required === null || ! $tracked) {
            return collect();
        }

        return $tools->reject(fn (PatternDevelopmentTool $t) => $t->reached($required))->values();
    }

    /** @return Collection<int, PatternDevelopmentTool> tools work or money has gone into */
    public function startedTools(): Collection
    {
        return $this->tools->filter(fn (PatternDevelopmentTool $t) => $t->isStarted())->values();
    }

    /** The list's short summary, e.g. "3 · 1 built", "2 · 1 passed trial" or "1 · not started". */
    public function toolSummary(): string
    {
        $tools = $this->tools;
        $passed = $tools->where('status', 'TRIAL_PASSED')->count();
        $built = $tools->filter(fn (PatternDevelopmentTool $t) => in_array($t->status, ['BUILT', 'TRIAL_PASSED'], true))->count();

        return $tools->count().' · '.match (true) {
            $passed > 0 => "{$passed} passed trial",
            $built > 0 || $this->startedTools()->isNotEmpty() => "{$built} built",
            default => 'not started',
        };
    }

    /** What the development waits on, e.g. "CB-0146 building, CB-0127 not started". Empty when no tool holds it up. */
    public function waitingOn(): string
    {
        return $this->toolsInTheWay()->map(fn (PatternDevelopmentTool $t) => ($t->registerCode() ?? $t->label).' '.strtolower($t->statusName()))->implode(', ');
    }

    /**
     * The developments that made, changed or replaced a register record, newest first.
     *
     * @return Collection<int, PatternDevelopment>
     */
    public static function touching(Pattern|CoreBox $record): Collection
    {
        [$made, $replaced] = $record instanceof CoreBox ? ['core_box_id', 'replaces_core_box_id'] : ['pattern_id', 'replaces_pattern_id'];

        return self::with('tools')->whereHas('tools', fn ($q) => $q->where($made, $record->id)->orWhere($replaced, $record->id))->latest()->get();
    }

    /** Keeps the development's actual and quoted cost equal to the sum of its tools, which older screens and the quote comparison read. */
    public function syncCosts(): void
    {
        $tools = $this->tools()->get();
        $this->update(['actual_cost' => $tools->whereNotNull('actual_cost')->isEmpty() ? null : $tools->sum('actual_cost')]);
    }

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function enquiryItem(): BelongsTo
    {
        return $this->belongsTo(EnquiryItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function tools(): HasMany
    {
        return $this->hasMany(PatternDevelopmentTool::class)->orderBy('created_at');
    }

    public function coreBox(): BelongsTo
    {
        return $this->belongsTo(CoreBox::class)->withTrashed();
    }

    public function events(): HasMany
    {
        return $this->hasMany(PatternDevelopmentEvent::class)->latest();
    }
}
