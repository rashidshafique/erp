<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasUuids, SoftDeletes, \App\Models\Concerns\GeneratesCodes;

    public string $codeKind = 'product';

    protected $fillable = [
        'code',
        'name',
        'product_type',
        'base_uom',
        'nominal_weight_kg',
        'description',
        'active',
    ];

    protected $casts = [
        'nominal_weight_kg' => 'decimal:4',
        'active' => 'boolean',
    ];

    /** The revision the product is made to now: the latest approved one, else the latest that is not obsolete, else the latest. */
    public function currentRevision(): ?ProductRevision
    {
        $revisions = $this->revisions()->orderByDesc('created_at')->get();

        return $revisions->firstWhere('status', 'APPROVED') ?? $revisions->first(fn ($r) => $r->status !== 'OBSOLETE') ?? $revisions->first();
    }

    /**
     * Sets the nominal weight from the net weight of the current revision. A product with no revision keeps the weight typed on it,
     * and gives it to its first revision.
     */
    public function syncNominalWeight(): void
    {
        $current = $this->currentRevision();
        if ($current && (string) $this->nominal_weight_kg !== (string) $current->net_weight_kg) {
            $this->forceFill(['nominal_weight_kg' => $current->net_weight_kg])->saveQuietly();
        }
    }

    /** Products with no revision that is not obsolete: lists offer them as "product:<id>" and give them their first revision on save. */
    public function scopeWithoutRevision(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereDoesntHave('revisions', fn ($q) => $q->where('status', '!=', 'OBSOLETE'));
    }

    /** The revision a new pattern link goes on: the latest approved one, else the latest not obsolete; a product with none gets its first. */
    public function linkableRevision(): ProductRevision
    {
        $revisions = $this->revisions()->where('status', '!=', 'OBSOLETE')->orderByDesc('created_at')->get();

        return $revisions->firstWhere('status', 'APPROVED') ?? $revisions->first()
            ?? $this->revisions()->create(['revision' => ProductRevision::nextLabel($this->revisions()->pluck('revision')), 'status' => 'APPROVED', 'engineering_notes' => 'First revision, recorded when linking a pattern.']);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ProductRevision::class);
    }
}