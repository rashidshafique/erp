<?php

namespace App\Support;

use App\Domains\Core\ModuleRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * The Settings area: one menu entry at the bottom of the sidebar, and a second column of links on every settings page.
 * The pages keep their own URLs; the layout wraps any page whose route is listed here in the settings column.
 */
class SettingsNav
{
    /** label => [route to open, route patterns that make it the current entry] */
    public const SECTIONS = [
        'Foundry' => [
            'General' => ['settings.edit', ['settings.edit', 'settings.update']],
            'Display & branding' => ['settings.display', ['settings.display*', 'settings.branding*']],
            'Codes & marking' => ['settings.codes', ['settings.codes*']],
            'Documents' => ['settings.documents', ['settings.documents*']],
            'Custom fields' => ['custom-fields.index', ['custom-fields.*']],
            'Flask rules' => ['settings.flasks.edit', ['settings.flasks*']],
            'Process depth' => ['settings.process-depth.edit', ['settings.process-depth*']],
            'Order rules' => ['settings.orders.edit', ['settings.orders*']],
            'Pouring rules' => ['settings.pouring.edit', ['settings.pouring*']],
            'Our process' => ['settings.process', ['settings.process']],
            'Production queue rules' => ['settings.queue.edit', ['settings.queue*']],
            'Melting days' => ['settings.melting-days.edit', ['settings.melting-days*']],
            'Pattern ownership rules' => ['settings.pattern-ownership.edit', ['settings.pattern-ownership*']],
        ],
        'Access & data' => [
            'Users' => ['users.index', ['users.*']],
            'Roles & permissions' => ['roles.index', ['roles.*']],
            'Approvals' => ['approvals.index', ['approvals.*', 'approval-policies.*']],
            'Audit trail' => ['audit.index', ['audit.*']],
            'Import data' => ['imports.index', ['imports.*']],
        ],
    ];

    /**
     * The core sections plus the links modules add to them, or to sections of their own.
     *
     * @return array<string, array<string, array{0: string, 1: list<string>}>>
     */
    public static function sections(): array
    {
        $sections = self::SECTIONS;
        foreach (app(ModuleRegistry::class)->settingsLinks() as $section => $links) {
            foreach ($links as $link) {
                $sections[$section][$link['label']] = [$link['route'], $link['active']];
            }
        }

        return $sections;
    }

    /** True on any page that belongs to the Settings area. */
    public static function isSettingsPage(): bool
    {
        return self::current() !== null;
    }

    /** The label of the current settings entry, or null outside the Settings area. */
    public static function current(): ?string
    {
        $route = Route::current();
        if (! $route) {
            return null;
        }
        foreach (self::sections() as $links) {
            foreach ($links as $label => [, $patterns]) {
                if ($route->named(...$patterns)) {
                    return $label;
                }
            }
        }

        return null;
    }

    /** Whether the person may open the Settings area. With sign-in switched off, everyone is the administrator. */
    public static function canOpen(): bool
    {
        return ! Auth::check() || Auth::user()->hasPermission('settings.manage');
    }
}
