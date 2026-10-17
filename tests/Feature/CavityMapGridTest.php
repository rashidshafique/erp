<?php

namespace Tests\Feature;

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

class CavityMapGridTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $code): ProductRevision
    {
        return ProductRevision::create(['product_id' => Product::create(['code' => $code, 'name' => $code.' casting'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
    }

    private function revision(int $cavities): PatternRevision
    {
        return PatternRevision::create(['pattern_id' => Pattern::create(['code' => 'P-0123', 'name' => 'Plate'])->id, 'status' => 'APPROVED', 'cavities_per_mould' => $cavities]);
    }

    /** Puts cavity $number of the revision on an assembled mould. */
    private function assemble(PatternRevision $revision, int $number): void
    {
        $assembly = MouldAssembly::create(['assembly_number' => 'MA-'.uniqid(), 'mould_configuration_id' => MouldConfiguration::firstOrCreate(['code' => 'MC-1'], ['name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2])->id, 'status' => 'PREPARED']);
        $layer = MouldLayer::create(['mould_assembly_id' => $assembly->id, 'layer_number' => 1, 'layer_type' => 'DRAG']);
        MouldCavity::create(['mould_layer_id' => $layer->id, 'cavity_number' => $number, 'pattern_revision_id' => $revision->id, 'product_revision_id' => $revision->cavityMap()->where('cavity_number', $number)->value('product_revision_id'), 'castings_per_cavity' => 1, 'is_active' => true]);
    }

    private function save(PatternRevision $revision, int $count, array $rows, array $extra = [])
    {
        return $this->from(route('patterns.show', $revision->pattern_id))
            ->put(route('patterns.cavities.map', [$revision->pattern_id, $revision]), ['_map' => $revision->id, 'cavities_per_mould' => $count, 'rows' => $rows] + $extra);
    }

    public function test_the_grid_creates_updates_and_clears_cavities_in_one_save(): void
    {
        $valve = $this->product('VALVE-1');
        $pump = $this->product('PUMP-1');
        $revision = $this->revision(3);
        $revision->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $valve->id]);
        $revision->cavityMap()->create(['cavity_number' => 3, 'product_revision_id' => $valve->id]);

        $response = $this->save($revision, 4, [
            1 => ['product_revision_id' => $pump->id, 'casting_weight_kg' => 5, 'poured_weight_kg' => 8],
            2 => ['product_revision_id' => $valve->id, 'cavity_role' => 'PART', 'component_label' => 'cover', 'pieces_per_product' => 2],
            3 => ['product_revision_id' => ''],
            4 => ['product_revision_id' => $valve->id],
        ]);

        $response->assertSessionHasNoErrors()->assertSessionHas('success', 'Cavity map of P-0123-R0 saved: 3 of 4 cavities linked to a product.');
        $this->assertSame(4, $revision->fresh()->cavities_per_mould);
        $this->assertSame([[1, $pump->id, 'WHOLE', null], [2, $valve->id, 'PART', 'cover'], [4, $valve->id, 'WHOLE', null]],
            $revision->cavityMap()->get()->map(fn ($c) => [$c->cavity_number, $c->product_revision_id, $c->cavity_role, $c->component_label])->all());
    }

    public function test_lowering_the_count_removes_the_cavities_beyond_it(): void
    {
        $valve = $this->product('VALVE-1');
        $revision = $this->revision(4);
        foreach ([1, 2, 3, 4] as $n) {
            $revision->cavityMap()->create(['cavity_number' => $n, 'product_revision_id' => $valve->id]);
        }

        $this->save($revision, 2, [1 => ['product_revision_id' => $valve->id], 2 => ['product_revision_id' => $valve->id]])->assertSessionHasNoErrors();

        $this->assertSame([1, 2], $revision->cavityMap()->pluck('cavity_number')->all());
    }

    public function test_one_bad_row_saves_nothing_and_keeps_the_entries(): void
    {
        $valve = $this->product('VALVE-1');
        $revision = $this->revision(2);

        $this->save($revision, 2, [1 => ['product_revision_id' => $valve->id, 'notes' => 'Left'], 2 => ['product_revision_id' => $valve->id, 'cavity_role' => 'PART']])
            ->assertSessionHasErrors(['rows.2.component_label' => 'Cavity 2: Name the component this cavity makes.'])
            ->assertSessionHasInput('rows.1.notes', 'Left');

        $this->assertSame(0, $revision->cavityMap()->count());
    }

    public function test_a_cavity_on_an_assembled_mould_keeps_its_product_and_the_count_needs_a_reason(): void
    {
        $valve = $this->product('VALVE-1');
        $pump = $this->product('PUMP-1');
        $revision = $this->revision(2);
        $revision->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $valve->id]);
        $revision->cavityMap()->create(['cavity_number' => 2, 'product_revision_id' => $valve->id]);
        $this->assemble($revision, 2);

        $this->save($revision, 2, [1 => ['product_revision_id' => $valve->id], 2 => ['product_revision_id' => $pump->id]])
            ->assertSessionHasErrors(['rows.2.product_revision_id' => 'Cavity 2: it is used on an assembled mould, so its product cannot change. Record a new revision instead.']);
        $this->save($revision, 1, [1 => ['product_revision_id' => $valve->id]])
            ->assertSessionHasErrors(['cavities_per_mould' => 'Cavity 2 is used on an assembled mould, so the count cannot go below 2.']);
        $this->save($revision, 3, [1 => ['product_revision_id' => $valve->id], 2 => ['product_revision_id' => $valve->id], 3 => ['product_revision_id' => $pump->id]])
            ->assertSessionHasErrors('correction_reason');
        $this->assertSame(2, $revision->fresh()->cavities_per_mould);

        $this->save($revision, 3, [1 => ['product_revision_id' => $valve->id], 2 => ['product_revision_id' => $valve->id], 3 => ['product_revision_id' => $pump->id]], ['correction_reason' => 'Third cavity was missed'])
            ->assertSessionHasNoErrors();
        $this->assertSame([3, 3], [$revision->fresh()->cavities_per_mould, $revision->cavityMap()->count()]);
    }

    public function test_a_failed_save_reopens_the_grid_with_the_entries_and_messages(): void
    {
        $valve = $this->product('VALVE-1');
        $revision = $this->revision(2);
        $this->save($revision, 3, [1 => ['product_revision_id' => $valve->id, 'notes' => 'Kept note'], 3 => ['product_revision_id' => $valve->id, 'cavity_role' => 'PART']]);

        $page = $this->get(route('patterns.show', $revision->pattern_id));

        $page->assertOk()->assertSee('data-cavity-row="3" class="has-error"', false)->assertSee('Cavity 3: Name the component this cavity makes.')
            ->assertSee('value="Kept note"', false)->assertSee('name="cavities_per_mould" min="1" max="200" value="3"', false);
    }

    public function test_cavity_marks_are_kept_unique_can_be_swapped_and_carry_over(): void
    {
        $valve = $this->product('VALVE-1');
        $revision = $this->revision(2);

        $this->save($revision, 2, [1 => ['product_revision_id' => $valve->id, 'cavity_mark' => ' a1 '], 2 => ['product_revision_id' => $valve->id, 'cavity_mark' => 'A1']])
            ->assertSessionHasErrors(['rows.2.cavity_mark' => 'Cavity 2: mark A1 is already on cavity 1.']);
        $this->save($revision, 2, [1 => ['product_revision_id' => $valve->id, 'cavity_mark' => ' a1 '], 2 => ['product_revision_id' => $valve->id, 'cavity_mark' => 'B2']])->assertSessionHasNoErrors();
        $this->save($revision, 2, [1 => ['product_revision_id' => $valve->id, 'cavity_mark' => 'B2'], 2 => ['product_revision_id' => $valve->id, 'cavity_mark' => 'A1']])->assertSessionHasNoErrors();
        $this->assertSame(['B2', 'A1'], $revision->cavityMap()->pluck('cavity_mark')->all());

        $next = $revision->pattern->addRevision(['change_description' => 'New gating'], copyLayout: true);

        $this->assertSame(['B2', 'A1'], $next->cavityMap()->pluck('cavity_mark')->all());
        $this->get(route('patterns.show', $revision->pattern_id))->assertOk()->assertSee('name="rows[1][cavity_mark]" value="B2"', false);
        $this->get(route('products.show', $valve->product_id))->assertOk()->assertSee('cavities 1 (B2), 2 (A1)');
    }

    public function test_older_revisions_are_folded_unless_they_have_an_error_to_show(): void
    {
        $valve = $this->product('VALVE-1');
        $old = $this->revision(2);
        $current = $old->pattern->addRevision(['change_description' => 'New gating'], copyLayout: true);

        $page = $this->get(route('patterns.show', $old->pattern_id));

        $page->assertOk()->assertSee('Older revisions')
            ->assertSee('class="rev-block is-current" id="cavities-'.$current->id.'"  open', false)
            ->assertSee('class="rev-block " id="cavities-'.$old->id.'" >', false);

        $this->save($old, 2, [1 => ['product_revision_id' => $valve->id, 'cavity_role' => 'PART']]);
        $this->get(route('patterns.show', $old->pattern_id))->assertSee('class="rev-block " id="cavities-'.$old->id.'"  open', false);
    }
}
