<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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

class PatternEditTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function form(array $over = []): array
    {
        return $over + ['code' => 'P-0123', 'name' => 'Flange plate', 'pattern_type' => 'COPE_DRAG', 'status' => 'ACTIVE', 'material' => 'Aluminium', 'length_mm' => 500, 'width_mm' => 400, 'height_mm' => 80, 'description' => 'Two flanges', 'notes' => null];
    }

    public function test_the_edit_page_shows_the_saved_values(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Flange plat', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE', 'material' => 'Wood']);

        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee(route('patterns.edit', $pattern));
        $this->get(route('patterns.edit', $pattern))->assertOk()
            ->assertSee('value="Flange plat"', false)->assertSee('value="Wood"', false)->assertSee('<option value="MATCHPLATE" selected', false);
    }

    public function test_a_mistake_is_corrected_and_audited_without_touching_revision_codes(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Flange plat', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);

        $response = $this->put(route('patterns.update', $pattern), $this->form(['code' => 'p-0124']));

        $response->assertRedirect(route('patterns.show', $pattern))->assertSessionHas('success', 'P-0124 saved. Mould layout of P-0123-R0 saved.');
        $this->assertSame(['P-0124', 'Flange plate', 'COPE_DRAG', 'Aluminium'], [$pattern->fresh()->code, $pattern->fresh()->name, $pattern->fresh()->pattern_type, $pattern->fresh()->material]);
        $this->assertSame('P-0123-R0', $revision->fresh()->code);
        $log = AuditLog::where('entity_type', 'Pattern')->where('entity_id', $pattern->id)->where('action', 'UPDATED')->latest('occurred_at')->firstOrFail();
        $this->assertEquals(['old' => 'Flange plat', 'new' => 'Flange plate'], json_decode($log->changes, true)['name']);
    }

    public function test_a_code_another_pattern_uses_is_refused(): void
    {
        Pattern::create(['code' => 'P-0999', 'name' => 'Other']);
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Flange plate']);

        $this->put(route('patterns.update', $pattern), $this->form(['code' => 'P-0999']))->assertSessionHasErrors('code');

        $this->assertSame('P-0123', $pattern->fresh()->code);
    }

    public function test_changing_the_type_to_a_stack_plate_records_the_stack_height_and_redraws_the_mould(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Ring plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 4]);
        $this->assertSame('MATCHPLATE', $revision->fresh()->mould_arrangement);

        $this->put(route('patterns.update', $pattern), $this->form(['pattern_type' => 'STACK_PLATE', 'mould_arrangement' => 'MATCHPLATE', 'layers_per_mould' => 8, 'cavities_per_mould' => 4]))
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'P-0123 saved. Mould layout of P-0123-R0 saved.');

        $revision = $revision->fresh();
        $this->assertSame(['STACK', 8, 7], [$revision->mould_arrangement, $revision->layers_per_mould, $revision->castingLevels()]);
        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('28 castings per mould');
        $this->get(route('patterns.edit', $pattern))->assertOk()->assertSee('Moulds in the stack')->assertSee('value="8"', false);
    }

    public function test_a_chosen_arrangement_is_kept_when_the_type_changes(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'mould_arrangement' => 'GATED']);

        $this->put(route('patterns.update', $pattern), $this->form(['pattern_type' => 'COPE_DRAG', 'mould_arrangement' => 'GATED']))->assertSessionHasNoErrors();

        $this->assertSame('GATED', $revision->fresh()->mould_arrangement);
    }

    public function test_resaving_an_unchanged_layout_needs_no_reason_even_after_assembly(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate', 'pattern_type' => 'COPE_DRAG', 'status' => 'ACTIVE']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        $assembly = MouldAssembly::create(['assembly_number' => 'MA-1', 'mould_configuration_id' => MouldConfiguration::create(['code' => 'MC-1', 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2])->id, 'status' => 'PREPARED']);
        $layer = MouldLayer::create(['mould_assembly_id' => $assembly->id, 'layer_number' => 1, 'layer_type' => 'DRAG']);
        MouldCavity::create(['mould_layer_id' => $layer->id, 'cavity_number' => 1, 'pattern_revision_id' => $revision->id, 'product_revision_id' => ProductRevision::create(['product_id' => Product::create(['code' => 'X-1', 'name' => 'X'])->id, 'revision' => 'A', 'status' => 'APPROVED'])->id, 'castings_per_cavity' => 1, 'is_active' => true]);

        $this->put(route('patterns.update', $pattern), $this->form(['name' => 'Plate renamed', 'mould_arrangement' => 'COPE_DRAG', 'cavities_per_mould' => 2]))->assertSessionHasNoErrors();
        $this->put(route('patterns.update', $pattern), $this->form(['mould_arrangement' => 'COPE_DRAG', 'cavities_per_mould' => 3]))->assertSessionHasErrors('correction_reason');

        $this->assertSame(['Plate renamed', 2], [$pattern->fresh()->name, $revision->fresh()->cavities_per_mould]);
    }
}
