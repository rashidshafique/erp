<?php

namespace Database\Factories;

use App\Models\BaseSand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaseSand>
 */
class BaseSandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' sand';

        return [
            'code' => strtoupper(str_replace(' ', '_', $name)),
            'name' => $name,
            'description' => fake()->sentence(),
            'status' => 'ACTIVE',
            'sort_order' => fake()->numberBetween(100, 900),
        ];
    }
}
