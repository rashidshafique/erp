<?php

namespace Tests\Feature;

use App\Support\FloorRules;
use App\Support\OrderRules;
use App\Support\ProcessDepth;
use App\Support\ProcessSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessSetupTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> the answers of a small jobbing shop */
    private function smallShop(): array
    {
        return ['review' => 'WHEN_OFF', 'flow' => 'STRAIGHT', 'screens' => 'QUICK', 'lists' => 'NO', 'assembly' => 'AUTO', 'heats' => 'NO',
            'pours' => 'ONE_STEP', 'gate' => 'WARN', 'holds' => 'NOTE', 'lots' => 'POUR', 'complete' => 'POUR', 'ncr' => 'OVER_5', 'gauges' => 'OFF'];
    }

    public function test_the_questions_open_on_the_answers_todays_rules_give(): void
    {
        $this->assertSame('ALWAYS', ProcessSetup::currentAnswers()['review']);
        $this->get(route('dashboard'))->assertSee(route('settings.process-setup.edit'), false);
        $this->get(route('settings.process-setup.edit'))->assertOk()->assertSee('Do you review an order before making it?');
    }

    public function test_the_answers_are_shown_as_rules_before_anything_is_set(): void
    {
        $this->post(route('settings.process-setup.review'), $this->smallShop())->assertOk()
            ->assertSee('What your answers set')->assertSee(OrderRules::RULES['contract_review'][2]['AUTO']);

        $this->assertSame('REQUIRED', OrderRules::get('contract_review'));
        $this->post(route('settings.process-setup.review'), ['review' => 'NO'])->assertSessionHasErrors('flow');
    }

    public function test_setting_the_answers_fills_the_rules_which_stay_editable_one_by_one(): void
    {
        $this->put(route('settings.process-setup'), $this->smallShop())->assertRedirect(route('settings.process-depth.edit'));

        $this->assertSame('AUTO', OrderRules::get('contract_review'));
        $this->assertTrue(FloorRules::usesQuickScreens());
        $this->assertSame('CUSTOM', ProcessDepth::current());
        $this->assertTrue(ProcessSetup::answered());
        $this->get(route('dashboard'))->assertDontSee(route('settings.process-setup.edit'), false);

        $this->put(route('settings.process-depth'), ['contract_review' => 'SKIP'])->assertSessionHasNoErrors();
        $this->assertSame('SKIP', OrderRules::get('contract_review'));
    }
}
