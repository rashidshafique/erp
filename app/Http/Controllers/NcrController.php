<?php

namespace App\Http\Controllers;

use App\Models\NonconformanceReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NcrController extends Controller
{
    public const DISPOSITIONS = ['SCRAP', 'REWORK', 'USE_AS_IS', 'RETURN_TO_SUPPLIER', 'SORT'];

    public function index(Request $request): View
    {
        $rows = NonconformanceReport::with('lot.product')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByRaw("case when status = 'CLOSED' then 1 else 0 end")->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('ncrs.index', ['ncrs' => $rows]);
    }

    public function show(NonconformanceReport $ncr): View
    {
        $ncr->load(['lot.product', 'inspection', 'defect']);

        return view('ncrs.show', ['ncr' => $ncr, 'dispositions' => self::DISPOSITIONS]);
    }

    public function update(Request $request, NonconformanceReport $ncr): RedirectResponse
    {
        if ($ncr->status === 'CLOSED') {
            return back()->withErrors(['ncr' => 'A closed NCR cannot be changed.']);
        }
        $d = $request->validate([
            'containment_action' => ['nullable', 'string', 'max:2000'], 'root_cause' => ['nullable', 'string', 'max:2000'],
            'corrective_action' => ['nullable', 'string', 'max:2000'], 'preventive_action' => ['nullable', 'string', 'max:2000'],
            'disposition' => ['nullable', 'in:'.implode(',', self::DISPOSITIONS)], 'assigned_to' => ['nullable', 'string', 'max:150'],
        ]);
        $ncr->update($d + ['status' => 'IN_PROGRESS']);

        return back()->with('success', 'NCR updated.');
    }

    public function close(Request $request, NonconformanceReport $ncr): RedirectResponse
    {
        if ($ncr->status === 'CLOSED') {
            return back()->withErrors(['ncr' => 'This NCR is already closed.']);
        }
        $d = $request->validate(['closed_by' => ['required', 'string', 'max:150'], 'verification_notes' => ['nullable', 'string', 'max:2000']]);
        $missing = collect(['root_cause' => 'root cause', 'corrective_action' => 'corrective action', 'disposition' => 'disposition'])
            ->filter(fn ($l, $f) => blank($ncr->$f))->values();
        if ($missing->isNotEmpty()) {
            return back()->withErrors(['ncr' => 'Record the '.$missing->implode(', ').' and save before closing.']);
        }
        if (! app(\App\Services\Approvals\ApprovalGate::class)->passes('ncr.close', 'Ncr', (string) $ncr->id, $ncr->ncr_number ?? (string) $ncr->id, $msg)) {
            return back()->withErrors(['ncr' => $msg]);
        }
        $ncr->update($d + ['status' => 'CLOSED', 'closed_at' => now()]);
        $lot = $ncr->lot;
        if ($lot && $lot->status === 'IN_PRODUCTION' && ! $lot->ncrs()->where('status', '!=', 'CLOSED')->exists()
            && $lot->inspections()->where('inspection_type', 'FINAL')->where('status', 'COMPLETED')->exists() && $lot->quantity_good > 0) {
            $lot->update(['status' => 'READY']);
        }

        return back()->with('success', 'NCR closed.');
    }
}
