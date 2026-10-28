<?php

namespace Tests\Feature;

use App\Support\FlaskRules;
use App\Support\PatternOwnershipRules;
use App\Support\SettingsNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_sit_at_the_bottom_of_the_menu_and_not_in_its_groups(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $navEnd = strpos($html, '</nav>');
        $settings = strpos($html, '<span>Settings</span>');
        $this->assertNotFalse($settings);
        $this->assertGreaterThan($navEnd, $settings, 'Settings comes after the main menu');
        $this->assertStringNotContainsString('<span>Administration</span>', $html);
        $this->assertStringNotContainsString('class="settings-nav"', $html, 'the settings column is only on settings pages');
    }

    public function test_menu_button_controls_the_sidebar_and_has_a_backdrop_for_small_screens(): void
    {
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('id="menu-backdrop"', false)
            ->assertSee('id="menu-toggle" aria-controls="main-sidebar"', false);
    }

    public function test_every_settings_page_shows_the_settings_column_with_its_entry_current(): void
    {
        foreach (SettingsNav::sections() as $links) {
            foreach ($links as $label => [$routeName]) {
                $html = $this->get(route($routeName))->assertOk()->assertSee('class="settings-nav"', false)->getContent();
                $this->assertMatchesRegularExpression('#aria-current="page"\s*><span>'.preg_quote(e($label), '#').'</span>#', $html, "{$label} is the current entry");
            }
        }
    }

    public function test_flask_and_pattern_ownership_rules_are_edited_under_settings(): void
    {
        $this->get(route('flasks.index'))->assertOk()->assertSee(route('settings.flasks.edit'), false)->assertDontSee('Save rules');
        $this->get(route('pattern-ownership.index'))->assertOk()->assertSee(route('settings.pattern-ownership.edit'), false)->assertDontSee('Save rules');

        $this->get(route('settings.flasks.edit'))->assertOk()->assertSee('Too few free flasks when assembling moulds');
        $this->put(route('settings.flasks'), ['shortage' => 'WARN', 'pairing' => 'ANY', 'release' => 'SHAKEOUT', 'run_claims' => 'ASSEMBLED'])->assertSessionHasNoErrors();
        $this->assertSame('WARN', FlaskRules::get('shortage'));

        $this->get(route('settings.pattern-ownership.edit'))->assertOk()->assertSee('Storage charge per pattern per month');
        $this->assertSame('BLOCK', PatternOwnershipRules::get('other_customer'));
    }

    public function test_the_menu_is_one_level_of_section_labels_with_a_hidden_items_dialog(): void
    {
        $this->get(route('dashboard'))->assertSee('<body class="menu-labels"', false)->assertSee('id="menu-toggle"', false)->assertSee('id="hidden-items-open"', false);
    }
}
