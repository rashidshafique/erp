<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Support\UiSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisplaySettingsController extends Controller
{
    public function edit()
    {
        return view('settings.display', ['ui' => UiSettings::all()]);
    }

    /** The font for the whole interface. Administrator only; other roles get a refusal even if they post to it directly. */
    public function updateFont(Request $request)
    {
        abort_unless(UiSettings::canEditFont(), 403, 'Only the administrator can change the font.');
        $d = $request->validate([
            'font' => ['required', 'string', function ($attr, $value, $fail) {
                if ($value !== 'custom' && ! isset(UiSettings::FONTS[$value])) {
                    $fail('Choose a font from the list.');
                }
            }],
            'custom_font' => ['nullable', 'string', 'regex:'.UiSettings::CUSTOM_FONT_PATTERN],
        ], ['custom_font.regex' => 'Use letters, numbers, spaces and hyphens only in the font name.']);
        if ($d['font'] === 'custom' && blank($d['custom_font'] ?? null)) {
            return back()->withErrors(['custom_font' => 'Type the name of the font.'])->withInput();
        }
        AppSetting::setValue('ui.font', $d['font'] === 'custom' ? 'custom:'.trim($d['custom_font']) : $d['font'], 'Font used across the interface');
        UiSettings::flush();

        return back()->with('success', 'Font saved.');
    }

    public function update(Request $request)
    {
        $request->validate(['density' => 'required|in:COMPACT,COMFORTABLE', 'dashboard' => ['nullable', Rule::in(UiSettings::DASHBOARD_LAYOUTS)]]);
        AppSetting::setValue('ui.table_toolbar', $request->boolean('table_toolbar') ? '1' : '0', 'Search and filters sit in the table header');
        AppSetting::setValue('ui.tidy_forms', $request->boolean('tidy_forms') ? '1' : '0', 'Long forms hide blank optional fields behind Show more');
        AppSetting::setValue('ui.density', $request->input('density'), 'Spacing of the interface');
        AppSetting::setValue('ui.dashboard', $request->input('dashboard', 'classic') ?: 'classic', 'Default dashboard layout');
        UiSettings::flush();

        return back()->with('success', 'Display settings saved.');
    }
}
