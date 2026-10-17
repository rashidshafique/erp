<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\MouldingStation;
use App\Models\Pattern;
use App\Models\Role;
use App\Models\SandSystem;
use App\Models\User;
use App\Services\Import\FieldParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandSystemListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_starts_with_the_former_fixed_sands_in_their_order(): void
    {
        $this->assertSame(['Sheera', 'Greensand', 'Alphaset', 'Resin-coated sand', 'Other'], SandSystem::names());
        $this->get(route('sand-systems.index'))->assertOk()->assertSee('Sand systems')->assertSee('Resin-coated sand');
        $this->get(route('moulding-teams.index'))->assertOk()->assertSee(route('sand-systems.index'));
    }

    public function test_an_added_sand_is_offered_and_accepted_on_patterns(): void
    {
        $this->post(route('sand-systems.store'), ['name' => '  No-bake   furan ', 'system_type' => 'CHEMICAL_BONDED'])->assertSessionHasNoErrors();
        $sand = SandSystem::where('name', 'No-bake furan')->sole();
        $this->assertSame(['ACTIVE', 'NO_BAKE_FURAN'], [$sand->status, $sand->code]);
        $this->assertSame('No-bake furan', SandSystem::names()[5], 'a new sand goes to the end of the list');

        $p = Pattern::create(['code' => 'PT-1', 'name' => 'Plate']);
        $this->get(route('patterns.show', $p))->assertOk()->assertSee('No-bake furan');
        $this->put(route('patterns.sand', $p), ['sand_system' => 'No-bake furan', 'compatible_sands' => ['Sheera']])->assertSessionHasNoErrors();
        $this->assertSame('No-bake furan', $p->fresh()->sand_system);
    }

    public function test_names_must_be_unique_ignoring_case(): void
    {
        $this->post(route('sand-systems.store'), ['name' => 'greensand', 'system_type' => 'CLAY_BONDED'])->assertSessionHasErrors('name');
        $alphaset = SandSystem::where('name', 'Alphaset')->sole();
        $this->put(route('sand-systems.update', $alphaset), ['name' => 'SHEERA', 'system_type' => 'OTHER', 'status' => 'ACTIVE'])->assertSessionHasErrors('name');
        $this->assertSame('Alphaset', $alphaset->fresh()->name);
    }

    public function test_renaming_a_sand_renames_it_on_the_records_that_use_it(): void
    {
        $made = Pattern::create(['code' => 'PT-1', 'name' => 'Plate', 'sand_system' => 'Sheera', 'compatible_sands' => ['Alphaset']]);
        $also = Pattern::create(['code' => 'PT-2', 'name' => 'Other plate', 'sand_system' => 'Greensand', 'compatible_sands' => ['Sheera', 'Alphaset']]);
        $station = MouldingStation::create(['code' => 'SH-1', 'name' => 'Sheera bench', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 4, 'default_sand_system' => 'Sheera']);

        $sheera = SandSystem::where('name', 'Sheera')->sole();
        $this->put(route('sand-systems.update', $sheera), ['name' => 'Molasses sand', 'system_type' => 'MOLASSES_BONDED', 'status' => 'ACTIVE', 'sort_order' => 10])
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'renamed from Sheera to Molasses sand'));

        $this->assertSame('Molasses sand', $made->fresh()->sand_system);
        $this->assertSame(['Molasses sand', 'Alphaset'], $also->fresh()->compatible_sands);
        $this->assertSame('Molasses sand', $station->fresh()->default_sand_system);
        $this->assertNotContains('Sheera', SandSystem::allNames());
    }

    public function test_an_inactive_sand_is_no_longer_offered_but_records_keep_it(): void
    {
        $p = Pattern::create(['code' => 'PT-1', 'name' => 'Plate', 'sand_system' => 'Alphaset']);
        $fresh = Pattern::create(['code' => 'PT-2', 'name' => 'New plate']);
        $alphaset = SandSystem::where('name', 'Alphaset')->sole();
        $this->put(route('sand-systems.update', $alphaset), ['name' => 'Alphaset', 'system_type' => 'CHEMICAL_BONDED', 'status' => 'INACTIVE'])->assertSessionHasNoErrors();

        $this->assertNotContains('Alphaset', SandSystem::names());
        $this->get(route('patterns.show', $fresh))->assertOk()->assertDontSee('value="Alphaset"', false);
        $this->get(route('patterns.show', $p))->assertOk()->assertSee('value="Alphaset"', false);
        $this->put(route('patterns.sand', $p), ['sand_system' => 'Alphaset', 'sand_note' => 'Still on file'])->assertSessionHasNoErrors();
    }

    public function test_a_sand_in_use_cannot_be_deleted_but_an_unused_one_can(): void
    {
        Pattern::create(['code' => 'PT-1', 'name' => 'Plate', 'compatible_sands' => ['Other']]);
        $other = SandSystem::where('name', 'Other')->sole();
        $this->delete(route('sand-systems.destroy', $other))->assertSessionHasErrors(['sand_system' => 'Other is used by 1 patterns, so it cannot be deleted. Set it to inactive to stop offering it.']);
        $this->assertNotSoftDeleted($other);

        $resin = SandSystem::where('name', 'Resin-coated sand')->sole();
        $this->delete(route('sand-systems.destroy', $resin))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($resin);
        $this->put(route('patterns.sand', Pattern::first()), ['sand_system' => 'Resin-coated sand'])->assertSessionHasErrors('sand_system');
    }

    public function test_a_station_takes_only_a_sand_from_the_list(): void
    {
        $data = ['code' => 'ML-1', 'name' => 'Line', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 2];
        $this->post(route('moulding-teams.stations.store'), $data + ['default_sand_system' => 'Mud'])->assertSessionHasErrors('default_sand_system');
        $this->post(route('moulding-teams.stations.store'), $data + ['default_sand_system' => 'Greensand'])->assertSessionHasNoErrors();
    }

    public function test_only_people_who_manage_settings_can_change_the_list(): void
    {
        config(['foundry.require_login' => true]);
        $supervisor = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $supervisor->roles()->attach(Role::where('code', 'MOULDING_SUPERVISOR')->value('id'));
        $this->actingAs($supervisor)->get(route('moulding-teams.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('sand-systems.index'))->assertForbidden();
        $this->actingAs($supervisor)->post(route('sand-systems.store'), ['name' => 'Furan', 'system_type' => 'OTHER'])->assertForbidden();
        $this->assertFalse(SandSystem::where('name', 'Furan')->exists());
    }

    public function test_pattern_import_reads_sands_from_the_list(): void
    {
        SandSystem::create(['code' => 'FURAN', 'name' => 'Furan', 'system_type' => 'CHEMICAL_BONDED', 'status' => 'ACTIVE']);
        $field = ImportBatch::definition('pattern')['fields']['sand_system'];
        $this->assertContains('Furan', $field['enum']);
        $this->assertSame(['Furan', null, false], FieldParser::parse('sand_system', $field, 'furan', []));
    }
}
