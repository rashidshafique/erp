<?php

namespace Tests\Feature;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Development\Support\DevelopmentRules;
use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\Customer;
use App\Models\Pattern;
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
        $this->get(route('developments.show', $d))->assertOk()->assertSeeHtml('<h2>Pattern</h2>');
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

    private function oldBox(): CoreBox
    {
        $core = CoreDefinition::create(['name' => 'Impeller core 160', 'core_type' => 'STANDARD', 'status' => 'ACTIVE']);

        return CoreBox::create(['core_definition_id' => $core->id, 'name' => 'Impeller core box 160', 'status' => 'ACTIVE', 'origin' => 'NEW', 'revision' => 'A', 'cavities' => 1]);
    }

    public function test_a_replacement_can_be_for_a_core_box_alone(): void
    {
        $old = $this->oldBox();
        $this->post(route('developments.store'), ['type' => 'REPLACEMENT', 'tool_pick' => 'box:'.$old->id, 'reason' => 'WORN', 'who_pays' => 'FOUNDRY'])->assertSessionHasNoErrors();

        $d = PatternDevelopment::sole();
        $tool = $d->tools->sole();
        $this->assertSame($old->id, $d->core_box_id);
        $this->assertSame('CORE_BOX', $tool->kind);
        $this->assertSame($old->id, $tool->replaces_core_box_id);
        $this->assertNull($tool->core_box_id);
        $this->assertStringContainsString('Replace '.$old->code, $d->title);
    }

    public function test_the_register_record_is_a_new_core_box_out_of_service_until_it_is_built_and_the_old_one_retires_at_the_end(): void
    {
        $old = $this->oldBox();
        $this->post(route('developments.store'), ['type' => 'REPLACEMENT', 'tool_pick' => 'box:'.$old->id, 'reason' => 'WORN', 'who_pays' => 'FOUNDRY']);
        $d = PatternDevelopment::sole();
        $tool = $d->tools->sole();

        $this->post(route('developments.tools.register', [$d, $tool]))->assertSessionHasNoErrors();
        $new = $tool->fresh()->coreBox;
        $this->assertSame('INACTIVE', $new->status);
        $this->assertSame('MODIFIED_BOX', $new->origin);
        $this->assertSame($old->id, $new->modified_from_core_box_id);
        $this->post(route('developments.tools.register', [$d, $tool]))->assertStatus(422);

        $this->post(route('developments.advance', $d));
        $this->put(route('developments.tools.update', [$d, $tool]), ['status' => 'BUILT']);
        $this->assertSame('ACTIVE', $new->fresh()->status);
        $this->assertSame('ACTIVE', $old->fresh()->status);

        $this->post(route('developments.advance', $d));
        $this->put(route('developments.tools.update', [$d, $tool]), ['status' => 'TRIAL_PASSED']);
        $this->post(route('developments.advance', $d));
        $this->assertSame('DONE', $d->fresh()->status);
        $this->assertSame('RETIRED', $old->fresh()->status);
        $this->assertNotNull($tool->fresh()->retired_at);
    }

    public function test_the_old_tool_stays_in_use_when_the_new_one_did_not_pass_trial(): void
    {
        $old = $this->oldBox();
        $this->post(route('developments.store'), ['type' => 'REPLACEMENT', 'tool_pick' => 'box:'.$old->id, 'reason' => 'WORN', 'who_pays' => 'FOUNDRY']);
        $d = PatternDevelopment::sole();
        DevelopmentRules::set('tool_gate', 'OFF');
        foreach (range(1, 3) as $i) {
            $this->post(route('developments.advance', $d));
        }

        $this->assertSame('DONE', $d->fresh()->status);
        $this->assertSame('ACTIVE', $old->fresh()->status);
        $this->assertTrue($d->events()->where('text', 'like', '%stays in use%')->exists());
    }

    public function test_a_pattern_tool_gets_a_register_record_owned_by_the_customer_who_pays(): void
    {
        $d = $this->development();
        $tool = $d->tools->sole();
        $this->put(route('developments.tools.update', [$d, $tool]), ['maker' => 'Erie Pattern Works']);
        $tool->update(['who_pays' => 'CUSTOMER', 'label' => 'Valve body match plate']);

        $this->post(route('developments.tools.register', [$d, $tool]))->assertSessionHasNoErrors();

        $pattern = Pattern::sole();
        $this->assertSame('Valve body match plate', $pattern->name);
        $this->assertSame('CUSTOMER', $pattern->owner_type);
        $this->assertSame($d->customer_id, $pattern->owner_customer_id);
        $this->assertSame($pattern->id, $tool->fresh()->pattern_id);
    }

    public function test_a_gauge_has_no_register_record(): void
    {
        $d = $this->development();
        $this->post(route('developments.tools.store', $d), ['kind' => 'GAUGE', 'label' => 'Checking gauge']);
        $gauge = $d->fresh()->tools->firstWhere('kind', 'GAUGE');

        $this->post(route('developments.tools.register', [$d, $gauge]))->assertStatus(422);
    }

    public function test_the_dialog_can_be_opened_from_a_core_box(): void
    {
        $box = $this->oldBox();

        $this->get(route('developments.create', ['from' => 'core_box:'.$box->id]))->assertOk()
            ->assertSeeHtml('name="type" value="REPLACEMENT" checked')->assertSee('Opened from core box '.$box->code)
            ->assertSeeHtml('value="box:'.$box->id.'"')->assertSee('Tool to replace or modify');
        $this->get(route('core-boxes.show', $box))->assertSee('Start replacement');
    }

    public function test_the_dialog_names_the_enquiry_and_closes_back_to_it(): void
    {
        $customer = Customer::create(['code' => 'ORB', 'name' => 'Orbis Auto', 'active' => true]);
        $enquiry = \App\Domains\Enquiry\Models\Enquiry::create(['customer_id' => $customer->id, 'title' => 'Second part', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()]);

        $this->get(route('developments.create', ['from' => 'enquiry:'.$enquiry->id]))->assertOk()
            ->assertSee($enquiry->code.' · New development', false)
            ->assertSee('window.location = '.\Illuminate\Support\Js::from(route('enquiries.show', $enquiry)), false);
        $this->get(route('developments.create'))->assertOk()->assertDontSee(' · New development', false);
    }

    public function test_opened_from_an_enquiry_the_dialog_offers_only_the_types_that_can_follow(): void
    {
        $customer = Customer::create(['code' => 'ORB', 'name' => 'Orbis Auto', 'active' => true]);
        $make = fn (string $source) => \App\Domains\Enquiry\Models\Enquiry::create(['customer_id' => $customer->id, 'title' => 'Part', 'source_type' => $source, 'status' => 'FEASIBILITY', 'received_on' => today()]);

        $drawing = $this->get(route('developments.create', ['from' => 'enquiry:'.$make('DRAWING')->id]))->assertOk();
        $drawing->assertSeeHtml('name="type" value="NEW_PRODUCT"')->assertSeeHtml('name="type" value="CUSTOMER_PATTERN"')
            ->assertDontSeeHtml('name="type" value="REPLACEMENT"')->assertDontSeeHtml('name="type" value="MODIFICATION"');

        $this->get(route('developments.create', ['from' => 'enquiry:'.$make('CHANGE')->id]))->assertOk()
            ->assertSeeHtml('name="type" value="MODIFICATION"')->assertDontSeeHtml('name="type" value="REPLACEMENT"');

        $this->get(route('developments.create'))->assertOk()->assertSeeHtml('name="type" value="REPLACEMENT"');
    }

    public function test_the_enquiry_offers_no_start_button_when_it_uses_an_existing_pattern(): void
    {
        $customer = Customer::create(['code' => 'ORB', 'name' => 'Orbis Auto', 'active' => true]);
        $pattern = Pattern::create(['code' => 'PT-0331', 'name' => 'Pump plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $make = fn (array $over) => \App\Domains\Enquiry\Models\Enquiry::create($over + ['customer_id' => $customer->id, 'title' => 'Part', 'source_type' => 'REPEAT', 'status' => 'FEASIBILITY', 'received_on' => today()]);

        $existing = $make(['pattern_route' => 'EXISTING', 'pattern_id' => $pattern->id]);
        $this->get(route('enquiries.show', $existing))->assertOk()->assertDontSee('Start pattern development');

        $new = $make(['pattern_route' => 'NEW']);
        $this->get(route('enquiries.show', $new))->assertOk()->assertSee('Start pattern development');
    }

    public function test_the_dialog_adds_tools_and_can_leave_out_the_pattern(): void
    {
        $customer = Customer::create(['code' => 'ORB', 'name' => 'Orbis Auto', 'active' => true]);
        $box = $this->coreBox();
        $this->post(route('developments.store'), ['type' => 'NEW_PRODUCT', 'title' => 'Valve body', 'customer_id' => $customer->id, 'skip_first_tool' => 1, 'tools' => [
            ['kind' => 'CORE_BOX', 'label' => $box->code.' — '.$box->name, 'route' => 'EXISTING'],
            ['kind' => 'GAUGE', 'label' => 'Bore gauge'],
        ]])->assertSessionHasNoErrors();

        $tools = PatternDevelopment::sole()->tools;
        $this->assertSame(['CORE_BOX', 'GAUGE'], $tools->pluck('kind')->sort()->values()->all());
        $this->assertSame($box->id, $tools->firstWhere('kind', 'CORE_BOX')->core_box_id);
    }

    public function test_a_planned_tool_links_to_a_record_of_its_own_kind_only(): void
    {
        $d = $this->development();
        $this->addBox($d);
        $box = $this->coreBox();
        $tool = $d->tools()->where('kind', 'CORE_BOX')->sole();
        $pattern = Pattern::create(['code' => 'PT-0331', 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);

        $this->post(route('developments.tools.link', [$d, $tool]), ['record' => 'pattern:'.$pattern->id])->assertStatus(422);
        $this->post(route('developments.tools.link', [$d, $tool]), ['record' => 'box:'.$box->id])->assertSessionHasNoErrors();
        $this->assertSame($box->id, $tool->fresh()->core_box_id);
    }

    public function test_saving_a_tool_keeps_its_rework_note_and_logs_only_real_cost_changes(): void
    {
        $d = $this->development();
        $tool = $d->tools()->sole();
        $tool->update(['status' => 'REWORK', 'status_note' => 'Sand inclusion', 'actual_cost' => 100]);

        $this->put(route('developments.tools.update', [$d, $tool]), ['status' => 'REWORK', 'actual_cost' => 100])->assertSessionHasNoErrors();

        $this->assertSame('Sand inclusion', $tool->fresh()->status_note);
        $this->assertFalse($d->events()->where('text', 'like', '%cost recorded%')->exists());
    }

    public function test_cancelling_needs_a_decision_on_each_started_tool_and_keeps_the_spend(): void
    {
        $d = $this->development();
        $this->addBox($d);
        $pattern = $d->tools()->where('kind', 'PATTERN')->sole();
        $pattern->update(['status' => 'BUILT', 'actual_cost' => 1720000]);

        $this->post(route('developments.cancel', $d), ['reason' => 'Part moved'])->assertSessionHasErrors("decisions.{$pattern->id}");
        $this->assertSame('OPEN', $d->fresh()->status);

        $this->post(route('developments.cancel', $d), ['reason' => 'Part moved', 'decisions' => [$pattern->id => 'HOLD']])->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $d->fresh()->status);
        $this->assertSame('Hold for customer', $pattern->fresh()->status_note);
        $this->assertSame(1720000.0, $pattern->fresh()->actual_cost);
        $this->assertSame('NOT_NEEDED', $d->tools()->where('kind', 'CORE_BOX')->sole()->status);
    }

    public function test_stop_and_scrap_retires_only_a_record_the_development_made_new(): void
    {
        $d = $this->development();
        $new = $d->tools()->sole();
        $made = Pattern::create(['code' => 'PT-0500', 'name' => 'New', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $new->update(['status' => 'BUILDING', 'pattern_id' => $made->id]);
        $this->addBox($d);
        $box = $this->coreBox();
        $used = $d->tools()->where('kind', 'CORE_BOX')->sole();
        $used->update(['status' => 'BUILDING', 'route' => 'MODIFY', 'core_box_id' => $box->id]);

        $this->post(route('developments.cancel', $d), ['decisions' => [$new->id => 'SCRAP', $used->id => 'SCRAP']])->assertSessionHasNoErrors();

        $this->assertSame('RETIRED', $made->fresh()->status);
        $this->assertSame('SCRAPPED', $made->fresh()->custody);
        $this->assertSame('ACTIVE', $box->fresh()->status);
    }

    public function test_the_list_shows_tools_finds_them_and_has_a_waiting_tab(): void
    {
        $d = $this->development();
        $this->addBox($d);
        $d->tools()->where('kind', 'CORE_BOX')->update(['status' => 'BUILDING']);

        $this->get(route('developments.index'))->assertOk()->assertSee('2 · 0 built');
        $this->get(route('developments.index', ['search' => 'Volute']))->assertSee($d->code);
        $this->get(route('developments.index', ['search' => 'Nothing like it']))->assertDontSee($d->code);
        $this->get(route('developments.index', ['tab' => 'waiting']))->assertSee($d->code);
        $this->get(route('developments.index', ['tab' => 'closed']))->assertDontSee($d->code);
    }

    public function test_one_pattern_depth_shows_a_plain_block_and_basic_sets_it(): void
    {
        $d = $this->development();
        \App\Support\ProcessDepth::apply('BASIC');

        $this->assertSame('OFF', DevelopmentRules::get('tool_gate'));
        $this->get(route('developments.show', $d))->assertOk()->assertSee('Actual pattern cost')->assertDontSee('+ Add tool')->assertDontSee('Create register record');
    }

    public function test_pattern_and_core_box_pages_list_their_developments(): void
    {
        $pattern = Pattern::create(['code' => 'PT-0331', 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        $box = $this->coreBox();
        $this->post(route('developments.store'), ['type' => 'REPLACEMENT', 'tool_pick' => 'pattern:'.$pattern->id, 'reason' => 'WORN', 'who_pays' => 'FOUNDRY']);
        $this->post(route('developments.store'), ['type' => 'REPLACEMENT', 'tool_pick' => 'box:'.$box->id, 'reason' => 'WORN', 'who_pays' => 'FOUNDRY']);

        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('Being replaced')->assertSee('Raise refurb request');
        $this->get(route('core-boxes.show', $box))->assertOk()->assertSee('Being replaced')->assertSee('Raise refurb request');
    }

    private function coreBox(): CoreBox
    {
        $core = CoreDefinition::create(['name' => 'Volute core', 'core_type' => 'STANDARD', 'status' => 'ACTIVE']);

        return CoreBox::create(['core_definition_id' => $core->id, 'name' => 'Volute box', 'revision' => 'A', 'cavities' => 1, 'status' => 'ACTIVE', 'origin' => 'NEW']);
    }
}
