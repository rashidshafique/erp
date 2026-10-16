<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Switches for how strictly the pour gate treats treatment records. */
class ProcessControlController extends Controller
{
    public static function enforceTreatment(): bool
    {
        return filter_var(AppSetting::getValue('enforce_treatment_records', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function maxFadeMinutes(): int
    {
        return (int) AppSetting::getValue('max_fade_minutes', '15');
    }

    public function update(Request $request): RedirectResponse
    {
        $d = $request->validate(['max_fade_minutes' => ['required', 'integer', 'min:1', 'max:120']]);
        AppSetting::setValue('enforce_treatment_records', $request->boolean('enforce_treatment_records') ? 'true' : 'false', 'Block pouring unless magnesium treatment and inoculation are recorded and within fade time.');
        AppSetting::setValue('max_fade_minutes', (string) $d['max_fade_minutes'], 'Maximum minutes from magnesium treatment to pouring.');

        return back()->with('success', 'Process controls saved.');
    }

    public function cooling(Request $request, string $revision): RedirectResponse
    {
        $d = $request->validate(['min_cooling_minutes' => ['nullable', 'integer', 'min:0', 'max:10080']]);
        abort_unless(\Illuminate\Support\Facades\DB::table('pattern_revisions')->where('id', $revision)->exists(), 404);
        $old = \Illuminate\Support\Facades\DB::table('pattern_revisions')->where('id', $revision)->value('min_cooling_minutes');
        \Illuminate\Support\Facades\DB::table('pattern_revisions')->where('id', $revision)->update(['min_cooling_minutes' => $d['min_cooling_minutes'] ?? null]);
        app(\App\Services\Audit\Audit::class)->record('UPDATED', 'PatternRevision', $revision, null, ['min_cooling_minutes' => ['old' => $old, 'new' => $d['min_cooling_minutes'] ?? null]]);

        return back()->with('success', 'Cooling time saved.');
    }
}
