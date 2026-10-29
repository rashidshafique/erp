<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRevision extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        // A revision made without a weight starts with the product's nominal weight; every save keeps that weight in step with the
        // current revision.
        static::creating(function (ProductRevision $revision) {
            if ($revision->net_weight_kg === null && $revision->product_id) {
                $nominal = Product::withTrashed()->whereKey($revision->product_id)->value('nominal_weight_kg');
                $revision->net_weight_kg = $nominal !== null && (float) $nominal > 0 ? $nominal : null;
            }
        });
        static::created(function (ProductRevision $revision) {
            app(\App\Services\Production\ControlPlans::class)->forRevision($revision);
            $revision->inheritExtraSteps();
        });
        static::saved(fn (ProductRevision $revision) => Product::withTrashed()->find($revision->product_id)?->syncNominalWeight());
        static::deleted(fn (ProductRevision $revision) => Product::withTrashed()->find($revision->product_id)?->syncNominalWeight());
    }

    /** Prefix of a list choice that names a product with no revision yet instead of one of its revisions. */
    public const PRODUCT_CHOICE = 'product:';

    /** The revision a list choice stands for: the revision itself, or for "product:<id>" the product's linkable revision. */
    public static function fromChoice(string $choice): self
    {
        return str_starts_with($choice, self::PRODUCT_CHOICE)
            ? Product::findOrFail(substr($choice, strlen(self::PRODUCT_CHOICE)))->linkableRevision()
            : self::findOrFail($choice);
    }

    /** Whether a list choice names a product with no revision yet. */
    public static function isProductChoice(?string $choice): bool
    {
        return is_string($choice) && str_starts_with($choice, self::PRODUCT_CHOICE);
    }

    /** The name of a product's next revision: 0 for the first, then the next number; a product still on letters continues its letters. */
    public static function nextLabel(iterable $existing): string
    {
        $names = collect($existing)->map(fn ($r) => strtoupper(trim((string) $r)))->filter();
        if ($names->isEmpty()) {
            return '0';
        }
        if ($names->every(fn ($r) => ctype_digit($r))) {
            return (string) ($names->map(fn ($r) => (int) $r)->max() + 1);
        }
        if ($names->every(fn ($r) => preg_match('/^[A-Y]$/', $r))) {
            return chr(ord($names->max()) + 1);
        }
        for ($n = $names->count(); $names->contains((string) $n); $n++);

        return (string) $n;
    }

    protected $fillable = [
        'product_id',
        'revision',
        'status',
        'net_weight_kg',
        'engineering_notes',
        'effective_from',
        'effective_to',
        'customer_part_number',
        'csr',
        'approved_for',
        'ppap_level',
        'unit_price',
        'annual_volume',
        'packing_spec',
        'default_delivery',
    ];

    /** What a revision is approved for with the customer. */
    public const APPROVED_FOR = ['SERIAL' => 'Serial supply', 'SAMPLES' => 'Samples only', 'PPAP_PENDING' => 'PPAP pending'];

    public const DELIVERY = ['EX_WORKS' => 'Ex works', 'DELIVERED' => 'Delivered, customer plant'];

    protected $casts = [
        'net_weight_kg' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'ppap_level' => 'integer',
        'annual_volume' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();   // archived products still show on old records
    }

    /** The product-specific steps this revision goes through, besides the foundry's default process. */
    public function extraSteps(): BelongsToMany
    {
        return $this->belongsToMany(ProductionStageDefinition::class, 'product_revision_steps', 'product_revision_id', 'stage_definition_id')->withTimestamps();
    }

    /** A new revision starts with the extra steps of the revision before it. */
    public function inheritExtraSteps(): void
    {
        $previous = static::where('product_id', $this->product_id)->where('id', '!=', $this->id)->latest('created_at')->first();
        if ($previous) {
            $this->extraSteps()->sync($previous->extraSteps()->pluck('production_stage_definitions.id')->all());
        }
    }

    public function patternCavities(): HasMany
    {
        return $this->hasMany(PatternRevisionCavity::class);
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }

    public function customerRequirements(): HasMany
    {
        return $this->hasMany(CustomerProductRequirement::class);
    }

    /** @return HasMany<\App\Domains\Engineering\Models\BomLine, $this> */
    public function bomLines(): HasMany
    {
        return $this->hasMany(\App\Domains\Engineering\Models\BomLine::class);
    }

    public function manufacturingMethods(): HasMany
    {
        return $this->hasMany(ManufacturingMethod::class);
    }
}
