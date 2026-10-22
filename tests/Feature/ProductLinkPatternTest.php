<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLinkPatternTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductRevision $productRevision;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->product = Product::create(['code' => 'VALVE-1', 'name' => 'Valve body']);
        $this->productRevision = ProductRevision::create(['product_id' => $this->product->id, 'revision' => 'A', 'status' => 'APPROVED']);
    }

    public function test_an_unlinked_product_offers_the_link_dialog_with_its_patterns(): void
    {
        Pattern::create(['code' => 'P-0100', 'name' => 'Valve plate']);

        $this->get(route('products.show', $this->product))->assertOk()
            ->assertSee('None linked yet')->assertSee('data-quick="qd-link-pattern"', false)->assertSee('Valve plate');
    }

    public function test_linking_a_pattern_without_a_revision_records_its_first_revision_and_takes_the_first_cavity(): void
    {
        $pattern = Pattern::create(['code' => 'P-0100', 'name' => 'Valve plate']);
        $this->assertSame(0, $pattern->revisions()->count());

        $this->post(route('products.link-pattern', $this->product), ['pattern_id' => $pattern->id, 'product_revision_id' => $this->productRevision->id])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $revision = $pattern->currentRevision()->first();
        $this->assertNotNull($revision);
        $this->assertSame([1, $this->productRevision->id], [$revision->cavityMap()->sole()->cavity_number, $revision->cavityMap()->sole()->product_revision_id]);
        $this->get(route('products.show', $this->product))->assertDontSee('None linked yet');
    }

    public function test_a_specific_revision_and_cavity_can_be_chosen_and_a_taken_cavity_is_refused(): void
    {
        $pattern = Pattern::create(['code' => 'P-0100', 'name' => 'Valve plate']);
        $first = $pattern->addRevision(['cavities_per_mould' => 2]);
        $pattern->addRevision(['cavities_per_mould' => 2]);
        $payload = ['pattern_id' => $pattern->id, 'pattern_revision_id' => $first->id, 'product_revision_id' => $this->productRevision->id, 'cavity_number' => 2];

        $this->post(route('products.link-pattern', $this->product), $payload)->assertSessionHasNoErrors()->assertSessionHas('warning');
        $this->assertSame(2, $first->cavityMap()->sole()->cavity_number);

        $this->post(route('products.link-pattern', $this->product), $payload)->assertSessionHasErrors('cavity_number');
        $this->assertSame(1, PatternRevision::find($first->id)->cavityMap()->count());
    }

    public function test_a_full_layout_is_refused_without_leaving_anything_behind(): void
    {
        $pattern = Pattern::create(['code' => 'P-0100', 'name' => 'Valve plate']);
        $revision = $pattern->addRevision(['cavities_per_mould' => 1]);
        $other = Product::create(['code' => 'CAP-1', 'name' => 'Cap']);
        $revision->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => ProductRevision::create(['product_id' => $other->id, 'revision' => 'A', 'status' => 'APPROVED'])->id, 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1]);

        $this->post(route('products.link-pattern', $this->product), ['pattern_id' => $pattern->id, 'product_revision_id' => $this->productRevision->id])
            ->assertSessionHasErrors('cavity_number');
        $this->assertSame(1, $revision->cavityMap()->count());
    }

    public function test_a_product_with_no_revision_can_be_linked_and_gets_its_first_revision(): void
    {
        $bare = Product::create(['code' => 'CAP-1', 'name' => 'Cap']);
        $pattern = Pattern::create(['code' => 'P-0100', 'name' => 'Valve plate']);

        $this->get(route('products.show', $bare))->assertOk()->assertSee('data-quick="qd-link-pattern"', false);
        $this->post(route('products.link-pattern', $bare), ['pattern_id' => $pattern->id])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(['A'], $bare->revisions()->pluck('revision')->all());
        $this->assertSame($bare->revisions()->value('id'), $pattern->currentRevision()->first()->cavityMap()->sole()->product_revision_id);
    }

    public function test_an_unpicked_product_revision_goes_on_the_latest_approved_one(): void
    {
        $pattern = Pattern::create(['code' => 'P-0100', 'name' => 'Valve plate']);

        $this->post(route('products.link-pattern', $this->product), ['pattern_id' => $pattern->id])->assertSessionHasNoErrors();

        $this->assertSame($this->productRevision->id, $pattern->currentRevision()->first()->cavityMap()->sole()->product_revision_id);
        $this->assertSame(1, $this->product->revisions()->count());
    }

    public function test_the_cavity_map_offers_and_saves_a_product_with_no_revision(): void
    {
        $bare = Product::create(['code' => 'CAP-1', 'name' => 'Cap']);
        $pattern = Pattern::create(['code' => 'P-0100', 'name' => 'Valve plate']);
        $revision = $pattern->addRevision(['cavities_per_mould' => 2]);

        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('value="product:'.$bare->id.'"', false);

        $this->put(route('patterns.cavities.map', [$pattern, $revision]), ['cavities_per_mould' => 2, 'rows' => [1 => ['product_revision_id' => 'product:'.$bare->id]]])
            ->assertSessionHasNoErrors();

        $this->assertSame($bare->revisions()->value('id'), $revision->cavityMap()->sole()->product_revision_id);
        $this->get(route('patterns.show', $pattern))->assertDontSee('value="product:'.$bare->id.'"', false);
    }

    public function test_the_cavity_map_refuses_an_unknown_product(): void
    {
        $pattern = Pattern::create(['code' => 'P-0100', 'name' => 'Valve plate']);
        $revision = $pattern->addRevision(['cavities_per_mould' => 1]);

        $this->put(route('patterns.cavities.map', [$pattern, $revision]), ['cavities_per_mould' => 1, 'rows' => [1 => ['product_revision_id' => 'product:'.fake()->uuid()]]])
            ->assertSessionHasErrors('rows.1.product_revision_id');
    }

    public function test_a_pattern_without_a_revision_gets_rev_0_when_its_cavity_map_is_saved(): void
    {
        $pattern = Pattern::create(['code' => 'P-0100', 'name' => 'Valve plate']);

        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee(route('patterns.cavities.first-map', $pattern))->assertSee('first revision (Rev 0)');
        $this->assertSame(0, $pattern->revisions()->count());

        $this->put(route('patterns.cavities.first-map', $pattern), ['cavities_per_mould' => 2, 'rows' => [1 => ['product_revision_id' => $this->productRevision->id]]])
            ->assertSessionHasNoErrors();

        $revision = $pattern->currentRevision()->first();
        $this->assertSame([0, 2, $this->productRevision->id], [(int) $revision->sequence, (int) $revision->cavities_per_mould, $revision->cavityMap()->sole()->product_revision_id]);
    }

    public function test_a_refused_first_cavity_map_leaves_the_pattern_without_a_revision(): void
    {
        $pattern = Pattern::create(['code' => 'P-0100', 'name' => 'Valve plate']);

        $this->put(route('patterns.cavities.first-map', $pattern), ['cavities_per_mould' => 1, 'rows' => [1 => ['product_revision_id' => 'product:'.fake()->uuid()]]])
            ->assertSessionHasErrors('rows.1.product_revision_id');

        $this->assertSame(0, $pattern->revisions()->count());
    }
}
