<?php

namespace Tests\Feature;

use App\Models\MouldingWorkList;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkLists;
use Tests\TestCase;

class FloorAlertsTest extends TestCase
{
    use BuildsWorkLists;
    use RefreshDatabase;

    private function user(string $roleCode): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function draftList(): MouldingWorkList
    {
        $w = $this->world();
        $this->post(route('work-lists.store'), ['list_date' => today()->toDateString(), 'shift' => 'Morning']);
        $list = MouldingWorkList::firstOrFail();
        $this->post(route('work-lists.items.add', $list), $this->item($w))->assertSessionHasNoErrors();

        return $list;
    }

    public function test_issuing_a_list_tells_the_people_who_mould_but_not_the_issuer_or_others(): void
    {
        $list = $this->draftList();
        $planner = $this->user('PRODUCTION_MANAGER');
        $supervisor = $this->user('MOULDING_SUPERVISOR');
        $salesperson = $this->user('SALES');

        $this->actingAs($planner)->post(route('work-lists.issue', $list))->assertSessionHasNoErrors();

        $notice = $supervisor->notifications()->firstOrFail();
        $this->assertSame('list-issued', $notice->data['kind']);
        $this->assertStringContainsString($list->list_number, $notice->data['title']);
        $this->assertSame(route('work-lists.show', $list), $notice->data['url']);
        $this->assertCount(0, $planner->notifications);
        $this->assertCount(0, $salesperson->notifications);
    }

    public function test_a_one_person_foundry_is_not_told_about_its_own_issue(): void
    {
        $list = $this->draftList();
        $owner = $this->user('PRODUCTION_MANAGER');

        $this->actingAs($owner)->post(route('work-lists.issue', $list))->assertSessionHasNoErrors();

        $this->assertCount(0, $owner->notifications);
    }

    public function test_a_refused_issue_tells_nobody(): void
    {
        $this->world();
        $this->post(route('work-lists.store'), ['list_date' => today()->toDateString(), 'shift' => 'Morning']);
        $list = MouldingWorkList::firstOrFail();
        $supervisor = $this->user('MOULDING_SUPERVISOR');

        $this->actingAs($this->user('PRODUCTION_MANAGER'))->post(route('work-lists.issue', $list))->assertSessionHasErrors('item');

        $this->assertCount(0, $supervisor->notifications);
    }
}
