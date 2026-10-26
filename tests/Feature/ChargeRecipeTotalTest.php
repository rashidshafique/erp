<?php

namespace Tests\Feature;

use App\Models\ChargeBatch;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeRecipeTotalTest extends TestCase
{
    use RefreshDatabase;

    private function material(string $code): Material
    {
        return Material::create(['code' => $code, 'name' => $code.' material', 'material_type' => 'PIG_IRON', 'base_uom' => 'kg', 'status' => 'ACTIVE']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $items, array $overrides = []): array
    {
        return array_merge([
            'code' => 'RCP-1',
            'name' => 'Test recipe',
            'process_type' => 'CUPOLA',
            'status' => 'ACTIVE',
            'revision' => 'A',
            'items' => $items,
        ], $overrides);
    }

    public function test_fixed_recipe_total_is_the_sum_of_its_components(): void
    {
        $pig = $this->material('PIG');
        $scrap = $this->material('SCRAP');

        $this->post(route('charge-batches.store'), $this->payload([
            ['material_id' => $pig->id, 'component_role' => 'CHARGE', 'target_qty_kg' => '600'],
            ['material_id' => $scrap->id, 'component_role' => 'CHARGE', 'target_qty_kg' => '400.5'],
        ], ['target_total_charge_weight_kg' => '1']))->assertSessionHasNoErrors();

        $this->assertSame('1000.500', ChargeBatch::where('code', 'RCP-1')->firstOrFail()->target_total_charge_weight_kg);
    }

    public function test_percentage_recipe_requires_a_total_charge_weight(): void
    {
        $pig = $this->material('PIG');

        $this->post(route('charge-batches.store'), $this->payload([
            ['material_id' => $pig->id, 'component_role' => 'CHARGE', 'target_percent' => '100'],
        ]))->assertSessionHasErrors('target_total_charge_weight_kg');

        $this->assertSame(0, ChargeBatch::count());
    }

    public function test_percentage_recipe_keeps_the_entered_total(): void
    {
        $pig = $this->material('PIG');
        $scrap = $this->material('SCRAP');

        $this->post(route('charge-batches.store'), $this->payload([
            ['material_id' => $pig->id, 'component_role' => 'CHARGE', 'target_percent' => '60'],
            ['material_id' => $scrap->id, 'component_role' => 'CHARGE', 'target_percent' => '40'],
        ], ['target_total_charge_weight_kg' => '2000']))->assertSessionHasNoErrors();

        $this->assertSame('2000.000', ChargeBatch::where('code', 'RCP-1')->firstOrFail()->target_total_charge_weight_kg);
    }

    public function test_mixing_fixed_and_percentage_components_is_rejected(): void
    {
        $pig = $this->material('PIG');
        $scrap = $this->material('SCRAP');

        $this->post(route('charge-batches.store'), $this->payload([
            ['material_id' => $pig->id, 'component_role' => 'CHARGE', 'target_qty_kg' => '500'],
            ['material_id' => $scrap->id, 'component_role' => 'CHARGE', 'target_percent' => '100'],
        ], ['target_total_charge_weight_kg' => '1000']))->assertSessionHasErrors('items');

        $this->assertSame(0, ChargeBatch::count());
    }

    public function test_show_page_calculates_kg_and_share_for_a_percentage_recipe(): void
    {
        $pig = $this->material('PIG');
        $scrap = $this->material('SCRAP');

        $this->post(route('charge-batches.store'), $this->payload([
            ['material_id' => $pig->id, 'component_role' => 'CHARGE', 'target_percent' => '60'],
            ['material_id' => $scrap->id, 'component_role' => 'CHARGE', 'target_percent' => '40'],
        ], ['target_total_charge_weight_kg' => '2000']));

        $this->get(route('charge-batches.show', ChargeBatch::firstOrFail()))
            ->assertOk()
            ->assertSee('1,200.000')
            ->assertSee('800.000')
            ->assertSee('2,000.0000 kg');
    }
}
