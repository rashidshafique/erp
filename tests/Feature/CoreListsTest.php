<?php

namespace Tests\Feature;

use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\CoreRequirement;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CoreListsTest extends TestCase
{
    use RefreshDatabase;

    private function core(string $name, array $over = []): CoreDefinition
    {
        return CoreDefinition::create($over + ['name' => $name, 'core_type' => 'STANDARD']);
    }

    private function box(string $name, CoreDefinition $core, array $over = []): CoreBox
    {
        return CoreBox::create($over + ['name' => $name, 'core_definition_id' => $core->id, 'revision' => 'A', 'status' => 'ACTIVE', 'origin' => 'NEW', 'cavities' => 1]);
    }

    /** @return list<string> */
    private function names($list, string $key): array
    {
        return collect($list->viewData($key)->items())->pluck('name')->all();
    }

    public function test_the_cores_list_filters_by_tab_type_and_search_and_shows_the_focused_core_in_the_panel(): void
    {
        $volute = $this->core('Volute core', ['process_type' => 'Cold box']);
        $this->core('Liner core', ['core_type' => 'CERAMIC']);
        $this->core('Old core', ['status' => 'INACTIVE']);
        $box = $this->box('Volute core box', $volute);
        $revision = ProductRevision::create(['product_id' => Product::create(['code' => 'PRD-1', 'name' => 'Pump body'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        CoreRequirement::create(['core_definition_id' => $volute->id, 'product_revision_id' => $revision->id, 'quantity_per_casting' => 2]);

        $this->get(route('cores.index'))->assertOk()->assertSee('Volute core')->assertSee('Core boxes')->assertSee('Details');

        $list = Livewire::test('tooling::core-list');
        $this->assertEqualsCanonicalizing(['Volute core', 'Liner core', 'Old core'], $this->names($list, 'cores'));
        $this->assertEqualsCanonicalizing(['Liner core', 'Volute core'], $this->names($list->call('showStatus', 'ACTIVE'), 'cores'));
        $this->assertSame(['Old core'], $this->names($list->call('showStatus', 'INACTIVE'), 'cores'));
        $list->call('showStatus', '');
        $this->assertSame(['Liner core'], $this->names($list->set('type', 'CERAMIC'), 'cores'));
        $list->call('resetFilters')->set('search', 'volute')->call('focus', $volute->id)
            ->assertSee('Core boxes that make it')->assertSee($box->code)->assertSee('1 of 1')->assertSee('Pump body')->assertSee('× 2');

        $list->call('toggleDetails')->assertDontSee('Core boxes that make it');
    }

    public function test_a_lent_box_has_its_own_tab_and_status_and_stays_active_for_core_making(): void
    {
        $core = $this->core('Manifold core');
        $this->box('Home box', $core);
        $lent = $this->box('Spare box', $core, ['custody' => 'LENT', 'custody_location' => 'Erie Core Works']);
        $this->box('Old box', $core, ['status' => 'RETIRED']);

        $this->assertSame('LENT', $lent->displayStatus());
        $this->assertTrue($core->hasActiveBox());

        $list = Livewire::test('tooling::core-box-list');
        $this->assertSame(['Home box'], $this->names($list->call('showStatus', 'ACTIVE'), 'boxes'));
        $this->assertSame(['Spare box'], $this->names($list->call('showStatus', 'LENT'), 'boxes'));
        $this->assertSame(['Old box'], $this->names($list->call('showStatus', 'RETIRED'), 'boxes'));
        $list->call('showStatus', '')->assertSee('Lent · Erie Core Works')->call('focus', $lent->id)
            ->assertSee('Cavity map')->assertSee('Cavity 1: Manifold core')->assertSee('Lent to a third party for core making · Erie Core Works');
        $list->set('details', false)->assertSee('Edit cavities')->assertSee(route('core-boxes.show', $lent).'#cavity-map');

        $this->get(route('core-boxes.show', $lent))->assertOk()->assertSee('Lent')->assertSee('with Erie Core Works');
        $this->get(route('core-boxes.index', ['status' => 'LENT']))->assertOk()->assertSee('Spare box')->assertDontSee('Home box');
    }

    public function test_archived_boxes_show_behind_the_footer_switch_with_restore(): void
    {
        $core = $this->core('Valve core');
        $this->box('Kept box', $core);
        $this->box('Gone box', $core)->delete();

        $list = Livewire::test('tooling::core-box-list');
        $this->assertSame(['Kept box'], $this->names($list, 'boxes'));
        $list->set('archived', true)->assertSee('Restore');
        $this->assertSame(['Gone box'], $this->names($list, 'boxes'));
    }
}
