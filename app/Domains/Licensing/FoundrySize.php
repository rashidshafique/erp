<?php

namespace App\Domains\Licensing;

use App\Domains\Core\Contracts\MenuFilter;
use App\Models\AppSetting;
use App\Support\ProcessDepth;

/**
 * The size of foundry this installation is set up for. A size shortens the menu and applies a process-depth preset; the
 * individual rules stay editable on their own pages and do not change the size. Full, the default, shows everything.
 * Temporary: like the Modules page it is meant to move out of this system, so the layout only ever asks MenuFilter.
 */
class FoundrySize implements MenuFilter
{
    public const SETTING = 'foundry.size';

    public const DEFAULT = 'FULL';

    /** @var array<string, array{label: string, description: string, preset: string}> */
    public const SIZES = [
        'MICRO' => ['label' => 'Micro', 'description' => 'A small shop run by the owner: orders, moulds made and poured, quick QC and dispatch. About a dozen menu entries. Applies Basic process depth.', 'preset' => 'BASIC'],
        'SMALL' => ['label' => 'Small', 'description' => 'A foundry with teams and a melt shop: the full production, quality, HR and document menu without the heavy extras. Applies Basic process depth.', 'preset' => 'BASIC'],
        'FULL' => ['label' => 'Full', 'description' => 'Every screen. Applies Full process depth.', 'preset' => 'FULL'],
    ];

    /** Micro shows only these routes (and the quick flow pages). */
    private const MICRO_SHOWS = [
        'dashboard', 'customers.index', 'sales-orders.index', 'products.index', 'patterns.index', 'pours.index', 'heats.index', 'furnaces.index',
        'materials.index', 'quick-qc.index', 'lots.index', 'dispatches.index',
    ];

    /** Micro shows only these module groups. */
    private const MICRO_MODULE_GROUPS = ['quickflow', 'dispatch'];

    /** Small hides these routes. */
    private const SMALL_HIDES = ['casting-analyses.index', 'tooling-maintenance.dashboard', 'tooling-maintenance.index', 'tooling-life.dashboard', 'parties.index'];

    private ?string $current = null;

    public function current(): string
    {
        if ($this->current === null) {
            try {
                $stored = AppSetting::getValue(self::SETTING, self::DEFAULT);
            } catch (\Throwable) {
                return self::DEFAULT;
            }
            $this->current = array_key_exists((string) $stored, self::SIZES) ? (string) $stored : self::DEFAULT;
        }

        return $this->current;
    }

    /** Save the size and apply its process-depth preset. */
    public function apply(string $size): void
    {
        AppSetting::setValue(self::SETTING, $size, 'Foundry size: which menu entries show and which process depth applies');
        $this->current = $size;
        ProcessDepth::apply(self::SIZES[$size]['preset']);
    }

    public function shows(string $route): bool
    {
        return match ($this->current()) {
            'MICRO' => in_array($route, self::MICRO_SHOWS, true) || str_starts_with($route, 'quick.'),
            'SMALL' => ! in_array($route, self::SMALL_HIDES, true),
            default => true,
        };
    }

    public function showsAny(array $routes): bool
    {
        foreach ($routes as $route) {
            if ($this->shows($route)) {
                return true;
            }
        }

        return false;
    }

    public function showsModuleGroup(string $key): bool
    {
        return $this->current() !== 'MICRO' || in_array($key, self::MICRO_MODULE_GROUPS, true);
    }
}
