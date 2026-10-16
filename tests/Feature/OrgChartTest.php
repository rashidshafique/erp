<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgChartTest extends TestCase
{
    use RefreshDatabase;

    private function add(array $o): Employee
    {
        $this->post(route('hr.orgchart.store'), $o + ['employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01'])->assertSessionHasNoErrors();

        return Employee::orderByDesc('code')->first();
    }

    public function test_empty_chart_offers_to_add_the_first_person(): void
    {
        $this->get(route('hr.orgchart'))->assertOk()->assertSee('Add the first person');
    }

    public function test_people_are_added_at_different_levels_and_drawn_as_a_tree(): void
    {
        $ceo = $this->add(['first_name' => 'Zed', 'last_name' => 'Lumin']);
        $head = $this->add(['first_name' => 'Saber', 'last_name' => 'Trot', 'manager_id' => $ceo->id]);
        $lead = $this->add(['first_name' => 'Ben', 'last_name' => 'Unclor', 'manager_id' => $head->id]);

        $this->assertNull($ceo->manager_id);
        $this->assertSame($head->id, $lead->manager_id);
        $this->assertSame('ONBOARDING', $lead->status);
        $this->assertDatabaseHas('employee_tasks', ['employee_id' => $lead->id]);           // joining checklist made

        $html = $this->get(route('hr.orgchart'))->assertOk()->assertSee('Top level')->assertSee('Level 2')->assertSee('Level 3')->getContent();
        $this->assertLessThan(strpos($html, 'id="n-'.$head->id.'"'), strpos($html, 'id="n-'.$ceo->id.'"'));
        $this->assertLessThan(strpos($html, 'id="n-'.$lead->id.'"'), strpos($html, 'id="n-'.$head->id.'"'));
        $this->assertStringContainsString('Add a person under', $html);
    }

    public function test_a_manager_who_does_not_exist_or_has_left_is_refused(): void
    {
        $a = $this->add(['first_name' => 'Aamir']);
        $a->update(['status' => 'EXITED']);
        $this->post(route('hr.orgchart.store'), ['first_name' => 'Bilal', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01', 'manager_id' => $a->id])->assertSessionHasErrors('manager_id');
        $this->post(route('hr.orgchart.store'), ['first_name' => '', 'employment_type' => 'PERMANENT', 'joined_on' => '2026-09-01'])->assertSessionHasErrors('first_name');
    }

    public function test_a_reporting_loop_in_the_data_cannot_hang_the_chart(): void
    {
        $a = $this->add(['first_name' => 'Aamir']);
        $b = $this->add(['first_name' => 'Bilal', 'manager_id' => $a->id]);
        $a->update(['manager_id' => $b->id]);
        $this->get(route('hr.orgchart'))->assertOk();
    }
}
