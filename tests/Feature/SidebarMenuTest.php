<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_has_no_add_new_items_and_a_width_handle(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $nav = substr($html, strpos($html, '<nav class="sidebar-nav"'), strpos($html, '</nav>') - strpos($html, '<nav class="sidebar-nav"'));
        foreach (['New production order', 'New sales order', 'New request (guided)', 'Record thermal test'] as $t) {
            $this->assertStringNotContainsString($t, $nav);
        }
        $this->assertStringContainsString('id="sidebar-resizer"', $html);
        $this->assertStringContainsString('erp.menuWidth', $html);
        // The index pages still carry the add buttons.
        $this->get(route('sales-orders.index'))->assertSee('New sales order');
    }

    public function test_selected_item_has_no_background(): void
    {
        $html = $this->get(route('dashboard'))->getContent();
        $this->assertMatchesRegularExpression('/\.nav-link\.active:hover\s*\{\s*background:\s*transparent !important/', $html);
    }

    public function test_signed_in_name_and_role_sit_at_the_bottom_of_the_menu(): void
    {
        config(['foundry.require_login' => true]);
        $user = User::factory()->create(['name' => 'Aisha Khan', 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'ADMIN')->first());
        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
        $aside = substr($html, strpos($html, '<aside'), strpos($html, '</aside>') - strpos($html, '<aside'));
        $this->assertStringContainsString('Aisha Khan', $aside);
        $this->assertStringContainsString($user->roles->first()->name, $aside);
        $this->assertStringContainsString('Sign out', $aside);
        $this->assertGreaterThan(strpos($aside, '</nav>'), strpos($aside, 'sidebar-profile'));
        $topbar = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));
        $this->assertStringNotContainsString('Sign out', $topbar);
    }
}
