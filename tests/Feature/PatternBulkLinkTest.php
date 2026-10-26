<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternBulkLinkTest extends TestCase
{
    use RefreshDatabase;

    private function pattern(string $code, string $name): Pattern
    {
        return Pattern::create(['code' => $code, 'name' => $name, 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
    }

    private function product(string $code, string $name): Product
    {
        return Product::create(['code' => $code, 'name' => $name, 'status' => 'ACTIVE']);
    }

    public function test_screen_suggests_the_same_named_product_and_the_piece_count(): void
    {
        $this->pattern('P-1', 'Outlet 6pc');
        $outlet = $this->product('PR-1', 'Outlet');

        $this->get(route('patterns.bulk-link'))->assertOk()
            ->assertSee('data-value="'.$outlet->id.'"', false)
            ->assertSee('name="rows[0][cavities]" min="1" max="200" step="1" value="6"', false);
    }

    public function test_links_every_cavity_to_the_chosen_revision_and_skips_rows_without_a_product(): void
    {
        $plate = $this->pattern('P-1', 'Outlet plate');
        $skipped = $this->pattern('P-2', 'Other');
        $product = $this->product('PR-1', 'Outlet');
        ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $chosen = ProductRevision::create(['product_id' => $product->id, 'revision' => 'B', 'status' => 'DRAFT']);

        $this->post(route('patterns.bulk-link.store'), ['rows' => [
            ['pattern_id' => $plate->id, 'product_id' => $product->id, 'cavities' => 4, 'product_revision_id' => $chosen->id],
            ['pattern_id' => $skipped->id, 'product_id' => '', 'cavities' => 1],
        ]])->assertSessionHasNoErrors();

        $revision = $plate->fresh()->currentRevision;
        $this->assertSame(4, (int) $revision->cavities_per_mould);
        $this->assertSame([$chosen->id], $revision->cavityMap()->pluck('product_revision_id')->unique()->values()->all());
        $this->assertSame(4, $revision->cavityMap()->count());
        $this->assertNull($skipped->fresh()->currentRevision);
    }

    public function test_a_product_without_a_revision_gets_its_first_one(): void
    {
        $plate = $this->pattern('P-1', 'Hub');
        $product = $this->product('PR-1', 'Hub');

        $this->post(route('patterns.bulk-link.store'), ['rows' => [['pattern_id' => $plate->id, 'product_id' => $product->id, 'cavities' => 1]]])
            ->assertSessionHasNoErrors();

        $this->assertSame($product->revisions()->value('id'), $plate->fresh()->currentRevision->cavityMap()->value('product_revision_id'));
    }

    public function test_nothing_is_saved_when_a_row_repeats_a_pattern_or_picks_an_obsolete_revision(): void
    {
        $plate = $this->pattern('P-1', 'Hub');
        $product = $this->product('PR-1', 'Hub');
        $obsolete = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'OBSOLETE']);

        $this->post(route('patterns.bulk-link.store'), ['rows' => [
            ['pattern_id' => $plate->id, 'product_id' => $product->id, 'cavities' => 1],
            ['pattern_id' => $plate->id, 'product_id' => $product->id, 'cavities' => 1, 'product_revision_id' => $obsolete->id],
        ]])->assertSessionHasErrors(['rows.1.pattern_id', 'rows.1.product_revision_id']);

        $this->assertNull($plate->fresh()->currentRevision);
    }
}
