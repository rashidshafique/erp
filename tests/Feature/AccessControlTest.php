<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleCode, array $attrs = []): User
    {
        $u = User::factory()->create($attrs + ['is_active' => true, 'must_change_password' => false]);
        $u->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $u;
    }

    public function test_everything_is_open_while_login_is_off(): void
    {
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_guests_are_sent_to_login_when_on(): void
    {
        config(['foundry.require_login' => true]);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
    }

    public function test_login_works_and_wrong_password_is_refused(): void
    {
        config(['foundry.require_login' => true]);
        $u = $this->user('VIEWER', ['email' => 'a@x.test', 'password' => 'Longpass123']);
        $this->post(route('login.attempt'), ['email' => 'a@x.test', 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->post(route('login.attempt'), ['email' => 'a@x.test', 'password' => 'Longpass123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->assertNotNull($u->fresh()->last_login_at);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        config(['foundry.require_login' => true]);
        $this->user('ADMIN', ['email' => 'off@x.test', 'password' => 'Longpass123', 'is_active' => false]);
        $this->post(route('login.attempt'), ['email' => 'off@x.test', 'password' => 'Longpass123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_permissions_gate_changes_but_not_reading(): void
    {
        config(['foundry.require_login' => true]);
        $viewer = $this->user('VIEWER');
        $this->actingAs($viewer)->get(route('customers.index'))->assertOk();
        $this->actingAs($viewer)->post(route('customers.store'), [])->assertForbidden();
        $this->actingAs($viewer)->get(route('audit.index'))->assertForbidden();   // reads need audit.view
        $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();

        $qm = $this->user('QUALITY_MANAGER');
        $this->actingAs($qm)->get(route('audit.index'))->assertOk();
        $this->actingAs($qm)->post(route('customers.store'), [])->assertForbidden();

        $sales = $this->user('SALES');
        $this->actingAs($sales)->post(route('customers.store'), [])->assertSessionHasErrors();   // allowed through to validation
    }

    public function test_admin_manages_users_and_last_admin_is_protected(): void
    {
        config(['foundry.require_login' => true]);
        $admin = $this->user('ADMIN');
        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Ali', 'email' => 'ali@x.test', 'password' => 'Longpass123', 'roles' => [Role::where('code', 'SALES')->value('id')]])
            ->assertRedirect(route('users.index'));
        $ali = User::where('email', 'ali@x.test')->firstOrFail();
        $this->assertTrue($ali->hasPermission('sales.manage'));
        $this->assertTrue($ali->must_change_password);

        $this->actingAs($admin)->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'is_active' => 1, 'roles' => []])
            ->assertSessionHasErrors('roles');
    }

    public function test_forced_password_change_and_role_matrix(): void
    {
        config(['foundry.require_login' => true]);
        $u = $this->user('SALES', ['password' => 'Longpass123', 'must_change_password' => true]);
        $this->actingAs($u)->get(route('customers.index'))->assertRedirect(route('account.password'));
        $this->actingAs($u)->put(route('account.password.update'), ['current_password' => 'Longpass123', 'password' => 'Another12345', 'password_confirmation' => 'Another12345'])->assertRedirect(route('dashboard'));
        $this->assertFalse($u->fresh()->must_change_password);

        $admin = $this->user('ADMIN');
        $sales = Role::where('code', 'SALES')->first();
        $this->actingAs($admin)->put(route('roles.update'), ['perm' => [$sales->id => ['stores.manage' => 1]]])->assertSessionHasNoErrors();
        $this->assertSame(['stores.manage'], \DB::table('role_permissions')->where('role_id', $sales->id)->pluck('permission')->all());
    }
}
