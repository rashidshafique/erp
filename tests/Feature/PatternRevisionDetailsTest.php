<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternRevisionDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function incompleteRevision(array $extra = []): PatternRevision
    {
        $pattern = Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE']);

        return PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'DRAFT'] + $extra);
    }

    private function save(PatternRevision $revision, array $fields)
    {
        return $this->put(route('patterns.revisions.update', [$revision->pattern, $revision]), $fields);
    }

    public function test_the_edit_page_opens_and_is_linked_from_the_pattern(): void
    {
        $revision = $this->incompleteRevision();

        $this->get(route('patterns.revisions.edit', [$revision->pattern, $revision]))->assertOk()->assertSee('Edit details of');
        $this->get(route('patterns.show', $revision->pattern))->assertSee(route('patterns.revisions.edit', [$revision->pattern, $revision]), false);
    }

    public function test_blank_fields_can_be_filled_without_a_reason(): void
    {
        $revision = $this->incompleteRevision();

        $this->save($revision, ['casting_weight_kg' => '2.5', 'pour_weight_kg' => '3.1', 'drawing_revision' => 'C', 'shrinkage_percent' => '1.0', 'tolerance_grade' => 9, 'pieces_per_mould' => 4])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $revision->refresh();
        $this->assertSame(['C', 4, 9], [$revision->drawing_revision, $revision->pieces_per_mould, $revision->tolerance_grade]);
        $this->assertSame('2.5000', $revision->casting_weight_kg);
    }

    public function test_changing_a_recorded_value_needs_a_reason_and_is_audited(): void
    {
        $revision = $this->incompleteRevision(['drawing_revision' => 'A', 'casting_weight_kg' => 2.5]);

        $this->save($revision, ['drawing_revision' => 'B', 'casting_weight_kg' => '2.5'])->assertSessionHasErrors('correction_reason');
        $this->assertSame('A', $revision->fresh()->drawing_revision);

        $this->save($revision, ['drawing_revision' => 'B', 'casting_weight_kg' => '2.5', 'correction_reason' => 'Customer reissued the drawing'])->assertSessionHasNoErrors();

        $this->assertSame('B', $revision->fresh()->drawing_revision);
        $this->assertTrue(AuditLog::where('entity_type', 'PatternRevision')->where('entity_id', $revision->id)->where('reason', 'like', '%Customer reissued the drawing%')->exists());
    }

    public function test_clearing_a_recorded_value_also_needs_a_reason(): void
    {
        $revision = $this->incompleteRevision(['drawing_revision' => 'A']);

        $this->save($revision, ['drawing_revision' => ''])->assertSessionHasErrors('correction_reason');
        $this->assertSame('A', $revision->fresh()->drawing_revision);
    }

    public function test_the_code_status_and_current_flag_cannot_be_changed_here(): void
    {
        $revision = $this->incompleteRevision(['is_current' => true]);
        $code = $revision->code;

        $this->save($revision, ['pieces_per_mould' => 2, 'status' => 'APPROVED', 'code' => 'HACKED', 'is_current' => 0])->assertSessionHasNoErrors();

        $revision->refresh();
        $this->assertSame(['DRAFT', $code, true], [$revision->status, $revision->code, (bool) $revision->is_current]);
    }

    public function test_a_revision_of_another_pattern_is_not_found(): void
    {
        $revision = $this->incompleteRevision();
        $other = Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Other', 'pattern_type' => 'MATCHPLATE']);

        $this->put(route('patterns.revisions.update', [$other, $revision]), ['pieces_per_mould' => 2])->assertNotFound();
    }

    private function linkCavity(PatternRevision $revision, int $number, string $productCode): void
    {
        $product = Product::create(['code' => $productCode, 'name' => $productCode.' casting']);
        $productRevision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $revision->cavityMap()->create(['cavity_number' => $number, 'product_revision_id' => $productRevision->id, 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1]);
    }

    public function test_casting_weight_is_asked_only_when_the_cavities_make_one_product(): void
    {
        $revision = $this->incompleteRevision();
        $this->linkCavity($revision, 1, 'VALVE-1');
        $this->get(route('patterns.revisions.edit', [$revision->pattern, $revision]))->assertSee('name="casting_weight_kg"', false);

        $this->linkCavity($revision, 2, 'PUMP-1');
        $this->get(route('patterns.revisions.edit', [$revision->pattern, $revision]))
            ->assertDontSee('name="casting_weight_kg"', false)
            ->assertSee('recorded per cavity');
    }

    public function test_a_casting_weight_sent_for_a_several_product_revision_is_ignored(): void
    {
        $revision = $this->incompleteRevision();
        $this->linkCavity($revision, 1, 'VALVE-1');
        $this->linkCavity($revision, 2, 'PUMP-1');

        $this->save($revision, ['casting_weight_kg' => '2.5', 'pieces_per_mould' => 4])->assertSessionHasNoErrors();

        $revision->refresh();
        $this->assertNull($revision->casting_weight_kg);
        $this->assertSame(4, $revision->pieces_per_mould);
    }
}
