<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A pattern revision's minimum cooling time before shakeout. */
class ProcessControlController extends Controller
{
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
