<?php

namespace App\Domains\Assets\Models;

use App\Models\CoreBox;
use App\Models\Equipment;
use App\Models\Pattern;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Database\Factories\FixedAssetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One fixed asset (design 28d): a furnace, a building, a forklift or a pattern or core box the foundry owns. Its cost is the sum of
 * its cost rows; what it has lost is the depreciation brought in when it was added plus every posted run.
 */
class FixedAsset extends Model
{
    use HasFactory, HasUuids;

    public const KINDS = ['PLANT' => 'Plant & machinery', 'BUILDING' => 'Building', 'VEHICLE' => 'Vehicle', 'PATTERN' => 'Pattern', 'CORE_BOX' => 'Core box (die)'];

    public const STATUSES = [
        'IN_PROGRESS' => 'In progress', 'IN_USE' => 'In use', 'SOLD' => 'Sold', 'SCRAPPED' => 'Scrapped', 'WRITTEN_OFF' => 'Written off', 'RETIRED' => 'Retired',
    ];

    /** Statuses of an asset the foundry no longer has. */
    public const GONE = ['SOLD', 'SCRAPPED', 'WRITTEN_OFF', 'RETIRED'];

    public const METHODS = ['STRAIGHT_LINE' => 'Straight line', 'REDUCING_BALANCE' => 'Reducing balance', 'USAGE' => 'Usage · fills', 'NONE' => 'Not depreciated'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'in_use_on' => 'date', 'opening_to' => 'date', 'disposed_on' => 'date', 'cost' => 'float', 'residual_value' => 'float', 'life_years' => 'float',
            'rate_percent' => 'float', 'life_fills' => 'integer', 'opening_depreciation' => 'float', 'opening_fills' => 'integer',
        ];
    }

    protected static function newFactory(): Factory
    {
        return FixedAssetFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (FixedAsset $asset): void {
            $asset->code ??= \App\Support\DocumentNumbers::next('fixed_asset');
            $asset->created_by_id ??= auth()->id();
        });
    }

    public function assetClass(): BelongsTo
    {
        return $this->belongsTo(AssetClass::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(AssetCost::class)->orderBy('incurred_on')->orderBy('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class)->orderBy('happened_on')->orderBy('created_at');
    }

    public function runLines(): HasMany
    {
        return $this->hasMany(DepreciationRunLine::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function coreBox(): BelongsTo
    {
        return $this->belongsTo(CoreBox::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @param Builder<FixedAsset> $query */
    public function scopeInRegister(Builder $query): void
    {
        $query->whereNotIn('status', self::GONE);
    }

    public function isTool(): bool
    {
        return in_array($this->kind, ['PATTERN', 'CORE_BOX'], true);
    }

    /** The tool's code (PT-0430) when the asset is a pattern or core box, else the equipment's. */
    public function toolCode(): ?string
    {
        return $this->pattern?->code ?? $this->coreBox?->code ?? $this->equipment?->code;
    }

    /** Depreciation brought in when the asset was added plus every posted run, to the end of the given month (all of it when none is given). */
    public function depreciatedTo(?string $periodEnd = null): float
    {
        $posted = DepreciationRunLine::query()->where('fixed_asset_id', $this->id)
            ->whereHas('run', fn ($q) => $q->where('status', DepreciationRun::POSTED)->when($periodEnd, fn ($r) => $r->where('period', '<=', $periodEnd)))
            ->sum('charge');

        return round($this->opening_depreciation + (float) $posted, 2);
    }

    public function bookValue(): float
    {
        return round($this->cost - $this->depreciatedTo(), 2);
    }

    /** Fills to date: those brought in plus those in posted runs. */
    public function fillsUsed(): int
    {
        return $this->opening_fills + (int) DepreciationRunLine::query()->where('fixed_asset_id', $this->id)
            ->whereHas('run', fn ($q) => $q->where('status', DepreciationRun::POSTED))->sum('fills');
    }

    /** "Usage · 124 of 5,000 fills", "Straight line · 15 years", "Reducing balance · 20% a year". */
    public function methodLabel(): string
    {
        return match ($this->method) {
            'USAGE' => 'Usage · '.number_format($this->fillsUsed()).' of '.number_format((int) $this->life_fills).' fills',
            'STRAIGHT_LINE' => 'Straight line · '.rtrim(rtrim(number_format((float) $this->life_years, 1), '0'), '.').' years',
            'REDUCING_BALANCE' => 'Reducing balance · '.rtrim(rtrim(number_format((float) $this->rate_percent, 1), '0'), '.').'% a year',
            default => 'Not depreciated',
        };
    }

    /** Adds up the cost rows into the asset's cost. */
    public function refreshCost(): void
    {
        $this->update(['cost' => round((float) $this->costs()->sum('amount'), 2)]);
    }
}
