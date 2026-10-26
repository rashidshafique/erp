<?php

namespace App\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Pattern extends Model
{
    use GeneratesCodes, HasUuids, SoftDeletes;

    public string $codeKind = 'pattern';

    /** Set for a pattern typed in on the form: the engraved mark stays blank until one is recorded or its label is printed. */
    public bool $withoutMark = false;

    public function marksOnCreate(): bool
    {
        return ! $this->withoutMark;
    }

    /**
     * Pattern types: what the tooling physically is. This is not how the mould is built; each type maps to the mould
     * arrangement it is usually used for (PatternRevision::ARRANGEMENTS), which is only the starting choice on a revision.
     */
    public const TYPES = [
        'MATCHPLATE' => 'COPE_DRAG', 'COPE_DRAG' => 'COPE_DRAG', 'STACK_PLATE' => 'STACK', 'VERTICAL_PLATES' => 'VERTICAL',
        'LOOSE_PATTERN' => 'COPE_DRAG', 'SPLIT_PATTERN' => 'COPE_DRAG', 'GATED' => 'COPE_DRAG', 'SHELL_PLATE' => 'SHELL', 'OTHER' => 'OTHER',
    ];

    public const TYPE_LABELS = [
        'MATCHPLATE' => 'Matchplate (cope and drag patterns on one plate)',
        'COPE_DRAG' => 'Cope and drag plates (a plate each)',
        'STACK_PLATE' => 'Stack mould plate',
        'VERTICAL_PLATES' => 'Vertical plates (DISA type)',
        'LOOSE_PATTERN' => 'Loose pattern',
        'SPLIT_PATTERN' => 'Split pattern',
        'GATED' => 'Gated pattern (runners on the pattern)',
        'SHELL_PLATE' => 'Shell pattern plate',
        'OTHER' => 'Other',
    ];

    /** The readable name of a pattern type; the part before the bracket when $short. */
    public static function typeName(?string $type, bool $short = false): string
    {
        $label = self::TYPE_LABELS[$type] ?? ucfirst(strtolower(str_replace('_', ' ', (string) ($type ?: 'other'))));

        return $short ? trim(\Illuminate\Support\Str::before($label, ' (')) : $label;
    }

    protected $fillable = [
        'mark_code',
        'code',
        'name',
        'pattern_type',
        'sand_system',
        'preferred_moulding_station_id',
        'flasks_freed',
        'compatible_sands',
        'sand_note',
        'status',
        'material',
        'length_mm',
        'width_mm',
        'height_mm',
        'description',
        'notes',
        'owner_type',
        'owner_customer_id',
        'supplied_by_customer',
        'ownership_quotation_id',
        'ownership_reference',
        'acquired_on',
        'insured_value',
        'amortise_qty',
        'ownership_notes',
        'custody',
        'custody_location',
        'condition_grade',
        'condition_graded_on',
    ];

    /** How fit the physical tool is for moulding, as graded at the last inspection. */
    public const CONDITIONS = ['A' => 'Good', 'B' => 'Worn, usable', 'C' => 'Needs repair', 'D' => 'Unfit for use'];

    /** Who owns the pattern: the foundry, a customer (held in the foundry's custody), or a customer paying it off. */
    public const OWNERS = ['FOUNDRY' => 'Foundry', 'CUSTOMER' => 'Customer', 'PAYING_OFF' => 'Being paid off by the customer'];

    /** Where the pattern physically is. Returned and scrapped patterns cannot be moulded. */
    public const CUSTODIES = ['IN_STORE' => 'In our store', 'PATTERN_SHOP' => 'At the pattern shop', 'LENT' => 'Lent to another supplier', 'RETURNED' => 'Returned to the customer', 'SCRAPPED' => 'Scrapped'];

    protected $casts = [
        'supplied_by_customer' => 'boolean',
        'acquired_on' => 'date',
        'condition_graded_on' => 'date',
        'insured_value' => 'decimal:2',
        'amortise_qty' => 'integer',
        'compatible_sands' => 'array',
        'length_mm' => 'decimal:2',
        'width_mm' => 'decimal:2',
        'height_mm' => 'decimal:2',
    ];

    public function preferredStation(): BelongsTo
    {
        return $this->belongsTo(MouldingStation::class, 'preferred_moulding_station_id');
    }

    /** The sand the pattern was made for: the one chosen by hand, else the default of its preferred station. */
    public function madeForSand(): ?string
    {
        return $this->sand_system ?: ($this->preferredStation?->default_sand_system ?: null);
    }

    /** Whether the pattern suits a sand system: it was made for it, is listed as working on it, or no sand has been recorded for it. */
    public function suitsSand(?string $sand): bool
    {
        if (! $sand || ! $this->madeForSand()) {
            return true;
        }

        return $sand === $this->madeForSand() || in_array($sand, $this->compatible_sands ?? [], true);
    }

