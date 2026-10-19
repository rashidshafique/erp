<?php

namespace Tests\Feature;

use App\Domains\Core\Contracts\ModuleAccess;
use App\Domains\Core\ModuleRegistry;
use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Core\OpenModuleAccess;
use App\Domains\Licensing\SettingsModuleAccess;
use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_modules_page_lists_every_module_as_on_by_default(): void
    {
        $html = $this->get(route('settings.modules.edit'))->assertOk()->assertSee('Gauges &amp; calibration', false)->assertSee('Tooling maintenance &amp; life', false)->getContent();

        $this->assertSame(2, substr_count($html, 'name="modules[]"'));
        $this->assertSame(2, substr_count($html, ' checked'));
    }

    public function test_unticked_modules_are_saved_as_off_and_ticked_ones_stay_on(): void
    {
        $this->put(route('settings.modules'), ['modules' => ['maintenance']])->assertSessionHasNoErrors();

        $registry = new ModuleRegistry(new SettingsModuleAccess);
        $this->assertTrue($registry->isEnabled('maintenance'));
        $this->assertFalse($registry->isEnabled('tooling'));

        $html = $this->get(route('settings.modules.edit'))->getContent();
        $this->assertSame(1, substr_count($html, ' checked'));
    }

    public function test_saving_with_every_box_unticked_switches_every_module_off_and_back_on_again(): void
    {
        $this->put(route('settings.modules'))->assertSessionHasNoErrors();
        $this->assertSame(['maintenance', 'tooling'], (new SettingsModuleAccess)->disabledModules());

        $this->put(route('settings.modules'), ['modules' => ['maintenance', 'tooling']]);
        $this->assertSame([], (new SettingsModuleAccess)->disabledModules());
    }

    public function test_the_settings_column_links_to_modules(): void
    {
        $this->get(route('settings.modules.edit'))->assertOk()->assertSee('aria-current="page"', false)->assertSee('<span>Modules</span>', false);
    }

    public function test_a_module_switched_off_adds_no_routes_menu_entries_or_attachables_when_it_boots(): void
    {
        $dir = storage_path('framework/testing/module-probe-'.uniqid());
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/routes.php', "<?php\nIlluminate\\Support\\Facades\\Route::get('/probe', fn () => 'probe')->name('probe.index');\n");

        $boot = function (string $name) use ($dir): ModuleRegistry {
            $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry(new SettingsModuleAccess));
            $provider = new class($this->app, $name, $dir) extends ModuleServiceProvider
            {
                public function __construct($app, private string $name, private string $dir)
                {
                    parent::__construct($app);
                }

                protected function moduleName(): string
                {
                    return $this->name;
                }

                protected function modulePath(): string
                {
                    return $this->dir;
                }

                protected function navItems(): array
                {
                    return [['group' => 'equipment', 'label' => 'Probe', 'route' => 'probe.index', 'icon' => 'gauge', 'active' => ['probe.*']]];
                }
            };
            $provider->boot();

            return $this->app->make(ModuleRegistry::class);
        };

        AppSetting::setValue(SettingsModuleAccess::DISABLED_SETTING, json_encode(['probe-off']));
        $off = $boot('probe-off');
        $this->assertSame([], $off->navItems('equipment'));
        $this->assertArrayHasKey('probe-off', $off->modules());
        $this->assertFalse($this->app['router']->has('probe.index'));

        $on = $boot('probe-on');
        $this->assertCount(1, $on->navItems('equipment'));
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->assertTrue($this->app['router']->has('probe.index'));

        unlink($dir.'/routes.php');
        rmdir($dir);
    }

    public function test_a_module_that_is_not_licensed_cannot_be_switched_on(): void
    {
        config(['modules.licensed' => ['maintenance']]);

        $access = new SettingsModuleAccess;
        $this->assertTrue($access->isEnabled('maintenance'));
        $this->assertFalse($access->isLicensed('tooling'));
        $this->assertFalse($access->isEnabled('tooling'));

        $html = $this->get(route('settings.modules.edit'))->getContent();
        $this->assertStringContainsString('Not licensed for this installation.', $html);

        $this->put(route('settings.modules'), ['modules' => ['maintenance', 'tooling']]);
        $this->assertFalse((new SettingsModuleAccess)->isEnabled('tooling'));
        config(['modules.licensed' => null]);
        $this->assertTrue((new SettingsModuleAccess)->isEnabled('tooling'), 'a module left on while unlicensed is on once licensed');
    }

    public function test_without_the_licensing_unit_every_module_is_on(): void
    {
        $registry = new ModuleRegistry(new OpenModuleAccess);

        $this->assertTrue($registry->isEnabled('maintenance'));
        $this->assertTrue($registry->isEnabled('anything'));
    }

    public function test_nothing_outside_the_licensing_folder_depends_on_it(): void
    {
        $files = array_merge(
            glob(app_path('*/*.php')) ?: [],
            glob(app_path('*/*/*.php')) ?: [],
            glob(app_path('*/*/*/*.php')) ?: [],
            glob(app_path('*/*/*/*/*.php')) ?: [],
            glob(resource_path('views/*/*.blade.php')) ?: [],
            glob(base_path('routes/*.php')) ?: [],
        );
        $offenders = [];
        foreach ($files as $file) {
            if (str_contains($file, '/Domains/Licensing/') || str_contains($file, 'ModuleSettingsTest')) {
                continue;
            }
            if (str_contains((string) file_get_contents($file), 'Domains\\Licensing')) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders);
        $this->assertInstanceOf(ModuleAccess::class, $this->app->make(ModuleAccess::class));
    }
}
