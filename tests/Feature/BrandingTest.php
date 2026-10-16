<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Support\UiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private function save(array $over = [], array $files = [])
    {
        return $this->put(route('settings.branding.update'), $over + ['accent' => '#24945a', 'brand_name' => 'Foundry ERP', 'logo_position' => 'TOP_LEFT', 'logo_height' => 40] + $files);
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    public function test_accent_colour_is_editable_and_drives_the_whole_theme(): void
    {
        $this->get(route('dashboard'))->assertSee('--accent: #24945a !important', false);
        $this->save(['accent' => '#1F6FEB'])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertSee('--accent: #1f6feb !important', false)->assertDontSee('#24945a', false);
        $this->get(route('settings.display'))->assertOk()->assertSee('Colour and logo');
        $this->save(['accent' => '#fef08a'])->assertSessionHasErrors('accent');            // too light for white text
        $this->save(['accent' => 'green'])->assertSessionHasErrors('accent');
        $this->save(['accent' => '#1f6feb', 'reset_accent' => 1]);
        $this->assertSame('#24945a', UiSettings::theme()['accent']);
    }

    public function test_derived_shades_and_contrast(): void
    {
        $t = UiSettings::theme();
        $this->assertNotSame($t['accent'], $t['hover']);
        $this->assertGreaterThan(3.0, UiSettings::contrast('#24945a'));
        $this->assertLessThan(3.0, UiSettings::contrast('#ffff00'));
    }

    public function test_logo_upload_position_and_removal(): void
    {
        Storage::fake('local');
        $this->save(['logo' => $this->png(), 'logo_position' => 'BOTTOM_LEFT', 'logo_height' => 55])->assertSessionHasNoErrors();
        $brand = UiSettings::brand();
        $this->assertNotNull($brand['logo']);
        $this->assertSame('BOTTOM_LEFT', $brand['position']);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('height:55px', $html);
        $this->assertGreaterThan(strpos($html, 'Production & shop-floor control') - 900, strpos($html, '/branding/logo'));   // near the footer, not the header

        $this->get(route('branding.logo'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'image/png');

        $this->save(['logo_position' => 'TOP_LEFT']);
        $this->assertSame('TOP_LEFT', UiSettings::brand()['position']);

        $this->save(['remove_logo' => 1]);
        $this->assertNull(UiSettings::brand()['logo']);
        $this->get(route('branding.logo'))->assertNotFound();
    }

    public function test_unsafe_or_fake_logos_are_refused(): void
    {
        Storage::fake('local');
        $evil = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->save(['logo' => $evil])->assertSessionHasErrors('logo');
        $fake = UploadedFile::fake()->createWithContent('logo.png', 'this is not a png');
        $this->save(['logo' => $fake])->assertSessionHasErrors('logo');
        $this->assertNull(UiSettings::brand()['logo']);
        $good = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>');
        $this->save(['logo' => $good])->assertSessionHasNoErrors();
        $this->assertNotNull(UiSettings::brand()['logo']);
    }

    public function test_the_login_page_wears_the_brand_for_guests(): void
    {
        Storage::fake('local');
        $this->save(['logo' => $this->png(), 'brand_name' => 'Karachi Castings']);
        config(['foundry.require_login' => true]);
        $this->get(route('login'))->assertOk()->assertSee('Karachi Castings')->assertSee('/branding/logo');
        $this->get(route('branding.logo'))->assertOk();   // not behind the login
    }

    public function test_no_screen_hard_codes_a_blue_button_colour(): void
    {
        $banned = ['#243b53', '#173b5e', '#24527a', '#176b50', '#2563eb', '#1d4ed8', '#0d6efd'];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $f) {
            if (! str_ends_with($f, '.blade.php')) {
                continue;
            }
            $body = strtolower(file_get_contents($f));
            foreach ($banned as $c) {
                $this->assertStringNotContainsString($c, $body, "$f still uses $c; use var(--accent)");
            }
        }
    }
}
