<?php

namespace Tests\Feature;

use App\Domains\Core\Contracts\MenuFilter;
use App\Domains\Maintenance\Support\CalibrationRules;
use App\Support\FloorRules;
use App\Support\OrderRules;
use App\Support\PouringRules;
use App\Support\ControlPlanRules;
use App\Support\FlaskRules;
use App\Support\PatternOwnershipRules;
use App\Support\ProcessDepth;
use App\Support\ProcessRules;
use App\Support\QualityRules;
use App\Support\QueueRules;
use App\Support\WorkListRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessDepthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_starts_on_full_depth(): void
    {
        $this->assertSame('FULL', ProcessDepth::current());
        $this->get(route('settings.rules'))->assertOk()->assertSee('In use');
    }

    public function test_the_menu_switch_shows_simple_mode_and_flips_between_basic_and_full(): void
    {
        config(['foundry.require_login' => true]);
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'ADMIN')->first());
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertSee('Simple mode')->assertSee('aria-checked="false"', false)->assertSee('name="preset" value="BASIC"', false);

        ProcessDepth::apply('BASIC');

        $this->get(route('dashboard'))->assertSee('aria-checked="true"', false)->assertSee('name="preset" value="FULL"', false);
    }

    public function test_basic_depth_loosens_the_checks_and_full_restores_them(): void
    {
        $this->put(route('settings.rules.update'), ['preset' => 'BASIC'])->assertSessionHasNoErrors();

        $this->assertSame('BASIC', ProcessDepth::current());
        $this->assertSame('OFF', PouringRules::get('gate_other'));
        $this->assertSame('FREE', PouringRules::get('hold_first_iron'));
        $this->assertSame('OFF', CalibrationRules::get('overdue_use'));
        $this->assertSame('SKIP', OrderRules::get('contract_review'));
        $this->assertTrue(OrderRules::completesAtPour());

        $this->put(route('settings.rules.update'), ['preset' => 'FULL'])->assertSessionHasNoErrors();
        $this->assertSame('FULL', ProcessDepth::current());
        $this->assertFalse(OrderRules::completesAtPour());
    }

    public function test_changing_a_single_rule_makes_the_depth_custom(): void
    {
        $this->put(route('settings.rules.update'), ['preset' => 'BASIC']);
        PouringRules::set('containment', 'HOLD');

        $this->assertSame('CUSTOM', ProcessDepth::current());
        $this->put(route('settings.rules.update'), ['preset' => 'NOPE'])->assertSessionHasErrors('preset');
    }

    public function test_the_rules_page_shows_every_rule_in_its_section_and_saves_a_floor_rule(): void
    {
        $this->get(route('settings.rules', ['advanced' => 1]))->assertOk()
            ->assertSeeInOrder(['data-tab-start="Development"', 'data-tab-start="Orders"', 'data-tab-start="Moulding"', 'data-tab-start="Flasks"', 'data-tab-start="Melting and pouring"', 'data-tab-start="Quality"', 'data-tab-start="Patterns"', 'data-tab-start="Purchasing"'], false)
            ->assertSee('Moulding work lists');

        $this->put(route('settings.rules.update'), ['work_lists' => 'OFF'])->assertSessionHasNoErrors();

        $this->assertFalse(FloorRules::usesWorkLists());
        $this->put(route('settings.rules.update'), ['work_lists' => 'SOMETIMES'])->assertSessionHasErrors('work_lists');
    }

    public function test_every_rule_is_on_the_page_once_and_the_old_rule_pages_open_its_tab(): void
    {
        $shown = collect(ProcessRules::sections(true))->flatMap(fn (array $rules) => array_keys($rules));
        $this->assertSame($shown->count(), $shown->unique()->count());
        foreach ([OrderRules::class, PouringRules::class, QueueRules::class, WorkListRules::class, FlaskRules::class, PatternOwnershipRules::class, QualityRules::class, ControlPlanRules::class, CalibrationRules::class] as $class) {
            $this->assertSame([], array_values(array_diff(array_keys($class::rules()), $shown->all())), $class);
        }

        $this->get('/settings/pouring')->assertRedirect('/settings/rules#melting-and-pouring');
        $this->get('/settings/process-depth')->assertRedirect('/settings/rules');
    }

    public function test_the_common_view_holds_only_the_numbered_rules_and_advanced_adds_the_rest(): void
    {
        $common = collect(ProcessRules::sections())->flatMap(fn (array $rules) => array_keys($rules));
        $all = collect(ProcessRules::sections(true))->flatMap(fn (array $rules) => array_keys($rules));

        $this->assertSame(array_keys(ProcessDepth::numbers()), array_values(array_intersect(array_keys(ProcessDepth::numbers()), $common->all())));
        $this->assertGreaterThan($common->count(), $all->count());
        $this->get(route('settings.rules'))->assertOk()->assertSee('Contract review')->assertDontSee('Too few free flasks')->assertDontSee('data-tab-start="Flasks"', false)->assertSee('more are hidden');
        $this->get(route('settings.rules', ['advanced' => 1]))->assertOk()->assertSee('Too few free flasks')->assertSee('data-tab-start="Flasks"', false);
        $this->put(route('settings.rules.update'), ['shortage' => 'WARN'])->assertSessionHasNoErrors();   // a hidden rule still saves
    }

    public function test_a_number_rule_is_checked_and_saved_from_its_tab(): void
    {
        $this->put(route('settings.rules.update'), ['max_fade_minutes' => 0])->assertSessionHasErrors('max_fade_minutes');
        $this->put(route('settings.rules.update'), ['max_fade_minutes' => 20, 'treatment' => 'true'])->assertSessionHasNoErrors();

        $this->assertSame(20, PouringRules::minutes('max_fade_minutes'));
        $this->assertTrue(PouringRules::enforcesTreatment());
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
