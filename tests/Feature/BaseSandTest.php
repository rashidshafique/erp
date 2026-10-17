<?php

namespace Tests\Feature;

use App\Models\BaseSand;
use App\Models\Material;
use App\Models\SandSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaseSandTest extends TestCase
{
    use RefreshDatabase;

    private function sandMaterial(string $code, string $type = 'SAND'): Material
    {
        return Material::create(['code' => $code, 'name' => $code.' material', 'material_type' => $type, 'base_uom' => 'kg', 'status' => 'ACTIVE']);
    }

    private function system(string $name): SandSystem
    {
        return SandSystem::where('name', $name)->with('sands')->sole();
    }

    private function sand(string $code): BaseSand
    {
        return BaseSand::where('code', $code)->sole();
    }

    /** The fields the sand system row posts, besides the sands. */
    private function systemRow(SandSystem $system): array
    {
        return ['name' => $system->name, 'system_type' => $system->system_type, 'status' => $system->status, 'sort_order' => $system->sort_order];
    }

    public function test_the_common_sands_are_listed_and_the_seeded_systems_are_silica_based(): void
    {
        $this->assertSame(
            ['Silica sand', 'River sand', 'Chromite sand', 'Zircon sand', 'Olivine sand', 'Ceramic sand (synthetic)', 'Chamotte (fireclay grog)'],
            BaseSand::ordered()->pluck('name')->all()
        );
        foreach (['Greensand', 'Alphaset', 'Resin-coated sand'] as $name) {
            $this->assertSame('Silica sand', $this->system($name)->mainSand()?->name, $name);
        }
        $this->assertSame(['River sand'], $this->system('Sheera')->sands->pluck('name')->all(), 'sheera moulding uses river sand');
        $this->assertNull($this->system('Other')->mainSand());
        $this->get(route('sand-systems.index'))->assertOk()->assertSee('Chromite sand')->assertSee('Add one under Materials');
    }

    public function test_a_sand_system_uses_several_sands_with_one_main(): void
    {
        $greensand = $this->system('Greensand');
        $silica = $this->sand('SILICA');
        $chromite = $this->sand('CHROMITE');
        $zircon = $this->sand('ZIRCON');

        $this->put(route('sand-systems.update', $greensand), $this->systemRow($greensand) + ['main_sand_id' => $silica->id, 'sand_ids' => [$chromite->id, $zircon->id, $silica->id]])
            ->assertSessionHasNoErrors();
        $greensand = $this->system('Greensand');
        $this->assertSame(['Silica sand', 'Chromite sand', 'Zircon sand'], $greensand->sands->pluck('name')->all(), 'the main sand comes first');
        $this->assertSame(1, $greensand->sands->where('pivot.is_main', true)->count());

        $this->put(route('sand-systems.update', $greensand), $this->systemRow($greensand) + ['main_sand_id' => $chromite->id, 'sand_ids' => [$silica->id]])
            ->assertSessionHasNoErrors();
        $greensand = $this->system('Greensand');
        $this->assertSame('Chromite sand', $greensand->mainSand()->name, 'the main flag moves');
        $this->assertSame(['Chromite sand', 'Silica sand'], $greensand->sands->pluck('name')->all(), 'unticked sands are removed');

        $this->put(route('sand-systems.update', $greensand), $this->systemRow($greensand))->assertSessionHasNoErrors();
        $this->assertCount(2, $this->system('Greensand')->sands, 'a save without the sand fields leaves the sands alone');
    }

    public function test_a_new_sand_system_can_be_given_its_main_sand(): void
    {
        $this->post(route('sand-systems.store'), ['name' => 'No-bake', 'system_type' => 'CHEMICAL_BONDED', 'main_sand_id' => $this->sand('ZIRCON')->id])->assertSessionHasNoErrors();
        $this->assertSame('Zircon sand', $this->system('No-bake')->mainSand()->name);
        $this->post(route('sand-systems.store'), ['name' => 'Bad', 'system_type' => 'OTHER', 'main_sand_id' => fake()->uuid()])->assertSessionHasErrors('main_sand_id');
    }

    public function test_a_sand_links_only_to_one_sand_material_from_inventory(): void
    {
        $quartz = $this->sandMaterial('SND-QTZ');
        $pig = $this->sandMaterial('PIG-1', 'METAL');
        $silica = $this->sand('SILICA');
        $row = fn (BaseSand $s) => ['name' => $s->name, 'status' => $s->status, 'sort_order' => $s->sort_order, 'description' => $s->description];

        $this->put(route('sand-systems.base.update', $silica), $row($silica) + ['material_id' => $pig->id])->assertSessionHasErrors(['material_id' => 'Choose a raw material whose type is Sand.']);
        $this->put(route('sand-systems.base.update', $silica), $row($silica) + ['material_id' => $quartz->id])->assertSessionHasNoErrors();
        $this->assertTrue($silica->fresh()->material->is($quartz));
        $this->get(route('sand-systems.index'))->assertOk()->assertSee(route('materials.show', $quartz));

        $olivine = $this->sand('OLIVINE');
        $this->put(route('sand-systems.base.update', $olivine), $row($olivine) + ['material_id' => $quartz->id])->assertSessionHasErrors(['material_id' => 'That raw material is already linked to another sand.']);

        $this->put(route('sand-systems.base.update', $silica), $row($silica) + ['material_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($silica->fresh()->material_id, 'choosing "not linked" removes the link');
    }

    public function test_sands_are_added_with_unique_names(): void
    {
        $this->post(route('sand-systems.base.store'), ['name' => ' Reclaimed  silica ', 'material_id' => $this->sandMaterial('SND-REC')->id])->assertSessionHasNoErrors();
        $reclaimed = BaseSand::where('name', 'Reclaimed silica')->sole();
        $this->assertSame(['RECLAIMED_SILICA', 'ACTIVE', 70], [$reclaimed->code, $reclaimed->status, $reclaimed->sort_order], 'a new sand goes to the end of the list');
        $this->post(route('sand-systems.base.store'), ['name' => 'zircon SAND'])->assertSessionHasErrors('name');
    }

    public function test_a_sand_in_use_cannot_be_deleted_and_a_deleted_sand_frees_its_material(): void
    {
        $this->delete(route('sand-systems.base.destroy', $this->sand('SILICA')))->assertSessionHasErrors(['base_sand' => 'Silica sand is used by 3 sand system(s), so it cannot be deleted. Set it to inactive to stop offering it.']);
        $this->assertNotSoftDeleted($this->sand('SILICA'));

        $material = $this->sandMaterial('SND-OLV');
        $olivine = $this->sand('OLIVINE');
        $olivine->update(['material_id' => $material->id]);
        $this->delete(route('sand-systems.base.destroy', $olivine))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($olivine);
        $this->post(route('sand-systems.base.store'), ['name' => 'Olivine (new supplier)', 'material_id' => $material->id])->assertSessionHasNoErrors();
    }
}
