<?php

namespace Database\Factories;

use App\Domains\Inventory\Models\StockLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockLocation> */
class StockLocationFactory extends Factory
{
    protected $model = StockLocation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('LOC-###')),
            'name' => fake()->words(2, true),
            'location_type' => 'STORE',
            'is_active' => true,
        ];
    }
}