    /** A plain-words warning when the pattern is not suited to the sand, else null. */
    public function sandWarning(?string $sand): ?string
    {
        if ($this->suitsSand($sand)) {
            return null;
        }

        return "Pattern {$this->code} was made for {$this->madeForSand()} and is not listed as suitable for {$sand}: another sand system usually needs a different taper allowance."
            .($this->sand_note ? " Note: {$this->sand_note}" : '');
    }

    public function ownerCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'owner_customer_id')->withTrashed();
    }

    public function ownershipQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'ownership_quotation_id');
    }

    public function ownershipEvents(): HasMany
    {
        return $this->hasMany(PatternOwnershipEvent::class)->orderByDesc('occurred_on')->orderByDesc('created_at');
    }

    /** Owned or being paid off by a customer: the pattern is theirs to decide about. */
    public function isCustomers(): bool
    {
        return $this->owner_type !== 'FOUNDRY' && $this->owner_customer_id !== null;
    }

    /** "Foundry", or "ACME Pumps" / "ACME Pumps (paying off)". */
    public function ownerLabel(): string
    {
        return $this->owner_type === 'FOUNDRY' ? 'Foundry' : ($this->ownerCustomer?->name ?? 'Customer').($this->owner_type === 'PAYING_OFF' ? ' (paying off)' : '');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PatternRevision::class);
    }

    public function currentRevision(): HasOne
    {
        return $this->hasOne(PatternRevision::class)->where('is_current', true);
    }

    /**
     * Records a new revision of the tooling geometry and makes it current. The previous current revision is superseded and
     * kept; the new one carries over its layout (parting, arrangement, cavities, sections, levels, shared metal) unless given. With $copyLayout, each
     * cavity of the previous revision is copied onto the new one and stays as it was until changed; the old cavities are left alone.
     *
     * @param  array<string, mixed>  $attributes
     */
    /** Patterns whose current revision has no cavity linked to a product, including patterns with no revision yet. */
    public function scopeUnlinked(Builder $query): Builder
    {
        return $query->whereDoesntHave('currentRevision', fn ($r) => $r->whereHas('cavityMap', fn ($c) => $c->whereNotNull('product_revision_id')));
    }

    public function addRevision(array $attributes, bool $copyLayout = false): PatternRevision
    {
        return DB::transaction(function () use ($attributes, $copyLayout) {
            static::whereKey($this->id)->lockForUpdate()->first();
            $previous = $this->revisions()->where('is_current', true)->first();
            if ($previous) {
                // Cleared first: the database allows one current revision per pattern at any moment.
                $previous->update(['is_current' => false, 'superseded_at' => now(), 'effective_to' => $previous->effective_to ?? today()]);
            }
            $carried = $previous ? $previous->only(['parting_type', 'cavities_per_mould', 'layers_per_mould', 'pattern_weight_kg', 'mould_arrangement', 'casting_levels', 'runner_weight_per_level_kg', 'sprue_weight_kg', 'poured_weight_per_mould_kg']) : [];
            $revision = $this->revisions()->create(array_merge($carried, array_filter($attributes, fn ($v) => $v !== null), [
                'status' => $attributes['status'] ?? 'APPROVED', 'is_current' => true, 'superseded_at' => null, 'legacy_baseline' => false,
                'effective_from' => $attributes['effective_from'] ?? today(),
            ]));
            if ($previous) {
                // Process limits set on the old geometry still apply until someone changes them.
                DB::table('pattern_revisions')->where('id', $revision->id)
                    ->update(['min_cooling_minutes' => DB::table('pattern_revisions')->where('id', $previous->id)->value('min_cooling_minutes')]);
            }
            if ($previous) {
                // The flask sizes the old geometry fitted still hold until someone changes them.
                $revision->flaskSizes()->attach($previous->flaskSizes()->get()->mapWithKeys(fn ($size) => [$size->id => ['is_primary' => (bool) $size->pivot->is_primary]])->all());
            }
            if ($previous && $copyLayout) {
                foreach ($previous->cavityMap()->get() as $cavity) {
                    $revision->cavityMap()->create($cavity->only([
                        'cavity_number', 'cavity_mark', 'product_revision_id', 'cavity_role', 'component_label', 'pieces_per_product', 'casting_weight_kg', 'poured_weight_kg', 'notes', 'is_closed',
                    ]) + ['copied_from_revision_id' => $previous->id, 'needs_review' => false]);
                }
            }
            $this->unsetRelation('revisions')->unsetRelation('currentRevision');
            if ($previous && $revision->status === 'APPROVED') {
                app(\App\Services\Production\ControlPlans::class)->flagForPattern($revision);
            }

            return $revision->fresh();
        });
    }
}
