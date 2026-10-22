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