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
            ->assertSeeInOrder(['P-0123 Rev 1', 'Current', '3 cavities', '<strong>3</strong>per mould', '1 earlier revision', 'P-0123 Rev 0', 'Superseded'], false)
            ->assertSee('<details class="mo-earlier">', false)
            ->assertSee('<div class="mo-item">', false);
    }

    public function test_a_pattern_with_one_revision_has_nothing_folded(): void
    {
        $product = Product::create(['code' => 'VALVE-2', 'name' => 'Valve cap']);
        $productRevision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $pattern = Pattern::create(['code' => 'P-0200', 'name' => 'Cap plate', 'pattern_type' => 'MATCHPLATE']);
        PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 2])
            ->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $productRevision->id]);

        $this->get(route('products.show', $product))->assertOk()->assertSee('Cap plate')->assertDontSee('<details class="mo-earlier">', false);
    }

    public function test_a_new_pattern_can_be_added_from_the_link_dialog_and_gets_its_first_revision_when_linked(): void
    {
        $product = Product::create(['code' => 'VALVE-3', 'name' => 'Valve seat']);
        ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);

        $this->get(route('products.show', $product))->assertOk()->assertSee('+ New pattern')->assertSee('id="qd-new-pattern"', false);
        $this->postJson(route('patterns.quick'), ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors(['name', 'pattern_type']);

        $id = $this->postJson(route('patterns.quick'), ['name' => 'Seat plate', 'pattern_type' => 'MATCHPLATE'])->assertCreated()->assertJsonPath('label', 'Seat plate')->json('id');
        $pattern = Pattern::findOrFail($id);
        $this->assertNotEmpty($pattern->code);
        $this->assertSame(0, $pattern->revisions()->count());

        $this->post(route('products.link-pattern', $product), ['pattern_id' => $id])->assertSessionHasNoErrors();
        $this->assertSame(1, $pattern->revisions()->count());
    }

    public function test_a_product_with_no_pattern_says_so(): void
    {
        $product = Product::create(['code' => 'PUMP-1', 'name' => 'Pump housing']);

        $this->get(route('products.show', $product))->assertOk()->assertSee('None linked yet')->assertSee('No pattern cavity is linked to this product yet.');
    }
}
