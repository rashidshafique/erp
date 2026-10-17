<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPatternsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_product_page_links_to_the_patterns_that_make_it(): void
    {
        $product = Product::create(['code' => 'VALVE-1', 'name' => 'Valve body']);
        $productRevision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Valve body plate', 'pattern_type' => 'MATCHPLATE']);
        $old = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 4]);
        foreach ([1, 2, 3] as $n) {
            $old->cavityMap()->create(['cavity_number' => $n, 'product_revision_id' => $productRevision->id]);
        }
        $current = $pattern->addRevision(['change_description' => 'New gating'], copyLayout: true);

        $page = $this->get(route('products.show', $product));

        $page->assertOk()
            ->assertSee('data-tab-start="Patterns"', false)
            ->assertSee('<a href="'.route('patterns.show', $pattern).'">Valve body plate</a>', false)
            ->assertSee(route('patterns.show', $pattern).'#cavities-'.$current->id, false)
            ->assertSeeInOrder(['P-0123-R1', 'Current', 'cavities 1, 2, 3', '<strong>3</strong>per mould', 'P-0123-R0', 'Superseded'], false);
    }

    public function test_a_product_with_no_pattern_says_so(): void
    {
        $product = Product::create(['code' => 'PUMP-1', 'name' => 'Pump housing']);

        $this->get(route('products.show', $product))->assertOk()->assertSee('None linked yet')->assertSee('No pattern cavity is linked to this product yet.');
    }
}
