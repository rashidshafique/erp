<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

/** Display preferences, set under Administration → Display. Safe to call before the database exists. */
class UiSettings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            return self::$cache = [
                'compact' => AppSetting::getValue('ui.density', 'COMPACT') !== 'COMFORTABLE',
                'toolbar' => AppSetting::getValue('ui.table_toolbar', '1') === '1',
                'tidy_forms' => AppSetting::getValue('ui.tidy_forms', '1') === '1',
                'menu' => AppSetting::getValue('ui.menu', 'GROUPS') === 'LABELS' ? 'LABELS' : 'GROUPS',
            ];
        } catch (\Throwable) {
            return ['compact' => true, 'toolbar' => true, 'tidy_forms' => true, 'menu' => 'GROUPS'];
        }
    }

    public const DASHBOARD_LAYOUTS = ['classic', 'command', 'columns', 'telemetry'];

    /** Which dashboard to show: ?layout= wins, then the person's own choice (cookie), then the site default from Display settings. */
    public static function dashboardLayout(?string $query = null, ?string $cookie = null): string
    {
        foreach ([$query, $cookie] as $c) {
            if (in_array($c, self::DASHBOARD_LAYOUTS, true)) {
                return $c;
            }
        }
        try {
            $set = AppSetting::getValue('ui.dashboard', 'classic');
        } catch (\Throwable) {
            $set = 'classic';
        }

        return in_array($set, self::DASHBOARD_LAYOUTS, true) ? $set : 'classic';
    }

    private const SYSTEM_STACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';

    /** Fonts the administrator can pick. 'google' fonts load from Google Fonts and fall back to the system font when offline. */
    public const FONTS = [
        'system' => ['label' => 'System default (fast, works offline)', 'stack' => self::SYSTEM_STACK, 'google' => null],
        'segoe' => ['label' => 'Segoe UI', 'stack' => '"Segoe UI", Tahoma, Arial, sans-serif', 'google' => null],
        'arial' => ['label' => 'Arial / Helvetica', 'stack' => 'Arial, Helvetica, sans-serif', 'google' => null],
        'verdana' => ['label' => 'Verdana', 'stack' => 'Verdana, Geneva, sans-serif', 'google' => null],
        'tahoma' => ['label' => 'Tahoma', 'stack' => 'Tahoma, Geneva, sans-serif', 'google' => null],
        'georgia' => ['label' => 'Georgia (serif)', 'stack' => 'Georgia, "Times New Roman", serif', 'google' => null],
        'inter' => ['label' => 'Inter (web font)', 'stack' => '"Inter", '.self::SYSTEM_STACK, 'google' => 'Inter:wght@300;400;500;600;700;900'],
        'roboto' => ['label' => 'Roboto (web font)', 'stack' => '"Roboto", '.self::SYSTEM_STACK, 'google' => 'Roboto:wght@300;400;500;700;900'],
        'opensans' => ['label' => 'Open Sans (web font)', 'stack' => '"Open Sans", '.self::SYSTEM_STACK, 'google' => 'Open+Sans:wght@300;400;500;600;700;800'],
        'notosans' => ['label' => 'Noto Sans (web font)', 'stack' => '"Noto Sans", '.self::SYSTEM_STACK, 'google' => 'Noto+Sans:wght@300;400;500;600;700;900'],
    ];

    /** A font name typed in by hand: letters, numbers, spaces and hyphens only, so it can never carry anything else into the page. */
    public const CUSTOM_FONT_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 \-]{1,58}$/';

    /** The font in use: key, label, CSS font-family stack and the stylesheet URL for web fonts (or null). */
    public static function font(): array
    {
        try {
            $set = (string) AppSetting::getValue('ui.font', 'system');
        } catch (\Throwable) {
            $set = 'system';
        }
        if (str_starts_with($set, 'custom:') && preg_match(self::CUSTOM_FONT_PATTERN, $name = substr($set, 7))) {
            return ['key' => 'custom', 'name' => $name, 'label' => $name.' (installed on the computer)', 'stack' => '"'.$name.'", '.self::SYSTEM_STACK, 'url' => null];
        }
        $f = self::FONTS[$set] ?? self::FONTS['system'];
        $key = isset(self::FONTS[$set]) ? $set : 'system';

        return ['key' => $key, 'name' => null, 'label' => $f['label'], 'stack' => $f['stack'], 'url' => $f['google'] ? 'https://fonts.googleapis.com/css2?family='.$f['google'].'&display=swap' : null];
    }

    /** Only the administrator may change the font. With sign-in switched off there are no roles, so the person at the screen is the administrator. */
    public static function canEditFont(): bool
    {
        return ! config('foundry.require_login') || (bool) auth()->user()?->isAdmin();
    }

    public const DEFAULT_ACCENT = '#24945a';

    /** Accent colour and the shades derived from it. Always a valid colour with enough contrast for white text. */
    public static function theme(): array
    {
        $hex = '#24945a';
        try {
            $set = strtolower((string) AppSetting::getValue('ui.accent', self::DEFAULT_ACCENT));
            if (preg_match('/^#[0-9a-f]{6}$/', $set) && self::contrast($set) >= 3.0) {
                $hex = $set;
            }
        } catch (\Throwable) {
        }

        return ['accent' => $hex, 'hover' => self::shade($hex, 0.18), 'dark' => self::shade($hex, 0.3), 'soft' => self::mix($hex, '#ffffff', 0.9)];
    }

    /** Contrast ratio of white text on this colour (WCAG). */
    public static function contrast(string $hex): float
    {
        [$r, $g, $b] = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));
        $l = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return 1.05 / ($l + 0.05);
    }

    public static function shade(string $hex, float $amount): string
    {
        return self::mix($hex, '#000000', $amount);
    }

    public static function mix(string $a, string $b, float $towardB): string
    {
        $x = self::rgb($a);
        $y = self::rgb($b);

        return sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($x[$i] + ($y[$i] - $x[$i]) * $towardB), [0, 1, 2]));
    }

    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    /** @return array{name:string,logo:?string,position:string,height:int,version:string} */
    public static function brand(): array
    {
        try {
            $path = AppSetting::getValue('ui.logo_path');
            $exists = $path && Storage::disk(config('foundry.attachments.disk'))->exists($path);

            return [
                'name' => AppSetting::getValue('ui.brand_name', 'Foundry ERP') ?: 'Foundry ERP',
                'logo' => $exists ? route('branding.logo', ['v' => AppSetting::getValue('ui.logo_version', '1')]) : null,
                'position' => AppSetting::getValue('ui.logo_position', 'TOP_LEFT') === 'BOTTOM_LEFT' ? 'BOTTOM_LEFT' : 'TOP_LEFT',
                'height' => max(20, min(120, (int) AppSetting::getValue('ui.logo_height', '40'))),
            ];
        } catch (\Throwable) {
            return ['name' => 'Foundry ERP', 'logo' => null, 'position' => 'TOP_LEFT', 'height' => 40];
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
