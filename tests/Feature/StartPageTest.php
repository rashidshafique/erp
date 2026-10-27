<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartPageTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $roleCode, array $extraRoles = []): \Illuminate\Testing\TestResponse
    {
        config(['foundry.require_login' => true]);
        $user = User::factory()->create(['email' => 'u@x.test', 'password' => 'Longpass123', 'is_active' => true, 'must_change_password' => false]);
        foreach ([$roleCode, ...$extraRoles] as $code) {
            $user->roles()->attach(Role::where('code', $code)->value('id'));
        }

        return $this->post(route('login.attempt'), ['email' => 'u@x.test', 'password' => 'Longpass123']);
    }

    public function test_each_shop_role_lands_on_its_own_page_and_can_open_it(): void
    {
        foreach ([
            'MELT_SUPERVISOR' => 'heats.index', 'MOULDING_SUPERVISOR' => 'quick.floor.index', 'QUALITY_INSPECTOR' => 'lots.queue',
            'SALES' => 'sales-orders.index', 'STORES_DISPATCH' => 'stock-movements.index',
        ] as $role => $route) {
            $this->post(route('logout'));
            User::query()->delete();
            $this->signIn($role)->assertRedirect(route($route));
            $this->get(route($route))->assertOk();
        }
    }

    public function test_admin_viewer_and_unmatched_roles_land_on_the_dashboard(): void
    {
        $this->signIn('ADMIN')->assertRedirect(route('dashboard'));
        $this->post(route('logout'));
        User::query()->delete();
        $this->signIn('VIEWER')->assertRedirect(route('dashboard'));
    }

    public function test_melting_comes_before_moulding_for_someone_with_both(): void
    {
        $this->signIn('MOULDING_SUPERVISOR', ['MELT_SUPERVISOR'])->assertRedirect(route('heats.index'));
    }

    public function test_on_the_quick_floor_screens_the_floor_lands_on_the_today_board(): void
    {
        \App\Support\FloorRules::set('floor_screens', 'QUICK');

        $this->signIn('MOULDING_SUPERVISOR')->assertRedirect(route('quick.today'));
        $this->get(route('quick.today'))->assertOk();
    }

    public function test_moulding_falls_back_to_runs_when_quick_flow_is_off(): void
    {
        config(['modules.licensed' => ['maintenance']]);
        $this->signIn('MOULDING_SUPERVISOR')->assertRedirect(route('moulding-runs.index'));
    }

    public function test_a_link_followed_before_signing_in_still_wins(): void
    {
        config(['foundry.require_login' => true]);
        $this->get(route('customers.index'))->assertRedirect(route('login'));
        $this->signIn('MELT_SUPERVISOR')->assertRedirect(route('customers.index'));
    }
}
