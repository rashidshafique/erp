<?php

namespace Database\Factories;

use App\Domains\Assets\Models\AssetClass;
use App\Domains\Assets\Models\FixedAsset;
use App\Models\Pattern;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FixedAsset> */
class FixedAssetFactory extends Factory
{
    protected $model = FixedAsset::class;

    /** @return array<string, mixed> A furnace in use since 1 January 2026, Rs 1,200,000 straight line over 10 years, its cost brought in. */
    public function definition(): array
    {
        return [
            'asset_class_id' => fn () => AssetClass::where('name', 'Furnaces and melting plant')->value('id'),
            'name' => 'Induction furnace '.fake()->unique()->numberBetween(1, 999), 'kind' => 'PLANT', 'status' => 'IN_USE', 'in_use_on' => '2026-01-01',
            'cost' => 1200000, 'residual_value' => 0, 'method' => 'STRAIGHT_LINE', 'life_years' => 10,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (FixedAsset $asset): void {
            if ($asset->cost > 0 && ! $asset->costs()->exists()) {
                $asset->costs()->create(['source_type' => 'OPENING', 'description' => 'Brought in', 'amount' => $asset->cost, 'incurred_on' => $asset->in_use_on]);
            }
        });
    }

    public function inProgress(): static
    {
        return $this->state(['status' => 'IN_PROGRESS', 'in_use_on' => null]);
    }

    public function reducingBalance(float $rate = 20): static
    {
        return $this->state(fn () => ['asset_class_id' => AssetClass::where('name', 'Vehicles and forklifts')->value('id'), 'kind' => 'VEHICLE', 'name' => 'Forklift 3 t',
            'method' => 'REDUCING_BALANCE', 'rate_percent' => $rate, 'life_years' => null]);
    }

    /** A pattern the foundry owns, depreciated by fills over its life. */
    public function pattern(Pattern $pattern, int $lifeFills = 5000): static
    {
        return $this->state(fn () => ['asset_class_id' => AssetClass::where('name', 'Patterns and core boxes (own)')->value('id'), 'kind' => 'PATTERN', 'name' => $pattern->name,
            'pattern_id' => $pattern->id, 'method' => 'USAGE', 'life_fills' => $lifeFills, 'life_years' => null]);
    }
}
