<?php

namespace Tests\Feature;

use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\MouldAssembly;
use App\Models\MouldCavity;
use App\Models\MouldConfiguration;
use App\Models\MouldLayer;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolingRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pattern_has_revisions(): void
    {
        $pattern = Pattern::create([
            'code' => 'PAT-TEST-001',
            'name' => 'Test Valve Pattern',
        ]);

        $revision = PatternRevision::create([
            'pattern_id' => $pattern->id,
            'revision' => 'A',
        ]);

        $this->assertTrue(
            $pattern->revisions()->whereKey($revision->id)->exists()
        );

        $this->assertTrue($revision->pattern->is($pattern));
    }

    public function test_core_definition_has_core_boxes(): void
    {
        $core = CoreDefinition::create([
            'code' => 'CORE-TEST-001',
            'name' => 'Test Valve Core',
            'core_type' => 'STANDARD',
        ]);

        $box = CoreBox::create([
            'core_definition_id' => $core->id,
            'code' => 'BOX-TEST-001',
            'name' => 'Test Valve Core Box',
        ]);

        $this->assertTrue(
            $core->coreBoxes()->whereKey($box->id)->exists()
        );

        $this->assertTrue($box->coreDefinition->is($core));
    }

    public function test_mould_configuration_has_ordered_layers_and_cavities(): void
    {
        $product = Product::create([
            'code' => 'TOOL-TEST-001',
            'name' => 'Test Valve Casting',
        ]);

        $productRevision = ProductRevision::create([
            'product_id' => $product->id,
            'revision' => 'A',
        ]);

        $pattern = Pattern::create([
            'code' => 'TOOL-PAT-001',
            'name' => 'Test Valve Pattern',
        ]);

        $patternRevision = PatternRevision::create([
            'pattern_id' => $pattern->id,
            'revision' => 'A',
            'cavities_per_mould' => 2,
        ]);

        $configuration = MouldConfiguration::create([
            'code' => 'MOULD-TEST-001',
            'name' => 'Two-Layer Test Mould',
            'mould_type' => 'COPE_DRAG',
            'nominal_layer_count' => 2,
        ]);

        $assembly = MouldAssembly::create([
            'assembly_number' => 'ASSY-TEST-001',
            'mould_configuration_id' => $configuration->id,
        ]);

        $drag = MouldLayer::create([
            'mould_assembly_id' => $assembly->id,
            'layer_number' => 2,
            'layer_type' => 'DRAG',
        ]);

        $cope = MouldLayer::create([
            'mould_assembly_id' => $assembly->id,
            'layer_number' => 1,
            'layer_type' => 'COPE',
        ]);

        $cavity = MouldCavity::create([
            'mould_layer_id' => $cope->id,
            'cavity_number' => 1,
            'pattern_revision_id' => $patternRevision->id,
            'product_revision_id' => $productRevision->id,
            'nominal_casting_weight_kg' => 0.6000,
            'castings_per_cavity' => 1,
        ]);

        $this->assertTrue(
            $configuration->assemblies()->whereKey($assembly->id)->exists()
        );

        $this->assertTrue($assembly->configuration->is($configuration));

        $this->assertSame(
            [$cope->id, $drag->id],
            $assembly->layers->pluck('id')->all()
        );

        $this->assertTrue($cope->assembly->is($assembly));

        $this->assertTrue(
            $cope->cavities()->whereKey($cavity->id)->exists()
        );

        $this->assertTrue($cavity->patternRevision->is($patternRevision));

        $this->assertTrue($cavity->productRevision->is($productRevision));
    }
}