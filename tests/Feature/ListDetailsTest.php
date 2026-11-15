<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ListLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_details_button_on_a_plain_list_hides_and_shows_its_side_panel_and_goes_back_to_the_list(): void
    {
        $this->get(route('sales-orders.index'))->assertOk()->assertSee('Past requested delivery')->assertSee(route('lists.details'));
        $this->get(route('enquiries.index'))->assertOk()->assertSee('Last 90 days');
        $this->get(route('equipment-maintenance.index'))->assertOk()->assertSee('Open breakdowns');

        $this->from(route('sales-orders.index'))->post(route('lists.details'))->assertRedirect(route('sales-orders.index'));
        $this->assertFalse(session(ListLayout::DETAILS_KEY));

        $this->get(route('sales-orders.index'))->assertOk()->assertDontSee('Past requested delivery')->assertSee('aria-pressed="false"', false);
        $this->get(route('enquiries.index'))->assertOk()->assertDontSee('Last 90 days');
        $this->get(route('equipment-maintenance.index'))->assertOk()->assertDontSee('Open breakdowns');

        $this->post(route('lists.details'));
        $this->assertTrue(session(ListLayout::DETAILS_KEY));
    }

    public function test_any_signed_in_user_can_use_the_details_button(): void
    {
        config(['foundry.require_login' => true]);
        $this->actingAs(User::factory()->create(['is_active' => true]));

        $this->from(route('sales-orders.index'))->post(route('lists.details'))->assertRedirect(route('sales-orders.index'));
        $this->assertFalse(session(ListLayout::DETAILS_KEY));
    }
}
