<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use App\Support\FloorRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    /** @return int the app_settings queries a GET of the route makes */
    private function settingQueries(string $route): int
    {
        AppSetting::forgetLoaded();
        $count = 0;
        DB::listen(function ($q) use (&$count): void {
            if (str_contains($q->sql, 'app_settings')) {
                $count++;
            }
        });
        $this->get(route($route))->assertOk();

        return $count;
    }

    public function test_a_page_reads_the_settings_table_once(): void
    {
        FloorRules::set('floor_screens', 'FULL');

        $this->assertSame(1, $this->settingQueries('heats.index'));
    }

    public function test_a_saved_setting_is_read_back_at_once(): void
    {
        $this->assertSame('USED', AppSetting::getValue('floor.work_lists', 'USED'));

        AppSetting::setValue('floor.work_lists', 'OFF');
        $this->assertSame('OFF', AppSetting::getValue('floor.work_lists'));

        AppSetting::where('key', 'floor.work_lists')->first()->update(['value' => 'USED']);
        $this->assertSame('USED', AppSetting::getValue('floor.work_lists'));

        AppSetting::where('key', 'floor.work_lists')->first()->delete();
        $this->assertSame('fallback', AppSetting::getValue('floor.work_lists', 'fallback'));
    }

    public function test_settings_loaded_for_an_administrator_give_no_access_to_a_user_without_the_permission(): void
    {
        config(['foundry.require_login' => true]);
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $clerk = User::factory()->create(['is_active' => true, 'must_change_password' => false]);

        $this->actingAs($admin)->get(route('settings.display'))->assertOk();
        $this->actingAs($clerk)->get(route('settings.display'))->assertForbidden();
        $this->actingAs($clerk)->put(route('settings.display.font'), ['font' => 'inter'])->assertForbidden();
    }
}
