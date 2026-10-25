<?php

namespace Tests\Feature;

use App\Domains\Engineering\Models\BomLine;
use App\Domains\Engineering\Services\BomExploder;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillOfMaterialsTest extends TestCase
{
    use RefreshDatabase;

    private function revision(string $label = 'A', ?Product $product = null): ProductRevision
    {
        $product ??= Product::create(['code' => 'P-'.uniqid(), 'name' => 'Part']);

        return ProductRevision::create(['product_id' => $product->id, 'revision' => $label, 'status' => 'APPROVED', 'net_weight_kg' => 10]);
    }

    private function material(string $name = 'Filter'): Material
    {
        return Material::create(['code' => 'M-'.uniqid(), 'name' => $name, 'material_type' => 'OTHER', 'base_uom' => 'piece', 'status' => 'ACTIVE', 'is_stock_tracked' => true, 'requires_lot_tracking' => false]);
    }

    private function order(ProductRevision $revision, int $qty): ProductionOrder
    {
        return ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'product_id' => $revision->product_id, 'product_revision_id' => $revision->id, 'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => $qty, 'uom' => 'piece']);
    }

    public function test_an_item_is_added_once_per_revision_and_the_list_shows_on_the_product_page(): void
    {
        $revision = $this->revision();
        $filter = $this->material();

        $this->post(route('bom.store', $revision), ['material_id' => $filter->id, 'quantity' => 2, 'basis' => 'PER_CASTING'])->assertSessionHasNoErrors();
        $this->post(route('bom.store', $revision), ['material_id' => $filter->id, 'quantity' => 1, 'basis' => 'PER_MOULD'])->assertSessionHasErrors('material_id');
        $this->post(route('bom.store', $revision), ['material_id' => $filter->id, 'quantity' => 0, 'basis' => 'PER_CASTING'])->assertSessionHasErrors('quantity');

        $this->assertSame(1, $revision->bomLines()->count());
        $this->get(route('products.show', $revision->product_id))->assertOk()->assertSee('Bill of materials')->assertSee($filter->name);
    }

    public function test_a_line_can_be_changed_and_removed(): void
    {
        $revision = $this->revision();
        $line = $revision->bomLines()->create(['material_id' => $this->material()->id, 'quantity' => 1, 'basis' => 'PER_CASTING']);

        $this->put(route('bom.update', $line), ['quantity' => 3, 'basis' => 'PER_KG_POURED'])->assertSessionHasNoErrors();
        $this->assertSame('PER_KG_POURED', $line->fresh()->basis);

        $this->delete(route('bom.destroy', $line))->assertRedirect();
        $this->assertSame(0, BomLine::count());
    }

    public function test_a_list_is_copied_from_another_revision_of_the_same_product_only_when_empty(): void
    {
        $a = $this->revision('A');
        $b = $this->revision('B', $a->product);
        $other = $this->revision('A');
        $a->bomLines()->create(['material_id' => $this->material()->id, 'quantity' => 1, 'basis' => 'PER_CASTING']);

        $this->post(route('bom.copy', $b), ['from_revision_id' => $other->id])->assertSessionHasErrors('from_revision_id');
        $this->post(route('bom.copy', $b), ['from_revision_id' => $a->id])->assertSessionHasNoErrors();
        $this->assertSame(1, $b->bomLines()->count());

        $this->post(route('bom.copy', $b), ['from_revision_id' => $a->id])->assertSessionHasErrors('bom');
        $this->assertSame(1, $b->bomLines()->count());
    }

    public function test_an_order_needs_per_casting_quantities_and_says_when_a_basis_cannot_be_worked_out(): void
    {
        $revision = $this->revision();
        $chill = $this->material('Chill');
        $sleeve = $this->material('Sleeve');
        $revision->bomLines()->create(['material_id' => $chill->id, 'quantity' => 2, 'basis' => 'PER_CASTING']);
        $revision->bomLines()->create(['material_id' => $sleeve->id, 'quantity' => 1, 'basis' => 'PER_MOULD']);
        $order = $this->order($revision, 50);

        $rows = app(BomExploder::class)->forOrder($order)->keyBy(fn ($r) => $r['line']->material_id);

        $this->assertSame(100.0, $rows[$chill->id]['required']);
        $this->assertSame(100.0, $rows[$chill->id]['short']);
        $this->assertNull($rows[$sleeve->id]['required']);
        $this->assertNotNull($rows[$sleeve->id]['note']);
        $this->get(route('production-orders.show', $order))->assertOk()->assertSee('Materials needed')->assertSee('Chill');
    }

    public function test_per_kg_poured_follows_the_metal_the_planner_works_out(): void
    {
        $revision = $this->revision();
        $coating = $this->material('Coating');
        $revision->bomLines()->create(['material_id' => $coating->id, 'quantity' => 0.02, 'basis' => 'PER_KG_POURED']);
        $perProduct = 10 / (float) config('foundry.planner.default_yield');

        $row = app(BomExploder::class)->forOrder($this->order($revision, 4))->first();

        $this->assertEqualsWithDelta(round($perProduct * 4 * 0.02, 3), $row['required'], 0.002);
    }

    public function test_an_order_without_a_list_or_a_metal_figure_shows_no_materials_panel(): void
    {
        $revision = $this->revision();
        $revision->update(['net_weight_kg' => null]);
        $order = $this->order($revision, 5);

        $this->get(route('production-orders.show', $order))->assertOk()->assertDontSee('Materials needed');
    }

    public function test_the_order_shows_the_liquid_metal_to_pour_without_a_bill_line(): void
    {
        $revision = $this->revision();
        $order = $this->order($revision, 4);
        $kg = round(10 / (float) config('foundry.planner.default_yield') * 4, 3);

        $metal = app(BomExploder::class)->metalFor($order);

        $this->assertEqualsWithDelta($kg, $metal['kg'], 0.002);
        $this->get(route('production-orders.show', $order))->assertOk()->assertSee('Materials needed')->assertSee('Liquid metal');
    }

    public function test_the_metal_says_why_it_is_missing_when_there_is_no_weight(): void
    {
        $revision = $this->revision();
        $revision->update(['net_weight_kg' => null]);
        $order = $this->order($revision, 4);

        $metal = app(BomExploder::class)->metalFor($order);

        $this->assertNull($metal['kg']);
        $this->assertStringContainsString('cavity map or a net weight', $metal['note']);
        $this->get(route('production-orders.show', $order))->assertOk()->assertDontSee('Materials needed');
    }
}
