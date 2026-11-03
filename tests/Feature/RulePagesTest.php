<?php

namespace Tests\Feature;

use App\Support\OrderRules;
use App\Support\ProcessRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RulePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_rule_page_opens_with_the_settings_column_and_its_entry_current(): void
    {
        foreach (ProcessRules::PAGES as $page => $info) {
            $this->get(route('settings.pages.'.$page))->assertOk()
                ->assertSee($info['title'])
                ->assertSee('class="settings-nav"', false)
                ->assertSee('← All settings');
        }
    }

    public function test_a_changed_rule_shows_its_default_and_reset_puts_it_back(): void
    {
        $rule = array_key_first(OrderRules::rules());
        $other = collect(array_keys(OrderRules::rules()[$rule][2] ?? []))->first(fn ($v) => $v !== OrderRules::defaultOf($rule));
        if ($other === null) {
            $this->markTestSkipped('first order rule is a number');
        }
        $this->get(route('settings.pages.orders', ['advanced' => 1]))->assertOk()->assertDontSee('>Reset</button>', false);

        OrderRules::set($rule, $other);
        $this->get(route('settings.pages.orders', ['advanced' => 1]))->assertOk()->assertSee('>Reset</button>', false);

        $this->post(route('settings.rules.reset'), ['rule' => $rule])->assertRedirect();
        $this->assertSame(OrderRules::defaultOf($rule), OrderRules::get($rule));
    }

    public function test_an_unknown_rule_page_is_not_found(): void
    {
        $this->get('/settings/rules/nothing')->assertNotFound();
    }
}
