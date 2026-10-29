<?php

namespace App\Http\Controllers;

use App\Models\ProductionStageDefinition;
use App\Models\QualityDefect;
use App\Services\Production\AfterPour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** The work between the pour and quality control (knock-out, fettling, shot blast), moved in batches per product. */
class AfterPourController extends Controller
{
    public function index(AfterPour $afterPour): View
    {
        return view('after-pour.index', [
            'rows' => $afterPour->board()->groupBy(fn ($r) => $r['step']->id),
            'defects' => QualityDefect::where('is_active', true)->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request, AfterPour $afterPour): RedirectResponse
    {
        $d = $request->validate([
            'stage_definition_id' => ['required', 'uuid', 'exists:production_stage_definitions,id'], 'product_revision_id' => ['required', 'uuid', 'exists:product_revisions,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'], 'unit' => ['nullable', Rule::in(['PIECES', 'MOULDS'])],
            'rejected' => ['nullable', 'integer', 'min:0', 'max:1000000'], 'rejection_reason' => ['nullable', 'string', 'max:100'],
            'cooling_override_reason' => ['nullable', 'string', 'max:300'],
        ]);
        $step = ProductionStageDefinition::findOrFail($d['stage_definition_id']);
        try {
            $res = $afterPour->record($step, $d['product_revision_id'], $d + ['operator_name' => Auth::user()?->name]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['batch' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "{$res['pieces']} pieces through {$step->name}".($res['lots'] > 1 ? " from {$res['lots']} lots." : '.'));
    }
}
