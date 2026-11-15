<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoreBox extends Model
{
    use HasUuids, SoftDeletes, \App\Models\Concerns\GeneratesCodes;

    public string $codeKind = 'core_box';

    public const ORIGINS = [
        'NEW' => 'New box',
        'MODIFIED_BOX' => 'Modified from another core box',
        'MODIFIED_PATTERN' => 'Modified from a pattern',
    ];

    protected $fillable = [
        'mark_code',
        'origin',
        'modified_from_core_box_id',
        'modified_from_pattern_id',
        'core_definition_id',
        'code',
        'name',
        'revision',
        'status',
        'material',
        'cavities',
        'nominal_core_weight_kg',
        'description',
        'notes',
        'commissioned_on',
        'owner_type',
        'owner_customer_id',
        'custody',
        'custody_location',
        'ownership_reference',
        'return_notice_days',
    ];

    /** Custodies a core box can be set to by hand; see CUSTODY_LABELS. */
    public const CUSTODIES = ['IN_STORE', 'PATTERN_SHOP', 'LENT'];

    /** Where a core box can be. Lent is a box given to a third party who makes the cores with it. */
    public const CUSTODY_LABELS = ['IN_STORE' => 'In our store', 'PATTERN_SHOP' => 'At the pattern shop', 'LENT' => 'Lent to a third party for core making'];

    /** The statuses the lists and the page head show: Lent is an active box that is out with a third party. */
    public const STATUS_LABELS = ['ACTIVE' => 'In use', 'LENT' => 'Lent', 'INACTIVE' => 'Inactive', 'RETIRED' => 'Retired'];

    protected $casts = [
        'return_notice_days' => 'integer',
        'cavities' => 'integer',
        'nominal_core_weight_kg' => 'decimal:4',
        'commissioned_on' => 'date',
    ];

    public function coreDefinition(): BelongsTo
    {
        return $this->belongsTo(CoreDefinition::class);
    }

    public function ownerCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'owner_customer_id')->withTrashed();
    }

    /** e.g. "Foundry-owned" or "Nordline · customer-owned". */
    public function ownerLabel(): string
    {
        return $this->owner_type === 'FOUNDRY' || ! $this->ownerCustomer ? 'Foundry-owned' : $this->ownerCustomer->name.' · customer-owned';
    }

    /** ACTIVE, LENT (active and lent to a third party), INACTIVE or RETIRED; a key of STATUS_LABELS. */
    public function displayStatus(): string
    {
        return $this->status === 'ACTIVE' && $this->custody === 'LENT' ? 'LENT' : (string) $this->status;
    }

    /** e.g. "In our store · CS-R2-04". */
    public function custodyLabel(): string
    {
        return (self::CUSTODY_LABELS[$this->custody] ?? $this->custody).($this->custody_location ? ' · '.$this->custody_location : '');
    }

    protected static function booted(): void
    {
        // A box made without a cavity map (an import, say) makes its core in every cavity.
        static::created(function (CoreBox $box) {
            if ($box->core_definition_id && ! $box->cavityMap()->exists()) {
                $box->saveCavityMap(array_fill(1, max(1, (int) $box->cavities), $box->core_definition_id));
            }
        });
    }

    /** The cores this box makes, one row per cavity. */
    public function cavityMap(): HasMany
    {
        return $this->hasMany(CoreBoxCavity::class)->orderBy('cavity_number');
    }

    public function modifiedFromBox(): BelongsTo
    {
        return $this->belongsTo(self::class, 'modified_from_core_box_id')->withTrashed();
    }

    public function modifiedFromPattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class, 'modified_from_pattern_id')->withTrashed();
    }

    /**
     * Sets the core made in each cavity and keeps the box's main core (the one in its lowest filled cavity) in step.
     *
     * @param  array<int, string|null>  $coreByCavity  cavity number => core id, or null for a cavity not in use
     */
    public function saveCavityMap(array $coreByCavity): void
    {
        $this->cavityMap()->delete();
        foreach ($coreByCavity as $number => $coreId) {
            if (filled($coreId)) {
                $this->cavityMap()->create(['cavity_number' => (int) $number, 'core_definition_id' => $coreId]);
            }
        }
        if ($first = $this->cavityMap()->first()) {
            $this->update(['core_definition_id' => $first->core_definition_id]);
        }
    }

    /**
     * Each core the box makes with how many cavities make it, in the order of its first cavity.
     *
     * @return list<array{core: CoreDefinition, qty: int}>
     */
    public function coreCounts(): array
    {
        return $this->cavityMap->sortBy('cavity_number')->groupBy('core_definition_id')
            ->map(fn ($rows) => ['core' => $rows->first()->coreDefinition, 'qty' => $rows->count()])->values()->all();
    }

    /** Cavities that make no core: plugged or not in use. */
    public function blockedCavities(): int
    {
        return max(0, (int) $this->cavities - $this->cavityMap->count());
    }

    /** e.g. "Pump body core × 3, Pump neck core × 1, 1 blocked". */
    public function makesLine(): string
    {
        $blocked = $this->blockedCavities();

        return collect($this->coreCounts())->map(fn ($r) => ($r['core']?->name ?? '—').' × '.$r['qty'])
            ->when($blocked > 0, fn ($c) => $c->push($blocked.' blocked'))->implode(', ');
    }

    /** @return \Illuminate\Support\Collection<int, CoreDefinition> the distinct cores this box makes */
    public function coresMade()
    {
        return $this->cavityMap()->with('coreDefinition')->get()->pluck('coreDefinition')->unique('id')->values();
    }
}
