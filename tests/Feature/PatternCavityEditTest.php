<?php

namespace Tests\Feature;

use App\Models\MouldCavity;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Services\PatternCavityMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class PatternCavityEditTest extends TestCase
{
    use BuildsCastingLots;
    use RefreshDatabase;

    private function productRevision(string $code): ProductRevision
    {
        return ProductRevision::create(['product_id' => Product::create(['code' => $code, 'name' => $code.' casting'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
    }

    /** @return array{0: Pattern, 1: PatternRevision, 2: PatternRevisionCavity, 3: ProductRevision} */
    private function mapped(): array
    {
        $pattern = Pattern::create(['code' => 'PAT-'.uniqid(), 'name' => 'Plate']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        $product = $this->productRevision('VLV-'.uniqid());
        $cavity = $revision->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $product->id, 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1, 'needs_review' => true]);

        return [$pattern, $revision, $cavity, $product];
    }

    public function test_a_cavity_link_is_updated_and_a_review_flag_cleared(): void
    {
        [$pattern, $revision, $cavity, $product] = $this->mapped();

        $this->put(route('patterns.cavities.update', [$pattern, $revision, $cavity]), [
            'cavity_number' => 1, 'product_revision_id' => $product->id, 'cavity_role' => 'WHOLE', 'casting_weight_kg' => 4.5,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(4.5, $cavity->fresh()->casting_weight_kg);

        $this->put(route('patterns.cavities.update', [$pattern, $revision, $cavity]), ['cavity_number' => 1, 'product_revision_id' => $product->id, 'cavity_role' => 'SPARE'])->assertSessionHasErrors('cavity_role');

        $this->assertTrue($cavity->fresh()->needs_review);
        $this->put(route('patterns.cavities.review', [$pattern, $revision, $cavity]))->assertRedirect(route('patterns.show', $pattern));
        $this->assertFalse($cavity->fresh()->needs_review);
    }

    public function test_a_cavity_used_on_a_mould_keeps_its_product(): void
    {
        [$pattern, $revision, $cavity, $product] = $this->mapped();
        $other = $this->productRevision('PMP-1');
        $lot = $this->castingLot();
        $layer = $lot->pourMouldAssembly->mouldAssembly->layers()->create(['layer_number' => 1, 'layer_type' => 'COPE', 'status' => 'READY']);
        MouldCavity::create(['mould_layer_id' => $layer->id, 'pattern_revision_id' => $revision->id, 'cavity_number' => 1, 'product_revision_id' => $product->id, 'castings_per_cavity' => 1, 'is_active' => true]);

        $this->put(route('patterns.cavities.update', [$pattern, $revision, $cavity]), ['cavity_number' => 1, 'product_revision_id' => $other->id, 'cavity_role' => 'WHOLE'])->assertSessionHasErrors('product_revision_id');
        $this->assertSame($product->id, $cavity->fresh()->product_revision_id);
    }

    public function test_a_cavity_cannot_be_reached_through_another_pattern(): void
    {
        [, $revision, $cavity, $product] = $this->mapped();
        [$otherPattern] = $this->mapped();

        $this->put(route('patterns.cavities.update', [$otherPattern, $revision, $cavity]), ['cavity_number' => 1, 'product_revision_id' => $product->id, 'cavity_role' => 'WHOLE'])->assertNotFound();
        $this->put(route('patterns.cavities.review', [$otherPattern, $revision, $cavity]))->assertNotFound();
    }

    public function test_a_closed_cavity_is_saved_from_the_map_and_makes_no_casting(): void
    {
        [$pattern, $revision, $cavity, $product] = $this->mapped();
        $row = ['product_revision_id' => $product->id, 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1, 'casting_weight_kg' => 2];

        $this->put(route('patterns.cavities.map', [$pattern, $revision]), ['cavities_per_mould' => 2, 'rows' => [1 => $row, 2 => $row + ['is_closed' => '1']]])
            ->assertSessionHasNoErrors();

        $this->assertSame([false, true], $revision->cavityMap()->pluck('is_closed')->all());
        $map = app(PatternCavityMap::class);
        $this->assertSame(1.0, $map->expectedProductsPerMould($revision->fresh(), $product->product_id));
        $this->assertSame(1, $map->metalPerMould($revision->fresh())['castings']);
        $this->assertSame(2.0, $map->metalPerMould($revision->fresh())['castings_kg']);
    }
}
