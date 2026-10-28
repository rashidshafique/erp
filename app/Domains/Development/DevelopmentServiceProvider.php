<?php

namespace App\Domains\Development;

use App\Domains\Core\ModuleServiceProvider;

/** Pattern developments: a new or replacement pattern worked through its stages with the customer, opened from an enquiry, a part, a pattern or a customer. */
class DevelopmentServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'development';
    }

    protected function moduleLabel(): string
    {
        return 'Pattern developments';
    }

    protected function moduleDescription(): string
    {
        return 'New and replacement pattern developments with their stages and log. Switching it off hides the screens and the Start development buttons.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function register(): void
    {
        parent::register();
        $this->mergeConfigFrom(__DIR__.'/config/pattern_developments.php', 'pattern_developments');
    }

    protected function navItems(): array
    {
        return [
            ['group' => 'sales', 'label' => 'Pattern developments', 'route' => 'developments.index', 'icon' => 'layers', 'active' => ['developments.*']],
        ];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'developments.*', 'permission' => 'sales.manage'],
        ];
    }

    protected function codeKinds(): array
    {
        return [
            'pattern_development' => ['pattern_developments', 'PD', 5, 'Pattern development'],
        ];
    }
}
