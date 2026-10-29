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

    public function test_coke_and_flux_go_on_top_of_a_percentage_metal_charge(): void
    {
        [$pig, $scrap, $coke, $flux] = [$this->material('PIG'), $this->material('SCRAP'), $this->material('COKE'), $this->material('LIME')];

        $this->post(route('charge-batches.store'), $this->payload([
            ['material_id' => $pig->id, 'component_role' => 'CHARGE', 'target_percent' => '60'],
            ['material_id' => $scrap->id, 'component_role' => 'RETURN', 'target_percent' => '40'],
            ['material_id' => $coke->id, 'component_role' => 'COKE', 'target_qty_kg' => '120'],
            ['material_id' => $flux->id, 'component_role' => 'FLUX', 'target_percent' => '3'],
        ], ['target_total_charge_weight_kg' => '1000']))->assertSessionHasNoErrors();

        $recipe = ChargeBatch::with('items.chargeBatch')->firstOrFail();
        $this->assertSame([600.0, 400.0, 120.0, 30.0], $recipe->items->map(fn ($i) => $i->perChargeKg())->all());
        $this->assertSame(1000.0, $recipe->metalPerChargeKg());
    }

    public function test_a_recipe_needs_metal_and_a_fixed_recipe_takes_its_additions_in_kg(): void
    {
        [$pig, $coke] = [$this->material('PIG'), $this->material('COKE')];

        $this->post(route('charge-batches.store'), $this->payload([
            ['material_id' => $coke->id, 'component_role' => 'COKE', 'target_qty_kg' => '100'],
        ]))->assertSessionHasErrors('items');
        $this->post(route('charge-batches.store'), $this->payload([
            ['material_id' => $pig->id, 'component_role' => 'CHARGE', 'target_qty_kg' => '1000'],
            ['material_id' => $coke->id, 'component_role' => 'COKE', 'target_percent' => '12'],
        ]))->assertSessionHasErrors('items.1.target_percent');

        $this->assertSame(0, ChargeBatch::count());
    }

    public function test_editing_a_recipe_adds_fuel_and_replaces_its_components(): void
    {
        [$pig, $fuel] = [$this->material('PIG'), $this->material('FUEL')];
        $this->post(route('charge-batches.store'), $this->payload([
            ['material_id' => $pig->id, 'component_role' => 'CHARGE', 'target_qty_kg' => '1000'],
        ]));
        $recipe = ChargeBatch::firstOrFail();

        $this->get(route('charge-batches.edit', $recipe))->assertOk()->assertSee('Fuel');
        $this->put(route('charge-batches.update', $recipe), $this->payload([
            ['material_id' => $pig->id, 'component_role' => 'CHARGE', 'target_qty_kg' => '900'],
            ['material_id' => $fuel->id, 'component_role' => 'FUEL', 'target_qty_kg' => '110'],
        ], ['name' => 'Renamed']))->assertSessionHasNoErrors()->assertRedirect(route('charge-batches.show', $recipe));

        $recipe->refresh()->load('items');
        $this->assertSame(['Renamed', 'RCP-1', '1010.000'], [$recipe->name, $recipe->code, $recipe->target_total_charge_weight_kg]);
        $this->assertSame(['CHARGE', 'FUEL'], $recipe->items->pluck('component_role')->all());
        $this->assertSame(900.0, $recipe->metalPerChargeKg());
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
