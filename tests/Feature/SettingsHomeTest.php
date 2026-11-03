<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_home_shows_the_areas_with_pages_and_the_developer_values_and_no_settings_column(): void
    {
        $this->get('/settings')->assertOk()
            ->assertSee('Company')
            ->assertSee('Production')
            ->assertSee('Needs')
            ->assertSee(route('settings.rules'), false)
            ->assertSee('Sign-in is off.')
            ->assertSee('Set by your developer')
            ->assertSee('Attachment limit')
            ->assertDontSee('class="settings-nav"', false);
    }
}
