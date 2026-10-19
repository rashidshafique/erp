<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HeatChargePosting;
use App\Models\HeatMaterialConsumption;
use App\Models\HeatPowerReading;
use App\Models\HeatProcessReading;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockLotCostLayer;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatLogTest extends TestCase
{
    use RefreshDatabase;

    private function heat(string $type = 'INDUCTION', string $status = 'PLANNED'): Heat
    {
        $furnace = Furnace::create(['code' => $type.'-'.uniqid(), 'name' => $type, 'furnace_type' => $type, 'status' => 'ACTIVE', 'rated_capacity_kg' => 1000, 'nominal_melting_rate_kg_per_hour' => 500]);

        return Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => $type, 'status' => $status]);
    }

    /** @return array{0: Material, 1: MaterialLot} a steel scrap material with 100 kg in an accepted, costed lot */
    private function stockedScrap(): array
    {
        $material = Material::create(['code' => 'SCRAP-'.uniqid(), 'name' => 'Steel scrap', 'material_type' => 'RAW_MATERIAL', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => true, 'requires_lot_tracking' => true]);
        $lot = MaterialLot::create(['lot_number' => 'LOT-'.uniqid(), 'material_id' => $material->id, 'status' => 'ACCEPTED', 'received_qty' => 100, 'accepted_qty' => 100, 'unit_cost' => 80, 'currency_code' => 'PKR']);
        $movement = StockMovement::create(['movement_number' => 'REC-'.uniqid(), 'movement_type' => 'RECEIPT', 'material_id' => $material->id, 'material_lot_id' => $lot->id, 'quantity' => 100, 'uom' => 'kg', 'unit_cost' => 80, 'total_cost' => 8000, 'source_type' => 'TEST', 'source_id' => $lot->id, 'occurred_at' => now(), 'recorded_by' => 'Test']);
        StockLotCostLayer::create(['material_lot_id' => $lot->id, 'stock_movement_id' => $movement->id, 'original_qty' => 100, 'remaining_qty' => 100, 'unit_cost' => 80, 'currency_code' => 'PKR', 'received_at' => now()]);

        return [$material, $lot];
    }

    private function charge(Heat $heat, Material $material, MaterialLot $lot, float $kg): void
    {
        $this->post(route('heats.charge-entries.store', $heat), [
            'consumption_type' => 'RAW_MATERIAL', 'material_id' => $material->id, 'material_lot_id' => $lot->id,
            'actual_qty_kg' => $kg, 'recorded_by' => 'Melter', 'consumed_at' => now()->toDateTimeString(),
        ])->assertSessionHasNoErrors();
    }

    public function test_power_readings_are_induction_only_numbered_and_need_a_value(): void
    {
        $heat = $this->heat('INDUCTION');
        $reading = ['recorded_at' => now()->toDateTimeString(), 'recorded_by' => 'Melter'];

        $this->post(route('heats.power-readings.store', $heat), $reading)->assertSessionHasErrors('actual_power_kw');
        $this->post(route('heats.power-readings.store', $heat), $reading + ['actual_power_kw' => 410])->assertSessionHasNoErrors();
        $this->post(route('heats.power-readings.store', $heat), $reading + ['cumulative_energy_kwh' => 520])->assertSessionHasNoErrors();

        $this->assertSame([1, 2], HeatPowerReading::where('heat_id', $heat->id)->orderBy('reading_number')->pluck('reading_number')->all());
        $this->post(route('heats.power-readings.store', $this->heat('CUPOLA')), $reading + ['actual_power_kw' => 410])->assertStatus(422);
    }

    public function test_process_readings_are_cupola_only_and_the_code_is_upper_cased(): void
    {
        $heat = $this->heat('CUPOLA');
        $reading = ['recorded_at' => now()->toDateTimeString(), 'parameter_code' => 'blast_pressure', 'numeric_value' => 12.5, 'unit' => 'kPa', 'recorded_by' => 'Melter'];

        $this->post(route('heats.process-readings.store', $heat), $reading)->assertSessionHasNoErrors();
        $this->post(route('heats.process-readings.store', $heat), ['parameter_code' => 'bad code!'] + $reading)->assertSessionHasErrors('parameter_code');

        $this->assertSame('BLAST_PRESSURE', HeatProcessReading::where('heat_id', $heat->id)->sole()->parameter_code);
        $this->post(route('heats.process-readings.store', $this->heat('INDUCTION')), $reading)->assertStatus(422);
    }

    public function test_a_purchased_charge_needs_an_accepted_costed_lot_of_that_material(): void
    {
        $heat = $this->heat();
        [$material, $lot] = $this->stockedScrap();
        [, $otherLot] = $this->stockedScrap();
        $entry = ['consumption_type' => 'RAW_MATERIAL', 'material_id' => $material->id, 'actual_qty_kg' => 20, 'recorded_by' => 'Melter', 'consumed_at' => now()->toDateTimeString()];

        $this->post(route('heats.charge-entries.store', $heat), $entry)->assertSessionHasErrors('material_lot_id');
        $this->post(route('heats.charge-entries.store', $heat), $entry + ['material_lot_id' => $otherLot->id])->assertSessionHasErrors('material_lot_id');
        $lot->update(['status' => 'QUARANTINE']);
        $this->post(route('heats.charge-entries.store', $heat), $entry + ['material_lot_id' => $lot->id])->assertSessionHasErrors('material_lot_id');
        $this->assertSame(0, HeatMaterialConsumption::count());

        $lot->update(['status' => 'ACCEPTED']);
        $this->post(route('heats.charge-entries.store', $heat), $entry + ['material_lot_id' => $lot->id])->assertSessionHasNoErrors();
        $this->assertSame(20.0, (float) HeatMaterialConsumption::sole()->actual_qty_kg);
    }

    public function test_posting_the_charge_issues_stock_once_and_records_the_cost(): void
    {
        $heat = $this->heat();
        [$material, $lot] = $this->stockedScrap();
        $this->charge($heat, $material, $lot, 30);

        $this->post(route('heats.charge-postings.store', $heat), ['posted_by' => 'Stores'])->assertSessionHasNoErrors();

        $posting = HeatChargePosting::sole();
        $this->assertSame(30.0, (float) $posting->total_quantity_kg);
        $this->assertSame(2400.0, (float) $posting->total_cost_pkr);
        $this->assertSame(70.0, (float) StockLotCostLayer::where('material_lot_id', $lot->id)->sum('remaining_qty'));
        $this->assertSame(1, StockMovement::where('movement_type', 'ISSUE')->where('source_id', $heat->id)->count());

        $this->post(route('heats.charge-postings.store', $heat), ['posted_by' => 'Stores'])->assertSessionHasErrors('posted_by');
        $this->assertSame(1, StockMovement::where('movement_type', 'ISSUE')->count());
    }

    public function test_the_charge_cannot_be_posted_empty_beyond_stock_or_on_a_closed_heat(): void
    {
        $heat = $this->heat();
        $this->post(route('heats.charge-postings.store', $heat), ['posted_by' => 'Stores'])->assertSessionHasErrors('posted_by');

        [$material, $lot] = $this->stockedScrap();
        $this->charge($heat, $material, $lot, 150);
        $this->post(route('heats.charge-postings.store', $heat), ['posted_by' => 'Stores'])->assertSessionHasErrors('posted_by');
        $this->assertSame(100.0, (float) StockLotCostLayer::where('material_lot_id', $lot->id)->sum('remaining_qty'));
        $this->assertSame(0, HeatChargePosting::count());

        $heat->update(['status' => 'CLOSED']);
        $this->post(route('heats.charge-postings.store', $heat), ['posted_by' => 'Stores'])->assertSessionHasErrors('posted_by');
        $this->post(route('heats.charge-entries.store', $heat), ['consumption_type' => 'RAW_MATERIAL', 'material_id' => $material->id, 'material_lot_id' => $lot->id, 'actual_qty_kg' => 5, 'recorded_by' => 'Melter', 'consumed_at' => now()->toDateTimeString()])->assertSessionHasErrors('actual_qty_kg');
    }
}
