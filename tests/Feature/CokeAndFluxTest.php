<?php

namespace Tests\Feature;

use App\Models\ChargeBatch;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Services\Production\CokeAndFlux;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CokeAndFluxTest extends TestCase
{
    use RefreshDatabase;

    /** A cupola recipe: 100 kg of bed coke, then 180 kg of iron, 25 kg of coke and 6 kg of limestone a charge. Everything costs 50/kg. */
    private function cupola(): array
    {
        $materials = [];
        foreach (['iron' => 'METAL', 'coke' => 'FUEL', 'lime' => 'FLUX'] as $key => $type) {
            $materials[$key] = Material::create(['code' => strtoupper($key), 'name' => ucfirst($key), 'material_type' => $type, 'base_uom' => 'kg', 'status' => 'ACTIVE']);
            MaterialLot::create(['lot_number' => 'L-'.$key, 'material_id' => $materials[$key]->id, 'received_date' => today(), 'status' => 'ACCEPTED', 'received_qty' => 9999, 'accepted_qty' => 9999, 'unit_cost' => 50, 'currency_code' => 'PKR']);
        }
        $recipe = ChargeBatch::create(['code' => 'CR-CUP', 'name' => 'Cupola', 'process_type' => 'CUPOLA', 'status' => 'ACTIVE', 'target_metal_weight_kg' => 180]);
        foreach ([['coke', 'COKE', 'BED', 100], ['iron', 'CHARGE', 'ROUND', 180], ['coke', 'COKE', 'ROUND', 25], ['lime', 'LIMESTONE', 'ROUND', 6]] as $i => [$key, $role, $stage, $kg]) {
            $recipe->items()->create(['material_id' => $materials[$key]->id, 'sequence' => $i + 1, 'component_role' => $role, 'charge_stage' => $stage, 'target_qty_kg' => $kg, 'is_required' => true]);
        }
        $furnace = Furnace::create(['code' => 'CU-1', 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'nominal_melting_rate_kg_per_hour' => 360]);
        $heat = Heat::create(['heat_number' => 'H-1', 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'MELTING', 'charge_batch_id' => $recipe->id, 'operator_name' => 'Ali']);

        return [$heat, $materials];
    }

    private function entry(Heat $heat, Material $material, float $kg, ?int $round, ?string $reason = null): void
    {
        HeatMaterialConsumption::create(['heat_id' => $heat->id, 'material_id' => $material->id, 'sequence' => (int) HeatMaterialConsumption::where('heat_id', $heat->id)->max('sequence') + 1,
            'consumption_type' => 'RAW_MATERIAL', 'actual_qty_kg' => $kg, 'actual_total_cost' => $kg * 50, 'consumed_at' => now(), 'recorded_by' => 'Ali', 'charge_round' => $round, 'addition_reason' => $reason]);
    }

    /** @return array<string, mixed> */
    private function extraForm(Material $material, array $extra = []): array
    {
        return $extra + ['consumption_type' => 'RAW_MATERIAL', 'material_id' => $material->id, 'material_lot_id' => MaterialLot::where('material_id', $material->id)->value('id'),
            'actual_qty_kg' => 20, 'recorded_by' => 'Ali', 'consumed_at' => now()->format('Y-m-d\TH:i')];
    }

    public function test_an_extra_keeps_its_reason_a_recipe_charge_does_not_and_the_history_shows_it(): void
    {
        [$heat, $m] = $this->cupola();

        $this->post(route('heats.charge-entries.store', $heat), $this->extraForm($m['coke'], ['addition_reason' => 'TOP_UP']))->assertSessionHasNoErrors();
        $this->post(route('heats.charge-entries.store', $heat), $this->extraForm($m['coke'], ['addition_reason' => 'SLAG', 'charge_round' => 3]))->assertSessionHasNoErrors();
        $this->post(route('heats.charge-entries.store', $heat), $this->extraForm($m['coke'], ['addition_reason' => 'MAGIC']))->assertSessionHasErrors('addition_reason');

        $this->assertSame(['TOP_UP', null], HeatMaterialConsumption::where('heat_id', $heat->id)->orderBy('sequence')->pluck('addition_reason')->all());
        \Livewire\Livewire::test('heat-charge-history', ['heatId' => $heat->id])->assertSee('Extra')->assertSee('Top-up')->assertDontSee('Slag fix');

        $heat->update(['charge_batch_id' => null]);   // without a recipe nothing is an extra
        \Livewire\Livewire::test('heat-charge-history', ['heatId' => $heat->id])->assertDontSee('Extra');
    }

    public function test_the_summary_compares_coke_and_flux_with_the_recipe_and_gives_the_ratio_cost_and_extras(): void
    {
        [$heat, $m] = $this->cupola();
        $this->entry($heat, $m['coke'], 100, 0);
        foreach ([1, 2] as $round) {
            $this->entry($heat, $m['iron'], 180, $round);
            $this->entry($heat, $m['coke'], 25, $round);
            $this->entry($heat, $m['lime'], 6, $round);
        }
        $this->entry($heat, $m['coke'], 20, null, 'TOP_UP');
        $this->entry($heat, $m['lime'], 4, null, 'SLAG');
        $this->entry($heat, $m['lime'], 1, null);

        $result = app(CokeAndFlux::class)->for($heat);

        $this->assertSame([150.0, 170.0, 8500.0], [$result['kinds']['COKE']['planned_kg'], $result['kinds']['COKE']['actual_kg'], $result['kinds']['COKE']['cost']]);
        $this->assertSame([12.0, 17.0], [$result['kinds']['FLUX']['planned_kg'], $result['kinds']['FLUX']['actual_kg']]);
        $this->assertSame([360.0, 2.1, 25972.22], [$result['metal_kg'], $result['coke_ratio'], $result['cost_per_tonne']]);
        $this->assertSame([['Coke', 20.0, 'Top-up'], ['Flux', 4.0, 'Slag fix'], ['Flux', 1.0, 'No reason given']],
            array_map(fn ($e) => [$e['kind'], $e['kg'], $e['label']], $result['extras']));

        $n = fn (float $v, int $places = 1) => \App\Support\Formats::number($v, $places);   // the site's decimal setting decides the places shown
        $this->get(route('heats.summary', $heat))->assertOk()
            ->assertSeeInOrder(['Coke and flux', 'Coke', $n(150).' kg', $n(170).' kg', '+'.$n(20).' kg', 'Flux', $n(12).' kg', $n(17).' kg',
                'Coke ratio 1 : '.$n(2.1), 'PKR '.$n(25972.22, 2).' per tonne melted', 'Coke '.$n(20).' kg (Top-up)']);
    }

    public function test_a_heat_without_coke_or_flux_has_no_coke_and_flux_section(): void
    {
        $furnace = Furnace::create(['code' => 'IND-1', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $heat = Heat::create(['heat_number' => 'H-2', 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'MELTING']);

        $this->assertNull(app(CokeAndFlux::class)->for($heat));
        $this->get(route('heats.summary', $heat))->assertOk()->assertDontSee('Coke and flux');
    }
}
