<?php

namespace App\Http\Controllers;

use App\Models\MouldingRun;
use App\Models\MouldingWorkList;
use App\Models\MouldingWorkListItem;
use App\Models\Pour;
use App\Models\PourMouldAssembly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Overrides when flasks are freed on a work list row, a run or a mould at the pour; empty goes back to the level above. */
class FlaskFreedController extends Controller
{
    public function workListItem(Request $request, MouldingWorkList $list, MouldingWorkListItem $item): RedirectResponse
    {
        abort_unless($item->moulding_work_list_id === $list->id, 404);
        $item->update(['flasks_freed' => $this->value($request)]);

        return back()->with('success', 'Flask release saved for the row.');
    }

    public function run(Request $request, MouldingRun $run): RedirectResponse
    {
        $run->update(['flasks_freed' => $this->value($request)]);

        return back()->with('success', "Flask release saved for {$run->run_number}.");
    }

    public function pourMould(Request $request, Pour $pour, PourMouldAssembly $row): RedirectResponse
    {
        abort_unless($row->pour_id === $pour->id, 404);
        if ($pour->status === 'COMPLETED') {
            return back()->withErrors(['flasks' => 'The pour is completed; its moulds have already freed their flasks or wait for shakeout.']);
        }
        $row->mouldAssembly?->update(['flasks_freed' => $this->value($request)]);

        return back()->with('success', 'Flask release saved for the mould.');
    }

    private function value(Request $request): ?string
    {
        return $request->validate(['flasks_freed' => ['nullable', Rule::in(['POUR', 'SHAKEOUT'])]])['flasks_freed'] ?? null;
    }
}
