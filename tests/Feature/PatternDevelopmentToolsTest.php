<?php

namespace Tests\Feature;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Development\Support\DevelopmentRules;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternDevelopmentToolsTest extends TestCase
{
    use RefreshDatabase;

    /** A new product development, whose stages are Design discussion, Pattern build, Trial & sample, Approved. */
    private function development(): PatternDevelopment
    {
        $customer = Customer::create(['code' => 'ORB', 'name' => 'Orbis Auto', 'active' => true]);
        $this->post(route('developments.store'), ['type' => 'NEW_PRODUCT', 'title' => 'Valve body', 'customer_id' => $customer->id, 'pattern_by' => 'SUBCONTRACT', 'pattern_maker' => 'Erie Pattern Works']);

        return PatternDevelopment::sole();
    }

    private function addBox(PatternDevelopment $d): void
    {
        $this->post(route('developments.tools.store', $d), ['kind' => 'CORE_BOX', 'label' => 'Volute core box', 'route' => 'NEW', 'quoted_cost' => 420000])->assertSessionHasNoErrors();
    }

    public function test_a_new_development_starts_with_its_pattern_as_the_first_tool(): void
    {
        $tool = $this->development()->tools->sole();

        $this->assertSame('PATTERN', $tool->kind);
        $this->assertSame('NEW', $tool->route);
        $this->assertSame('SUBCONTRACT', $tool->made_by);
        $this->assertSame('Erie Pattern Works', $tool->maker);
        $this->assertSame('NOT_STARTED', $tool->status);
    }

    public function test_tools_are_added_updated_and_removed_and_the_actual_cost_follows(): void
    {
        $d = $this->development();
        $this->addBox($d);
        [$pattern, $box] = $d->fresh()->tools->all();

        $this->put(route('developments.tools.update', [$d, $pattern]), ['actual_cost' => 1720000, 'status' => 'BUILT'])->assertSessionHasNoErrors();
        $this->put(route('developments.tools.update', [$d, $box]), ['actual_cost' => 310000])->assertSessionHasNoErrors();
        $this->assertSame(2030000.0, $d->fresh()->actual_cost);
        $this->assertSame('BUILT', $pattern->fresh()->status);

        $this->delete(route('developments.tools.destroy', [$d, $box]))->assertSessionHasNoErrors();
        $this->assertSame(1720000.0, $d->fresh()->actual_cost);
        $this->get(route('developments.show', $d))->assertOk()->assertSee('Tooling · 1 tool');
    }

    public function test_a_plain_single_pattern_is_never_held_up_by_the_gate(): void
    {
        $d = $this->development();
        $this->post(route('developments.advance', $d));
        $this->post(route('developments.advance', $d))->assertSessionHas('success');

        $this->assertSame('Trial & sample', $d->fresh()->stageName());
    }

    public function test_warn_asks_to_confirm_and_logs_the_override(): void
    {
        $d = $this->development();
        $this->addBox($d);
        $this->post(route('developments.advance', $d));
        $this->assertSame('Pattern build', $d->fresh()->stageName());

        $this->post(route('developments.advance', $d))->assertSessionHas('gate_warn');
        $this->assertSame('Pattern build', $d->fresh()->stageName());

        $this->post(route('developments.advance', $d), ['override' => 1])->assertSessionHas('success');
        $this->assertSame('Trial & sample', $d->fresh()->stageName());
        $this->assertTrue($d->events()->where('text', 'like', 'Moved on with an override:%')->exists());
    }

    public function test_block_holds_it_and_off_lets_it_go(): void
    {
        $d = $this->development();
        $this->addBox($d);
        $this->post(route('developments.advance', $d));

        DevelopmentRules::set('tool_gate', 'BLOCK');
        $this->post(route('developments.advance', $d), ['override' => 1])->assertSessionHasErrors('gate');
        $this->assertSame('Pattern build', $d->fresh()->stageName());

        DevelopmentRules::set('tool_gate', 'OFF');
        $this->post(route('developments.advance', $d))->assertSessionHas('success');
        $this->assertSame('Trial & sample', $d->fresh()->stageName());
    }

    public function test_it_moves_on_when_every_tool_has_reached_the_stage(): void
    {
        $d = $this->development();
        $this->addBox($d);
        $this->post(route('developments.advance', $d));
        DevelopmentRules::set('tool_gate', 'BLOCK');
        foreach ($d->fresh()->tools as $tool) {
            $this->put(route('developments.tools.update', [$d, $tool]), ['status' => 'BUILT']);
        }

        $this->post(route('developments.advance', $d))->assertSessionHas('success');
        $this->assertSame('Trial & sample', $d->fresh()->stageName());
        $this->post(route('developments.advance', $d))->assertSessionHasErrors('gate');
    }

    public function test_sending_back_puts_only_the_ticked_tools_in_rework(): void
    {
        $d = $this->development();
        $this->addBox($d);
        [$pattern, $box] = $d->fresh()->tools->all();
        $this->post(route('developments.advance', $d));
        foreach ([$pattern, $box] as $tool) {
            $this->put(route('developments.tools.update', [$d, $tool]), ['status' => 'TRIAL_PASSED']);
        }
        $this->post(route('developments.advance', $d));

        $this->post(route('developments.back', $d), ['reason' => 'Sand inclusion at the core print', 'tools' => [$pattern->id]])->assertSessionHasNoErrors();

        $this->assertSame('Pattern build', $d->fresh()->stageName());
        $this->assertSame('REWORK', $pattern->fresh()->status);
        $this->assertSame('Sand inclusion at the core print', $pattern->fresh()->status_note);
        $this->assertSame('TRIAL_PASSED', $box->fresh()->status);
    }

    public function test_sending_back_a_single_tool_development_sends_that_tool_to_rework(): void
    {
        $d = $this->development();
        $this->post(route('developments.advance', $d));
        $this->post(route('developments.back', $d), ['reason' => 'Flange thin on first-off']);

        $this->assertSame('REWORK', $d->fresh()->tools->sole()->status);
    }

    public function test_the_cost_route_still_sets_the_patterns_actual_cost(): void
    {
        $d = $this->development();
        $this->put(route('developments.cost', $d), ['actual_cost' => 175000])->assertSessionHasNoErrors();

        $this->assertSame(175000.0, $d->fresh()->actual_cost);
        $this->assertSame(175000.0, $d->tools->sole()->fresh()->actual_cost);
    }

    public function test_the_page_shows_the_tooling_panel_and_stage_counts(): void
    {
        $d = $this->development();
        $this->addBox($d);
        $this->post(route('developments.advance', $d));

        $this->get(route('developments.show', $d))->assertOk()->assertSee('Tooling · 2 tools')->assertSee('Volute core box')
            ->assertSee('0 of 2 built')->assertSee('not reached built yet');
    }
}
