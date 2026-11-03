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
    /** @var array<string, array{perm: string, pages: list<string>}> area => the permission it needs and its pages, by menu label */
    public const AREAS = [
        'Company' => ['perm' => 'settings.manage', 'pages' => ['General', 'Codes & marking', 'Documents']],
        'People & approvals' => ['perm' => 'users.manage', 'pages' => ['Users', 'Roles & permissions', 'Approvals']],
        'Sales' => ['perm' => 'settings.manage', 'pages' => ['Quotation rates', 'Price lists', 'Requirement profiles', 'Order & pricing rules']],
        'Production' => ['perm' => 'settings.manage', 'pages' => ['Foundry size', 'How you work', 'Melting days', 'Moulding & flasks', 'Melting & pouring', 'Production queue', 'Patterns & development', 'Process rules', 'Our process']],
        'Quality' => ['perm' => 'settings.manage', 'pages' => ['Quality rules', 'Calibration & incoming']],
        'Accounts' => ['perm' => 'finance / ledger / tax.manage', 'pages' => ['Invoicing', 'Tax & year end', 'Ledger accounts', 'Posting rules', 'Sales documents']],
        'HR' => ['perm' => 'hr.manage · hr.payroll', 'pages' => ['Payroll rules']],
        'System' => ['perm' => 'settings.manage', 'pages' => ['Custom fields', 'Modules', 'Audit trail', 'Import data']],
    ];

    /** label => [route to open, route patterns that make it the current entry] */
    public const PAGES = [
        'General' => ['settings.display', ['settings.display*', 'settings.branding*']],
        'Codes & marking' => ['settings.codes', ['settings.codes*']],
        'Documents' => ['settings.documents', ['settings.documents*']],
        'Custom fields' => ['custom-fields.index', ['custom-fields.*']],
        'How you work' => ['settings.process-setup.edit', ['settings.process-setup*']],
        'Process rules' => ['settings.rules', ['settings.rules', 'settings.rules.update', 'settings.rules.reset']],
        'Order & pricing rules' => ['settings.pages.orders', ['settings.pages.orders']],
        'Moulding & flasks' => ['settings.pages.moulding', ['settings.pages.moulding']],
        'Melting & pouring' => ['settings.pages.melting', ['settings.pages.melting']],
        'Production queue' => ['settings.pages.queue', ['settings.pages.queue']],
        'Patterns & development' => ['settings.pages.patterns', ['settings.pages.patterns']],
        'Quality rules' => ['settings.pages.quality', ['settings.pages.quality']],
        'Calibration & incoming' => ['settings.pages.calibration', ['settings.pages.calibration']],
        'Our process' => ['settings.process', ['settings.process']],
        'Melting days' => ['settings.melting-days.edit', ['settings.melting-days*']],
        'Users' => ['users.index', ['users.*']],
        'Roles & permissions' => ['roles.index', ['roles.*']],
        'Approvals' => ['approvals.index', ['approvals.*', 'approval-policies.*']],
        'Audit trail' => ['audit.index', ['audit.*']],
        'Import data' => ['imports.index', ['imports.*']],
    ];

    /**
     * The areas with their pages: the core pages plus the links modules add, each under the area that names it. A page no area
     * names goes under System; an area with no page is left out.
     *
     * @return array<string, array<string, array{0: string, 1: list<string>}>>
     */
    public static function sections(): array
    {
        $pages = self::PAGES;
        foreach (app(ModuleRegistry::class)->settingsLinks() as $links) {
            foreach ($links as $link) {
                $pages[$link['label']] = [$link['route'], $link['active']];
            }
        }

        $sections = [];
        foreach (self::AREAS as $area => ['pages' => $labels]) {
            foreach ($labels as $label) {
                if (isset($pages[$label])) {
                    $sections[$area][$label] = $pages[$label];
                    unset($pages[$label]);
                }
            }
        }
        foreach ($pages as $label => $page) {
            $sections['System'][$label] = $page;
        }

        return array_filter(array_replace(array_fill_keys(array_keys(self::AREAS), []), $sections));
    }

    /** The permission the current settings page needs, or null outside the Settings area. */
    public static function permission(): ?string
    {
        $current = self::current();
        foreach (self::sections() as $area => $links) {
            if (isset($links[$current])) {
                return self::AREAS[$area]['perm'];
            }
        }

        return null;
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
