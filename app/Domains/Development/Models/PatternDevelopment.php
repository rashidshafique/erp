<?php

namespace App\Domains\Development\Models;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryItem;
use App\Models\Concerns\GeneratesCodes;
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

    /** Every development starts with its pattern as the first tool, taken from the pattern fields it was opened with. */
    protected static function booted(): void
    {
        static::created(function (PatternDevelopment $d) {
            if ($d->tools()->exists()) {
                return;
            }
            $routes = ['MODIFICATION' => 'MODIFY', 'CUSTOMER_PATTERN' => 'EXISTING'];
            $d->tools()->create([
                'kind' => 'PATTERN', 'label' => 'Pattern', 'route' => $routes[$d->type] ?? 'NEW', 'made_by' => $d->pattern_by ?: 'DECIDE_LATER',
                'maker' => $d->pattern_maker, 'who_pays' => $d->who_pays, 'quoted_cost' => $d->quoted_tooling_cost, 'due_on' => $d->due_on, 'pattern_id' => $d->pattern_id,
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

    public function events(): HasMany
    {
        return $this->hasMany(PatternDevelopmentEvent::class)->latest();
    }
}
