<?php

namespace Tests\Feature;

use App\Domains\Core\Contracts\MenuFilter;
use App\Domains\Maintenance\Support\CalibrationRules;
use App\Support\FloorRules;
use App\Support\OrderRules;
use App\Support\PouringRules;
use App\Support\ProcessDepth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessDepthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_starts_on_full_depth(): void
    {
        $this->assertSame('FULL', ProcessDepth::current());
        $this->get(route('settings.process-depth.edit'))->assertOk()->assertSee('In use');
    }

    public function test_basic_depth_loosens_the_checks_and_full_restores_them(): void
    {
        $this->put(route('settings.process-depth'), ['preset' => 'BASIC'])->assertSessionHasNoErrors();

        $this->assertSame('BASIC', ProcessDepth::current());
        $this->assertSame('OFF', PouringRules::get('gate_other'));
        $this->assertSame('FREE', PouringRules::get('hold_first_iron'));
        $this->assertSame('OFF', CalibrationRules::get('overdue_use'));
        $this->assertSame('SKIP', OrderRules::get('contract_review'));
        $this->assertTrue(OrderRules::completesAtPour());

        $this->put(route('settings.process-depth'), ['preset' => 'FULL'])->assertSessionHasNoErrors();
        $this->assertSame('FULL', ProcessDepth::current());
        $this->assertFalse(OrderRules::completesAtPour());
    }

    public function test_changing_a_single_rule_makes_the_depth_custom(): void
    {
        $this->put(route('settings.process-depth'), ['preset' => 'BASIC']);
        PouringRules::set('containment', 'HOLD');

        $this->assertSame('CUSTOM', ProcessDepth::current());
        $this->put(route('settings.process-depth'), ['preset' => 'NOPE'])->assertSessionHasErrors('preset');
    }

    public function test_the_rules_page_shows_every_rule_in_its_section_and_saves_a_floor_rule(): void
    {
        $this->get(route('settings.process-depth.edit'))->assertOk()
            ->assertSeeInOrder(['Orders', 'Moulding', 'Melting and pouring', 'Quality and gauges'])->assertSee('Moulding work lists');

        $this->put(route('settings.process-depth'), ['work_lists' => 'OFF'])->assertSessionHasNoErrors();

        $this->assertFalse(FloorRules::usesWorkLists());
        $this->put(route('settings.process-depth'), ['work_lists' => 'SOMETIMES'])->assertSessionHasErrors('work_lists');
    }

    public function test_a_rule_switched_off_hides_its_screen_from_the_menu_but_the_screen_still_opens(): void
    {
        $this->get(route('dashboard'))->assertSee('href="'.route('work-lists.index').'"', false)->assertSee('href="'.route('heat-plan.index').'"', false);

        FloorRules::set('work_lists', 'OFF');
        FloorRules::set('melting_plan', 'OFF');

        $this->get(route('dashboard'))->assertDontSee('href="'.route('work-lists.index').'"', false)->assertDontSee('href="'.route('heat-plan.index').'"', false);
        $this->get(route('work-lists.index'))->assertOk();
    }

    public function test_quick_screens_take_the_full_floor_screens_out_of_the_menu(): void
    {
        FloorRules::set('floor_screens', 'QUICK');
        $menu = app(MenuFilter::class);

        $this->assertFalse($menu->shows('moulding-runs.index'));
        $this->assertFalse($menu->shows('pours.index'));
        $this->assertTrue($menu->shows('quick.floor.index'));
        $this->assertTrue($menu->shows('heats.index'));
    }
}
