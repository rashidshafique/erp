<?php

namespace Tests\Feature;

use App\Domains\Cameras\Models\Camera;
use App\Domains\Cameras\Models\Dvr;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CameraViewerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function dvr(array $over = []): Dvr
    {
        return Dvr::create($over + ['name' => 'Plant recorder', 'brand' => 'DAHUA', 'protocol' => 'http', 'host' => '192.168.1.108', 'port' => 80, 'username' => 'viewer', 'password' => 's3cret-pass', 'active' => true]);
    }

    private function camera(Dvr $dvr, int $channel = 1, array $over = []): Camera
    {
        return $dvr->cameras()->create($over + ['channel' => $channel, 'name' => 'Camera '.$channel, 'sort_order' => $channel, 'active' => true]);
    }

    /** A fresh set of faked recorder answers; Laravel keeps earlier ones and uses the first that matches. */
    private function recorderAnswers(array $stubs): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake($stubs);
        Http::preventStrayRequests();   // a request that matches no answer fails the test instead of reaching a real recorder on the network
    }

    private function userWith(array $permissions): User
    {
        $role = Role::create(['code' => 'CAM'.uniqid(), 'name' => 'Cameras']);
        foreach ($permissions as $p) {
            DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission' => $p]);
        }
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_the_menu_has_a_cameras_group_with_the_live_view_and_the_setup(): void
    {
        $html = $this->get(route('cameras.index'))->assertOk()->getContent();
        $menu = [];
        foreach (array_slice(preg_split('/<div class="nav-group/', $html), 1) as $group) {
            preg_match('/<span class="nav-lab">.*?<span>(.*?)<\/span><\/span>/s', $group, $title);
            preg_match_all('/<span>([^<]+)<\/span><\/a>/', explode("</div>\n            </div>", $group)[0], $items);
            $menu[html_entity_decode($title[1])] = array_map('html_entity_decode', $items[1]);
        }

        $this->assertSame(['Live view', 'Camera setup'], $menu['Cameras']);
    }

    public function test_the_recorder_is_set_up_with_its_password_kept_encrypted_and_never_shown(): void
    {
        $this->post(route('cameras.dvrs.store'), ['name' => 'Plant recorder', 'protocol' => 'http', 'host' => '192.168.1.108', 'port' => 80, 'username' => 'viewer', 'password' => 's3cret-pass'])
            ->assertSessionHasNoErrors();

        $dvr = Dvr::sole();
        $this->assertSame('s3cret-pass', $dvr->password);
        $this->assertNotSame('s3cret-pass', DB::table('dvrs')->value('password'), 'The password is stored encrypted.');
        $this->get(route('cameras.setup'))->assertOk()->assertSee('http://192.168.1.108:80')->assertDontSee('s3cret-pass');

        $this->put(route('cameras.dvrs.update', $dvr), ['name' => 'Plant DVR', 'protocol' => 'http', 'host' => '192.168.1.108', 'port' => 80, 'username' => 'viewer', 'password' => '', 'active' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['Plant DVR', 's3cret-pass'], [$dvr->fresh()->name, $dvr->fresh()->password], 'A blank password keeps the saved one.');
    }

    public function test_the_address_must_be_a_plain_address_and_the_login_is_required(): void
    {
        foreach (['http://192.168.1.108', '192.168.1.108/admin', '192.168.1.108:80', 'a b', '../etc'] as $bad) {
            $this->post(route('cameras.dvrs.store'), ['name' => 'X', 'protocol' => 'http', 'host' => $bad, 'port' => 80, 'username' => 'u', 'password' => 'p'])->assertSessionHasErrors('host');
        }
        $this->post(route('cameras.dvrs.store'), ['name' => 'X', 'protocol' => 'http', 'host' => 'dvr.plant.local', 'port' => 80, 'username' => 'u', 'password' => ''])->assertSessionHasErrors('password');
        $this->assertSame(0, Dvr::count());
    }

    public function test_channels_are_added_in_a_range_skipping_existing_ones_and_cameras_are_named_and_switched_off(): void
    {
        $dvr = $this->dvr();
        $this->camera($dvr, 2, ['name' => 'Furnace']);

        $this->post(route('cameras.channels.store', $dvr), ['from' => 1, 'to' => 4])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, '3 cameras added'));
        $this->assertSame([1, 2, 3, 4], $dvr->cameras()->pluck('channel')->all());
        $this->assertSame('Furnace', $dvr->cameras()->where('channel', 2)->value('name'), 'An existing channel keeps its name.');
        $this->post(route('cameras.channels.store', $dvr), ['from' => 4, 'to' => 2])->assertSessionHasErrors('to');

        $camera = $dvr->cameras()->where('channel', 3)->first();
        $this->put(route('cameras.update', $camera), ['name' => 'Moulding line', 'area' => 'Moulding shop', 'sort_order' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['Moulding line', 'Moulding shop', false], [$camera->fresh()->name, $camera->fresh()->area, $camera->fresh()->active], 'An unticked "Shown" switches the camera off.');
    }

    public function test_the_live_page_shows_only_cameras_that_are_on_and_never_the_recorder_password(): void
    {
        $dvr = $this->dvr();
        $on = $this->camera($dvr, 1, ['name' => 'Melting shop', 'area' => 'Melting']);
        $this->camera($dvr, 2, ['name' => 'Switched off', 'active' => false]);
        $off = $this->dvr(['name' => 'Old recorder', 'active' => false, 'host' => '10.0.0.9']);
        $this->camera($off, 1, ['name' => 'On a dead recorder']);

        $this->get(route('cameras.index'))->assertOk()->assertSee('Melting shop')->assertSee(route('cameras.snapshot', $on), false)
            ->assertDontSee('Switched off')->assertDontSee('On a dead recorder')->assertDontSee('s3cret-pass')->assertDontSee('192.168.1.108');
    }

    public function test_a_picture_is_read_from_the_recorder_by_the_app_and_handed_on_without_being_kept(): void
    {
        $this->recorderAnswers(['*192.168.1.108/*' => Http::response('JPEGBYTES', 200, ['Content-Type' => 'image/jpeg'])]);
        $camera = $this->camera($this->dvr(), 3);

        $this->get(route('cameras.snapshot', $camera))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertSee('JPEGBYTES');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/cgi-bin/snapshot.cgi') && str_contains($request->url(), 'channel=3'));
    }

    public function test_a_recorder_that_refuses_or_cannot_be_reached_gives_a_no_picture_image_saying_why(): void
    {
        $camera = $this->camera($this->dvr(), 1);

        $why = fn () => rawurldecode($this->get(route('cameras.snapshot', $camera))->assertStatus(502)->assertHeader('Content-Type', 'image/svg+xml')->headers->get('X-Camera-Error'));

        $this->recorderAnswers(['*' => Http::response('', 401)]);
        $this->assertSame('Plant recorder refused the user name or password.', $why());
        $this->get(route('cameras.snapshot', $camera))->assertSee('No picture from Camera 1');

        $this->recorderAnswers(['*' => fn () => throw new ConnectionException('timeout')]);
        $this->assertStringContainsString('could not be reached at 192.168.1.108:80', $why());

        $this->recorderAnswers(['*' => Http::response('<html>login</html>', 200, ['Content-Type' => 'text/html'])]);
        $this->assertStringContainsString('did not send a picture', $why());
    }

    public function test_the_connection_test_reports_what_the_recorder_answered(): void
    {
        $dvr = $this->dvr();
        $this->recorderAnswers(['*' => Http::response('JPEG', 200, ['Content-Type' => 'image/jpeg'])]);
        $this->post(route('cameras.dvrs.test', $dvr))->assertSessionHas('success', fn ($m) => str_contains($m, 'sent a picture'));

        $this->recorderAnswers(['*' => Http::response('', 403)]);
        $this->post(route('cameras.dvrs.test', $dvr))->assertSessionHas('warning', fn ($m) => str_contains($m, 'refused'));
    }

    public function test_switched_off_cameras_and_recorders_give_no_picture_at_all(): void
    {
        $this->recorderAnswers([]);
        $dvr = $this->dvr();
        $this->get(route('cameras.snapshot', $this->camera($dvr, 1, ['active' => false])))->assertNotFound();
        $dead = $this->dvr(['active' => false, 'host' => '10.0.0.9']);
        $this->get(route('cameras.snapshot', $this->camera($dead, 1)))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_viewing_needs_the_view_permission_and_setting_up_needs_the_manage_permission(): void
    {
        config(['foundry.require_login' => true]);
        $camera = $this->camera($this->dvr(), 1);
        Http::fake(['*' => Http::response('JPEG', 200, ['Content-Type' => 'image/jpeg'])]);
        $nobody = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $viewer = $this->userWith(['cameras.view']);
        $manager = $this->userWith(['cameras.view', 'cameras.manage']);

        $this->actingAs($nobody)->get(route('cameras.index'))->assertForbidden();
        $this->actingAs($nobody)->get(route('cameras.snapshot', $camera))->assertForbidden();
        $this->actingAs($viewer)->get(route('cameras.index'))->assertOk();
        $this->actingAs($viewer)->get(route('cameras.snapshot', $camera))->assertOk();
        $this->actingAs($viewer)->get(route('cameras.setup'))->assertForbidden();
        $this->actingAs($viewer)->post(route('cameras.dvrs.test', $camera->dvr))->assertForbidden();
        $this->actingAs($manager)->get(route('cameras.setup'))->assertOk();
    }
}
