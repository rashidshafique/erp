<?php

namespace Tests\Feature;

use App\Models\MouldingRun;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveProductsAndPatternsTest extends TestCase
{
    use RefreshDatabase;

    private function order(Product $product, string $status): ProductionOrder
    {
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);

        return ProductionOrder::create(['production_order_number' => 'PO-'.$status, 'product_id' => $product->id, 'product_revision_id' => $revision->id, 'order_type' => 'STOCK', 'status' => $status, 'planned_qty' => 10, 'uom' => 'piece']);
    }

    public function test_an_archived_product_leaves_the_list_and_counts_but_stays_on_old_orders_and_can_be_restored(): void
    {
        $kept = Product::create(['code' => 'VALVE-1', 'name' => 'Valve body', 'active' => true]);
        $gone = Product::create(['code' => 'PUMP-1', 'name' => 'Old pump housing', 'active' => true]);
        $order = $this->order($gone, 'COMPLETED');

        $this->delete(route('products.archive', $gone))->assertRedirect(route('products.index'))->assertSessionHas('success');

        $this->get(route('products.index'))->assertOk()->assertSee('VALVE-1')->assertDontSee('Old pump housing')
            ->assertViewHas('totalProducts', 1)->assertViewHas('activeProducts', 1)->assertSee('1 archived');
        $this->get(route('products.index', ['archived' => 1]))->assertOk()->assertSee('Old pump housing')->assertDontSee('Valve body')->assertSee('Restore');
        $this->get(route('products.show', $gone))->assertOk()->assertSee('Archived on')->assertSee('Restore product');
        $this->assertSame('PUMP-1', $order->fresh()->product->code);

        $this->post(route('products.restore', $gone))->assertRedirect(route('products.show', $gone));
        $this->get(route('products.index'))->assertSee('Old pump housing')->assertViewHas('totalProducts', 2);
        $this->assertModelExists($kept);
    }

    public function test_a_product_with_an_open_order_is_not_archived(): void
    {
        $product = Product::create(['code' => 'PUMP-1', 'name' => 'Pump housing', 'active' => true]);
        $this->order($product, 'RELEASED');

        $this->from(route('products.show', $product))->delete(route('products.archive', $product))
            ->assertSessionHasErrors(['archive' => 'PUMP-1 has open production orders (PO-RELEASED). Complete or cancel them before archiving it.']);

        $this->assertNotSoftDeleted($product);
    }

    public function test_an_archived_pattern_leaves_the_list_counts_and_pickers_and_can_be_restored(): void
    {
        Pattern::create(['code' => 'MP-1', 'name' => 'Flange plate', 'status' => 'ACTIVE']);
        $gone = Pattern::create(['code' => 'MP-2', 'name' => 'Old cover plate', 'status' => 'ACTIVE']);
        $revision = PatternRevision::create(['pattern_id' => $gone->id, 'status' => 'APPROVED']);

        $this->delete(route('patterns.archive', $gone))->assertRedirect(route('patterns.index'))->assertSessionHas('success');

        $this->get(route('patterns.index'))->assertOk()->assertDontSee('Old cover plate')->assertViewHas('totalPatterns', 1)->assertSee('1 archived');
        $this->get(route('patterns.index', ['archived' => 1]))->assertOk()->assertSee('Old cover plate');
        $this->get(route('moulding-runs.bulk'))->assertOk()->assertSee('Flange plate')->assertDontSee('Old cover plate');
        $this->get(route('patterns.show', $gone))->assertOk()->assertSee('Archived on');
        $this->assertSame('MP-2', $revision->fresh()->pattern->code);

        $this->post(route('patterns.restore', $gone))->assertRedirect(route('patterns.show', $gone));
        $this->assertNotSoftDeleted($gone);
    }

    public function test_a_pattern_being_moulded_is_not_archived(): void
    {
        $product = Product::create(['code' => 'PUMP-1', 'name' => 'Pump housing', 'active' => true]);
        $pattern = Pattern::create(['code' => 'MP-2', 'name' => 'Cover plate', 'status' => 'ACTIVE']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);
        MouldingRun::create(['run_number' => 'MR-1', 'production_order_id' => $this->order($product, 'IN_PROGRESS')->id, 'pattern_revision_id' => $revision->id, 'run_date' => today(), 'status' => 'IN_PROGRESS']);

        $this->from(route('patterns.show', $pattern))->delete(route('patterns.archive', $pattern))
            ->assertSessionHasErrors(['archive' => 'MP-2 is still in use: moulding run MR-1 is in progress. Finish or remove those before archiving it.']);

        $this->assertNotSoftDeleted($pattern);
    }
}
