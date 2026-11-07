<?php

namespace Tests\Feature;

use App\Models\ChargeBatch;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Models\IronGrade;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MouldingRun;
use App\Models\PatternRevision;
use App\Models\Pattern;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\User;
use App\Services\Production\ChargeSchedule;
use App\Services\Production\FurnaceFloor;
use App\Support\PouringRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The furnace tablet (design F4): start with the furnace's recipe, charges, the pour and the close. */
class FurnaceTabletTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $u = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $u->roles()->attach(Role::where('code', $role)->value('id'));

        return $u;
    }

    /** @return array{0: Furnace, 1: IronGrade, 2: ChargeBatch, 3: Material} a cupola at 1,400 kg/h with a recipe of 50 kg pig iron a charge */
    private function world(string $code = 'CUP-02'): array
    {
        PouringRules::set('auto_charge', 'MANUAL');
        $pig = Material::firstOrCreate(['code' => 'PIG'], ['name' => 'Pig iron', 'material_type' => 'PIG_IRON', 'base_uom' => 'kg', 'status' => 'ACTIVE']);
        MaterialLot::firstOrCreate(['lot_number' => 'L-PIG'], ['material_id' => $pig->id, 'received_date' => today(), 'status' => 'ACCEPTED', 'received_qty' => 99999, 'accepted_qty' => 99999, 'unit_cost' => 50, 'currency_code' => 'PKR']);
        $grade = IronGrade::firstOrCreate(['code' => 'GG-20'], ['name' => 'Grey 20', 'family' => 'GREY']);
        $recipe = ChargeBatch::create(['code' => 'R-'.$code, 'name' => 'Cupola', 'process_type' => 'CUPOLA', 'status' => 'ACTIVE', 'target_metal_weight_kg' => 50, 'target_total_charge_weight_kg' => 50]);
        $recipe->items()->create(['material_id' => $pig->id, 'sequence' => 1, 'component_role' => 'CHARGE', 'charge_stage' => 'ROUND', 'target_qty_kg' => 50, 'is_required' => true]);
        $furnace = Furnace::create(['code' => $code, 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'nominal_melting_rate_kg_per_hour' => 1400, 'default_charge_batch_id' => $recipe->id]);

        return [$furnace, $grade, $recipe, $pig];
    }

    private function running(Furnace $furnace, ChargeBatch $recipe, array $over = []): Heat
    {
        return Heat::create($over + ['heat_number' => 'HT-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'MELTING', 'charge_batch_id' => $recipe->id,
            'target_metal_weight_kg' => 500, 'charge_rounds' => 10, 'actual_start_at' => now()->subMinutes(60), 'blast_on_at' => now()->subMinutes(40)]);
    }

    public function test_the_screens_open_for_the_melt_shop_and_are_refused_to_a_role_without_it(): void
    {
        config(['foundry.require_login' => true]);
        [$furnace, , $recipe] = $this->world();
        $heat = $this->running($furnace, $recipe);

        $this->actingAs($this->user('QUALITY_INSPECTOR'))->get(route('floor.furnace.next-heat'))->assertForbidden();
        $this->actingAs($this->user('QUALITY_INSPECTOR'))->post(route('floor.furnace.heat.close.store', $heat))->assertForbidden();

        $melter = $this->user('MELT_SUPERVISOR');
        $this->actingAs($melter)->get(route('floor.furnace.next-heat'))->assertOk()->assertSee('Nothing to melt');
        $this->actingAs($melter)->get(route('floor.furnace.heat.show', $heat))->assertOk()->assertSee('Charged #1');
        $this->actingAs($melter)->get(route('floor.furnace.heat.pour.gate', $heat))->assertOk()->assertSee('Metal in ladle');
        $this->actingAs($melter)->get(route('floor.furnace.heat.pour.create', [$heat, 'ladle' => 300]))->assertOk();
        $this->actingAs($melter)->get(route('floor.furnace.heat.close', $heat))->assertOk()->assertSee('Close heat');
        $this->actingAs($melter)->get(route('floor.furnace.heat'))->assertRedirect(route('floor.furnace.heat.show', $heat));
    }

    public function test_a_kilogram_changed_when_starting_is_for_that_heat_only_and_blast_is_on_by_default(): void
    {
        [$furnace, $grade, $recipe, $pig] = $this->world();
        $item = $recipe->items()->firstOrFail();
        $melter = $this->user('MELT_SUPERVISOR');
        $moulder = $this->user('MOULDING_SUPERVISOR');

        $this->actingAs($melter)->post(route('floor.furnace.heat.start'), [
            'furnace_id' => $furnace->id, 'iron_grade_id' => $grade->id, 'kg' => 1680, 'charge_kg' => [$item->id => 60],
        ])->assertSessionHasNoErrors();

        $heat = Heat::firstOrFail();
        $this->assertEquals([$item->id => 60.0], $heat->chargeKgOverrides());
        $this->assertEquals(50, $item->fresh()->target_qty_kg, 'The recipe itself does not change.');
        $this->assertSame(60.0, (float) app(ChargeSchedule::class)->lines($heat, 1)->firstWhere('material_id', $pig->id)['actual_qty_kg'], 'The heat charges what was changed for it.');
        $this->assertNotNull($heat->blast_on_at);
        $this->assertSame('MELTING', $heat->status);
        $this->assertSame(1, $moulder->notifications->where('data.kind', 'heat-started')->count(), 'The moulders are asked to count once.');
    }

    public function test_lighting_up_only_leaves_blast_for_later(): void
    {
        [$furnace, $grade] = $this->world();
        $this->actingAs($this->user('MELT_SUPERVISOR'))->post(route('floor.furnace.heat.start'), ['furnace_id' => $furnace->id, 'iron_grade_id' => $grade->id, 'kg' => 900, 'light_only' => 1])->assertSessionHasNoErrors();

        $heat = Heat::firstOrFail();
        $this->assertNull($heat->blast_on_at);
        $this->assertNotNull($heat->actual_start_at);
    }

    public function test_the_most_overdue_step_gets_the_big_button_and_the_charge_wins_a_tie(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        [$furnace, , $recipe] = $this->world();
        PouringRules::set('test_interval_minutes', 10);
        $floor = app(FurnaceFloor::class);

        // Charge 1 was due at blast on, 40 minutes ago. The first tap was 50 minutes ago, so a test is 40 minutes late: a tie.
        $heat = $this->running($furnace, $recipe, ['first_tap_at' => now()->subMinutes(50)]);
        $this->assertSame(['charge', 1, 40], [$floor->bigStep($heat)['kind'], $floor->bigStep($heat)['round'], $floor->bigStep($heat)['minutes']]);

        $heat->update(['first_tap_at' => now()->subMinutes(55)]);
        $this->assertSame('test', $floor->bigStep($heat->fresh())['kind'], 'A test later than the charge is the bigger step.');
    }

    public function test_pouring_more_moulds_than_are_ready_is_refused(): void
    {
        [$furnace, , $recipe] = $this->world();
        $heat = $this->running($furnace, $recipe);
        $product = Product::create(['code' => 'FLG-1', 'name' => 'Flange']);
        $rev = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = ProductionOrder::create(['production_order_number' => 'PO-F1', 'product_id' => $product->id, 'product_revision_id' => $rev->id, 'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 50, 'uom' => 'piece']);
        $pattern = Pattern::create(['code' => 'MP-7', 'name' => 'Flange plate', 'pattern_type' => 'MATCHPLATE']);
        $pr = PatternRevision::create(['pattern_id' => $pattern->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 1]);
        MouldingRun::create(['run_number' => 'MR-F1', 'production_order_id' => $order->id, 'pattern_revision_id' => $pr->id, 'run_date' => today(), 'status' => 'COMPLETED', 'moulds_made_qty' => 5, 'accepted_mould_qty' => 5]);

        $melter = $this->user('MELT_SUPERVISOR');
        $this->actingAs($melter)->get(route('floor.furnace.heat.pour.create', [$heat, 'ladle' => 300]))->assertOk()->assertSee('ready 5');

        $this->actingAs($melter)->post(route('floor.furnace.heat.pour.store', $heat), ['ladle_kg' => 300, 'poured' => [$pr->id.'|'.$order->id => 6]])
            ->assertSessionHasErrors('poured');
        $this->assertSame(0, $heat->pours()->count());
    }

    public function test_a_charge_cannot_be_entered_out_of_order_and_a_fix_keeps_the_entry_with_its_reason(): void
    {
        [$furnace, , $recipe, $pig] = $this->world();
        $heat = $this->running($furnace, $recipe);
        $melter = $this->user('MELT_SUPERVISOR');

        $this->actingAs($melter)->post(route('floor.furnace.heat.charge.store', [$heat, 3]), ['lines' => [['material_id' => $pig->id, 'actual_qty_kg' => 50]]])->assertSessionHasErrors('lines');
        $this->assertSame(0, HeatMaterialConsumption::count());

        $this->actingAs($melter)->post(route('floor.furnace.heat.charge.store', [$heat, 1]), ['lines' => [['material_id' => $pig->id, 'actual_qty_kg' => 45]]])->assertSessionHasNoErrors();
        $entry = HeatMaterialConsumption::firstOrFail();
        $this->actingAs($melter)->post(route('floor.furnace.heat.charge.correct', [$heat, 1]), ['reason' => 'scale was off', 'entries' => [$entry->id => 50]])->assertSessionHasNoErrors();

        $this->assertSame(1, HeatMaterialConsumption::count(), 'Nothing is deleted.');
        $this->assertEquals(50, $entry->fresh()->actual_qty_kg);
        $this->assertStringContainsString('scale was off', $entry->fresh()->notes);
    }

    public function test_closing_with_unaccounted_metal_still_closes_and_tells_the_planner(): void
    {
        [$furnace, , $recipe, $pig] = $this->world();
        $furnace->update(['furnace_type' => 'INDUCTION']);
        $recipe->update(['process_type' => 'INDUCTION']);
        $heat = $this->running($furnace, $recipe, ['process_type' => 'INDUCTION', 'status' => 'IN_PROGRESS', 'blast_on_at' => null]);
        HeatMaterialConsumption::create(['heat_id' => $heat->id, 'material_id' => $pig->id, 'sequence' => 1, 'consumption_type' => 'RAW_MATERIAL', 'actual_qty_kg' => 1000, 'actual_total_cost' => 50000, 'consumed_at' => now()->subHour(), 'recorded_by' => 'Ali', 'charge_round' => 1]);
        $planner = $this->user('PRODUCTION_MANAGER');

        $this->actingAs($this->user('MELT_SUPERVISOR'))->post(route('floor.furnace.heat.close.store', $heat), ['tapped_kg' => 900])->assertSessionHasNoErrors();

        $this->assertSame('CLOSED', $heat->fresh()->status);
        $this->assertNotNull($planner->fresh()->notifications->firstWhere('data.kind', 'heat-unaccounted'), 'The planner is told once, over the limit.');
        $this->assertSame(1, $planner->fresh()->notifications->where('data.kind', 'heat-unaccounted')->count());
    }

    public function test_a_heat_open_on_another_furnace_warns_but_does_not_stop_a_start(): void
    {
        [$furnaceA, , $recipeA] = $this->world('CUP-01');
        [$furnaceB, $grade] = $this->world('CUP-02');
        $open = $this->running($furnaceA, $recipeA);

        $floor = app(FurnaceFloor::class);
        $this->assertSame([$open->id], $floor->openHeats($furnaceB->id)->pluck('id')->all(), 'It is listed as open for the other furnace.');
        $this->assertSame([], $floor->openHeats($furnaceA->id)->pluck('id')->all());

        $this->actingAs($this->user('MELT_SUPERVISOR'))->post(route('floor.furnace.heat.start'), ['furnace_id' => $furnaceB->id, 'iron_grade_id' => $grade->id, 'kg' => 900])->assertSessionHasNoErrors();
        $this->assertSame(2, Heat::count());
        $this->assertSame('MELTING', $open->fresh()->status, 'The earlier heat stays open.');
    }
}
