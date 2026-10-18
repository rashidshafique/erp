<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternSpecFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function newPattern(array $extra = []): array
    {
        return $extra + ['name' => 'Valve plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE', 'revision' => 'A', 'revision_status' => 'DRAFT'];
    }

    public function test_a_new_pattern_has_no_engraved_mark_and_the_pages_still_open(): void
    {
        $this->post(route('patterns.store'), $this->newPattern())->assertSessionHasNoErrors();

        $pattern = Pattern::firstOrFail();
        $this->assertNull($pattern->mark_code);
        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('Not engraved');
        $this->get(route('patterns.edit', $pattern))->assertOk();
        $this->assertNull($pattern->fresh()->mark_code);
    }

    public function test_a_mark_typed_on_creation_is_kept_and_a_clash_is_refused(): void
    {
        $this->post(route('patterns.store'), $this->newPattern(['mark_code' => 'ab123']))->assertSessionHasNoErrors();
        $this->assertSame('AB123', Pattern::firstOrFail()->mark_code);

        $this->post(route('patterns.store'), $this->newPattern(['name' => 'Other', 'mark_code' => 'AB123']))->assertSessionHasErrors('mark_code');
    }

    public function test_saving_the_edit_form_with_a_blank_mark_leaves_it_unchanged(): void
    {
        $this->post(route('patterns.store'), $this->newPattern(['mark_code' => 'XY789']));
        $pattern = Pattern::firstOrFail();

        $this->put(route('patterns.update', $pattern), ['code' => $pattern->code, 'name' => 'Renamed', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE', 'mark_code' => ''])->assertRedirect(route('patterns.show', $pattern))->assertSessionHas('success');

        $this->assertSame(['XY789', 'Renamed'], [$pattern->fresh()->mark_code, $pattern->fresh()->name]);
    }

    public function test_shrinkage_and_tolerance_are_saved_on_the_first_revision(): void
    {
        $this->post(route('patterns.store'), $this->newPattern(['shrinkage_percent' => '1.250', 'tolerance_grade' => 9, 'machining_allowance_grade' => 'F']))->assertSessionHasNoErrors();

        $revision = PatternRevision::firstOrFail();
        $this->assertSame(['1.250', 9, 'F'], [$revision->shrinkage_percent, $revision->tolerance_grade, $revision->machining_allowance_grade]);
        $this->get(route('patterns.show', $revision->pattern))->assertSee('1.25 %')->assertSee('CT 9')->assertSee('RMAG F');
    }

    public function test_a_new_revision_takes_its_own_shrinkage_and_tolerance(): void
    {
        $this->post(route('patterns.store'), $this->newPattern(['shrinkage_percent' => 1]));
        $pattern = Pattern::firstOrFail();

        $this->post(route('patterns.revisions.store', $pattern), ['change_description' => 'Opened up the allowance', 'shrinkage_percent' => '1.500', 'tolerance_grade' => 11])->assertSessionHasNoErrors();

        $current = $pattern->currentRevision()->first();
        $this->assertSame(['1.500', 11], [$current->shrinkage_percent, $current->tolerance_grade]);
    }

    public function test_out_of_range_grades_are_refused(): void
    {
        $this->post(route('patterns.store'), $this->newPattern(['tolerance_grade' => 17, 'machining_allowance_grade' => 'Z', 'shrinkage_percent' => 12]))
            ->assertSessionHasErrors(['tolerance_grade', 'machining_allowance_grade', 'shrinkage_percent']);
    }
}
