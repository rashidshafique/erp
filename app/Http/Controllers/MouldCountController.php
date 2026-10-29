<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\MouldCount;
use App\Services\Production\MouldCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** Counting the moulds on the floor before a pour, per pattern, with the shortfall written off. */
class MouldCountController extends Controller
{
    public function index(MouldCounter $counter): View
    {
        return view('mould-count.index', [
            'waiting' => $counter->waiting(),
            'heats' => Heat::whereNotIn('status', ['CLOSED', 'CANCELLED'])->orderByDesc('created_at')->limit(10)->get(),
            'recent' => MouldCount::with('patternRevision.pattern', 'heat')->latest('counted_at')->limit(20)->get(),
        ]);
    }

    public function store(Request $request, MouldCounter $counter): RedirectResponse
    {
        $d = $request->validate([
            'counted' => ['required', 'array', 'min:1'], 'counted.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'reason' => ['nullable', Rule::in(array_keys(MouldCount::REASONS))], 'note' => ['nullable', 'string', 'max:300'],
            'heat_id' => ['nullable', 'uuid', 'exists:heats,id'],
        ]);
        $counted = array_map('intval', array_filter($d['counted'], fn ($n) => $n !== null && $n !== ''));
        if (! $counted) {
            return back()->withErrors(['counted' => 'Enter the moulds counted for at least one pattern.'])->withInput();
        }
        try {
            $res = $counter->record($counted, $d['reason'] ?? null, $d['note'] ?? null, $d['heat_id'] ?? null, Auth::user()?->name);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "{$res['counted']} moulds counted".($res['written_off'] ? ", {$res['written_off']} written off. They go back on what the orders still need." : '; all match the records.'));
    }
}
