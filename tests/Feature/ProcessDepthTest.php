<?php

namespace Tests\Feature;

use App\Domains\Maintenance\Support\CalibrationRules;
use App\Support\OrderRules;
use App\Support\PouringRules;
use App\Support\ProcessDepth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessDepthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_starts_on_full_depth(): void
    {
        $this->assertSame('FULL', ProcessDepth::current());
        $this->get(route('settings.process-depth.edit'))->assertOk()->assertSee('In use');
    }

    public function test_basic_depth_loosens_the_checks_and_full_restores_them(): void
    {
        $this->put(route('settings.process-depth'), ['preset' => 'BASIC'])->assertSessionHasNoErrors();

        $this->assertSame('BASIC', ProcessDepth::current());
        $this->assertSame('OFF', PouringRules::get('gate_other'));
        $this->assertSame('FREE', PouringRules::get('hold_first_iron'));
        $this->assertSame('OFF', CalibrationRules::get('overdue_use'));
        $this->assertSame('SKIP', OrderRules::get('contract_review'));
        $this->assertTrue(OrderRules::completesAtPour());

        $this->put(route('settings.process-depth'), ['preset' => 'FULL'])->assertSessionHasNoErrors();
        $this->assertSame('FULL', ProcessDepth::current());
        $this->assertFalse(OrderRules::completesAtPour());
    }

    public function test_changing_a_single_rule_makes_the_depth_custom(): void
    {
        $this->put(route('settings.process-depth'), ['preset' => 'BASIC']);
        PouringRules::set('containment', 'HOLD');

        $this->assertSame('CUSTOM', ProcessDepth::current());
        $this->put(route('settings.process-depth'), ['preset' => 'NOPE'])->assertSessionHasErrors('preset');
    }
}
