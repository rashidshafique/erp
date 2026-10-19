<?php

namespace Tests\Feature;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Licensing\SettingsModuleAccess;
use App\Models\AppSetting;
use App\Services\Codes\CodeGenerator;
use App\Support\GlobalSearch;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleHooksTest extends TestCase
{
    use RefreshDatabase;

    private function boot(string $name): ModuleRegistry
    {
        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry(new SettingsModuleAccess));
        $provider = new class($this->app, $name) extends ModuleServiceProvider
        {
            public function __construct($app, private string $name)
            {
                parent::__construct($app);
            }

            protected function moduleName(): string
            {
                return $this->name;
            }

            protected function modulePath(): string
            {
                return storage_path('framework/testing/no-such-module');
            }

            protected function navGroups(): array
            {
                return [['key' => $this->name, 'label' => 'People', 'icon' => 'users', 'items' => [
                    ['label' => 'Everyone', 'route' => 'dashboard', 'icon' => 'users', 'active' => ['dashboard']],
                    ['label' => 'Secret', 'route' => 'dashboard', 'icon' => 'wallet', 'active' => ['dashboard'], 'visible' => fn () => false],
                ]]];
            }

            protected function permissionCatalog(): array
            {
                return ['probe.manage' => 'Probe things'];
            }

            protected function openRoutes(): array
            {
                return ['probe-open.*'];
            }

            protected function roleDefaults(): array
            {
                return ['PROBE_ROLE' => ['name' => 'Probe role', 'permissions' => ['probe.manage']]];
            }

            protected function approvalActions(): array
            {
                return ['probe.approve' => 'Approve a probe'];
            }

            protected function schedule(Schedule $schedule): void
            {
                $schedule->command('probe:run')->dailyAt('03:00');
            }

            protected function searchTypes(): array
            {
                return ['probe' => ['label' => 'Probe', 'table' => 'probes', 'code' => 'code', 'name' => null, 'route' => null, 'permission' => null, 'words' => ['prb']]];
            }

            protected function codeKinds(): array
            {
                return ['probe' => ['probes', 'PRB', 5, 'Probe']];
            }
        };
        $provider->boot();

        return $this->app->make(ModuleRegistry::class);
    }

    public function test_an_enabled_module_contributes_through_every_hook(): void
    {
        $registry = $this->boot('probe-on');

        $this->assertSame(['probe-on'], array_column($registry->navGroups(), 'key'));
        $this->assertSame('Probe things', config('permissions.catalog')['probe.manage']);
        $this->assertContains('probe-open.*', config('permissions.open_routes'));
        $this->assertSame('Probe role', config('permissions.roles.PROBE_ROLE.name'));
        $this->assertSame('Approve a probe', config('approvals.actions')['probe.approve']);
        $this->assertArrayHasKey('probe', GlobalSearch::types());
        $this->assertSame('Probe', CodeGenerator::kinds()['probe'][3]);
        $this->assertTrue(collect($this->app->make(Schedule::class)->events())->contains(fn ($e) => str_contains($e->command, 'probe:run')));
    }

    public function test_a_disabled_module_adds_nothing_except_its_role_defaults(): void
    {
        AppSetting::setValue(SettingsModuleAccess::DISABLED_SETTING, json_encode(['probe-off']));
        $registry = $this->boot('probe-off');

        $this->assertSame([], $registry->navGroups());
        $this->assertArrayNotHasKey('probe.manage', config('permissions.catalog'));
        $this->assertNotContains('probe-open.*', config('permissions.open_routes'));
        $this->assertArrayNotHasKey('probe.approve', config('approvals.actions'));
        $this->assertArrayNotHasKey('probe', GlobalSearch::types());
        $this->assertArrayNotHasKey('probe', CodeGenerator::kinds());
        $this->assertSame('Probe role', config('permissions.roles.PROBE_ROLE.name'), 'roles are seed data and stay');
    }

    public function test_a_nav_group_draws_its_visible_items_only(): void
    {
        $this->boot('probe-on');

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('<span>People</span>', $html);
        $this->assertStringContainsString('<span>Everyone</span>', $html);
        $this->assertStringNotContainsString('<span>Secret</span>', $html);
    }

    public function test_the_gauge_code_kind_now_comes_from_the_maintenance_module(): void
    {
        $this->assertSame('GAU', CodeGenerator::kinds()['gauge'][1]);
    }
}
