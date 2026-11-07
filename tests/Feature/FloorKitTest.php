<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The round 4 floor components page (design R4 Components) shows every part and is only for people who manage settings. */
class FloorKitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shows_each_component_in_its_states(): void
    {
        $this->get(route('settings.floor-kit'))->assertOk()
            ->assertSee('C8 Big next-step button')->assertSee('Charge #7 is 4 min late · due 10:31')
            ->assertSee('! More than were counted ready (93). Lower it to save.')
            ->assertSee('Locked: the pour gate for HT-313 is open.')
            ->assertSee('inputmode="none"', false)
            ->assertSee('class="fl-badge is-bad"', false)
            ->assertSee('○ Not ready')
            ->assertSee('Quality manager only')
            ->assertSee('In production');
    }

    public function test_only_people_who_manage_settings_open_it(): void
    {
        config(['foundry.require_login' => true]);
        $inspector = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $inspector->roles()->attach(Role::where('code', 'QUALITY_INSPECTOR')->value('id'));

        $this->actingAs($inspector)->get(route('settings.floor-kit'))->assertForbidden();
    }
}
