<?php

namespace Tests\Feature;

use App\Models\MouldAssembly;
use App\Models\MouldCavity;
use App\Models\MouldConfiguration;
use App\Models\MouldLayer;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Services\PatternCavityMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PatternCavityMapTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $code, string $status = 'APPROVED'): ProductRevision
    {
        $p = Product::create(['code' => $code, 'name' => $code.' casting']);

        return ProductRevision::create(['product_id' => $p->id, 'revision' => 'A', 'status' => $status]);
    }

    private function patternRev(int $cavities = 4): PatternRevision
    {
        $pat = Pattern::create(['code' => 'PAT-'.uniqid(), 'name' => 'Test']);

        return PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'cavities_per_mould' => $cavities]);
    }

    public function test_cavities_link_to_whole_and_part_products(): void
    {
        $rev = $this->patternRev();
        $a = $this->make('VALVE-1');
        $b = $this->make('PUMP-1');

        $map = app(PatternCavityMap::class);
        foreach ([
            ['cavity_number' => 1, 'product_revision_id' => $a->id, 'cavity_role' => 'WHOLE'],
            ['cavity_number' => 2, 'product_revision_id' => $b->id, 'cavity_role' => 'PART', 'component_label' => 'cover'],
        ] as $d) {
            $map->assertDefinable($rev, $d);
            $rev->cavityMap()->create($d + ['pieces_per_product' => 1]);
        }

        $this->assertCount(2, $rev->cavityMap);
        $this->assertSame('Part: cover', $rev->cavityMap[1]->label());
        $this->assertTrue($a->patternCavities()->exists());
    }

    public function test_rejects_cavity_beyond_pattern_capacity_duplicate_and_obsolete_product(): void
    {
        $rev = $this->patternRev(2);
        $a = $this->make('A-1');
        $old = $this->make('OLD-1', 'OBSOLETE');
        $map = app(PatternCavityMap::class);

        $this->expectValidation(fn () => $map->assertDefinable($rev, ['cavity_number' => 3, 'product_revision_id' => $a->id]), 'cavity_number');

        $rev->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $a->id]);
        $this->expectValidation(fn () => $map->assertDefinable($rev, ['cavity_number' => 1, 'product_revision_id' => $a->id]), 'cavity_number');
        $this->expectValidation(fn () => $map->assertDefinable($rev, ['cavity_number' => 2, 'product_revision_id' => $old->id]), 'product_revision_id');
        $this->expectValidation(fn () => $map->assertDefinable($rev, ['cavity_number' => 2, 'product_revision_id' => $a->id, 'cavity_role' => 'PART']), 'component_label');
        $this->expectValidation(fn () => $map->assertDefinable($rev, ['cavity_number' => 2, 'product_revision_id' => $a->id, 'casting_weight_kg' => 5, 'poured_weight_kg' => 4]), 'poured_weight_kg');
    }

    public function test_database_rejects_part_without_label_and_bad_weights(): void
    {
        $rev = $this->patternRev();
        $a = $this->make('A-2');

        $this->expectException(\Illuminate\Database\QueryException::class);
        PatternRevisionCavity::create(['pattern_revision_id' => $rev->id, 'cavity_number' => 1, 'product_revision_id' => $a->id, 'cavity_role' => 'PART']);
    }

    public function test_mould_cavity_must_match_the_map_once_one_exists(): void
    {
        $rev = $this->patternRev();
        $a = $this->make('A-3');
        $b = $this->make('B-3');
        $rev->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $a->id]);

        $cfg = MouldConfiguration::create(['code' => 'MC-1', 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);
        $asm = MouldAssembly::create(['mould_configuration_id' => $cfg->id, 'assembly_number' => 'ASSY-'.uniqid()]);
        $layer = MouldLayer::create(['mould_assembly_id' => $asm->id, 'layer_number' => 1, 'layer_type' => 'COPE']);

        $ok = MouldCavity::create(['mould_layer_id' => $layer->id, 'cavity_number' => 1, 'pattern_revision_id' => $rev->id, 'product_revision_id' => $a->id]);
        $this->assertNotNull($ok->id);

        $this->expectValidation(fn () => MouldCavity::create(['mould_layer_id' => $layer->id, 'cavity_number' => 1, 'pattern_revision_id' => $rev->id, 'product_revision_id' => $b->id]), 'product_revision_id');
        $this->expectValidation(fn () => MouldCavity::create(['mould_layer_id' => $layer->id, 'cavity_number' => 3, 'pattern_revision_id' => $rev->id, 'product_revision_id' => $a->id]), 'cavity_number');
    }

    public function test_expected_products_per_mould_converts_parts(): void
    {
        $rev = $this->patternRev(4);
        $a = $this->make('ASM-1');
        $rev->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $a->id]);
        $rev->cavityMap()->create(['cavity_number' => 2, 'product_revision_id' => $a->id, 'cavity_role' => 'PART', 'component_label' => 'body']);
        $rev->cavityMap()->create(['cavity_number' => 3, 'product_revision_id' => $a->id, 'cavity_role' => 'PART', 'component_label' => 'body']);
        $rev->cavityMap()->create(['cavity_number' => 4, 'product_revision_id' => $a->id, 'cavity_role' => 'PART', 'component_label' => 'lid']);

        // 1 whole + min(2 bodies, 1 lid) = 2 complete products per mould
        $this->assertSame(2.0, app(PatternCavityMap::class)->expectedProductsPerMould($rev, $a->product_id));
    }

    public function test_cavity_map_screens_and_endpoints(): void
    {
        $rev = $this->patternRev(4);
        $a = $this->make('WEB-1');
        $pattern = $rev->pattern;

        $this->post(route('patterns.cavities.store', [$pattern, $rev]), [
            'cavity_number' => 1, 'product_revision_id' => $a->id, 'cavity_role' => 'WHOLE',
            'casting_weight_kg' => 8, 'poured_weight_kg' => 12,
        ])->assertRedirect(route('patterns.show', $pattern));

        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('Cavity map')->assertSee('WEB-1')->assertSee('66.7');
        $this->get(route('products.show', $a->product))->assertOk()->assertSee('Made on these patterns')->assertSee($pattern->code);

        $this->post(route('patterns.cavities.store', [$pattern, $rev]), [
            'cavity_number' => 9, 'product_revision_id' => $a->id, 'cavity_role' => 'WHOLE',
        ])->assertSessionHasErrors('cavity_number');

        $cav = $rev->cavityMap()->first();
        $this->delete(route('patterns.cavities.destroy', [$pattern, $rev, $cav]))->assertRedirect();
        $this->assertSame(0, $rev->cavityMap()->count());
    }

    public function test_backfill_style_mismatch_report(): void
    {
        $rev = $this->patternRev();
        $a = $this->make('A-4');
        $b = $this->make('B-4');
        $cfg = MouldConfiguration::create(['code' => 'MC-2', 'name' => 'Cfg2', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);
        $asm = MouldAssembly::create(['mould_configuration_id' => $cfg->id, 'assembly_number' => 'ASSY-'.uniqid()]);
        $layer = MouldLayer::create(['mould_assembly_id' => $asm->id, 'layer_number' => 1, 'layer_type' => 'COPE']);
        // Cavity created before any map exists, then a map is added that disagrees.
        MouldCavity::create(['mould_layer_id' => $layer->id, 'cavity_number' => 1, 'pattern_revision_id' => $rev->id, 'product_revision_id' => $a->id]);
        $rev->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $b->id]);

        $this->assertCount(1, app(PatternCavityMap::class)->mismatches());
    }

    public function test_moulding_run_pattern_must_make_the_order_product(): void
    {
        $rev = $this->patternRev();
        $a = $this->make('RUN-A');
        $other = $this->make('RUN-B');
        $rev->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $a->id]);

        $order = fn ($pr) => \App\Models\ProductionOrder::create([
            'production_order_number' => 'PO-'.uniqid(),
            'product_id' => $pr->product_id,
            'product_revision_id' => $pr->id,
            'planned_qty' => 10,
        ]);
        $map = app(PatternCavityMap::class);

        $map->assertPatternMakesOrderProduct($rev->id, $order($a)->id);
        $map->assertPatternMakesOrderProduct(null, $order($other)->id);
        $this->expectValidation(fn () => $map->assertPatternMakesOrderProduct($rev->id, $order($other)->id), 'pattern_revision_id');
    }

    private function expectValidation(callable $fn, string $key): void
    {
        try {
            $fn();
            $this->fail("Expected a validation error on {$key}");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }
}
