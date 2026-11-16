<?php

namespace Tests\Feature;

use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\CoreLot;
use App\Models\Customer;
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

    public function test_one_box_can_make_different_cores_and_its_pages_show_how_many_of_each(): void
    {
        $a = $this->core('Core A');
        $b = $this->core('Core B');

        $this->post(route('core-boxes.store'), $this->boxPayload($a, ['box_cores' => [['core' => $a->id, 'qty' => 2], ['core' => $b->id, 'qty' => 1]], 'blocked' => 1]))->assertSessionHasNoErrors();

        $box = CoreBox::sole();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $box->coresMade()->pluck('id')->all());
        $this->assertSame('Core A × 2, Core B × 1, 1 blocked', $box->makesLine());
        $this->get(route('core-boxes.show', $box))->assertOk()->assertSee('Cavity map')->assertSee('Cavity 4: blocked', false)->assertSee('Blocked × 1');
        $this->get(route('cores.show', $b))->assertOk()->assertSee($box->code)->assertSee('1 of 4');
    }

    public function test_a_box_must_make_at_least_one_core(): void
    {
        $core = $this->core();

        $this->post(route('core-boxes.store'), $this->boxPayload($core, ['box_cores' => [], 'blocked' => 2]))->assertSessionHasErrors('box_cores');

        $this->assertSame(0, CoreBox::count());
    }

    public function test_changing_only_the_cavity_count_keeps_set_cores_and_adds_the_main_core(): void
    {
        $a = $this->core('Core A');
        $b = $this->core('Core B');
        $this->post(route('core-boxes.store'), $this->boxPayload($a, ['box_cores' => [['core' => $a->id, 'qty' => 1], ['core' => $b->id, 'qty' => 1]]]));
        $box = CoreBox::sole();

        $this->put(route('core-boxes.update', $box), $this->boxPayload($a, ['cavities' => 3]))->assertSessionHasNoErrors();

        $this->assertSame([1 => $a->id, 2 => $b->id, 3 => $a->id], $box->cavityMap()->pluck('core_definition_id', 'cavity_number')->all());
    }

    public function test_the_form_lists_each_core_with_how_many_cavities_make_it(): void
    {
        $a = $this->core('Core A');
        $b = $this->core('Core B');
        $payload = $this->boxPayload($a, ['box_cores' => [['core' => $a->id, 'qty' => 3], ['core' => $b->id, 'qty' => 1]], 'blocked' => 1]);
        unset($payload['core_definition_id'], $payload['cavities']);

        $this->post(route('core-boxes.store'), $payload)->assertSessionHasNoErrors();

        $box = CoreBox::sole();
        $this->assertSame(5, $box->cavities);
        $this->assertSame($a->id, $box->core_definition_id);
        $this->assertSame([1 => $a->id, 2 => $a->id, 3 => $a->id, 4 => $b->id], $box->cavityMap()->pluck('core_definition_id', 'cavity_number')->all());
        $this->get(route('core-boxes.edit', $box))->assertOk()
            ->assertSee('name="box_cores[0][qty]" min="1" max="200" required value="3"', false)->assertSee('name="box_cores[1][qty]" min="1" max="200" required value="1"', false)
            ->assertSee('name="blocked" min="0" max="199" value="1"', false);
    }

    public function test_rows_for_the_same_core_merge_and_bad_rows_save_nothing(): void
    {
        $a = $this->core('Core A');
        $b = $this->core('Core B');

        $this->post(route('core-boxes.store'), $this->boxPayload($a, ['box_cores' => [['core' => $b->id, 'qty' => 2], ['core' => $a->id, 'qty' => 1], ['core' => $b->id, 'qty' => 1]]]))->assertSessionHasNoErrors();
        $this->assertSame([1 => $b->id, 2 => $b->id, 3 => $b->id, 4 => $a->id], CoreBox::sole()->cavityMap()->pluck('core_definition_id', 'cavity_number')->all());
        CoreBox::query()->forceDelete();

        $this->post(route('core-boxes.store'), $this->boxPayload($a, ['box_cores' => [['core' => $a->id, 'qty' => 150], ['core' => $b->id, 'qty' => 51]]]))->assertSessionHasErrors('box_cores');
        $this->post(route('core-boxes.store'), $this->boxPayload($a, ['box_cores' => [['core' => 'not-a-core', 'qty' => 1]]]))->assertSessionHasErrors('box_cores.0.core');
        $this->post(route('core-boxes.store'), $this->boxPayload($a, ['box_cores' => [['core' => $a->id, 'qty' => 0]]]))->assertSessionHasErrors('box_cores.0.qty');
        $this->assertSame(0, CoreBox::count());
    }

    public function test_new_counts_number_the_cavities_in_row_order(): void
    {
        $a = $this->core('Core A');
        $b = $this->core('Core B');
        $this->post(route('core-boxes.store'), $this->boxPayload($a, ['box_cores' => [['core' => $a->id, 'qty' => 3], ['core' => $b->id, 'qty' => 1]]]));
        $box = CoreBox::sole();

        $this->put(route('core-boxes.update', $box), $this->boxPayload($a, ['box_cores' => [['core' => $b->id, 'qty' => 2], ['core' => $a->id, 'qty' => 1]], 'blocked' => 1]))->assertSessionHasNoErrors();

        $this->assertSame([1 => $b->id, 2 => $b->id, 3 => $a->id], $box->cavityMap()->pluck('core_definition_id', 'cavity_number')->all());
        $this->assertSame(4, $box->fresh()->cavities);
        $this->assertSame($b->id, $box->fresh()->core_definition_id);
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

    public function test_the_forms_open_for_a_new_and_an_existing_core_and_box(): void
    {
        $core = $this->core();
        $box = CoreBox::create(['core_definition_id' => $core->id, 'name' => 'Box']);

        $this->get(route('cores.create'))->assertOk();
        $this->get(route('cores.edit', $core))->assertOk();
        $this->get(route('core-boxes.create', ['core' => $core->id]))->assertOk()->assertSee($core->name);
        $this->get(route('core-boxes.edit', $box))->assertOk();
        $this->get(route('core-boxes.show', $box))->assertOk();
    }

    public function test_a_core_box_owner_and_custody_are_saved_and_shown(): void
    {
        $this->post(route('core-boxes.store'), $this->boxPayload($this->core()));
        $box = CoreBox::sole();
        $this->assertSame('FOUNDRY', $box->owner_type);
        $customer = Customer::create(['code' => 'NL', 'name' => 'Nordline', 'active' => true]);

        $this->put(route('core-boxes.ownership', $box), ['owner_type' => 'CUSTOMER', 'custody' => 'IN_STORE'])->assertSessionHasErrors('owner_customer_id');
        $this->put(route('core-boxes.ownership', $box), ['owner_type' => 'FOUNDRY', 'custody' => 'LENT'])->assertSessionHasErrors('custody_location');
        $this->put(route('core-boxes.ownership', $box), ['owner_type' => 'CUSTOMER', 'owner_customer_id' => $customer->id, 'custody' => 'IN_STORE',
            'custody_location' => 'CS-R2-04', 'ownership_reference' => 'NL-T-0121', 'return_notice_days' => 14])->assertSessionHasNoErrors();

        $this->assertSame($customer->id, $box->fresh()->owner_customer_id);
        $this->get(route('core-boxes.show', $box))->assertOk()->assertSee('Nordline · customer-owned')->assertSee('In our store')->assertSee('CS-R2-04')
            ->assertSee('NL-T-0121')->assertSee('Return on request: 14 days');
        $this->put(route('core-boxes.ownership', $box), ['owner_type' => 'FOUNDRY', 'owner_customer_id' => $customer->id, 'custody' => 'IN_STORE']);
        $this->assertNull($box->fresh()->owner_customer_id);
    }
    public function test_the_core_form_has_no_status_and_saves_a_typed_mark(): void
    {
        $this->get(route('cores.create'))->assertOk()->assertDontSee('name="status"', false)->assertSee('Set automatically')->assertSee('+ Add engineering notes, photo or barcode');

        $this->post(route('cores.store'), ['name' => 'Volute core', 'core_type' => 'STANDARD', 'mark_code' => 'volc'])->assertSessionHasNoErrors();

        $core = CoreDefinition::sole();
        $this->assertSame('ACTIVE', $core->status);
        $this->assertSame('VOLC', $core->mark_code);
        $this->get(route('cores.edit', $core))->assertOk()->assertSee('Edit core');
    }

    public function test_a_core_mark_taken_by_another_tool_is_refused_before_saving(): void
    {
        $this->post(route('cores.store'), ['name' => 'First', 'core_type' => 'STANDARD', 'mark_code' => 'ABC1']);

        $this->post(route('cores.store'), ['name' => 'Second', 'core_type' => 'STANDARD', 'mark_code' => 'abc1'])->assertSessionHasErrors('mark_code');

        $this->assertSame(1, CoreDefinition::count());
    }

    public function test_the_core_page_shows_made_in_on_hand_and_the_empty_box_prompt(): void
    {
        $core = $this->core();
        $this->get(route('cores.show', $core))->assertOk()->assertSee('leave it empty for a bought-in core')->assertSee('0 cores · 0 lots');

        $this->post(route('core-boxes.store'), $this->boxPayload($core, ['cavities' => 2]));
        $box = CoreBox::sole();
        CoreLot::create(['lot_number' => 'CL-1', 'core_definition_id' => $core->id, 'core_box_id' => $box->id, 'quantity_made' => 12, 'made_on' => now(), 'status' => 'STORED', 'qc_status' => 'NOT_REQUIRED']);

        $this->get(route('cores.show', $core))->assertOk()->assertSee('Made in')->assertSee('12 cores · 1 lot')->assertSee('Box cavities')->assertSee('2 of 2');
    }

    public function test_an_archived_core_hides_editing_and_offers_restore(): void
    {
        $core = $this->core();
        $core->delete();

        $this->get(route('cores.show', $core))->assertOk()->assertSee('Restore')->assertDontSee(route('cores.edit', $core))->assertDontSee('+ Product');
    }

    public function test_a_cavity_is_changed_or_blocked_from_the_map_and_the_main_core_follows(): void
    {
        $a = $this->core('Core A');
        $b = $this->core('Core B');
        $this->post(route('core-boxes.store'), $this->boxPayload($a, ['box_cores' => [['core' => $a->id, 'qty' => 2], ['core' => $b->id, 'qty' => 1]]]));
        $box = CoreBox::sole();
        $this->get(route('core-boxes.show', $box))->assertOk()->assertSee(route('core-boxes.cavity', [$box, 3]))->assertSee('Save cavity');

        $this->put(route('core-boxes.cavity', [$box, 1]), ['core' => $b->id])->assertSessionHasNoErrors();
        $this->put(route('core-boxes.cavity', [$box, 2]), ['core' => ''])->assertSessionHasNoErrors();

        $this->assertSame([1 => $b->id, 3 => $b->id], $box->cavityMap()->pluck('core_definition_id', 'cavity_number')->all());
        $this->assertSame($b->id, $box->fresh()->core_definition_id);
        $this->assertSame(1, $box->fresh()->blockedCavities());
        $this->put(route('core-boxes.cavity', [$box, 4]), ['core' => $a->id])->assertNotFound();
    }

    public function test_the_last_cavity_making_a_core_cannot_be_blocked(): void
    {
        $core = $this->core();
        $this->post(route('core-boxes.store'), $this->boxPayload($core, ['cavities' => 2]));
        $box = CoreBox::sole();
        $this->put(route('core-boxes.cavity', [$box, 1]), ['core' => '']);

        $this->put(route('core-boxes.cavity', [$box, 2]), ['core' => ''])->assertSessionHasErrors('core');

        $this->assertSame([2 => $core->id], $box->cavityMap()->pluck('core_definition_id', 'cavity_number')->all());
    }

    public function test_saving_the_form_with_the_same_counts_keeps_cavities_set_on_the_map(): void
    {
        $a = $this->core('Core A');
        $b = $this->core('Core B');
        $this->post(route('core-boxes.store'), $this->boxPayload($a, ['box_cores' => [['core' => $a->id, 'qty' => 2], ['core' => $b->id, 'qty' => 1]], 'blocked' => 1]));
        $box = CoreBox::sole();
        $this->put(route('core-boxes.cavity', [$box, 4]), ['core' => $b->id]);
        $this->put(route('core-boxes.cavity', [$box, 3]), ['core' => '']);

        $this->put(route('core-boxes.update', $box), $this->boxPayload($a, ['name' => 'Renamed', 'box_cores' => [['core' => $a->id, 'qty' => 2], ['core' => $b->id, 'qty' => 1]], 'blocked' => 1]))->assertSessionHasNoErrors();

        $this->assertSame([1 => $a->id, 2 => $a->id, 4 => $b->id], $box->cavityMap()->pluck('core_definition_id', 'cavity_number')->all());
        $this->assertSame('Renamed', $box->fresh()->name);
    }

    public function test_an_archived_box_shows_its_map_read_only(): void
    {
        $this->post(route('core-boxes.store'), $this->boxPayload($this->core(), ['cavities' => 2]));
        $box = CoreBox::sole();
        $box->delete();

        $this->get(route('core-boxes.show', $box))->assertOk()->assertSee('Restore')->assertSee('Cavity map')->assertDontSee('Save cavity')->assertDontSee(route('core-boxes.edit', $box));
        $this->put(route('core-boxes.cavity', [$box, 1]), ['core' => ''])->assertNotFound();
    }

    public function test_the_new_box_form_has_a_preview_no_status_and_saves_ownership(): void
    {
        $core = $this->core();
        $customer = Customer::create(['code' => 'MAS', 'name' => 'Meridian', 'active' => true]);
        $this->get(route('core-boxes.create'))->assertOk()->assertSee('As you type it')->assertSee('data-quick-add="qd-core"', false)->assertDontSee('name="status"', false);

        $payload = $this->boxPayload($core, ['owner_type' => 'CUSTOMER', 'owner_customer_id' => $customer->id, 'custody' => 'LENT', 'custody_location' => 'Erie Pattern Works', 'ownership_reference' => 'MAS-114']);
        unset($payload['status']);
        $this->post(route('core-boxes.store'), $payload)->assertSessionHasNoErrors();

        $box = CoreBox::sole();
        $this->assertSame(['ACTIVE', 'CUSTOMER', $customer->id, 'LENT', 'MAS-114'], [$box->status, $box->owner_type, $box->owner_customer_id, $box->custody, $box->ownership_reference]);
        $this->post(route('core-boxes.store'), $this->boxPayload($core, ['name' => 'Other', 'owner_type' => 'FOUNDRY', 'custody' => 'LENT']))->assertSessionHasErrors('custody_location');
        $this->get(route('core-boxes.edit', $box))->assertOk()->assertSee('name="status"', false);
    }

    public function test_a_core_added_from_the_box_form_comes_back_as_json(): void
    {
        $this->postJson(route('cores.store'), ['name' => 'Pump neck core', 'core_type' => 'STANDARD'])->assertCreated()->assertJsonPath('label', CoreDefinition::sole()->code.' · Pump neck core');

        $this->assertSame('ACTIVE', CoreDefinition::sole()->status);
    }
}
