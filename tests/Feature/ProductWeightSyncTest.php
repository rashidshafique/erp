<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductWeightSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_revision_made_without_a_weight_starts_with_the_products_nominal_weight(): void
    {
        $product = Product::create(['code' => 'W-1', 'name' => 'Weighted', 'nominal_weight_kg' => 4.5]);

        $revision = $product->revisions()->create(['revision' => '0', 'status' => 'APPROVED']);

        $this->assertSame('4.5000', $revision->fresh()->net_weight_kg);
        $this->assertSame('4.5000', $product->fresh()->nominal_weight_kg);
    }

    public function test_the_nominal_weight_follows_the_current_revision_and_ignores_obsolete_ones(): void
    {
        $product = Product::create(['code' => 'W-2', 'name' => 'Weighted']);
        $old = $product->revisions()->create(['revision' => '0', 'status' => 'APPROVED', 'net_weight_kg' => 5]);
        $this->assertSame('5.0000', $product->fresh()->nominal_weight_kg);

        $new = $product->revisions()->create(['revision' => '1', 'status' => 'DRAFT', 'net_weight_kg' => 5.8]);
        $this->assertSame('5.0000', $product->fresh()->nominal_weight_kg, 'An approved revision stays current over a newer draft.');

        $old->update(['status' => 'OBSOLETE']);
        $this->assertSame('5.8000', $product->fresh()->nominal_weight_kg, 'With the old one obsolete, the draft is the one the product is made to.');

        $new->update(['net_weight_kg' => 5.9]);
        $this->assertSame('5.9000', $product->fresh()->nominal_weight_kg);
        $old->update(['net_weight_kg' => 1]);
        $this->assertSame('5.9000', $product->fresh()->nominal_weight_kg, 'An obsolete revision no longer moves it.');
    }

    public function test_setting_a_revisions_weight_from_the_product_page_moves_the_nominal_weight(): void
    {
        $product = Product::create(['code' => 'W-3', 'name' => 'Weighted']);
        $revision = $product->revisions()->create(['revision' => '0', 'status' => 'APPROVED']);

        $this->put(route('products.revisions.update', [$product, $revision]), ['net_weight_kg' => '7.25'])->assertSessionHasNoErrors();

        $this->assertSame('7.2500', $product->fresh()->nominal_weight_kg);
    }

    public function test_changing_the_nominal_weight_on_the_product_changes_the_current_revision(): void
    {
        $product = Product::create(['code' => 'W-4', 'name' => 'Weighted']);
        $revision = $product->revisions()->create(['revision' => '0', 'status' => 'APPROVED', 'net_weight_kg' => 3]);
        $form = ['code' => 'W-4', 'name' => 'Weighted', 'product_type' => 'CASTING', 'base_uom' => 'PCS', 'active' => 1, 'status' => 'ACTIVE'];

        $this->put(route('products.update', $product), $form + ['nominal_weight_kg' => '3.6'])->assertSessionHasNoErrors();

        $this->assertSame(['3.6000', '3.6000'], [$revision->fresh()->net_weight_kg, $product->fresh()->nominal_weight_kg]);
    }

    public function test_a_product_with_no_revision_keeps_the_weight_typed_on_it(): void
    {
        $product = Product::create(['code' => 'W-5', 'name' => 'No revision']);
        $form = ['code' => 'W-5', 'name' => 'No revision', 'product_type' => 'CASTING', 'base_uom' => 'PCS', 'active' => 1, 'status' => 'ACTIVE'];

        $this->put(route('products.update', $product), $form + ['nominal_weight_kg' => '2.2'])->assertSessionHasNoErrors();

        $this->assertSame('2.2000', $product->fresh()->nominal_weight_kg);
    }

    public function test_the_migration_moves_a_weight_that_was_only_on_the_product_onto_its_revision_and_lines_the_others_up(): void
    {
        $onlyNominal = Product::create(['code' => 'M-1', 'name' => 'Only nominal']);
        $r1 = $onlyNominal->revisions()->create(['revision' => '0', 'status' => 'APPROVED']);
        $apart = Product::create(['code' => 'M-2', 'name' => 'Apart']);
        $r2 = $apart->revisions()->create(['revision' => '0', 'status' => 'APPROVED', 'net_weight_kg' => 8]);
        // Old data: the two weights were typed separately and never agreed.
        DB::table('products')->where('id', $onlyNominal->id)->update(['nominal_weight_kg' => 6]);
        DB::table('products')->where('id', $apart->id)->update(['nominal_weight_kg' => 9]);

        (require database_path('migrations/'.collect(scandir(database_path('migrations')))->first(fn ($f) => str_contains($f, 'align_nominal_weight_with_current_revision'))))->up();

        $this->assertSame(['6.0000', '6.0000'], [$r1->fresh()->net_weight_kg, $onlyNominal->fresh()->nominal_weight_kg]);
        $this->assertSame(['8.0000', '8.0000'], [$r2->fresh()->net_weight_kg, $apart->fresh()->nominal_weight_kg], 'The revision weight wins where they disagreed.');
    }
}
