<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_opens_on_a_home_listing_every_area_and_the_developer_values(): void
    {
        $this->get('/settings')->assertOk()
            ->assertSee('Process rules')
            ->assertSee(route('settings.rules'), false)
            ->assertSee('Users')
            ->assertSee('Sign-in is off.')
            ->assertSee('Set by your developer')
            ->assertSee('Attachment limit');
    }
}
