<?php

namespace Tests\Feature;

use App\Domains\Licensing\FoundrySize;
use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use App\Support\ProcessDepth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundrySizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_is_the_default_and_shows_the_whole_menu(): void
    {
        $this->assertSame('FULL', app(FoundrySize::class)->current());
        $this->get(route('dashboard'))->assertOk()->assertSee('Sand systems')->assertSee('Casting analysis')->assertSee('Controlled documents');
    }

    public function test_micro_shortens_the_menu_and_keeps_quick_flow(): void
    {
        $this->put(route('settings.size'), ['size' => 'MICRO'])->assertRedirect();
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Moulds made / poured')->assertSee('Quick QC')->assertSee('Sales orders')
            ->assertDontSee('Sand systems')->assertDontSee('Casting analysis')->assertDontSee('Controlled documents')->assertDontSee('Iron grades');
    }

    public function test_small_hides_only_the_heavy_extras(): void
    {
        $this->put(route('settings.size'), ['size' => 'SMALL']);
        $this->get(route('dashboard'))->assertOk()->assertSee('Sand systems')->assertSee('Controlled documents')->assertDontSee('Casting analysis');
    }

    public function test_a_hidden_screen_still_opens(): void
    {
        $this->put(route('settings.size'), ['size' => 'MICRO']);
        $this->get(route('sand-systems.index'))->assertOk();
    }

    public function test_a_size_applies_its_process_depth_and_later_rule_edits_leave_the_size_alone(): void
    {
        $this->put(route('settings.size'), ['size' => 'MICRO']);
        $this->assertSame('BASIC', ProcessDepth::current());
        $this->put(route('settings.size'), ['size' => 'FULL']);
        $this->assertSame('FULL', ProcessDepth::current());

        $this->put(route('settings.process-depth'), ['gate_induction' => 'OFF']);
        $this->assertSame('CUSTOM', ProcessDepth::current());
        $this->assertSame('FULL', app(FoundrySize::class)->current());
        $this->assertSame('FULL', AppSetting::getValue('foundry.size'));
    }

    public function test_an_unknown_size_is_refused(): void
    {
        $this->put(route('settings.size'), ['size' => 'HUGE'])->assertSessionHasErrors('size');
    }

    public function test_the_page_lists_the_sizes_and_needs_settings_permission(): void
    {
        $this->get(route('settings.size.edit'))->assertOk()->assertSee('Micro')->assertSee('Small')->assertSee('Full')->assertDontSee('Automotive');

        config(['foundry.require_login' => true]);
        $viewer = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $viewer->roles()->attach(Role::where('code', 'VIEWER')->value('id'));
        $this->actingAs($viewer)->get(route('settings.size.edit'))->assertForbidden();
        $this->actingAs($viewer)->put(route('settings.size'), ['size' => 'MICRO'])->assertForbidden();
    }
}
