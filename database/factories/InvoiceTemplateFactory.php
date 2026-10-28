<?php

namespace Database\Factories;

use App\Domains\Finance\Models\InvoiceTemplate;
use App\Domains\Finance\Services\InvoiceLayouts;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceTemplate> */
class InvoiceTemplateFactory extends Factory
{
    protected $model = InvoiceTemplate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true), 'applies_to' => 'BOTH', 'layout' => app(InvoiceLayouts::class)->builtIn('SIMPLE'), 'is_active' => true];
    }
}
