<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppSettingController extends Controller
{
    private const AUTO_RETURNS_KEY = 'auto_post_internal_returns_to_stock';

    public function edit(): View
    {
        $autoPostReturns = filter_var(
            AppSetting::getValue(self::AUTO_RETURNS_KEY, 'false'),
            FILTER_VALIDATE_BOOLEAN
        );

        return view('settings.edit', compact('autoPostReturns'));
    }

    public function updateInterface(Request $request): RedirectResponse
    {
        AppSetting::setValue('ui.row_click', $request->boolean('row_click') ? '1' : '0', 'Clicking a table row opens its detail page');

        return back()->with('success', 'Screen behaviour saved.');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'auto_post_internal_returns_to_stock' => ['required', 'boolean'],
        ]);

        AppSetting::setValue(
            self::AUTO_RETURNS_KEY,
            $validated['auto_post_internal_returns_to_stock'] ? 'true' : 'false',
            'Automatically create traceable stock receipts for internal returns when posting a heat charge.'
        );

        return redirect()
            ->route('settings.edit')
            ->with('success', 'Settings saved successfully.');
    }
}