<?php

namespace Tests\Feature;

use App\Domains\Maintenance\Models\EquipmentBreakdown;
use App\Domains\Maintenance\Models\EquipmentMeter;
use App\Domains\Maintenance\Models\MaintenancePlan;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Models\Equipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function equipment(array $extra = []): Equipment
    {
        return Equipment::create($extra + [
            'code' => 'EQ-'.uniqid(),
            'name' => 'Moulding line 1',
            'equipment_type' => 'MOULDING_LINE',
            'status' => 'AVAILABLE',
        ]);
    }

    private function meter(Equipment $equipment, array $extra = []): EquipmentMeter
    {
        return EquipmentMeter::create($extra + [
            'equipment_id' => $equipment->id,
            'meter_code' => 'MOULDS',
            'name' => 'Moulds made',
            'unit' => 'MOULDS',
            'current_reading' => 0,
            'is_cumulative' => true,
            'active' => true,
        ]);
    }

    private function plan(Equipment $equipment, array $extra = []): MaintenancePlan
    {
        return MaintenancePlan::create($extra + [
            'equipment_id' => $equipment->id,
            'name' => 'Weekly hydraulic check',
            'maintenance_type' => 'PREVENTIVE',
            'trigger_type' => 'CALENDAR',
            'interval_days' => 7,
            'requires_shutdown' => true,
            'active' => true,
        ]);
    }

    public function test_equipment_and_a_plan_are_added_from_the_maintenance_screen(): void
    {
        $this->post(route('equipment-maintenance.equipment.store'), [
            'code' => 'hml-1',
            'name' => 'Horizontal moulding line',
            'equipment_type' => 'MOULDING_LINE',
            'status' => 'AVAILABLE',
            'location_description' => 'Bay 2',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $equipment = Equipment::firstOrFail();
        $this->assertSame('HML-1', $equipment->code);
        $this->assertSame('AVAILABLE', $equipment->status);

        $this->post(route('equipment-maintenance.plans.store'), [
            'equipment_id' => $equipment->id,
            'name' => 'Weekly hydraulic check',
            'maintenance_type' => 'PREVENTIVE',
            'trigger_type' => 'CALENDAR',
            'interval_days' => 30,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $plan = MaintenancePlan::firstOrFail();
        $this->assertStringStartsWith('MNT', $plan->code);
        $this->assertTrue($plan->active);
        $this->assertSame(today()->addDays(30)->toDateString(), $plan->next_due_at->toDateString());

        $this->get(route('equipment-maintenance.index'))
            ->assertOk()
            ->assertSee('HML-1')
            ->assertSee('Weekly hydraulic check')
            ->assertSee($plan->code);
    }

    public function test_a_plan_cannot_be_scheduled_by_meter_without_a_meter(): void
    {
        $equipment = $this->equipment();

        $this->post(route('equipment-maintenance.plans.store'), [
            'equipment_id' => $equipment->id,
            'name' => 'Service after 1,000 moulds',
            'maintenance_type' => 'PREVENTIVE',
            'trigger_type' => 'METER',
            'interval_meter_units' => 1000,
        ])->assertSessionHasErrors('trigger_type');
    }

    public function test_due_states_follow_the_calendar_and_the_meter(): void
    {
        $equipment = $this->equipment();
        $meter = $this->meter($equipment, ['current_reading' => 900]);

        $overdue = $this->plan($equipment, ['next_due_at' => today()->subDay()]);
        $soon = $this->plan($equipment, ['next_due_at' => today()->addDays(3), 'code' => 'MNT-SOON']);
        $scheduled = $this->plan($equipment, ['next_due_at' => today()->addDays(60), 'code' => 'MNT-LATER']);
        $meterScheduled = $this->plan($equipment, [
            'trigger_type' => 'METER', 'interval_days' => null, 'interval_meter_units' => 250,
            'next_due_meter' => 1200, 'code' => 'MNT-METER',
        ]);
        $meterDue = $this->plan($equipment, [
            'trigger_type' => 'BOTH', 'interval_days' => 30, 'interval_meter_units' => 250,
            'next_due_at' => today()->addDays(20), 'next_due_meter' => 950, 'code' => 'MNT-BOTH',
        ]);
        $notScheduled = $this->plan($equipment, ['next_due_at' => null, 'code' => 'MNT-NEW']);

        $this->assertSame(['overdue', true], [$overdue->dueState(), $overdue->isDue()]);
        $this->assertSame('due_soon', $soon->dueState());
        $this->assertSame('scheduled', $scheduled->dueState());
        $this->assertSame('scheduled', $meterScheduled->dueState());
        $this->assertSame(['scheduled', false], [$meterDue->dueState(), $meterDue->isDue()]);
        $this->assertSame('not_scheduled', $notScheduled->dueState());

        $this->post(route('equipment-maintenance.meters.readings.store', $meter), ['reading_value' => 1250])
            ->assertSessionHasNoErrors();

        $this->assertSame('meter_due', $meterScheduled->fresh()->dueState());
        $this->assertSame('meter_due', $meterDue->fresh()->dueState());
    }

    public function test_completing_a_work_order_rolls_the_plan_forward_and_frees_the_equipment(): void
    {
        $equipment = $this->equipment();
        $meter = $this->meter($equipment, ['current_reading' => 100]);
        $plan = $this->plan($equipment, [
            'trigger_type' => 'BOTH',
            'interval_days' => 14,
            'interval_meter_units' => 50,
            'next_due_at' => today()->subDay(),
            'next_due_meter' => 120,
        ]);

        $this->post(route('equipment-maintenance.work-orders.store'), [
            'equipment_id' => $equipment->id,
            'maintenance_plan_id' => $plan->id,
            'maintenance_type' => 'PREVENTIVE',
            'priority' => 'NORMAL',
        ])->assertSessionHasNoErrors();

        $workOrder = MaintenanceWorkOrder::firstOrFail();
        $this->assertStringStartsWith('MWO-', $workOrder->work_order_number);
        $this->assertSame($plan->name, $workOrder->problem_description);

        $this->post(route('equipment-maintenance.work-orders.start', $workOrder))->assertSessionHasNoErrors();
        $this->assertSame('IN_PROGRESS', $workOrder->fresh()->status);
        $this->assertSame('MAINTENANCE', $equipment->fresh()->status);

        $this->post(route('equipment-maintenance.meters.readings.store', $meter), ['reading_value' => 160])->assertSessionHasNoErrors();

        $this->post(route('equipment-maintenance.work-orders.complete', $workOrder), [
            'work_performed' => 'Replaced seals and topped up oil.',
            'labor_hours' => 2.5,
        ])->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame('COMPLETED', $workOrder->fresh()->status);
        $this->assertSame(today()->toDateString(), $plan->last_completed_at->toDateString());
        $this->assertSame(today()->addDays(14)->toDateString(), $plan->next_due_at->toDateString());
        $this->assertSame('160.000', $plan->last_completed_meter);
        $this->assertSame('210.000', $plan->next_due_meter);
        $this->assertSame('AVAILABLE', $equipment->fresh()->status);
    }

    public function test_a_breakdown_takes_the_equipment_down_and_restoring_records_the_downtime(): void
    {
        $equipment = $this->equipment();

        $this->post(route('equipment-maintenance.breakdowns.store'), [
            'equipment_id' => $equipment->id,
            'severity' => 'MAJOR',
            'started_at' => now()->subHours(6)->toDateTimeString(),
            'failure_category' => 'HYDRAULIC',
            'failure_description' => 'Hydraulic pump lost pressure.',
        ])->assertSessionHasNoErrors();

        $breakdown = EquipmentBreakdown::firstOrFail();
        $this->assertStringStartsWith('BD-', $breakdown->breakdown_number);
        $this->assertSame('OPEN', $breakdown->status);
        $this->assertSame('BREAKDOWN', $equipment->fresh()->status);

        $this->post(route('equipment-maintenance.breakdowns.restore', $breakdown), [
            'restored_at' => now()->subHours(2)->toDateTimeString(),
            'root_cause' => 'Worn shaft seal',
            'corrective_action' => 'Seal replaced',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $breakdown->refresh();
        $this->assertSame('RESTORED', $breakdown->status);
        $this->assertEqualsWithDelta(4, $breakdown->downtimeHours(), 0.1);
        $this->assertSame('AVAILABLE', $equipment->fresh()->status);

        $this->get(route('equipment-maintenance.index'))->assertOk()->assertSee('No open breakdowns.');
    }

    public function test_a_cumulative_meter_cannot_read_backwards(): void
    {
        $equipment = $this->equipment();
        $meter = $this->meter($equipment, ['current_reading' => 500]);

        $this->post(route('equipment-maintenance.meters.readings.store', $meter), ['reading_value' => 400])
            ->assertSessionHasErrors('reading_value');
        $this->assertSame('500.000', $meter->fresh()->current_reading);
        $this->assertSame(0, $meter->readings()->count());

        $this->post(route('equipment-maintenance.meters.readings.store', $meter), ['reading_value' => 620])
            ->assertSessionHasNoErrors();

        $this->assertSame('620.000', $meter->fresh()->current_reading);
        $this->assertSame(1, $meter->readings()->count());
    }

    public function test_an_equipment_code_is_unique_among_live_equipment(): void
    {
        $this->equipment(['code' => 'MIXER-1']);

        $this->post(route('equipment-maintenance.equipment.store'), [
            'code' => 'mixer-1',
            'name' => 'Sand mixer',
            'equipment_type' => 'SAND_MIXER',
            'status' => 'AVAILABLE',
        ])->assertSessionHasErrors('code');

        $this->assertSame(1, Equipment::count());
    }

    public function test_every_tab_lists_its_records_and_the_rail_shows_meters(): void
    {
        $equipment = $this->equipment(['code' => 'HPML-1']);
        $this->meter($equipment, ['current_reading' => 90]);
        $plan = $this->plan($equipment, ['trigger_type' => 'METER', 'interval_days' => null, 'interval_meter_units' => 100, 'next_due_meter' => 100, 'next_due_at' => null]);
        $this->post(route('equipment-maintenance.work-orders.store'), ['equipment_id' => $equipment->id, 'maintenance_plan_id' => $plan->id, 'maintenance_type' => 'PREVENTIVE', 'priority' => 'HIGH'])->assertSessionHasNoErrors();
        $this->post(route('equipment-maintenance.breakdowns.store'), ['equipment_id' => $equipment->id, 'severity' => 'MINOR', 'started_at' => now()->subHour()->toDateTimeString(), 'failure_description' => 'Squeeze head jammed'])->assertSessionHasNoErrors();
        $work = MaintenanceWorkOrder::firstOrFail();
        $breakdown = EquipmentBreakdown::firstOrFail();

        $this->get(route('equipment-maintenance.index'))->assertOk()->assertSee($work->work_order_number)->assertSee('HPML-1')->assertSee('Squeeze head jammed')
            ->assertSee(route('equipment-maintenance.work-orders.start', $work), false)->assertSee(route('equipment-maintenance.work-orders.complete', $work), false)->assertSee('90 / 100');
        $this->get(route('equipment-maintenance.index', ['tab' => 'plans']))->assertOk()->assertSee($plan->code);
        $this->get(route('equipment-maintenance.index', ['tab' => 'breakdowns']))->assertOk()->assertSee($breakdown->breakdown_number)->assertSee(route('equipment-maintenance.breakdowns.restore', $breakdown), false);
        $this->get(route('equipment-maintenance.index', ['tab' => 'assets']))->assertOk()->assertSee('HPML-1')->assertSee('Moulds made');
    }
}
