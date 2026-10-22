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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PatternEditTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function form(array $over = []): array
    {
        return $over + ['code' => 'P-0123', 'name' => 'Flange plate', 'pattern_type' => 'COPE_DRAG', 'status' => 'ACTIVE', 'material' => 'Aluminium', 'length_mm' => 500, 'width_mm' => 400, 'height_mm' => 80, 'description' => 'Two flanges', 'notes' => null,
            'mark_code' => Pattern::where('code', 'P-0123')->value('mark_code')];
    }

    public function test_the_edit_page_shows_the_saved_values(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Flange plat', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE', 'material' => 'Wood']);

        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('data-inplace-form', false)->assertDontSee('Edit pattern');
        $this->get(route('patterns.edit', $pattern))->assertOk()
            ->assertSee('value="Flange plat"', false)->assertSee('value="Wood"', false)->assertSee('<option value="MATCHPLATE" selected', false);
    }

    public function test_a_mistake_is_corrected_and_audited_without_touching_revision_codes(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Flange plat', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);

        $response = $this->put(route('patterns.update', $pattern), $this->form(['code' => 'p-0124']));

        $response->assertRedirect(route('patterns.show', $pattern))->assertSessionHas('success', 'P-0124 saved.');
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
        $this->assertSame('COPE_DRAG', $revision->fresh()->mould_arrangement, 'A matchplate is tooling; the mould it makes is cope and drag.');

        $this->put(route('patterns.update', $pattern), $this->form(['pattern_type' => 'STACK_PLATE', 'mould_arrangement' => 'COPE_DRAG', 'layers_per_mould' => 8, 'cavities_per_mould' => 4]))
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'P-0123 saved. Mould layout of P-0123-R0 saved.');

        $revision = $revision->fresh();
        $this->assertSame(['STACK', 8, 7], [$revision->mould_arrangement, $revision->layers_per_mould, $revision->castingLevels()]);
        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('28 castings per mould');
        $this->get(route('patterns.edit', $pattern))->assertOk()->assertSee('Moulds in the stack')->assertSee('value="8"', false);
    }

    public function test_a_chosen_arrangement_is_kept_when_the_type_changes(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'mould_arrangement' => 'SHELL']);

        $this->put(route('patterns.update', $pattern), $this->form(['pattern_type' => 'COPE_DRAG', 'mould_arrangement' => 'SHELL']))->assertSessionHasNoErrors();

        $this->assertSame('SHELL', $revision->fresh()->mould_arrangement);
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

    public function test_the_engraved_mark_is_edited_with_the_pattern_and_must_be_unique(): void
    {
        Pattern::create(['code' => 'P-0999', 'name' => 'Other', 'mark_code' => 'ZZ999']);
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Flange plate', 'pattern_type' => 'COPE_DRAG', 'status' => 'ACTIVE']);
        $this->get(route('patterns.edit', $pattern))->assertOk()->assertSee('name="mark_code"', false)->assertSee('value="'.$pattern->mark_code.'"', false);

        $this->put(route('patterns.update', $pattern), $this->form(['mark_code' => 'zz999']))->assertSessionHasErrors(['mark_code' => 'Mark ZZ999 is already on another tool.']);
        $this->put(route('patterns.update', $pattern), $this->form(['mark_code' => 'ab123']))->assertSessionHasNoErrors();

        $this->assertSame('AB123', $pattern->fresh()->mark_code);
        $this->assertDatabaseHas('tool_marks', ['tool_type' => 'pattern', 'tool_id' => $pattern->id, 'mark_code' => 'AB123']);
    }

    public function test_the_change_history_covers_the_pattern_its_revisions_cavities_and_files(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Rashid']));
        $product = ProductRevision::create(['product_id' => Product::create(['code' => 'VALVE-1', 'name' => 'Valve'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Flange plat', 'pattern_type' => 'COPE_DRAG', 'status' => 'ACTIVE']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        $this->put(route('patterns.update', $pattern), $this->form(['name' => 'Flange plate']))->assertSessionHasNoErrors();
        $this->put(route('patterns.cavities.map', [$pattern, $revision]), ['cavities_per_mould' => 2, 'rows' => [1 => ['product_revision_id' => $product->id, 'cavity_mark' => 'A1']]])->assertSessionHasNoErrors();
        $this->put(route('patterns.cavities.map', [$pattern, $revision]), ['cavities_per_mould' => 2, 'rows' => []])->assertSessionHasNoErrors();

        $page = $this->get(route('patterns.edit', $pattern));

        $page->assertOk()->assertSee('Change history')
            ->assertSee('Changed</strong> Pattern', false)->assertSee('Flange plat</span> → Flange plate', false)
            ->assertSee('Added</strong> Cavity 1 of P-0123-R0', false)->assertSee('VALVE-1 rev A')->assertSee('A1')
            ->assertSee('Removed</strong> Cavity 1 of P-0123-R0', false)
            ->assertSee('Rashid');
        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('Change history');
    }

    public function test_pattern_page_links_to_the_previous_and_next_pattern_by_code(): void
    {
        $a = Pattern::create(['code' => 'P-1', 'name' => 'First', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $b = Pattern::create(['code' => 'P-2', 'name' => 'Second', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $c = Pattern::create(['code' => 'P-3', 'name' => 'Third', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);

        $this->get(route('patterns.show', $b))->assertOk()->assertSee(route('patterns.show', $a), false)->assertSee(route('patterns.show', $c), false);
        $this->get(route('patterns.show', $a))->assertOk()->assertDontSee('Previous pattern', false)->assertSee(route('patterns.show', $b), false);
    }

    public function test_the_tooling_record_is_edited_in_place_only_by_roles_that_may_edit_patterns(): void
    {
        config(['foundry.require_login' => true]);
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Flange plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $nobody = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $editor = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $editor->roles()->attach(\App\Models\Role::where('code', 'ADMIN')->value('id'));

        $this->actingAs($nobody)->get(route('patterns.show', $pattern))->assertOk()->assertDontSee('data-inplace-form', false);
        $this->actingAs($nobody)->put(route('patterns.update', $pattern), ['code' => 'P-0123', 'name' => 'Changed', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE'])->assertForbidden();
        $this->actingAs($editor)->get(route('patterns.show', $pattern))->assertOk()->assertSee('data-inplace-form', false);
    }

    public function test_saving_the_in_place_form_updates_the_pattern_and_a_mistake_reopens_it(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Flange plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $payload = ['_inplace' => 1, 'code' => 'P-0123', 'name' => 'Flange plate B', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE', 'material' => 'Resin'];

        $this->from(route('patterns.show', $pattern))->put(route('patterns.update', $pattern), ['name' => ''] + $payload)
            ->assertRedirect(route('patterns.show', $pattern))->assertSessionHasErrors('name');
        $this->followingRedirects()->from(route('patterns.show', $pattern))->put(route('patterns.update', $pattern), ['name' => ''] + $payload)
            ->assertSee('The name field is required.');

        $this->put(route('patterns.update', $pattern), $payload)->assertRedirect(route('patterns.show', $pattern));
        $this->assertSame(['Flange plate B', 'Resin'], [$pattern->fresh()->name, $pattern->fresh()->material]);
    }

    public function test_pattern_type_and_mould_arrangement_are_separate_lists_with_readable_names(): void
    {
        $this->assertSame([], array_intersect(['MATCHPLATE', 'LOOSE', 'GATED', 'LOOSE_PATTERN', 'SPLIT_PATTERN'], array_keys(PatternRevision::ARRANGEMENTS)), 'Tooling is not a way of building a mould.');
        $this->assertSame(['COPE_DRAG', 'STACK', 'VERTICAL', 'SHELL', 'CORE_PACKAGE', 'CLUSTER', 'OTHER'], array_keys(PatternRevision::ARRANGEMENTS));
        foreach (Pattern::TYPES as $type => $defaultArrangement) {
            $this->assertArrayHasKey($defaultArrangement, PatternRevision::ARRANGEMENTS, "{$type} defaults to a real arrangement");
            $this->assertArrayHasKey($type, Pattern::TYPE_LABELS);
        }
        $this->assertSame(['Matchplate', 'Vertical plates', 'Shell pattern plate'], [Pattern::typeName('MATCHPLATE', true), Pattern::typeName('VERTICAL_PLATES', true), Pattern::typeName('SHELL_PLATE', true)]);

        $pattern = Pattern::create(['code' => 'P-0300', 'name' => 'Shell plate', 'pattern_type' => 'SHELL_PLATE', 'status' => 'ACTIVE']);
        $this->assertSame('SHELL', PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED'])->mould_arrangement);
        $this->get(route('patterns.edit', $pattern))->assertOk()->assertSee('Shell pattern plate')->assertSee('Matchplate (cope and drag patterns on one plate)')->assertDontSee('>MATCHPLATE<', false)->assertSee('Mould arrangement');
    }

    public function test_old_arrangements_that_were_really_tooling_become_cope_and_drag(): void
    {
        $pattern = Pattern::create(['code' => 'P-0301', 'name' => 'Old plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $stack = Pattern::create(['code' => 'P-0302', 'name' => 'Old stack', 'pattern_type' => 'STACK_PLATE', 'status' => 'ACTIVE']);
        $rows = [];
        foreach (['MATCHPLATE', 'LOOSE', 'GATED'] as $i => $old) {
            $rows[$old] = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'sequence' => 10 + $i]);
            DB::table('pattern_revisions')->where('id', $rows[$old]->id)->update(['mould_arrangement' => $old]);
        }
        $keep = PatternRevision::create(['pattern_id' => $stack->id, 'status' => 'APPROVED']);

        (require database_path('migrations/'.collect(scandir(database_path('migrations')))->first(fn ($f) => str_contains($f, 'separate_mould_arrangement_from_pattern_type'))))->up();

        $this->assertSame(['COPE_DRAG', 'COPE_DRAG', 'COPE_DRAG', 'STACK'], [$rows['MATCHPLATE']->fresh()->mould_arrangement, $rows['LOOSE']->fresh()->mould_arrangement, $rows['GATED']->fresh()->mould_arrangement, $keep->fresh()->mould_arrangement]);
    }

    public function test_every_pattern_type_and_mould_arrangement_has_its_own_icon_and_the_pages_show_them(): void
    {
        $types = collect(array_keys(Pattern::TYPE_LABELS))->mapWithKeys(fn ($t) => [$t => \App\Support\MouldIcons::patternType($t)]);
        $arrangements = collect(array_keys(PatternRevision::ARRANGEMENTS))->mapWithKeys(fn ($a) => [$a => \App\Support\MouldIcons::arrangement($a)]);

        $this->assertSame($types->count(), $types->unique()->count(), 'Each pattern type draws differently.');
        $this->assertSame($arrangements->count(), $arrangements->unique()->count(), 'Each arrangement draws differently.');
        $this->assertTrue($types->every(fn ($svg) => str_starts_with($svg, '<svg class="mi"') && str_contains($svg, 'aria-label="Pattern type: ')));
        $this->assertTrue($arrangements->every(fn ($svg) => str_contains($svg, 'aria-label="Mould arrangement: ')));
        $this->assertStringContainsString('<circle cx="6" cy="12" r="1.3"/>', \App\Support\MouldIcons::patternType('NOT_A_TYPE'), 'An unknown type draws as "other".');

        $pattern = Pattern::create(['code' => 'P-0400', 'name' => 'Stack plate', 'pattern_type' => 'STACK_PLATE', 'status' => 'ACTIVE']);
        PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);
        $this->get(route('patterns.index'))->assertOk()->assertSee('aria-label="Pattern type: Stack mould plate"', false);
        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('aria-label="Pattern type: Stack mould plate"', false)->assertSee('aria-label="Mould arrangement: Stack mould"', false)
            ->assertSee('data-mi-kind="arrangement"', false);
        $this->get(route('patterns.edit', $pattern))->assertOk()->assertSee('data-mi-for="pattern_type"', false)->assertSee('data-mi-for="mould_arrangement"', false);
    }

    public function test_one_action_creates_rev_0_for_every_pattern_without_a_revision_and_leaves_the_rest_alone(): void
    {
        $bare = Pattern::create(['code' => 'P-0501', 'name' => 'Bare plate', 'pattern_type' => 'STACK_PLATE', 'status' => 'ACTIVE']);
        $other = Pattern::create(['code' => 'P-0502', 'name' => 'Other bare', 'pattern_type' => 'MATCHPLATE', 'status' => 'INACTIVE']);
        $archived = Pattern::create(['code' => 'P-0503', 'name' => 'Archived bare', 'status' => 'ACTIVE']);
        $archived->delete();
        $has = Pattern::create(['code' => 'P-0504', 'name' => 'Has one', 'status' => 'ACTIVE']);
        $existing = PatternRevision::create(['pattern_id' => $has->id, 'status' => 'DRAFT', 'revision' => 'B']);

        $this->get(route('patterns.index'))->assertOk()->assertSee('Create Rev 0 for 2 patterns without a revision');

        $this->post(route('patterns.first-revisions.store'))->assertSessionHasNoErrors()->assertSessionHas('success', 'Rev 0 created for 2 patterns that had no revision.');

        $first = $bare->fresh()->currentRevision;
        $this->assertSame(['0', true, 'STACK', 'APPROVED'], [$first->revision, (bool) $first->is_current, $first->mould_arrangement, $first->status]);
        $this->assertSame(1, $other->fresh()->revisions()->count());
        $this->assertSame(0, Pattern::withTrashed()->find($archived->id)->revisions()->count(), 'Archived patterns are left alone.');
        $this->assertSame([$existing->id], $has->fresh()->revisions()->pluck('id')->all(), 'A pattern that has a revision is not touched.');

        $this->get(route('patterns.index'))->assertOk()->assertDontSee('without a revision');
        $this->post(route('patterns.first-revisions.store'))->assertSessionHas('warning', 'Every pattern already has a revision.');
        $this->assertSame(2, PatternRevision::whereIn('pattern_id', [$bare->id, $other->id])->count());
    }
}
