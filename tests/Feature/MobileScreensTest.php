<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class MobileScreensTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    private function tech(string $role): User
    {
        $u = User::factory()->create(['is_active' => true, 'must_change_password' => false, 'password' => 'Longpass123']);
        $u->roles()->attach(Role::where('code', $role)->value('id'));

        return $u;
    }

    public function test_the_phone_login_is_open_and_sends_a_signed_in_tech_to_the_phone_home(): void
    {
        config(['foundry.require_login' => true]);
        $this->get(route('m.login'))->assertOk()->assertSee('Sign in')->assertSee('/manifest.webmanifest', false)->assertSee('Install on this phone');
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('icons/icon-512.png'));
        $u = $this->tech('QA_TEST_TECH');
        $this->post(route('login.attempt'), ['email' => $u->email, 'password' => 'Longpass123', 'mobile' => 1])->assertRedirect(route('m.home'));
    }

    public function test_tests_and_defects_are_given_separately(): void
    {
        config(['foundry.require_login' => true]);
        $tests = $this->tech('QA_TEST_TECH');
        $this->actingAs($tests)->get(route('m.home'))->assertOk()->assertSee('Thermal analysis')->assertDontSee('Log defect');
        $this->get(route('m.tests.pick', 'thermal'))->assertOk();
        $this->get(route('m.defects.pick'))->assertForbidden();

        $defects = $this->tech('QA_DEFECT_TECH');
        $this->actingAs($defects)->get(route('m.home'))->assertOk()->assertSee('Log defect')->assertDontSee('Thermal analysis');
        $this->get(route('m.tests.pick', 'thermal'))->assertForbidden();
        $this->get(route('m.defects.pick'))->assertOk();
    }

    public function test_a_role_with_no_phone_permission_has_no_home(): void
    {
        config(['foundry.require_login' => true]);
        $this->actingAs($this->tech('VIEWER'))->get(route('m.home'))->assertForbidden();
    }

    public function test_the_lot_list_is_newest_first_and_adjustable(): void
    {
        $old = $this->castingLot();
        $old->forceFill(['created_at' => now()->subDays(5)])->save();
        $new = $this->castingLot();
        $new->forceFill(['created_at' => now()])->save();

        $this->get(route('m.defects.pick', ['within' => 'all']))->assertSeeInOrder([$new->label, $old->label]);
        $this->get(route('m.defects.pick', ['within' => '1']))->assertSee($new->label)->assertDontSee($old->label);
        $this->get(route('m.defects.pick', ['size' => 10]))->assertOk();
    }

    public function test_a_thermal_test_is_always_linked_to_a_heat(): void
    {
        $this->get(route('m.tests.pick', 'thermal'))->assertOk()->assertDontSee('>Lots<', false);
        $this->get(route('m.tests.pick', 'inspection'))->assertOk()->assertSee('Heats')->assertSee('Lots');
        $this->get(route('m.tests.pick', 'nope'))->assertNotFound();
    }

    public function test_a_record_sent_twice_from_the_phone_is_saved_once(): void
    {
        $lot = $this->castingLot();
        $url = route('lots.inspections.store', $lot);
        $data = ['inspection_type' => 'FINAL', 'sample_size' => 1];

        $this->withHeaders(['X-Queue-Id' => 'abc-1', 'Accept' => 'application/json'])->post($url, $data)->assertRedirect();
        $this->withHeaders(['X-Queue-Id' => 'abc-1', 'Accept' => 'application/json'])->post($url, $data)->assertOk()->assertJson(['duplicate' => true]);

        $this->assertSame(1, $lot->inspections()->count());
    }

    public function test_a_refused_record_can_be_sent_again_once_fixed(): void
    {
        $lot = $this->castingLot();
        $url = route('lots.inspections.store', $lot);

        $this->withHeaders(['X-Queue-Id' => 'abc-2', 'Accept' => 'application/json'])->post($url, [])->assertStatus(422);
        $this->withHeaders(['X-Queue-Id' => 'abc-2', 'Accept' => 'application/json'])->post($url, ['inspection_type' => 'FINAL', 'sample_size' => 1])->assertRedirect();
        $this->assertSame(1, $lot->inspections()->count());
    }

    public function test_a_signed_out_phone_gets_401_not_a_redirect_and_a_token_when_signed_in(): void
    {
        config(['foundry.require_login' => true]);
        $this->getJson(route('m.token'))->assertUnauthorized();
        $this->actingAs($this->tech('QA_TEST_TECH'))->getJson(route('m.token'))->assertOk()->assertJsonStructure(['token']);
    }
}
