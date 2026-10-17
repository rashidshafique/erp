<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use App\Support\UiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FontSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        UiSettings::flush();
    }

    public function test_menu_sub_items_use_regular_weight(): void
    {
        $this->get(route('dashboard'))->assertOk();
        $html = file_get_contents(public_path('css/app-base.css')).file_get_contents(public_path('css/app-overrides.css'));
        $this->assertMatchesRegularExpression('/\.nav-submenu \.nav-link, \.nav-link \{[^}]*font-weight: 400;/s', $html);
        $this->assertMatchesRegularExpression('/\.nav-submenu \.nav-link\.active, \.nav-link\.active \{[^}]*font-weight: 400;/s', $html);
    }

    public function test_the_font_is_editable_and_applies_everywhere(): void
    {
        $this->get(route('dashboard'))->assertSee('--ui-font: -apple-system', false)->assertDontSee('fonts.googleapis.com', false);
        $this->get(route('settings.display'))->assertOk()->assertSee('Font for the whole interface')->assertSee('Only the administrator sees this setting');

        $this->put(route('settings.display.font'), ['font' => 'inter'])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertSee('--ui-font: "Inter"', false)->assertSee('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&amp;display=swap', false);

        $this->put(route('settings.display.font'), ['font' => 'tahoma'])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertSee('--ui-font: Tahoma', false)->assertDontSee('fonts.googleapis.com', false);

        $this->put(route('settings.display.font'), ['font' => 'custom', 'custom_font' => 'Calibri'])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertSee('--ui-font: "Calibri", ', false);
        $this->assertSame('Calibri', UiSettings::font()['name']);

        $this->put(route('settings.display.font'), ['font' => 'custom', 'custom_font' => ''])->assertSessionHasErrors('custom_font');
        $this->put(route('settings.display.font'), ['font' => 'custom', 'custom_font' => 'x"; } body { display:none'])->assertSessionHasErrors('custom_font');
        $this->put(route('settings.display.font'), ['font' => 'comic-sans'])->assertSessionHasErrors('font');
        $this->assertSame('Calibri', UiSettings::font()['name']);                                                  // refused saves changed nothing
    }

    public function test_a_tampered_stored_value_cannot_inject_css(): void
    {
        AppSetting::setValue('ui.font', 'custom:x"; } body { display:none', 'test');
        $this->assertSame('system', UiSettings::font()['key']);
        $this->get(route('dashboard'))->assertDontSee('display:none', false);
    }

    public function test_only_the_administrator_can_change_the_font(): void
    {
        config(['foundry.require_login' => true]);
        $role = Role::create(['code' => 'SETTINGS_ONLY', 'name' => 'Settings only', 'is_system' => false]);
        DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission' => 'settings.manage']);
        $manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $manager->roles()->attach($role->id);
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));

        // someone who may open Settings still does not see or get the font control
        $this->actingAs($manager)->get(route('settings.display'))->assertOk()->assertDontSee('Font for the whole interface')->assertSee('set by the administrator');
        $this->actingAs($manager)->put(route('settings.display.font'), ['font' => 'inter'])->assertForbidden();
        $this->assertSame('system', UiSettings::font()['key']);

        $this->actingAs($admin)->get(route('settings.display'))->assertOk()->assertSee('Font for the whole interface');
        $this->actingAs($admin)->put(route('settings.display.font'), ['font' => 'inter'])->assertSessionHasNoErrors();
        $this->assertSame('inter', UiSettings::font()['key']);
    }
}
