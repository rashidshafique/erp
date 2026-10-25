<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Support\Formats;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DisplayFormatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Formats::flush();
    }

    protected function tearDown(): void
    {
        Formats::flush();
        parent::tearDown();
    }

    public function test_defaults_keep_the_existing_look(): void
    {
        $d = Carbon::parse('2026-10-18 14:30');

        $this->assertSame('18 Oct 2026', $d->displayDate());
        $this->assertSame('18 Oct 2026 14:30', $d->displayDateTime());
        $this->assertSame('1,234.50', Formats::number(1234.5, 2));
    }

    public function test_saved_formats_change_dates_numbers_and_money(): void
    {
        $this->put(route('settings.display.formats'), [
            'date' => 'mdy/', 'time' => '12', 'number' => 'eu', 'currency' => 'EUR', 'currency_position' => 'before',
        ])->assertSessionHasNoErrors();

        $d = Carbon::parse('2026-10-18 14:30');
        $this->assertSame('10/18/2026 2:30 PM', $d->displayDateTime());
        $this->assertSame('10/18', $d->displayShortDate());
        $this->assertSame('1.234,50', Formats::number(1234.5, 2));
        $this->assertSame('EUR 1.234,50', Formats::money(1234.5));
        $this->assertSame('eu', AppSetting::getValue('format.number'));
    }

    public function test_typed_numbers_keep_the_sitewide_decimal_places_and_decimal_boxes_take_any_value(): void
    {
        $this->assertSame(4, Formats::decimals());
        $this->put(route('settings.display.formats'), [
            'date' => 'dMY', 'time' => '24', 'number' => 'us', 'currency_position' => 'after', 'decimals' => 2,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Formats::decimals());
        $this->get(route('settings.display'))->assertOk()->assertSee('const places = 2;', false);
        // A fixed decimal step makes the browser refuse whole numbers whenever the box's minimum is off the step (min 0.001, step 0.01).
        $views = collect(File::allFiles(resource_path('views')));
        foreach (glob(app_path('Domains/*/resources/views'), GLOB_ONLYDIR) as $dir) {
            $views = $views->merge(File::allFiles($dir));
        }
        $fixedSteps = $views->filter(fn ($file) => preg_match('/step="0\.\d+"/', $file->getContents()))->map(fn ($file) => $file->getRelativePathname())->values()->all();
        $this->assertSame([], $fixedSteps);
        $this->put(route('settings.display.formats'), ['date' => 'dMY', 'time' => '24', 'number' => 'us', 'currency_position' => 'after', 'decimals' => 9])
            ->assertSessionHasErrors('decimals');
    }

    public function test_unknown_choices_are_refused(): void
    {
        $this->put(route('settings.display.formats'), [
            'date' => 'nope', 'time' => '24', 'number' => 'us', 'currency_position' => 'after',
        ])->assertSessionHasErrors('date');
    }
}
