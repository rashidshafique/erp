<?php

namespace Tests\Feature;

use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\Pattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreScreensTest extends TestCase
{
    use RefreshDatabase;

    private function core(string $name = 'Valve core'): CoreDefinition
    {
        return CoreDefinition::create(['name' => $name, 'core_type' => 'STANDARD']);
    }

    private function boxPayload(CoreDefinition $core, array $over = []): array
    {
        return $over + ['name' => 'Valve box', 'core_definition_id' => $core->id, 'revision' => 'A', 'status' => 'ACTIVE', 'origin' => 'NEW', 'cavities' => 1];
    }

    public function test_a_core_is_added_with_an_automatic_code_and_listed(): void
    {
        $this->post(route('cores.store'), ['name' => 'Water jacket', 'core_type' => 'STANDARD', 'status' => 'ACTIVE'])->assertSessionHasNoErrors();

        $core = CoreDefinition::firstOrFail();
        $this->assertNotEmpty($core->code);
        $this->get(route('cores.index'))->assertOk()->assertSee('Water jacket');
        $this->get(route('cores.show', $core))->assertOk()->assertSee($core->code);
    }

    public function test_a_box_with_many_cavities_starts_every_cavity_on_its_main_core(): void
    {
        $core = $this->core();

        $this->post(route('core-boxes.store'), $this->boxPayload($core, ['cavities' => 4]))->assertSessionHasNoErrors();

        $box = CoreBox::firstOrFail();
        $this->assertSame([1, 2, 3, 4], $box->cavityMap->pluck('cavity_number')->all());
        $this->assertSame([$core->id], $box->cavityMap->pluck('core_definition_id')->unique()->all());
    }

    public function test_cavities_of_one_box_can_make_different_cores(): void
    {
        $a = $this->core('Core A');
        $b = $this->core('Core B');
        $this->post(route('core-boxes.store'), $this->boxPayload($a, ['cavities' => 3]));
        $box = CoreBox::firstOrFail();

        $this->put(route('core-boxes.cavities', $box), ['cores' => [1 => $a->id, 2 => $b->id, 3 => null]])->assertSessionHasNoErrors();

        $this->assertSame([1 => $a->id, 2 => $b->id], $box->cavityMap()->pluck('core_definition_id', 'cavity_number')->all());
        $this->get(route('cores.show', $b))->assertOk()->assertSee($box->code);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $box->coresMade()->pluck('id')->all());
    }

    public function test_a_box_must_keep_at_least_one_cavity_in_use(): void
    {
        $core = $this->core();
        $this->post(route('core-boxes.store'), $this->boxPayload($core, ['cavities' => 2]));
        $box = CoreBox::firstOrFail();

        $this->put(route('core-boxes.cavities', $box), ['cores' => [1 => null, 2 => null]])->assertSessionHasErrors('cores');

        $this->assertSame(2, $box->cavityMap()->count());
    }

    public function test_changing_the_cavity_count_keeps_set_cores_and_adds_the_main_core(): void
    {
        $a = $this->core('Core A');
        $b = $this->core('Core B');
        $this->post(route('core-boxes.store'), $this->boxPayload($a, ['cavities' => 2]));
        $box = CoreBox::firstOrFail();
        $this->put(route('core-boxes.cavities', $box), ['cores' => [1 => $a->id, 2 => $b->id]]);

        $this->put(route('core-boxes.update', $box), $this->boxPayload($a, ['cavities' => 3]))->assertSessionHasNoErrors();

        $this->assertSame([1 => $a->id, 2 => $b->id, 3 => $a->id], $box->cavityMap()->pluck('core_definition_id', 'cavity_number')->all());
    }

    public function test_a_modified_box_needs_the_tooling_it_came_from(): void
    {
        $core = $this->core();
        $this->post(route('core-boxes.store'), $this->boxPayload($core, ['origin' => 'MODIFIED_BOX']))->assertSessionHasErrors('modified_from_core_box_id');
        $this->post(route('core-boxes.store'), $this->boxPayload($core, ['origin' => 'MODIFIED_PATTERN']))->assertSessionHasErrors('modified_from_pattern_id');

        $old = CoreBox::create(['core_definition_id' => $core->id, 'name' => 'Old box']);
        $pattern = Pattern::create(['code' => 'PT-1', 'name' => 'Plate']);
        $this->post(route('core-boxes.store'), $this->boxPayload($core, ['name' => 'From box', 'origin' => 'MODIFIED_BOX', 'modified_from_core_box_id' => $old->id]))->assertSessionHasNoErrors();
        $this->post(route('core-boxes.store'), $this->boxPayload($core, ['name' => 'From pattern', 'origin' => 'MODIFIED_PATTERN', 'modified_from_pattern_id' => $pattern->id, 'modified_from_core_box_id' => $old->id]))->assertSessionHasNoErrors();

        $this->assertTrue(CoreBox::where('name', 'From box')->first()->modifiedFromBox->is($old));
        $fromPattern = CoreBox::where('name', 'From pattern')->first();
        $this->assertTrue($fromPattern->modifiedFromPattern->is($pattern));
        $this->assertNull($fromPattern->modified_from_core_box_id);
        $this->get(route('core-boxes.show', $old))->assertOk()->assertSee('From box');
    }

    public function test_a_core_made_by_a_box_cannot_be_archived_until_the_box_is_archived(): void
    {
        $core = $this->core();
        $this->post(route('core-boxes.store'), $this->boxPayload($core));
        $box = CoreBox::firstOrFail();

        $this->delete(route('cores.archive', $core))->assertSessionHasErrors('archive');
        $this->assertNull($core->fresh()->deleted_at);

        $this->delete(route('core-boxes.archive', $box));
        $this->delete(route('cores.archive', $core));
        $this->assertNotNull($core->fresh()->deleted_at);
    }

    public function test_a_scanned_core_box_code_opens_its_page(): void
    {
        $box = CoreBox::create(['core_definition_id' => $this->core()->id, 'name' => 'Box']);

        $this->get(route('scan', ['code' => $box->code]))->assertRedirect(route('core-boxes.show', $box));
    }
}
