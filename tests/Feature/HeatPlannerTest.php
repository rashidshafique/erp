<?php

namespace Tests\Feature;

use App\Domains\QuickFlow\Services\QuickFloorLinker;
use App\Models\Furnace;
use App\Models\IronGrade;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\PlannedHeat;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\ProductSpecification;
use App\Services\Planning\HeatPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class HeatPlannerTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    private function furnace(string $type, float $cap, string $code): Furnace
    {
        return Furnace::create(['code' => $code, 'name' => $code, 'furnace_type' => $type, 'status' => 'ACTIVE', 'rated_capacity_kg' => $cap, 'nominal_melting_rate_kg_per_hour' => $cap / 1.5]);
    }

    private function orderFor(string $grade, float $qty, float $netKg, ?string $required = null, ?string $code = null): ProductionOrder
    {
        $p = Product::create(['code' => $code ?? 'P'.uniqid(), 'name' => 'Part']);
        $r = ProductRevision::create(['product_id' => $p->id, 'revision' => 'A', 'status' => 'APPROVED', 'net_weight_kg' => $netKg]);
        ProductSpecification::create(['product_revision_id' => $r->id, 'specification_code' => 'S'.uniqid(), 'title' => 's', 'status' => 'APPROVED', 'material_grade' => $grade]);

        return ProductionOrder::create([
            'production_order_number' => 'PO-'.uniqid(), 'product_id' => $p->id, 'product_revision_id' => $r->id, 'status' => 'RELEASED',
            'planned_qty' => $qty, 'required_date' => $required,
        ]);
    }

    public function test_orders_group_by_grade_and_split_to_furnace_capacity(): void
    {
        $this->furnace('INDUCTION', 1000, 'IF1');
        // 600 kg net / 0.6 yield = 1000 kg poured per piece; 2 pieces = 2000 kg for the grade.
        $this->orderFor('EN-GJL-250', 1, 600);
        $this->orderFor('EN-GJL-250', 1, 600);

        $res = app(HeatPlanner::class)->rebuild(today());

        $heats = PlannedHeat::with('orders')->orderBy('planned_date')->orderBy('sequence')->get();
        $this->assertSame(3, $res['heats']);                       // 2000 kg at 900 kg per heat = 3 heats
        $this->assertEqualsWithDelta(2000.0, (float) $heats->sum('planned_metal_kg'), 0.01);
        $this->assertTrue($heats->every(fn ($h) => (float) $h->planned_metal_kg <= 900.001));
    }

    public function test_cupola_only_gets_grey_and_ductile_goes_to_induction(): void
    {
        $cupola = $this->furnace('CUPOLA', 5000, 'CU1');
        $ind = $this->furnace('INDUCTION', 1000, 'IF1');
        $this->orderFor('EN-GJL-250', 1, 300);
        $this->orderFor('EN-GJS-500-7', 1, 300);

        app(HeatPlanner::class)->rebuild(today());

        $grey = PlannedHeat::whereHas('ironGrade', fn ($q) => $q->where('family', 'GREY'))->first();
        $ductile = PlannedHeat::whereHas('ironGrade', fn ($q) => $q->where('family', 'DUCTILE'))->first();
        $this->assertNotNull($grey);
        $this->assertSame($ind->id, $ductile->furnace_id);
        $this->assertContains($grey->furnace_id, [$cupola->id, $ind->id]);
    }

    public function test_grey_before_ductile_and_changeover_flagged_on_one_furnace(): void
    {
        $this->furnace('INDUCTION', 1000, 'IF1');
        $this->orderFor('EN-GJS-500-7', 1, 300);
        $this->orderFor('EN-GJL-250', 1, 300);

        app(HeatPlanner::class)->rebuild(today());
        $heats = PlannedHeat::with('ironGrade')->orderBy('planned_date')->orderBy('sequence')->get();

        $this->assertSame('GREY', $heats[0]->ironGrade->family);
        $this->assertSame('DUCTILE', $heats[1]->ironGrade->family);
        $this->assertFalse($heats[0]->changeover_required);
        $this->assertTrue($heats[1]->changeover_required);
    }

    public function test_late_risk_and_unplannable_orders_are_reported(): void
    {
        $this->furnace('INDUCTION', 1000, 'IF1');
        $this->orderFor('EN-GJL-250', 1, 300, today()->toDateString());
        $noGrade = $this->orderFor('', 1, 300);
        $noWeight = $this->orderFor('EN-GJL-250', 1, 0);

        $res = app(HeatPlanner::class)->rebuild(today());

        $this->assertTrue(PlannedHeat::where('late_risk', true)->exists());
        $this->assertCount(2, $res['skipped']);
        $this->assertStringContainsString($noGrade->production_order_number, implode(' ', $res['skipped']));
    }

    public function test_rebuild_is_repeatable_and_keeps_released_heats(): void
    {
        $this->furnace('INDUCTION', 1000, 'IF1');
        $o = $this->orderFor('EN-GJL-250', 1, 300);
        $planner = app(HeatPlanner::class);

        $planner->rebuild(today());
        $first = PlannedHeat::first();
        $heat = $planner->release($first, 'Imran');
        $this->assertSame('PLANNED', $heat->status);
        $this->assertSame($first->iron_grade_id, $heat->iron_grade_id);
        $this->assertSame('RELEASED', $first->fresh()->status);

        // The order's metal is already covered by the released heat, so a rebuild plans nothing new.
        $res = $planner->rebuild(today());
        $this->assertSame(0, $res['heats']);
        $this->assertSame(1, PlannedHeat::count());
    }

    public function test_a_started_order_keeps_its_unpoured_metal_in_the_plan(): void
    {
        $this->furnace('INDUCTION', 1000, 'IF1');
        // 300 kg net / 0.6 yield = 500 kg per piece; 4 pieces ordered, 1 already poured, so 1500 kg is still to melt.
        $o = $this->orderFor('EN-GJL-250', 4, 300);
        $o->update(['status' => 'IN_PROGRESS']);
        $this->castingLot()->update(['production_order_id' => $o->id, 'product_id' => $o->product_id, 'product_revision_id' => $o->product_revision_id, 'quantity_poured' => 1, 'quantity_good' => 1]);

        app(HeatPlanner::class)->rebuild(today());

        $this->assertEqualsWithDelta(1500.0, (float) PlannedHeat::sum('planned_metal_kg'), 0.01);
    }

    public function test_cavity_map_weights_drive_the_metal_estimate(): void
    {
        $this->furnace('INDUCTION', 1000, 'IF1');
        $o = $this->orderFor('EN-GJL-250', 10, 50);
        $pat = Pattern::create(['code' => 'PP'.uniqid(), 'name' => 'x']);
        $pr = PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        $pr->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $o->product_revision_id, 'casting_weight_kg' => 50, 'poured_weight_kg' => 80]);
        $pr->cavityMap()->create(['cavity_number' => 2, 'product_revision_id' => $o->product_revision_id, 'casting_weight_kg' => 50, 'poured_weight_kg' => 80]);

        $per = app(HeatPlanner::class)->metalPerProduct($o->productRevision);
        $this->assertSame('mould metal (castings, gating, runners, sprue), shared by casting weight', $per['source']);
        $this->assertEqualsWithDelta(80.0, $per['kg'], 0.001);   // 160 kg poured per mould / 2 products
    }

    public function test_a_closed_cavity_does_not_count_in_the_metal_estimate_or_castings_per_mould(): void
    {
        $this->furnace('INDUCTION', 1000, 'IF1');
        $o = $this->orderFor('EN-GJL-250', 10, 50);
        $pat = Pattern::create(['code' => 'PP'.uniqid(), 'name' => 'x']);
        $pr = PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 3]);
        foreach ([1, 2] as $n) {
            $pr->cavityMap()->create(['cavity_number' => $n, 'product_revision_id' => $o->product_revision_id, 'casting_weight_kg' => 50, 'poured_weight_kg' => 80]);
        }
        $pr->cavityMap()->create(['cavity_number' => 3, 'product_revision_id' => $o->product_revision_id, 'casting_weight_kg' => 50, 'poured_weight_kg' => 80, 'is_closed' => true]);

        $this->assertEqualsWithDelta(80.0, app(HeatPlanner::class)->metalPerProduct($o->productRevision)['kg'], 0.001);
        $this->assertSame(2, app(QuickFloorLinker::class)->cavitiesFromMap($pr->fresh()));
    }

    public function test_cannot_release_on_a_furnace_that_cannot_melt_the_grade(): void
    {
        $cu = $this->furnace('CUPOLA', 5000, 'CU1');
        $this->orderFor('EN-GJL-250', 1, 300);
        app(HeatPlanner::class)->rebuild(today());
        $planned = PlannedHeat::first();
        $planned->update(['iron_grade_id' => IronGrade::where('code', 'EN-GJS-500-7')->value('id')]);

        $this->expectException(\InvalidArgumentException::class);
        app(HeatPlanner::class)->release($planned->fresh());
    }
}
