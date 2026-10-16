<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Support\UiSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BrandingController extends Controller
{
    private const ALLOWED = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];

    public function logo()
    {
        $path = AppSetting::getValue('ui.logo_path');
        $disk = Storage::disk(config('foundry.attachments.disk'));
        abort_unless($path && $disk->exists($path), 404);
        $mime = self::ALLOWED[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';

        return $disk->response($path, 'logo', [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",   // an SVG opened directly cannot run anything
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function update(Request $request)
    {
        $d = $request->validate([
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_name' => ['required', 'string', 'max:40'],
            'logo_position' => ['required', 'in:TOP_LEFT,BOTTOM_LEFT'],
            'logo_height' => ['required', 'integer', 'between:20,120'],
            'logo' => ['nullable', 'file', 'max:1024', 'extensions:png,jpg,jpeg,webp,svg'],
        ], ['logo.max' => 'The logo may be at most 1 MB.', 'logo.extensions' => 'Use a PNG, JPG, WEBP or SVG file.', 'accent.regex' => 'Pick a colour like #24945a.']);

        $accent = strtolower($d['accent']);
        if (UiSettings::contrast($accent) < 3.0) {
            throw ValidationException::withMessages(['accent' => 'That colour is too light for white button text. Pick a darker one.']);
        }

        $disk = config('foundry.attachments.disk');
        if ($request->boolean('remove_logo')) {
            $this->dropLogo();
        }
        if ($file = $request->file('logo')) {
            $ext = strtolower($file->getClientOriginalExtension());
            $this->checkContent($file->getRealPath(), $ext, (string) @mime_content_type($file->getRealPath()));
            $this->dropLogo();
            $path = $file->storeAs('branding', 'logo-'.bin2hex(random_bytes(6)).'.'.$ext, $disk);
            AppSetting::setValue('ui.logo_path', $path, 'Company logo file');
            AppSetting::setValue('ui.logo_version', (string) time(), 'Cache buster for the logo');
        }
        AppSetting::setValue('ui.accent', $request->boolean('reset_accent') ? UiSettings::DEFAULT_ACCENT : $accent, 'Accent colour');
        AppSetting::setValue('ui.brand_name', trim($d['brand_name']), 'Name shown top left');
        AppSetting::setValue('ui.logo_position', $d['logo_position'], 'Logo position');
        AppSetting::setValue('ui.logo_height', (string) $d['logo_height'], 'Logo height in px');
        UiSettings::flush();

        return back()->with('success', 'Branding saved.');
    }

    private function dropLogo(): void
    {
        $old = AppSetting::getValue('ui.logo_path');
        if ($old) {
            Storage::disk(config('foundry.attachments.disk'))->delete($old);
        }
        AppSetting::setValue('ui.logo_path', null, 'Company logo file');
        AppSetting::setValue('ui.logo_version', (string) time(), 'Cache buster for the logo');
    }

    /** The bytes must really be what the extension says; SVG may not carry scripts. */
    private function checkContent(string $file, string $ext, string $mime): void
    {
        if ($ext === 'svg') {
            $body = (string) file_get_contents($file);
            if (stripos($body, '<svg') === false || preg_match('/<script|on[a-z]+\s*=|javascript:|<foreignObject|<iframe/i', $body)) {
                throw ValidationException::withMessages(['logo' => 'That SVG is not a plain image (it contains scripts or is not an SVG).']);
            }

            return;
        }
        if (($mime === '' || $mime !== self::ALLOWED[$ext]) && ! ($ext === 'jpeg' && $mime === 'image/jpeg')) {
            throw ValidationException::withMessages(['logo' => "That file is not really a .{$ext} image."]);
        }
    }
}
