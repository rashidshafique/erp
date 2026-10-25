<?php

namespace Tests\Feature;

use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\CoreRequirement;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function revision(string $code = 'P-1'): ProductRevision
    {
        return ProductRevision::create(['product_id' => Product::create(['code' => $code, 'name' => 'Casting '.$code])->id, 'revision' => 'A', 'status' => 'APPROVED']);
    }

    private function core(string $name = 'Jacket core'): CoreDefinition
    {
        return CoreDefinition::create(['name' => $name, 'core_type' => 'STANDARD']);
    }

    public function test_a_product_revision_lists_the_cores_it_needs_with_a_count_per_casting(): void
    {
        $revision = $this->revision();
        $core = $this->core();

        $this->post(route('core-requirements.store'), ['core_definition_id' => $core->id, 'product_revision_id' => $revision->id, 'quantity_per_casting' => 2])->assertSessionHasNoErrors();

        $this->assertSame(2, CoreRequirement::firstOrFail()->quantity_per_casting);
        $this->get(route('products.show', $revision->product))->assertOk()->assertSee($core->name)->assertSee('No core box');
    }

    public function test_one_core_can_serve_several_products_and_adding_it_again_changes_the_count(): void
    {
        $core = $this->core();
        $one = $this->revision('P-1');
        $two = $this->revision('P-2');
        $this->post(route('core-requirements.store'), ['core_definition_id' => $core->id, 'product_revision_id' => $one->id, 'quantity_per_casting' => 1]);
        $this->post(route('core-requirements.store'), ['core_definition_id' => $core->id, 'product_revision_id' => $two->id, 'quantity_per_casting' => 3]);
        $this->post(route('core-requirements.store'), ['core_definition_id' => $core->id, 'product_revision_id' => $one->id, 'quantity_per_casting' => 4]);

        $this->assertSame(2, $core->requirements()->count());
        $this->assertSame(4, $core->requirements()->where('product_revision_id', $one->id)->value('quantity_per_casting'));
        $this->get(route('cores.show', $core))->assertOk()->assertSee('P-1')->assertSee('P-2');
    }

    public function test_a_core_needs_a_positive_whole_count_and_a_live_core(): void
    {
        $revision = $this->revision();
        $core = $this->core();
        foreach ([0, 100, 1.5] as $bad) {
            $this->post(route('core-requirements.store'), ['core_definition_id' => $core->id, 'product_revision_id' => $revision->id, 'quantity_per_casting' => $bad])->assertSessionHasErrors('quantity_per_casting');
        }
        $core->delete();

        $this->post(route('core-requirements.store'), ['core_definition_id' => $core->id, 'product_revision_id' => $revision->id, 'quantity_per_casting' => 1])->assertSessionHasErrors('core_definition_id');
        $this->assertSame(0, CoreRequirement::count());
    }

    public function test_the_product_page_shows_the_box_that_makes_a_needed_core(): void
    {
        $revision = $this->revision();
        $core = $this->core();
        $box = CoreBox::create(['core_definition_id' => $core->id, 'name' => 'Jacket box']);
        CoreRequirement::create(['core_definition_id' => $core->id, 'product_revision_id' => $revision->id]);

        $this->get(route('products.show', $revision->product))->assertOk()->assertSee($box->code)->assertDontSee('No core box');
    }

    public function test_a_requirement_can_be_removed(): void
    {
        $requirement = CoreRequirement::create(['core_definition_id' => $this->core()->id, 'product_revision_id' => $this->revision()->id]);

        $this->delete(route('core-requirements.destroy', $requirement))->assertSessionHasNoErrors();

        $this->assertSame(0, CoreRequirement::count());
    }
}
